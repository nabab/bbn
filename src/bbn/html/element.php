<?php
namespace bbn\html;
/**
 * Model View Controller Class
 *
 *
 * This class will route a request to the according model and/or view through its controller.
 * A model and a view can be automatically associated if located in the same directory branch with the same name than the controller in their respective locations
 * A view can be directly imported in the controller through this very class
 *
 * @author Thomas Nabet <thomas.nabet@gmail.com>
 * @copyright BBN Solutions
 * @since Apr 4, 2011, 23:23:55 +0000
 * @category  MVC
 * @license   http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @version 0.2r89
 * @todo Merge the output objects and combine JS strings.
 * @todo Stop to rely only on sqlite and offer file-based or any db-based solution.
 * @todo Look into the check function and divide it
 */
class element
{
	protected
          $cfg,
          $content;
  
  public
          $tag = false,
          $attr = array(),
          $css,
          $script,
          $events,
          $data,
          $widget,
          $help;

  public static 
          $self_closing_tags = ["area", "base", "hr", "col", "command", "embed", "hr", "img", "input", "keygen", "link", "meta", "param", "source", "track", "wbr"];
	/**
	 * This will build a new HTML form element according to the given configuration.
	 * Only name and tag are mandatory, then other values depending on the tag
	 *
	 * @param array $cfg The element configuration
	 */
  protected static
          $schema = '{
	"type":"object",
	"$schema": "http:\/\/json-schema.org\/draft-03\/schema",
	"id": "#",
	"required":false,
	"properties":{
		"attr": {
			"type":"object",
			"id": "attr",
      "description": "Attributes",
			"required":false,
			"properties":{}
		},
    "css": {
			"type":["object","null"],
			"id": "css",
			"required":false,
      "description": "Css properties"
		},
		"field": {
			"type":"string",
			"id": "field",
      "description": "Shortcut (??) field",
			"required":false
		},
		"events": {
			"type":"object",
			"id": "events",
      "description": "Events",
			"required":false,
			"properties":{
				"change": {
					"type":"string",
					"required":false
				}
			}
		},
		"script": {
			"type":"string",
      "description": "Script",
			"id": "script",
			"required":false
		},
		"tag": {
			"type":"string",
			"id": "tag",
      "description": "Tag",
			"required":true
		},
		"widget": {
			"type":["object","array"],
			"id": "widget",
      "description": "Widget",
			"required":false,
			"properties":{
				"name": {
					"type":"string",
					"required":true
				},
				"options": {
					"type":["object","array"],
					"required":false
				}
			}
		},
		"xhtml": {
			"type":"boolean",
			"id": "xhtml",
      "description": "XHTML conformity",
			"required":false
		}
	}
}',
          $validator = false,
          $input_fields = ["input", "textarea", "select"],
          $error;
  
  protected static function _init(){
    if ( !self::$validator ){
      self::$validator = new \JsonSchema\Validator();
      if ( is_string(self::$schema) ){
        self::$schema = json_decode(self::$schema);
      }
    }
    if ( is_string(static::$schema) ){
      $tmp = json_decode(static::$schema, 1);
      static::$schema = \bbn\tools::to_object(
              \bbn\tools::merge_arrays(\bbn\tools::to_array(self::$schema), $tmp));
    }
  }
  
  protected static function get_schema(){
    static::_init();
    return static::$schema;
  }
  
 	/**
	 * Returns a config more adequate for the schema
	 * @return array
	 */
  private static function cast($cfg, $schema=null){
    if ( is_null($schema) && is_object(static::$schema) ){
      $schema = static::$schema;
    }
    if ( is_object($schema) && is_array($cfg) && isset($schema->properties) ){
      foreach ( $schema->properties as $k => $p ){
        if ( isset($cfg[$k]) && is_object($p) ){
          if ( is_string($cfg[$k]) && $p->type === 'integer' ){
            $cfg[$k] = (int)$cfg[$k];
          }
          else if ( is_int($cfg[$k]) && $p->type === 'boolean' ){
            $cfg[$k] = (bool)$cfg[$k];
          }
          else if ( is_array($cfg[$k]) ){
            $cfg[$k] = self::cast($cfg[$k], $p);
          }
        }
      }
    }
    return $cfg;
  }

  public static function add_css($css){
    if ( is_string($css) ){
      return ' style="'.\bbn\str\text::escape_dquotes($css).'"';
    }
    else if ( is_array($css) && count($css) > 0 ){
      $st = '';
      foreach ( $css as $prop => $val ){
        $st .= $prop.':'.$val.';';
      }
      return ' style="'.\bbn\str\text::escape_dquotes($st).'"';
    }
  }

  public static function check_config($cfg){
    if ( !is_array($cfg) ){
      self::$error = "The configuration is not a valid array";
      return false;
    }
    self::$validator->check(json_decode(json_encode($cfg)), self::$schema);
    self::$error = '';
    if ( self::$validator->isValid() ){
      return 1;
    }
    foreach ( self::$validator->getErrors() as $error ) {
      self::$error .= sprintf("[%s] %s in \n",$error['property'], $error['message']);
    }
    return false;
  }
  
  public static function get_error()
  {
    return self::$error;
  }

  protected function update()
  {
    $this->cfg = [];
    foreach ( $this as $key => $var ){
      if ( $key !== 'cfg' && !is_null($var) ){
        if ( is_array($var) ){
          foreach ( $var as $k => $v ){
            if ( !isset($this->cfg[$key]) ){
              $this->cfg[$key] = [];
            }
            if ( !is_null($v) ){
              $this->cfg[$key][$k] = $v;
            }
          }
        }
        else{
          $this->cfg[$key] = $var;
        }
      }
    }
  }
  
  public function __construct(array $cfg = null)
	{
    self::_init();
    if ( is_string($cfg) ){
      $cfg = ["tag" => $cfg];
    }
   $cfg = self::cast($cfg);
		if ( self::check_config($cfg) ){
      foreach ( $cfg as $key => $val ){
        if ( $key === 'tag' ){
          $this->tag = strtolower($val);
        }
        else if ( property_exists(get_called_class(), $key) ){
          $this->$key = $val;
        }
      }
      $this->update();
    }
    else{
      var_dump("Error".
              ( isset($cfg['name']) ? " in ".$cfg['name'] : '' ).
              " !".self::get_error());
    }
	}
	
	/**
	 * Returns the current configuration.
	 */
	public function get_config()
	{
    $this->update();
		return $this->cfg;
	}
  
  public function get_param()
  {
    return \bbn\str\text::make_readable($this->get_config());
  }
  
  public function show_config()
  {
    return \bbn\str\text::export(\bbn\str\text::make_readable($this->get_config()), 1);
  }
	
	/**
	 * Returns the javascript coming with the object.
	 */
	public function get_script()
	{
    $this->update();
		$r = '';
		if ( isset($this->attr['id']) ){
      if ( isset($this->cfg['events']) ){
        foreach ( $this->cfg['events'] as $event => $fn ){
          $r .= '.'.$event.'(function(e){'.$fn.'})';
        }
      }
      if ( isset($this->cfg['widget'], $this->cfg['widget']['name']) ){
        $r .= '.'.$this->cfg['widget']['name'].'('.
                ( isset($this->cfg['widget']['options']) ? json_encode($this->cfg['widget']['options']) : '' ).
                ')';
      }
      if ( $this->help ){
        // tooltip
      }
      if ( !empty($r) ){
        $r = '$("#'.$this->attr['id'].'")'.$r.';'.PHP_EOL;
      }
		}
    if ( $this->script ){
      $r .= $this->script.PHP_EOL;
    }
		return $r;
	}
	
	/**
	 * Returns the corresponding HTML string 
	 */
	public function get_html($with_js = 1)
	{
    $html = '';
		if ( $this->tag ){
			$this->update();
      // TAG
			$html .= '<'.$this->tag;

      foreach ( $this->attr as $key => $val ){
        if ( is_string($key) ){
          $html .= ' '.htmlspecialchars($key).'="';
          if ( is_numeric($val) ){
            $html .= $val;
          }
          else if (is_string($val) ){
            $html .= htmlspecialchars($val);
          }
          $html .= '"';
        }
      }
			
      if ( isset($this->css) ){
				$html .= self::add_css($this->css);
			}

      $html .= '>';
			
			if ( !in_array($this->tag, self::$self_closing_tags) ){
        if ( isset($this->content) ){
          // @todo: Add the ability to imbricate elements
          if ( is_string($this->content) ){
            $html .= $this->content;
          }
        }
				$html .= '</'.$this->tag.'>';
			}
			
			if ( isset($this->placeholder) && strpos($this->placeholder,'%s') !== false ){
				$html = sprintf($this->placeholder, $html);
			}
		}
		return $html;
	}
	
}
?>