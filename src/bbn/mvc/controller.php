<?php

namespace bbn\mvc;
use bbn;

class controller implements api{

  use common;

  private
    /**
     * When reroute is used $reroutes will be used to check we're not in an infinite reroute loop
     * @var array $last_reroute
     */
    $reroutes = [],
    /**
     * The MVC class from which the controller is called
     * @var mvc
     */
    $mvc,
    /**
     * Is set to null while not controled, then 1 if controller was found, and false otherwise.
     * @var null|boolean
     */
    $is_controlled,
    /**
     * Is set to false while not rerouted
     * @var null|boolean
     */
    $is_rerouted = false,
    /**
     * The internal path to the controller.
     * @var null|string
     */
    $path,
    /**
     * The request sent to get to the controller
     * @var null|string
     */
    $request,
		/**
		 * The directory of the controller.
		 * @var null|string
		 */
		$dir,
    /**
     * The full path to the controller's file.
     * @var null|string
     */
    $file,
    /**
     * The full path to the root directory.
     * @var null|string
     */
    $root,
		/**
		 * The checkers files (with full path)
		 * If any they will be checked before the controller
		 * @var null|string
		 */
		$checkers = [],
    /**
     * @var null|string If the controller is inside a plugin this property will be set to its name
     */
    $plugin;

  public
    /**
     * The db connection if accepted by the mvc class.
     */
    $db,
    /**
     * @var string The mode of the controller (dom, cli...), which will determine its route
     */
    $mode,
    /**
     * The data model
     * @var array
     */
    $data = [],
    /**
     * All the parts of the path requested
     * @var array
     */
    $params = [],
    /**
     * All the parts of the path requested which are not part of the controller path
     * @var array
     */
    $arguments = [],
    /**
     * The data sent through POST
     * @var array
     */
    $post = [],
    /**
     * The data sent through GET
     * @var array
     */
    $get = [],
    /**
     * A numeric indexed array of the files sent through POST (different from native)
     * @var array
     */
    $files = [],
		/**
		 * The output object
		 * @var null|object
		 */
		$obj,
    /**
     * An external object that can be filled after the object creation and can be used as a global with the function add_inc
     * @var stdClass
     */
    $inc;


	/**
	 * This will call the initial build a new instance. It should be called only once from within the script. All subsequent calls to controllers should be done through $this->add($path).
	 *
	 * @param bbn\mvc $mvc
	 * @param array $files
   * @param array|boolean $data
	 */
	public function __construct(bbn\mvc $mvc, array $files, $data = false){
    $this->mvc = $mvc;
    $this->reset($files, $data);
	}

  public function reset(array $info, $data = false){
    if ( \defined('BBN_CUR_PATH') && isset($info['mode'], $info['path'], $info['file'], $info['request'], $info['root']) ){
      $this->path = $info['path'];
      $this->plugin = $info['plugin'];
      $this->request = $info['request'];
      $this->file = $info['file'];
      $this->root = $info['root'];
      $this->arguments = $info['args'];
      $this->checkers = $info['checkers'];
      $this->mode = $info['mode'];
      $this->data = \is_array($data) ? $data : [];
      // When using CLI a first parameter can be used as route,
      // a second JSON encoded can be used as $this->post
      /** @var bbn\db db */
      $this->db = $this->mvc->get_db();
      $this->inc = $this->mvc->inc;
      $this->post = $this->mvc->get_post();
      $this->get = $this->mvc->get_get();
      $this->files = $this->mvc->get_files();
      $this->params = $this->mvc->get_params();
      $this->url = $this->get_url();
      $this->obj = new \stdClass();
    }
  }

  public function get_root(){
    return $this->mvc->get_root();
  }

  public function set_root($root){
    $this->mvc->set_root($root);
    return $this;
  }

  public function get_url(){
		return $this->mvc->get_url();
	}

  public function get_path(){
    return $this->path;
  }

  public function get_request(){
    return $this->request;
  }

	public function exists(){
		return !empty($this->path);
	}

