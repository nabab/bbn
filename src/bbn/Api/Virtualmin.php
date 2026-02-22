<?php

/**
 * Class virtualmin
 * @package api
 *
 * @author Edwin Mugendi <edwinmugendi@gmail.com>
 * @author Thomas Nabet <thomas.nabet@gmail.com>
 *
 */

namespace bbn\Api;

use bbn\X;
use bbn\Str;
use bbn\Models\Tts\Cache;
use bbn\Api\CloudminVirtualmin\Common;

class Virtualmin
{
  use Cache;
  use Common;

  const CACHE_NAME = 'bbn/Api/Virtualmin';

  /** @var array Info properties */
  public $infoProps = [
    'cpu',
    'disk_free',
    'disk_fs',
    'disk_total',
    'fcount',
    'ftypes',
    'host',
    'io',
    'kernel',
    'load',
    'maxquota',
    'mem',
    'procs',
    'progs',
    'poss',
    'reboot',
    'status',
    'vposs'
  ];


  /**
   * Re-collect info
   * @return string|false
   */
  public function collectInfo()
  {
    $connection = ssh2_connect($this->hostname, 22);
    ssh2_auth_password($connection, $this->user, $this->pass);
    $cmd       = "echo '" . $this->pass . "' | sudo -S virtualmin collectinfo";
    $stream    = ssh2_exec($connection, $cmd);
    $errStream = ssh2_fetch_stream($stream, SSH2_STREAM_STDERR);
    stream_set_blocking($errStream, true);
    return stream_get_contents($errStream);
  }


  /**
   * Gets server info
   *
   * @param array $arguments
   * @return array
   */
  public function info(array $arguments = []): array
  {
    $infoStr = $this->__call('info', [$arguments]);
    $info    = [];
    if (Str::len($infoStr)) {
      preg_match_all('/^[a-z|_]*\:\h{1}/m', $infoStr, $delimiters, PREG_OFFSET_CAPTURE);
      $delimiters = \array_map(
          function ($m) {
            return [
              'name' => \trim(str_replace(':', '', $m[0])),
              'pos' => $m[1]
            ];
          },
          $delimiters[0]
      );
      foreach ($delimiters as $i => $d) {
        $pos1 = $d['pos'];
        $pos2 = isset($delimiters[$i + 1]) ? ($delimiters[$i + 1]['pos'] - $pos1) : null;
        $st   = \is_null($pos2) ? \trim(Str::sub($infoStr, $pos1)) : \trim(Str::sub($infoStr, $pos1, $pos2));
        if (Str::len($st)) {
          $info[$d['name']] = [];
          switch ($d['name']) {
            case 'disk_fs':
            case 'poss':
            case 'status':
            case 'vposs':
              $info[$d['name']] = explode('*', $st);
              unset($info[$d['name']][0]);
              $info[$d['name']] = array_values($info[$d['name']]);
              foreach ($info[$d['name']] as $istr => $str) {
                if ($str = explode("\n", \trim($str))) {
                  $tmp = [];
                  foreach ($str as $s) {
                    if ($s = explode(':', $s)) {
                      $tmp[\trim($s[0])] = \trim($s[1]);
                    }
                  }

                  $str = $tmp;
                }

                $info[$d['name']][$istr] = $str;
              }
              break;

            case 'progs':
              $info[$d['name']] = explode('*', $st);
              unset($info[$d['name']][0]);
              $info[$d['name']] = array_values($info[$d['name']]);
              foreach ($info[$d['name']] as $istr => $str) {
                $info[$d['name']][$istr] = \trim(str_replace("\n", '', $str));
                if (!Str::len($info[$d['name']][$istr])) {
                  unset($info[$d['name']][$istr]);
                }
              }

              $info[$d['name']] = array_values($info[$d['name']]);
              foreach ($info[$d['name']] as $istr => $str) {
                if ($istr % 2 === 0) {
                  $info[$d['name']][$str] = $info[$d['name']][$istr + 1];
                  unset($info[$d['name']][$istr], $info[$d['name']][$istr + 1]);
                }
              }
              break;

            default:
              if (substr_count($st, '*')) {
                $info[$d['name']] = explode('*', $st);
                unset($info[$d['name']][0]);
                $info[$d['name']] = array_values($info[$d['name']]);
                foreach ($info[$d['name']] as $istr => $str) {
                  $str = str_replace("\n", '', $str);
                  $info[$d['name']][$istr] = \trim($str);
                }
              }
              elseif (substr_count($st, ':')) {
                $fields = array_filter(
                  explode("\n", $st),
                  function ($val) {
                    return $val !== '';
                  }
                );
                foreach ($fields as $field) {
                  if (($pos = Str::pos($field, ':')) !== false) {
                    $idx   = str_replace(' ', '', Str::sub($field, 0, $pos));
                    $value = Str::sub($field, $pos, Str::len($field) - $pos);
                    $value = str_replace(': ', '', $value);
                    if (count($fields) > 1) {
                      $info[$d['name']][$idx] = $value;
                      if (($idx === $d['name'])
                          && ($info[$d['name']][$idx] === '')
                      ) {
                        unset($info[$d['name']][$idx]);
                      }
                    }
                    else {
                      $info[$d['name']] = $value;
                    }
                  }
                }
              }
              break;
          }
        }
      }
    }

    return $info;
  }


  /**
   * Checks if the given property is part of the info
   * @param string $prop The property to check
   * @return bool
   */
  public function isInfoProp(string $prop): bool
  {
    return \in_array($prop, $this->infoProps, true);
  }


  /**
   * This function allows the cancellation of the cache of the used commands
   *
   * @param $uid file cache
   * @param $method name
   * @return bool
   */
  public function deleteCache($command_name = '', $arguments = false)
  {
    $uid = $this->hostname;
    if (!empty($arguments)) {
      $uid .= md5(json_encode($arguments));
    }

    if (!empty($this->cacheDelete($uid, $command_name))) {
      X::log([$uid, $command_name], 'cache_delete');
      return true;
    }

    return false;
  }
}
