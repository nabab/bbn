<?php

namespace bbn\Appui\Search;

use bbn\X;
use bbn\Str;
use bbn\Appui\Search;
use bbn\Util\Timer;
use bbn\Mvc\Controller;
use Generator;

/**
 * Class Manager
 * Encapsulates the logic for handling parallel search processes.
 */
class Manager
{
  /** @var Controller */
  protected $ctrl;

  /** @var string Unique identifier from POST */
  protected $uid;

  /** @var array Array of search conditions from POST */
  protected $conditions;

  /** @var string Full path to the JSON conditions file */
  protected $filePath;

  /** @var string Base path for log files */
  protected $logFileBase;

  protected Search $search;

  /** @var array Results array to eventually return/stream */
  protected $result = [
    'success' => false,
    'errors' => []
  ];

  /** @var array Current child worker processes */
  protected $workers = [];

  /** @var int Maximum number of results to collect */
  protected $maxResults = 10000;

  /** @var int Maximum number of parallel worker processes */
  protected $maxWorkers = 5;

  /** @var string Command template for launching a worker */
  protected $commandTpl = 'php -f router.php %s "%s"';

  /**
   * Constructor.
   *
   * @param Controller $ctrl       The BBN MVC controller
   * @param string     $uid        Unique ID (usually from POST)
   * @param array      $conditions Array of conditions (usually from POST)
   */
  public function __construct(Controller $ctrl, string $uid, array $conditions)
  {
    $this->ctrl       = $ctrl;
    $this->conditions = $conditions;
    $this->logFileBase = $this->ctrl->pluginDataPath('appui-search') . "config/{$this->uid}";
    $this->filePath   = "{$this->logFileBase}.json";
    // Write the first condition into the JSON file
    $this->search = new Search($this->ctrl, []);
    $this->setCondition($this->conditions[0]);
  }