	public function say_all(){
		return [
			'controller' => $this->say_controller(),
			'dir' => $this->say_dir(),
			'local_path' => $this->say_local_path(),
			'local_route' => $this->say_local_route(),
      'path' => $this->say_path(),
      'root' => $this->say_root(),
			'route' => $this->say_route(),
      'checkers' => $this->checkers
		];
	}

  /**
   * Returns the current controller's root drrectory.
   *
   * @return string
   */
  public function say_root()
  {
    return $this->root;
  }

  /**
   * Returns the current controller's file's name.
   *
   * @return string
   */
  public function say_controller()
  {
    return $this->file;
  }

  /**
   * Returns the current controller's path.
   *
   * @return string
   */
  public function say_path()
  {
    return $this->path;
  }

  /**
   * Returns the current controller's path.
   *
   * @return string
   */
  public function say_local_path()
  {
    if ( ($pp = $this->get_prepath()) && (strpos($this->path, $pp) === 0) ){
      return substr($this->path, \strlen($pp));
    }
    return $this->path;
  }

  /**
	 * Returns the current controller's route, i.e as demanded by the client.
	 *
	 * @return string
	 */
	public function say_route()
	{
		return $this->request;
	}

  /**
   * Returns the current controller's path.
   *
   * @return string
   */
  public function say_local_route()
  {
    if ( ($pp = $this->get_prepath()) && (strpos($this->request, $pp) === 0) ){
      return substr($this->request, \strlen($pp));
    }
    return $this->request;
  }

	/**
	 * Returns the current controller's file's name.
	 *
	 * @return string
	 */
	public function say_dir()
	{
    if ( $this->path ){
      $p = dirname($this->path);
      if ( $p === '.' ){
        return '';
      }
			if (
				($prepath = $this->get_prepath()) &&
				(strpos($p, $prepath) === 0)
			){
				return substr($p, \strlen($prepath));
			}
      return $p;
    }
		return false;
	}

  /**
   * @return mixed
   */
  public function say_plugin(){
    return $this->plugin;
  }

  /**
	 * This directly renders content with arbitrary values using the existing Mustache engine.
	 *
	 * @param string $view The view to be rendered
	 * @param array $model The data model to fill the view with
	 * @return void
	 */
	public function render($view, $model=''){
    if ( empty($model) && $this->has_data() ){
			$model = $this->data;
		}
    if ( \is_string($view) ){
      return \is_array($model) ? bbn\tpl::render($view, $model) : $view;
		}
		die(bbn\x::hdump("Problem with the template", $view, $this->path, $this->mode));
	}

	/**
	 * Returns true if called from CLI/Cron, false otherwise
	 *
	 * @return boolean
	 */
	public function is_cli()
	{
		return $this->mvc->is_cli();
	}

	/**
	 * This will reroute a controller to another one seemlessly. Chainable
	 *
	 * @param string $path The request path <em>(e.g books/466565 or xml/books/48465)</em>
	 * @return void
	 */
	public function reroute($path='', $post = false, $arguments = false)
	{
	  if ( !\in_array($path, $this->reroutes) && ($this->path !== $path) ){
      $this->reroutes[] = $path;
      $this->mvc->reroute($path, $post, $arguments);
      $this->is_rerouted = 1;
    }
	}

  /**
	 * This will include a file from within the controller's path. Chainable
	 *
	 * @param string $file_name If .php is ommited it will be added
	 * @return $this
	 */
	public function incl($file_name){
		if ( $this->exists() ){
			$d = dirname($this->file).'/';
			if ( substr($file_name, -4) !== '.php' ){
				$file_name .= '.php';
			}
			if ( (strpos($file_name, '..') === false) && file_exists($d.$file_name) ){
				$bbn_path = $d.$file_name;
				$ctrl =& $this;
				unset($d, $file_name);
				include($bbn_path);
			}
		}
		return $this;
	}

	/**
	 * This will add the given string to the script property, and create it if needed. Chainable
	 *
	 * @param string $script The javascript chain to add
	 * @return $this
	 */
	public function add_script($script){
		if ( \is_object($this->obj) ){
			if ( !isset($this->obj->script) ){
				$this->obj->script = '';
			}
			$this->obj->script .= $script;
		}
		return $this;
	}

