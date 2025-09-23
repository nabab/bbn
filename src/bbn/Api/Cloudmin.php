<?php

namespace bbn\Api;

use bbn;
use bbn\X;

/**
 * A class for executing Cloudmin commands.
 * php version 7.4
 *
 * @category Api
 * @package BBN_Library
 * @author Thomas Nabet <thomas.nabet@gmail.com>
 * @author Vito Fava <vito.nabet@gmail.com>
 * @license http://www.opensource.org/licenses/mit-license.php MIT
 * @version Release: 0.1
 * @link https://bbn.io/bbn-php/doc/class/appui/api
 * @since Apr 4, 2011, 23:23:55 +0000
 *
 */
class Cloudmin
{
  use CloudminVirtualmin\Common;

  const CACHE_NAME = 'bbn/Api/Cloudmin';


  /**
   * return list of virtual machine
   *
   * @return array
   **/
  public function listSystems()
  {
    $this->lastAction = "list-systems";
    //Defining  the $url_part and the command to be executed
    $url_part = "list-systems";
    //Concatenating the header url and $url_part to create the full url to be executed
    $url_part = $this->getHeaderUrl() . $url_part . "'";
    //Calling shell_exec and returning the result array
    return array_map(
        function ($a) {
          array_walk(
              $a['values'],
              function (&$b): void {
                if (\is_array($b) && array_key_exists(0, $b) && (count($b) === 1)) {
                  $b = $b[0];
                }
              }
          );
          $a['values']['name'] = $a['name'];
          if ($a['values']['filesystem']) {
            array_walk(
                $a['values']['filesystem'],
                function (&$b): void {
                  $tmp = explode(' ', $b);
                  $b   = [
                    'name' => $tmp[0],
                    'size' => $tmp[2],
                    'size_unit' => $tmp[3],
                    'used' => $tmp[5],
                    'used_unit' => $tmp[6],
                    'free' => $tmp[8],
                    'free_unit' => $tmp[9]
                  ];
                }
            );
          }

          $a['values']['available_updates'] = count(explode(', ', $a['values']['available_updates']));
          return $a['values'];
        },
        $this->callShellExec($url_part)
    );
  }
}