  /**
   * Runs the entire search process.
   */
  public function run()
  {
    // We need at least one condition to proceed
    if (empty($this->conditions)) {
      return null;
    }


    // Mark process as successful by default
    $this->result['success'] = true;

    // Prepare iteration counters
    $loopCount = 0;
    $totalResults = 0;
    $currentValue = null;
    // Create a timer (if needed for logging/debugging)
    $timer = new Timer();

    // Read the (latest) condition
    $condition = $this->readCondition();

    // Main loop: runs while the condition file still exists and matches our search value
    while ($this->isOk($condition)) {
      if (!empty($condition['value'])) {
        // When value changes, reset some things
        if ($currentValue !== $condition['value']) {
          $currentValue = $condition['value'];
          $this->removeWorker();
          $totalResults = 0;
          $step = 0;
          $prev = $this->search->retrievePreviousResults($condition['value']);
          if (!empty($prev['data'])) {
            $prev['is_previous'] = true;
            $prev['value'] = $condition['value'];
            yield $prev;
          }
        }

        // If we haven't reached our global max
        if ($totalResults < $this->maxResults) {
          // Launch new workers while conditions allow
          while (!is_null($step) && (count($this->workers) < $this->maxWorkers)) {
            if (!$this->isOk($condition)) {
              break;
            }
            if ($result = $this->search->stream($condition['value'], $step)) {
              if (!$this->isOk($condition)) {
                break;
              }

              $this->addWorker($result, $step);
              $timer->start("step-$step");
              //X::log("[STEP $step] " . microtime(true) . " ADDING SEARCH WORKER POUR $currentValue $step " . ($result['item']['name'] ?? $result['item']['file'] ?? '?'), 'searchTimings');
              // If the condition file is removed externally, stop everything
              if (!file_exists($this->filePath)) {
                $this->removeWorker();
                break;
              }
              // If there's a next step, move forward
              elseif (!empty($result['next_step'])) {
                $step = $result['next_step'];
              }
              else {
                // No next step, end the worker-adding loop
                $step = null;
              }
            }

            break;
          }

          for ($j = 0; $j < count($this->workers); $j++) {
            // Re-check the condition each time in case it changed
            if ($this->isOk($condition)) {
              $worker = $this->workers[$j];
              $status = proc_get_status($worker['proc']);
              //X::log("[STEP $worker[step]] " . microtime(true) . ' ' . $status['running'], 'searchTimings');

              // If the process has finished
              if (!$status['running']) {
                //X::log("[STEP $worker[step]] " . microtime(true) . ' NOT RUNNING', 'searchTimings');
                // Read data from stdout
                $jsonOutput = stream_get_contents($worker['pipes'][1]);

                if ($jsonOutput) {
                  //X::log("[STEP $worker[step]] " . microtime(true) . ' JSON OK', 'searchTimings');
                  $ret  = json_decode($jsonOutput, true);

                  // If results are found, stream them
                  if (!empty($ret['num'])) {
                    //X::log("[STEP $worker[step]] " . microtime(true) . ' RESULTS OK', 'searchTimings');
                    $data = json_decode(file_get_contents($worker['data']), true);
                    // If we exceed the max, trim them
                    /*
                    if ($totalResults > $this->maxResults) {
                      $excess = $totalResults - $this->maxResults;
                      array_splice($ret['data']['results'], $newCount - $excess);
                    }*/


                    // Stream partial results
                    yield [
                      'value' => $condition['value'],
                      'data' => $data['results'],
                      'step' => $worker['step'],
                      'item' => $ret['item'],
                      'id'   => $worker['id']
                    ];

                  }
                  else {
                    //X::log("[STEP $worker[step]] " . microtime(true) . ' RESULTS NOT OK ' . json_encode($ret), 'searchTimings');
                  }
                }
                else {
                  X::log($jsonOutput, 'searchError');
                  X::log($status, 'searchError');
                }

                // Check worker-specific log file for errors
                if (file_exists($worker['log'])) {
                  $err = file_get_contents($worker['log']);
                  if ($err) {
                    $this->result['errors'][] = $err;
                    yield ['error' => $err, 'command' => $worker['cmd']];
                  }
                }

                // Remove this single worker from the array
                $j--;
                // Clean up the process and its log
                $this->removeWorker($worker['uid']);
              }
              /*
              else if ($timer->measure('step-' . $worker['step']) > $worker['timeout']) {
                X::log("[STEP $worker[step]] " . microtime(true) . ' TIMEOUT: ' . $worker['timeout'], 'searchTimings');
                $this->removeWorker($worker['uid']);
              }*/
              // If max reached, kill all workers
              if ($totalResults > $this->maxResults) {
                $this->removeWorker();
                break;
              }
            }
            else {
              // The condition changed or file missing: re-read and stop
              $condition = $this->readCondition();
              $this->removeWorker();
              break;
            }
          }

          if (!count($this->workers) && is_null($step)) {
            break;
          }
        }
        else {
          $this->removeWorker();
          $step = null;
        }
      }
      else {
        $this->removeWorker();
        $step = null;
      }

      if (!count($this->workers) && is_null($step)) {
        break;
      }

      // Avoid burning CPU in the loop
      if (isset($step) && (count($this->workers) >= $this->maxWorkers)) {
        usleep(50000);
      }

      $loopCount++;
      $condition = $this->readCondition();
    }

    // End of main loop; clean up any remaining workers
    $this->removeWorker();

    // Delete the condition file if it still exists
    if (file_exists($this->filePath)) {
      unlink($this->filePath);
    }

    // Record the loop iteration count or any other helpful info
    $this->result['progress'] = $loopCount;

    // Stream the final result back
    return;
  }

  /**
   * Initializes the condition file with the given data.
   *
   * @param array $condition The condition data to store
   * @return bool
   */
  public function setCondition(array $condition): bool
  {
    $this->conditions = [$condition];
    file_put_contents($this->filePath, json_encode($condition));
    clearstatcache();
    return true;
  }

