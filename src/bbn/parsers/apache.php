<?php

namespace bbn\parsers;

use bbn\x;
use bbn\str;

class apache
{

  protected static $parse_limit = 50000;

  protected static $file_limit = 500;

  protected static $last_errors = 0;

  protected static $err1 = '/^\[([^\]]+)\]\s+PHP\s+([^:]+):\s+(.+)\s+called\s+in\s+\/([^\s]+)\s+on\s+line\s+(\d+)(.*)$/';

  protected static $err2 = '/^\[([^\]]+)\]\s+PHP\s+([^:]+):\s+(.+)\s+in\s+\/([^\s]+)(?:\s+on\s+line\s+|:)(\d+)$/';

  protected static $err_line = '/^\[([^\]]+)\]\s+PHP\s+\d+\.\s+(.+)\s+\/([^:]+):(\d+)$/';

  protected static $err_line2 = '/^\s*#\d+\s+\/([^\(]+)\((\d+)\):\s+(.+)$/';

  protected static $err_trace = '/^(\[[^\]]+\]\s+PHP)?\s*Stack\s+trace:$/';

  protected static function set_last_errors(int $num)
  {
    self::$last_errors = $num;
  }

  public static function get_last_errors(): int
  {
    return self::$last_errors;
  }


  public static function cut_log_file($file, $size)
  {
    if (file_exists($file)) {
      if (filesize($file) > $size) {
        $handle = fopen($file, "r");
        if ($handle) {
          $current_error = false;
          $fsize = 0;
          $res = [];
          while (($buffer = fgets($handle)) !== false) {
            $fsize += strlen($buffer);
            $res[] = $buffer;
            while ($fsize > $size) {
              array_shift($res);
              $fsize = strlen(implode('', $res));
            }
          }

          if (file_put_contents($file, implode('', $res))) {
            return true;
          }
        }
      }

      return false;
    }

    return null;
  }


  public static function get_parse_limit(): int
  {
    return self::$limit;
  }


  public static function set_parse_limit(int $limit): void
  {
    self::$limit = $limit;
  }


  public static function parse_file($file, array $res = []): array
  {
    $num = 0;
    self::set_last_errors($num);
    if (file_exists($file)) {
      $handle = fopen($file, "r");
      if ($handle) {
        $current_error = false;
        $min = 0;
        if ($res) {
          $min = x::max_with_key($res, 'last_date');
        }

        while (($buffer = fgets($handle)) !== false) {
          if ($parsed = self::parse_line($buffer)) {
            if (isset($parsed['error'])) {
              if ($min > $parsed['date']) {
                continue;
              }

              $num++;
              $idx = x::find(
                $res,
                [
                  'error' => $parsed['error'],
                  'file' => $parsed['file'],
                  'line' => $parsed['line']
                ]
              );
              if ($idx !== null) {
                $res[$idx]['count']++;
                $res[$idx]['last_date'] = $parsed['date'];
                if (isset($res[$idx]['backtrace'])) {
                  unset($res[$idx]['backtrace']);
                }

                $current_error = $idx;
              }
              else{
                $current_error = count($res);
                $res[]         = [
                  'first_date' => $parsed['date'],
                  'last_date' => $parsed['date'],
                  'count' => 1,
                  'type' => $parsed['type'],
                  'error' => $parsed['error'],
                  'file' => $parsed['file'],
                  'line' => $parsed['line']
                ];
                if (count($res) > self::$file_limit) {
                  $tmp = $res[$current_error];
                  x::sort_by($res, 'last_date', 'DESC');
                  $current_error = x::find($res, $tmp);
                  array_pop($res);
                }
                self::set_last_errors($num);
              }
            }
            elseif (isset($parsed['action'], $res[$current_error])) {
              if (!isset($res[$current_error]['backtrace'])) {
                $res[$current_error]['backtrace'] = [];
              }
              elseif (count($res[$current_error]['backtrace']) > 10) {
                continue;
              }

              array_unshift($res[$current_error]['backtrace'],  $parsed);
            }
          }
        }

        if (!feof($handle)) {
          throw new \Exception(_("Error: unexpected fgets() fail"));
        }

        fclose($handle);
        x::sort_by($res, 'last_date', 'DESC');
      }
    }

    return $res;
  }


  public static function parse_line($st)
  {
    $ln = trim($st);
    if (empty($ln)) {
      return null;
    }

    $m = [];
    if (preg_match(self::$err1, $ln, $m)) {
      $in_lib = strpos('/'.$m[4], BBN_LIB_PATH) === 0;
      return [
        'date' => date('Y-m-d H:i:s', strtotime(str_replace('-', ' ', $m[1]))),
        'type' => $m[2],
        'error' => $m[3],
        'file' => str_replace(
          $in_lib ? BBN_LIB_PATH : BBN_APP_PATH,
          $in_lib ? 'lib/' : 'app/',
          '/'.$m[4]
        ),
        'line' => $m[5],
      ];
    }

    if (preg_match(self::$err2, $ln, $m)) {
      $in_lib = strpos('/'.$m[4], BBN_LIB_PATH) === 0;
      return [
        'date' => date('Y-m-d H:i:s', strtotime(str_replace('-', ' ', $m[1]))),
        'type' => $m[2],
        'error' => $m[3],
        'file' => str_replace(
          $in_lib ? BBN_LIB_PATH : BBN_APP_PATH,
          $in_lib ? 'lib/' : 'app/',
          '/'.$m[4]
        ),
        'line' => $m[5],
      ];
    }

    if (preg_match(self::$err_line, $ln, $m)) {
      $in_lib = strpos('/'.$m[3], BBN_LIB_PATH) === 0;
      return [
        'action' => str::cut($m[2], 255),
        'file' => str_replace(
          $in_lib ? BBN_LIB_PATH : BBN_APP_PATH,
          $in_lib ? 'lib/' : 'app/',
          '/'.$m[3]
        ),
        'line' => $m[4]
      ];
    }

    if (preg_match(self::$err_line2, $ln, $m)) {
      $in_lib = strpos('/'.$m[1], BBN_LIB_PATH) === 0;
      return [
        'action' => str::cut($m[3], 255),
        'file' => str_replace(
          $in_lib ? BBN_LIB_PATH : BBN_APP_PATH,
          $in_lib ? 'lib/' : 'app/',
          '/'.$m[1]
        ),
        'line' => $m[2]
      ];
    }

    return null;
  }


  public static function parse($st): array
  {
    $num   = 0;
    $res   = [];
    $errs  = [];
    $lines = explode(PHP_EOL, $st, self::$parse_limit);
    $err   = false;
    foreach ($lines as $ln) {
      if ($parsed = self::parse_line($ln)) {
        if (isset($parsed['error'])) {
          if ($err) {
            $res[] = $err;
          }

          $err = $parsed;
        }
        elseif (isset($parsed['action'])) {
          if (!$err) {
            continue;
            //throw new \Exception(_("A trace is starting so an error should exist"));
          }

          if (!isset($err['trace'])) {
            $err['trace'] = [];
          }

          $err['trace'][] = $parsed;
        }
        else {
          if ($err) {
            $res[] = $err;
            $err   = false;
          }

          $errs[] = $ln;
          $num++;
          //throw new \Exception(_("Impossible to parse log string")." $ln");
        }
      }
    }

    if ($err) {
      $res[] = $err;
    }

    //die(x::dump($errs));
    return $res;
  }


}
