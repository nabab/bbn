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
		 * The path to the controller.
		 * @var null|string
		 */
		$path,
		/**
		 * The content the view file.
		 * @var null|string
		 */
		$content;

	public
		/**
		 * List of possible outputs with their according file extension possibilities
		 * @var array
		 */
		$outputs = ['dom'=>'html','html'=>'html','image'=>'jpg,jpeg,gif,png,svg','json'=>'json','text'=>'txt','xml'=>'xml','js'=>'js','css'=>'css','less'=>'less'];

	const
		/**
		 * Path to the views.
		 */
		root = 'mvc/views/';

	/**
	 * This will call the initial build a new instance. It should be called only once from within the script. All subsequent calls to controllers should be done through $this->add($path).
	 *
	 * @param object | string $db The database object in the first call and the controller path in the calls within the class (through Add)<em>(e.g books/466565 or html/home)</em>
	 * @param string | object $parent The parent controller</em>
	 * @return bool
	 */
	public function __construct($path)
	{
		if ( $this->check_path($path) && is_file(self::root.$path) ){
			$this->path = $path;
		}
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
				$this->content = file_get_contents(self::root.$this->path);
			}
			else{
				$this->error("File not found: ".$this->path);
			}
			if ( empty($this->content) ){
				return '';
			}
			if ( is_array($data)) {
				return \bbn\tpl::render($this->content, $data);
			}
			return $this->content;
		}
		return false;
	}
}
