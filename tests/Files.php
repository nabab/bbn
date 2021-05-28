<?php

namespace tests;

trait Files
{
  /**
   *
   * Create a file on the fly to be used in tests
   *
   * @param string $filename
   * @param string $file_content
   * @param string $dirname
   *
   * @return string
   */
  protected function createFile(string $filename, string $file_content, string $dirname)
  {
    if (!is_dir($dir = BBN_APP_PATH . BBN_DATA_PATH . $dirname)) {
      mkdir($dir);
    }

    $fp = fopen($file_path = "$dir/$filename", 'w');
    if (!empty($file_content)) {
      fputs($fp, $file_content);
    }

    fclose($fp);

    return $file_path;
  }

  /**
   * @param string $name
   */
  public function createDir(string $dirname)
  {
    $total_dirs = [];
    foreach (explode('/', $dirname) as $name) {
      if (!is_dir($dir = BBN_APP_PATH . BBN_DATA_PATH . implode('/', $total_dirs) . '/' . $name)) {
        mkdir($dir);
        $total_dirs[] = $name;
      }
    }
  }
}