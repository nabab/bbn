<?php

/* 
 * Copyright (C) 2014 BBN
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace bbn\mvc;

class view{

	use common;

	private
    /**
     * The full path to the view file
     * @var null|string
     */
    $file,
    /**
     * The file's extension
     * @var null|string
     */
    $ext,
		/**
		 * The content the view file.
		 * @var null|string
		 */
		$content;

	/**
	 * This will call the initial build a new instance. It should be called only once from within the script. All subsequent calls to controllers should be done through $this->add($path).
	 *
	 * @param object | string $db The database object in the first call and the controller path in the calls within the class (through Add)<em>(e.g books/466565 or html/home)</em>
	 * @param string | object $parent The parent controller</em>
	 * @return bool
	 */
	public function __construct(array $info)
	{
    if ( router::is_mode($info['mode']) ){
      $this->ext = $info['ext'];
      $this->file = $info['file'];
    }
	}

  public function check(){
    return !empty($this->file);
  }

	/**
	 * Processes the controller and checks whether it has been routed or not.
	 *
	 * @return bool
	 */
	public function get(array $data=null)
	{
		if ( $this->check() ) {
			if ( is_null($this->content) ) {
				$this->content = file_get_contents($this->file);
			}
			if ( empty($this->content) ){
				return '';
			}
      $res = is_array($data) ? \bbn\tpl::render($this->content, $data) : $this->content;
      switch ( $this->ext ){
        case 'js':
          break;
        case 'coffee':
          break;
        case 'css':
          break;
        case 'less':
          break;
        case 'scss':
          break;
        case 'css':
          break;
        case 'html':
          break;
      }
			return $res;
		}
		return false;
	}
}