	public function register_plugin_classes($plugin_path): self
  {
    spl_autoload_register(function($class_name) use ($plugin_path){
      if (
        (strpos($class_name,'/') === false) &&
        (strpos($class_name,'.') === false)
      ){
        $cls = explode('\\', $class_name);
        $path = implode('/', $cls);
        if ( file_exists($plugin_path.'lib/'.$path.'.php') ){
          include_once($plugin_path.'lib/'.$path.'.php');
        }
      }
    });
    return $this;
  }

	/**
	 * This will enclose the controller's inclusion
	 * It can be publicly launched through check()
	 *
	 * @return boolean
	 */
	private function control(){
		if ( $this->file && !isset($this->is_controlled) ){
      $ok = 1;
      if ( $this->plugin ){
        $this->register_plugin_classes($this->plugin_path());
      }
			ob_start();
			foreach ( $this->checkers as $appui_checker_file ){
				// If a checker file returns false, the controller is not processed
				// The checker file can define data and inc that can be used in the subsequent controller
        if ( bbn\mvc::include_controller($appui_checker_file, $this, true) === false ){
					$ok = false;
					break;
				}
			}
      if ( ($log = ob_get_contents()) && \is_string($log) ){
			  $this->obj->content = $log;
      }
      ob_end_clean();
      // If rerouted during the checkers
      if ( $this->is_rerouted ){
        $this->is_rerouted = false;
        return $this->control();
      }
      if ( !$ok ){
        return false;
      }
      $output = bbn\mvc::include_controller($this->file, $this);
      // If rerouted during the controller
      if ( $this->is_rerouted ){
        $this->is_rerouted = false;
        return $this->control();
      }
			if ( \is_object($this->obj) && !isset($this->obj->content) && !empty($output) ){
				$this->obj->content = $output;
			}
      $this->is_controlled = 1;
		}
		return $this->is_controlled ? true : false;
	}

	/**
	 * This will launch the controller in a new function.
	 * It is publicly launched through check().
	 *
	 * @return $this
	 */
	public function process(){
		if ( \is_null($this->is_controlled) ){
			$this->control();
		}
		return $this;
	}

	public function has_been_rerouted(){
	  return $this->is_rerouted;
}

	/**
	 * This will get a javascript view encapsulated in an anonymous function for embedding in HTML.
	 *
	 * @param string $path
	 * @return string|false
	 */
	public function get_js($path='', array $data=null, $encapsulated = true){
    if ( \is_array($path) ){
      $data = $path;
      $path = '';
    }
    if ( $r = $this->get_view($path, 'js', $data) ){
      return '<script>'.
        ( $encapsulated ? '(function($){' : '' ).
        ( empty($data) ? '' : 'var data = '.\bbn\x::js_object($data).';' ).
        $r.
        ( $encapsulated ? '})(jQuery);' : '' ).
        '</script>';
    }
    return false;
	}

  /**
   * This will get a javascript view encapsulated in an anonymous function for embedding in HTML.
   *
   * @param array|string $files
   * @param array $data
   * @param boolean $encapsulated
   * @return string|false
   */
  public function get_js_group($files='', array $data=null, $encapsulated = true){
    if ( $js = $this->get_view_group($files, $data, 'js') ){
      return '<script>'.
      ( $encapsulated ? '(function($){' : '' ).
      ( empty($data) ? '' : 'var data = '.\bbn\x::js_object($data).';' ).
      $js.
      ( $encapsulated ? '})(jQuery);' : '' ).
      '</script>';
    }
    return false;
  }

  /**
   * This will get a javascript view encapsulated in an anonymous function for embedding in HTML.
   *
   * @param array|string $files
   * @param array $data
   * @param string $mode
   * @return string|false
   */
  public function get_view_group($files='', array $data=null, $mode = 'html'){
    if ( !\is_array($files) ){
      if ( !($tmp = $this->mvc->fetch_dir($files, $mode)) ){
        $this->error("Impossible to get files from directory $files");
        return false;
      }
      $files = $tmp;
    }
    if ( \is_array($files) && \count($files) ){
      $st = '';
      foreach ( $files as $f ){
        if ( $tmp = $this->get_view($f, $mode, $data) ){
          $st .= $tmp;
        }
      }
      return $st;
    }
    $this->error('Impossible to get files from get_view_group files argument empty');
  }

