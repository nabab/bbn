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
class input extends element
{
  protected
	/**
	 * The input's label/title
	 * @var null|string
	 */
		$label,
	/**
	 * The input's value
	 * @var mixed
	 */
		$value = '',
	/**
	 * The input's default value
	 * @var mixed
	 */
		$default = '';
  
	/**
	 * This will build a new HTML form element according to the given configuration.
	 * Only name and tag are mandatory, then other values depending on the tag
	 *
	 * @param array $cfg The element configuration
	 */
  protected static
    $schema = '{
	"properties":{
		"attr": {
			"type":"object",
			"id": "attr",
      "description": "Attributes",
			"required":true,
			"properties":{
				"maxlength": {
					"type":"integer",
					"id": "maxlength",
          "description": "Maxlength",
					"required":false
				},
        "name": {
          "type":"string",
          "id": "name",
          "description": "Name",
          "required":true
        },
        "required": {
          "type": "boolean",
          "id": "required",
          "description": "Required",
          "required":false
        },
				"type": {
					"type":"string",
					"id": "type",
          "description": "Input type",
					"required":false
				},
        "value": {
          "type":"string",
          "description": "Value",
          "id": "value",
          "required":false
        }
			}
		},
		"default": {
			"type":"any",
			"id": "default",
      "description": "Default value",
			"required":false
		},
		"elements": {
			"type":"array",
      "description": "Items",
			"id": "items",
			"required":false
		},
		"field": {
			"type":"string",
			"id": "field",
      "description": "Shortcut (??) field",
			"required":false
		},
		"label": {
			"type":"string",
			"id": "label",
      "description": "Label",
			"required":false
		},
		"lang": {
			"type":"string",
			"id": "lang",
      "description": "Language",
			"required":false
		},
		"null": {
			"type":"boolean",
			"id": "null",
      "description": "can be null?",
			"required":false
		},
		"params": {
			"type": ["array","null"],
			"id": "params",
			"required":false,
      "description": "Parameters from BBN"
		},
		"placeholder": {
			"type":"boolean",
			"id": "placeholder",
      "description": "Place holder",
			"required":false
		},
		"table": {
			"type":"string",
      "description": "Table",
			"id": "table",
			"required":false
		},
		"tag": {
      "enum": ["input","select","textarea"]
		},
		"value": {
			"type":"string",
      "description": "Value",
			"id": "value",
			"required":false
		}
	}
}';
  
  protected static function _init(){
    if ( is_string(self::$schema) ){
      self::$schema = array_merge(parent::$schema, self::$schema);
      parent::_init();
    }
  }

  public function __construct(array $cfg = null)
	{
    parent::__construct($cfg);
		if ( $this->tag ){
			$mandatory_attr = array();
      if ( !isset($this->attr['id']) ){
  			$this->attr['id'] = \bbn\str\text::genpwd(20,15);
      }
			$this->script = isset($cfg['script']) ? $cfg['script'] : '';
			$this->value = isset($cfg['value']) ? $cfg['value'] : '';
			switch ( $this->tag )
			{
				case "input":
					array_push($mandatory_attr, "type");
					break;
				case "textarea":
					array_push($mandatory_attr, "cols", "rows");
					break;
				case "select":
					array_push($mandatory_attr, "attr");
					break;
			}
			foreach ( $mandatory_attr as $m ){
				if ( !isset($this->attr[$m]) ){
					die("Argument $m is missing in your config... Sorry!");
				}
			}
		}
	}
	
	public function get_label_input()
	{
		$s = $this->get_html();
		if ( !empty($s) ){
			if ( BBN_IS_DEV ){
        $title = str_replace('"', '', print_r (\bbn\str\text::make_readable($this->cfg), true));
			}
			else if ( isset($this->attr['title']) ){
				$title = $this->attr['title'];
			}
			else{
				$title = isset($this->label) ? $this->label : '';
			}
      if ( !isset($this->cfg['field']) || $this->cfg['field'] !== 'hidden' ){
  			$s = '<label class="appui-form-label" title="'.$title.'" for="'.$this->attr['id'].'">'.$this->label.'</label><div class="appui-form-field">'.$s.'</div>';
      }
		}
		return $s;
	}
}
?>