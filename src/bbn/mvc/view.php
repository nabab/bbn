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


	private
		$dest,
		/**
		 * The directory of the controller.
		 * @var null|string
		 */
		$dir,
		/**
		 * The path to the controller.
		 * @var null|string
		 */
		$path;

	public
		/**
		 * The data model
		 * @var null|array
		 */
		$data = [],
		/**
		 * The file extension of the view
		 * @var null|string
		 */
		$ext,
		/**
		 * The request sent to the server to get the actual controller.
		 * @var null|string
		 */
		$url,
		/**
		 * List of possible outputs with their according file extension possibilities
		 * @var array
		 */
		$outputs = ['dom'=>'html','html'=>'html','image'=>'jpg,jpeg,gif,png,svg','json'=>'json','text'=>'txt','xml'=>'xml','js'=>'js','css'=>'css','less'=>'less'];
	const
		/**
		 * Path to the views.
		 */
		vpath = 'mvc/views/';

	/**
	 * This will call the initial build a new instance. It should be called only once from within the script. All subsequent calls to controllers should be done through $this->add($path).
	 *
	 * @param object | string $db The database object in the first call and the controller path in the calls within the class (through Add)<em>(e.g books/466565 or html/home)</em>
	 * @param string | object $parent The parent controller</em>
	 * @return bool
	 */
	public function __construct($db, $parent='', $data = [])
	{
	}

	/**
	 * This checks whether an argument used for getting controller, view or model - which are files - doesn't contain malicious content.
	 *
	 * @param string $p The request path <em>(e.g books/466565 or html/home)</em>
	 * @return bool
	 */
	private function check_path()
	{
		$ar = func_get_args();
		foreach ( $ar as $a ){
			if ( !is_string($a) ||
				(strpos($a,'./') !== false) ||
				(strpos($a,'/') === 0) ){
				die("The path $a is not an acceptable value");
			}
		}
		return 1;
	}

	/**
	 * This will launch the controller in a new function.
	 * It is publicly launched through check().
	 *
	 * @return void
	 */
	private function process()
	{
		if ( $this->controller && is_null($this->is_controlled) ){
			$this->obj = new \stdClass();
			$this->control();
			if ( $this->has_data() && isset($this->obj->output) ){
				$this->obj->output = $this->render($this->obj->output, $this->data);
			}
		}
		return $this;
	}

	/**
	 * This will get a javascript view encapsulated in an anonymous function for embedding in HTML.
	 *
	 * @param string $path
	 * @return string|false
	 */
	public function get_js($path='')
	{
		if ( $r = $this->get_view($path, 'js') ){
			return '
<script>
(function($){
'.$r.'
})(jQuery);
</script>';
		}
		return false;
	}

	/**
	 * This will get a CSS view encapsulated in a scoped style tag.
	 *
	 * @param string $path
	 * @return string|false
	 */
	public function get_css($path='')
	{
		if ( $r = $this->get_view($path, 'css') ){
			return '<style scoped>'.\CssMin::minify($r).'</style>';
		}
		return false;
	}

	/**
	 * This will get and compile a LESS view encapsulated in a scoped style tag.
	 *
	 * @param string $path
	 * @return string|false
	 */
	public function get_less($path='')
	{
		if ( !isset($this->less) ){
			if ( !class_exists('lessc') ){
				die("No less class, check composer");
			}
			$this->less = new \lessc();
		}
		if ( $r = $this->get_view($path, 'less') ){
			return '<style scoped>'.\CssMin::minify($this->less->compile($r)).'</style>';
		}
		return false;
	}

	/**
	 * This will add a javascript view to $this->obj->script
	 * Chainable
	 *
	 * @param string $path
	 * @param string $mode
	 * @return string|false
	 */
	public function add_js()
	{
		$args = func_get_args();
		foreach ( $args as $a ){
			if ( is_array($a) ){
				$data = $a;
			}
			else if ( is_string($a) ){
				$path = $a;
			}
		}
		if ( $r = $this->get_view(isset($path) ? $path : '', 'js') ){
			$this->add_script($this->render($r, isset($data) ? $data : $this->data));
		}
		return $this;
	}

	/**
	 * This will get a PHP template view
	 *
	 * @param string $path
	 * @param string $mode
	 * @return string|false
	 */
	private function get_php($path='', $mode='')
	{
		if ( $this->mode && !is_null($this->dest) && $this->check_path($path, $this->mode) ){
			if ( empty($mode) ){
				$mode = $this->mode;
			}
			if ( empty($path) ){
				$path = $this->dest;
			}
			if ( isset($this->outputs[$mode]) ){
				$file = $mode.'/'.$path.'.php';
				if ( isset($this->loaded_phps[$file]) ){
					$bbn_php = $this->loaded_phps[$file];
				}
				else if ( is_file(self::vpath.$file) ){
					$bbn_php = $this->add_php($file);
				}
				if ( isset($bbn_php) ){
					$args = array();
					if ( $this->has_data() ){
						foreach ( (array)$this->data as $key => $val ){
							$$key = $val;
							array_push($args, '$'.$key);
						}
					}
					return eval('return call_user_func(function() use ('.implode(',', $args).'){ ?>'.$bbn_php.' <?php });');
				}
			}
		}
		return false;
	}

	/**
	 * Processes the controller and checks whether it has been routed or not.
	 *
	 * @return bool
	 */
	public function check()
	{
		foreach ( $this->checkers as $chk ){
			// If a checker file returns false, the controller is not processed
			if ( !include_once($chk) ){
				return false;
			}
		}
		$this->process();
		return $this->is_routed;
	}

}