  /**
   * This will get a CSS view encapsulated in a scoped style tag.
   *
   * @param string $path
   * @return string|false
   */
  public function get_css($path=''){
    if ( $r = $this->get_view($path, 'css') ){
      return \CssMin::minify($r);
    }
    return false;
  }

  /**
   * This will get and compile a LESS view encapsulated in a scoped style tag.
   *
   * @param string $path
   * @return string|false
   */
  public function get_less($path=''){
    if ( $r = $this->get_view($path, 'css', false) ){
      return \CssMin::minify($r);
    }
  }

  /**
   * This will get a CSS view encapsulated in a scoped style tag.
   *
   * @param string $path
   * @return string|false
   */
  public function add_css($path=''){
    if ( $css = $this->get_css($path) ){
      if ( !isset($this->obj->css) ){
        $this->obj->css = '';
      }
      $this->obj->css .= $css;
    }
    return $this;
  }

  /**
   * This will get and compile a LESS view encapsulated in a scoped style tag.
   *
   * @param string $path
   * @return string|false
   */
  public function add_less($path=''){
    if ( $css = $this->get_less($path) ){
      if ( !isset($this->obj->css) ){
        $this->obj->css = '';
      }
      $this->obj->css .= $css;
    }
    return $this;
  }

  /**
   * This will add a javascript view to $this->obj->script
   * Chainable
   *
   * @param string $path
   * @param string $mode
   * @return string|false
   */
  public function add_js(){
    $args = \func_get_args();
    $has_path = false;
    foreach ( $args as $i => $a ){
      if ( $new_data = $this->retrieve_var($a) ){
        $this->js_data($new_data);
      }
      else if ( \is_string($a) ){
        $has_path = 1;
      }
      else if ( \is_array($a) ){
        $this->js_data($a);
      }
    }
    if ( !$has_path ){
      array_unshift($args, $this->path);
    }
    $args[] = 'js';
    if ( $r = $this->get_view(...$args) ){
      $this->add_script($r);
    }
    return $this;
  }

  /**
   * This will add a javascript view to $this->obj->script
   * Chainable
   *
   * @param string $path
   * @param string $mode
   * @return string|false
   */
  public function add_js_group($files = '', array $data = []){
    if ( $js = $this->get_view_group($files, $data, 'js') ){
      $this->js_data($data)->add_script($js);
    }
    return $this;
  }

  public function set_title($title){
    $this->obj->title = $title;
    return $this;
  }

  public function set_icon(string $icon){
    $this->obj->icon = $icon;
    return $this;
  }

  public function set_color(string $bg = null, string $txt = null){
    if ( $bg ){
      $this->obj->bcolor = $bg;
    }
    if ( $txt ){
      $this->obj->fcolor = $txt;
    }
    return $this;
  }

  public function js_data($data){
		if ( bbn\x::is_assoc($data) ){
			if ( !isset($this->obj->data) ){
				$this->obj->data = $data;
			}
			else if ( bbn\x::is_assoc($this->obj->data) ){
				$this->obj->data = bbn\x::merge_arrays($this->obj->data, $data);
			}
		}
		return $this;
	}

	private function get_arguments(array $args){
    $r = [];
    foreach ( $args as $a ){
      if ( $new_data = $this->retrieve_var($a) ){
        $r['data'] = $new_data;
      }
      else if ( \is_string($a) && !isset($r['path']) ){
        $r['path'] = \strlen($a) ? $a : $this->path;
      }
      else if ( \is_string($a) && router::is_mode($a) && !isset($r['mode']) ){
        $r['mode'] = $a;
      }
      else if ( \is_array($a) && !isset($r['data']) ){
        $r['data'] = $a;
      }
      else if ( \is_bool($a) && !isset($r['die']) ){
        $r['die'] = $a;
      }
    }
    if ( !isset($r['mode']) && isset($r['path']) && router::is_mode($r['path']) ){
      $r['mode'] = $r['path'];
      unset($r['path']);
    }
    if ( !isset($r['path']) ){
      $r['path'] = $this->path;
    }
    else if ( strpos($r['path'], './') === 0 ){
      $r['path'] = $this->say_dir().substr($r['path'], 1);
    }
    if ( !isset($r['data']) ){
      $r['data'] = $this->data;
    }
    if ( !isset($r['die']) ){
      $r['die'] = true;
    }
    return $r;
  }