  /**
   * Reads the current condition from the JSON file.
   *
   * @return array|null The condition data or null if file doesn't exist
   */
  protected function readCondition(): ?array
  {
    if (!file_exists($this->filePath)) {
      return null;
    }
    return json_decode(file_get_contents($this->filePath), true);
  }

  /**
   * Checks if the condition file still exists and matches the passed condition.
   *
   * @param array|null $condition The reference condition
   * @return bool Returns false if file doesn't exist, 0 if changed, or 1 if OK
   */
  protected function isOk(?array $condition): bool
  {
    if (!$condition || !file_exists($this->filePath) || connection_aborted()) {
      return false;
    }

    $tmp = json_decode(file_get_contents($this->filePath), true);
    if (!isset($tmp['value']) || $tmp['value'] !== $condition['value']) {
      return false;
    }

    return true;
  }

  /**
   * Kills and removes all given workers, or all tracked workers if none specified.
   *
   * @param array|null $workers Optional list of workers to terminate
   * @return void
   */
  protected function removeWorker(?string $worker = null): void
  {
    if (!$worker) {
      $workers = array_map(fn($a) => $a['uid'], $this->workers);
    }
    else {
      $workers = [$worker];
    }

    foreach ($workers as $uid) {
      $idx = X::find($this->workers, ['uid' => $uid]);
      if (isset($this->workers[$idx])) {
        $w = array_splice($this->workers, $idx, 1)[0];
        //X::log("[STEP $w[step]] " . microtime(true) . ' KILLING WORKER WITH FILE ' . $w['log'], 'searchTimings');
        $status = proc_get_status($w['proc']);
        if ($status['running']) {
          proc_terminate($w['proc']);
        }
        proc_close($w['proc']);
        if (file_exists($w['log'])) {
          unlink($w['log']);
        }
        if (file_exists($w['data'])) {
          unlink($w['data']);
        }
      }
    }
  }

  /**
   * Spawns a new worker process to perform a chunk of work.
   *
   * @param array $result The data describing what this worker should process
   * @param int   $step   The current step
   * @return void
   */
  protected function addWorker(array $result, int $step): void
  {
    // Prepare descriptor array: [stdin, stdout, stderr]
    // stderr is set dynamically to a unique log file

    // Generate unique ID for this worker
    $workerUid = Str::genpwd();

    // Build final command by substituting the URL and encoded arguments
    $url = $this->ctrl->pluginUrl('appui-search') . '/results';
    $dataFile = $this->logFileBase . '-' . $workerUid . '.json';
    $cmd = sprintf(
      $this->commandTpl,
      $url,
      Str::escapeDquotes(json_encode([
        'item' => $result['item'] ?? null,
        'step' => $step,
        'file' => $dataFile
      ]))
    );

    // Create and clear the log file
    $logFile = $this->logFileBase . '-' . $workerUid . '.log';
    //X::log("[STEP $step] " . microtime(true) . ' CREATING WORKER WITH FILE ' . $logFile, 'searchTimings');
    file_put_contents($logFile, '');

    // Attach the log file as stderr
    $descriptors = [
      ["pipe", "r"],
      ["pipe", "w"],
      ["file", $logFile, "a"]
    ];

    // Open the process
    $proc = proc_open(
      $cmd,
      $descriptors,
      $pipes,
      $this->ctrl->appPath()
    );

    // Non-blocking read from the stdout pipe
    stream_set_blocking($pipes[0], 0);
    stream_set_blocking($pipes[1], 0);

    // Track the worker
    $this->workers[] = [
      'proc'    => $proc,
      'id'      => $result['id'],
      'timeout' => $result['timeout'] ?? 10,
      'cmd'     => $cmd,
      'uid'     => $workerUid,
      'pipes'   => $pipes,
      'log'     => $logFile,
      'data'    => $dataFile,
      'step'    => $step
    ];
  }
}