	/**
	 * This will get a view.
	 *
	 * @param string $path
	 * @param string $mode
	 * @return string|false
	 */
	public function get_view()
	{
    $args = $this->get_arguments(\func_get_args());
		/*if ( !isset($args['mode']) ){
      $v = $this->mvc->get_view($args['path'], 'html', $args['data']);
      if ( !$v ){
        $v = $this->mvc->get_view($args['path'], 'php', $args['data']);
      }
		}
		else{
      $v = $this->mvc->get_view($args['path'], $args['mode'], $args['data']);
    }*/
		if ( empty($args['mode']) ){
      $args['mode'] = 'html';
    }
    $v = $this->mvc->get_view($args['path'], $args['mode'], $args['data']);
		/*
    if ( !$v && $args['die'] ){
      die("Impossible to find the $args[mode] view $args[path] from $args[file]");
    }
		*/
    return $v;
	}

  public function get_external_view(string $full_path, string $mode = 'html', array $data=null){
    return $this->mvc->get_external_view($full_path, $mode, $data);
  }

  public function get_plugin_view(string $path, string $type = 'html', array $data = []){
    return $this->mvc->get_plugin_view($path, $type, $data, $this->say_plugin());
  }

  public function get_plugin_views(string $path, array $data = [], array $data2 = null){
    return [
      'html' => $this->mvc->get_plugin_view($path, 'html', $data, $this->say_plugin()),
      'css' => $this->mvc->get_plugin_view($path, 'css', [], $this->say_plugin()),
      'js' => $this->mvc->get_plugin_view($path, 'js', $data2 ?: $data, $this->say_plugin()),
    ];
  }
/*
  public function get_php(){
    $args = $this->get_arguments(\func_get_args());
    $v = $this->mvc->get_view($args['path'], 'php', $args['data']);
    if ( !$v && $args['die'] ){
      die("Impossible to find the PHP view $args[path]");
    }
    return $v;
  }

  public function get_html(){
    $args = $this->get_arguments(\func_get_args());
    $v = $this->mvc->get_view($args['path'], 'html', $args['data']);
    if ( !$v && $args['die'] ){
      die("Impossible to find the HTML view $args[path]");
    }
    return $v;
  }
*/
	private function retrieve_var($var){
		if ( \is_string($var) && (substr($var, 0, 1) === '$') && isset($this->data[substr($var, 1)]) ){
			return $this->data[substr($var, 1)];
		}
		return false;
	}

  public function action(){
    $this->obj = $this->add_data(['res' => ['success' => false]])->add_data($this->post)->get_object_model('', $this->data);
  }

  public function cached_action($ttl = 60){
    $this->obj = \bbn\x::to_object(
      $this->add_data(['res' => ['success' => false]])
           ->add_data($this->post)
           ->get_cached_model('', $this->data, $ttl)
    );
  }

  /**
   * @param string $title
   * @param null|array $data
   */
  public function combo($title = null, $data = null, $cached = null)
  {
		$this->obj->css = $this
      ->add_data($cached ?
        $this->get_cached_model(bbn\x::merge_arrays($this->post, $this->data), $cached) :
        $this->get_model(bbn\x::merge_arrays($this->post, $this->data))
      )
      ->get_less($this->path, false);
		if ( $new_title = $this->retrieve_var($title) ){
			$this->set_title($new_title);
		}
		else if ( $title ){
			$this->set_title($title);
		}
		if ( $tmp = $this->retrieve_var($data) ){
		  $data = $tmp;
    }
    else if ( !\is_array($data) ){
      $data = $data === true ? $this->data : [];
    }
		if ( $this->mode === 'dom' ){
		  $this->data['script'] = $this->get_js($this->path, $data);
    }
    else{
      $this->add_js($this->path, $data, false);
    }
		echo $this->get_view($this->path, false);
	}

  /**
   * This will get a the content of a file located within the data path
   *
   * @param string $file_name
   * @return string|false
   */
  public function get_content($file_name){
    if ( $this->check_path($file_name) &&
      \defined('BBN_DATA_PATH') &&
      is_file(BBN_DATA_PATH.$file_name)
    ){
      return file_get_contents(BBN_DATA_PATH.$file_name);
    }
    return false;
  }

  /**
   * This will return the path to the directory of the current controller
   *
   * @return string
   */
  public function get_dir()
  {
    return $this->dir;
  }

  public function get_routes(){
    return $this->mvc->get_routes();
  }

  public function get_route($path, $mode, $root = null){
    return $this->mvc->get_route($path, $mode, $root);
  }

  public function get_prepath(){
    if ( $this->exists() ){
      return $this->mvc->get_prepath();
    }
  }

  public function set_prepath($path){
    if ( $this->exists() && $this->mvc->set_prepath($path) ){
      $this->params = $this->mvc->get_params();
      return $this;
    }
    die("Prepath $path is not valid");
	}

	/**
	 * This will get the model. There is no order for the arguments.
	 *
	 * @params string path to the model
	 * @params array data to send to the model
	 * @return array|false A data model
	 */
	public function get_model(){
    $args = \func_get_args();
    $die = false;
    foreach ( $args as $a ){
      if ( \is_string($a) && \strlen($a) ){
        $path = $a;
      }
      else if ( \is_array($a) ){
        $data = $a;
      }
      else if ( \is_bool($a) ){
        $die = $a;
      }
    }
    if ( !isset($path) ){
      $path = $this->path;
    }
		else if ( strpos($path, './') === 0 ){
			$path = $this->say_dir().substr($path, 1);
		}
    if ( !isset($data) ){
      $data = $this->data;
    }
		$m = $this->mvc->get_model($path, $data, $this);
		if ( \is_object($m) ){
			$m = bbn\x::to_array($m);
		}
    if ( !\is_array($m) ){
			if ( $die ){
				die("$path is an invalid model");
			}
			return [];
    }
    return $m;
	}

  public function get_plugin_model($path, $data = []){
	  return $this->mvc->get_plugin_model($path, $data, $this, $this->say_plugin());
  }

  /**
   * This will get the model. There is no order for the arguments.
   *
   * @params string path to the model
   * @params array data to send to the model
   * @return array|false A data model
   */
  public function get_cached_model(){
    $args = \func_get_args();
    $die = 1;
    foreach ( $args as $a ){
      if ( \is_string($a) && \strlen($a) ){
        $path = $a;
      }
      else if ( \is_array($a) ){
        $data = $a;
      }
      else if ( \is_int($a) ){
        $ttl = $a;
      }
      else if ( \is_bool($a) ){
        $die = $a;
      }
    }
    if ( !isset($path) ){
      $path = $this->path;
    }
    else if ( strpos($path, './') === 0 ){
      $path = $this->say_dir().substr($path, 1);
    }
    if ( !isset($data) ){
      $data = $this->data;
    }
    $m = $this->mvc->get_cached_model($path, $data, $this, $ttl);
    if ( !\is_array($m) && !$die ){
      die("$path is an invalid model");
    }
    return $m;
  }

  /**
   * This will delete the cached model. There is no order for the arguments.
   *
   * @params string path to the model
   * @params array data to send to the model
   */
  public function delete_cached_model(){
    $args = \func_get_args();

    foreach ( $args as $a ){
      if ( \is_string($a) && \strlen($a) ){
        $path = $a;
      }
      else if ( \is_array($a) ){
        $data = $a;
      }
    }
    if ( !isset($path) ){
      $path = $this->path;
    }
    else if ( strpos($path, './') === 0 ){
      $path = $this->say_dir().substr($path, 1);
    }
    if ( !isset($data) ){
      $data = $this->data;
    }
    return $this->mvc->delete_cached_model($path, $data, $this);
  }

	/**
	 * This will get the model. There is no order for the arguments.
	 *
	 * @params string path to the model
	 * @params array data to send to the model
	 * @return $this
	 */
	public function set_cached_model(){
		$args = \func_get_args();
		$die = 1;
		foreach ( $args as $a ){
			if ( \is_string($a) && \strlen($a) ){
				$path = $a;
			}
			else if ( \is_array($a) ){
				$data = $a;
			}
      else if ( \is_int($a) ){
        $ttl = $a;
      }
      else if ( \is_bool($a) ){
        $die = $a;
      }
		}
		if ( !isset($path) ){
			$path = $this->path;
		}
		else if ( strpos($path, './') === 0 ){
			$path = $this->say_dir().substr($path, 1);
		}
    if ( !isset($data) ){
      $data = $this->data;
    }
    if ( !isset($ttl) ){
      $ttl = 10;
    }
		$this->mvc->set_cached_model($path, $data, $this, $ttl);
		return $this;
	}

	public function get_object_model(){
    $m = $this->get_model(...func_get_args());
    if ( \is_array($m) ){
      return bbn\x::to_object($m);
    }
  }

  /**
	 * Adds a property to the MVC object inc if it has not been declared.
	 *
	 * @return bool
	 */
	public function add_inc($name, $obj)
	{
		if ( !isset($this->inc->{$name}) ){
			$this->inc->{$name} = $obj;
		}
	}

	/**
	 * Returns the output object.
	 *
	 * @return object|false
	 */
	public function get()
	{
    return $this->obj;
	}

	public function transform(callable $fn): void
  {
    $this->obj = $fn($this->obj);
  }

  /**
   * Checks if data exists
   *
   * @return bool
   */
  public function has_data($data=null)
  {
    if ( \is_null($data) ){
      $data = $this->data;
    }
    return ( \is_array($data) && (\count($data) > 0) ) ? 1 : false;
  }

  /**
   * Checks if there is ny HTML content in the object
   *
   * @return bool
   */
  public function has_content()
  {
    if ( !\is_object($this->obj) ){
      return false;
    }
    return !empty($this->obj->content);
  }

  /**
	 * Returns the rendered result from the current mvc if successufully processed
	 * process() (or check()) must have been called before.
	 *
	 * @return string|false
	 */
	public function get_rendered()
	{
		if ( isset($this->obj->content) ){
			return $this->obj->content;
		}
		return false;
	}

	public function get_mode(){
		return $this->mode;
	}

	public function set_mode($mode){
		if ( $this->mvc->set_mode($mode) ){
			$this->mode = $mode;
			//die(var_dump($mode));
		}
		return $this;
	}

	/**
	 * Returns the rendered result from the current mvc if successufully processed
	 * process() (or check()) must have been called before.
	 *
	 * @return string|false
	 */
	public function get_script()
	{
		if ( isset($this->obj->script) ){
			return $this->obj->script;
		}
		return '';
	}

	/**
	 * Sets the data. Chainable. Should be useless as $this->data is public. Chainable.
	 *
	 * @param array $data
	 * @return $this
	 */
	public function set_data(array $data)
	{
		$this->data = $data;
		return $this;
	}

	/**
	 * Merges the existing data if there is with this one. Chainable.
	 *
	 * @return $this
	 */
	public function add_data(array $data){
		$ar = \func_get_args();
		foreach ( $ar as $d ){
			if ( \is_array($d) ){
				$this->data = $this->has_data() ? array_merge($this->data, $d) : $d;
			}
		}
		return $this;
	}

	/**
	 * Merges the existing data if there is with this one. Chainable.
	 *
	 * @return void
	 */
	public function add($path, $data=[], $internal = false)
	{
		if ( substr($path, 0, 2) === './' ){
			$path = $this->say_dir().substr($path, 1);
		}
    if ( $route = $this->mvc->get_route($path, $internal ? 'private' : 'public') ){
      $o = new controller($this->mvc, $route, $data);
      $o->process();
      return $o;
    }
		return false;
	}

	public function get_result(){
		return $this->obj;
	}

}
