<?php
/**
 * @package html
 */
namespace bbn\html;
use bbn;
/**
 * HTML Class creating a form INPUT
 *
 *
 * @author Thomas Nabet <thomas.nabet@gmail.com>
 * @copyright BBN Solutions
 * @since Apr 2, 2013, 21:27:42 +0000
 * @category  MVC
 * @license   http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @version 0.4
 * @todo ???
 */
class input extends element
{
  protected
          /** @var null|string The input's label/title */
          $label,
          
          /** @var mixed The input's value */
      		$value = '',
          
          /** @var mixed The input's default value */
          $default = '',
          
          /** @var bool Can the input's value be null */
          $null,

          /** @var string The corresponding DB table??? */
          $table,
          
          /** @var string The field shortcut */
          $field,

          /** @var string The language */
          $lang;
  
	/**
	 * @param array $cfg The JSON schema configuration (combined with element::$schema)
	 */
  protected static
    $schema = '{
	"properties":{
		"attr": {
			"type":"array",
			"id": "attr",
      "description": "Attributes",
			"required":true,
			"properties":{
				"maxlength": {
					"type":"string",
					"id": "id",
          "description": "ID",
					"required":false
				},
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
		"sql": {
			"type": ["string"],
			"id": "sql",
			"required":false,
      "description": "SQL request"
		},
		"table": {
			"type":"string",
      "description": "Table",
			"id": "table",
			"required":false
		},
	}
}';
  
	/**
	 * This will build a new HTML form element according to the given configuration.
	 * Only name and tag are mandatory, then other values depending on the tag
   * 
   * @param array $cfg The configuration
   * @return self
	 */
  public function __construct($cfg)
	{
    
    if ( \is_string($cfg) ){
      $cfg = [
          'field' => 'text',
          'attr' => [
              'name' => $cfg
          ]
      ];
    }
    
    parent::__construct($cfg);
    
		if ( $this->tag ){
      
			$mandatory_attr = [];
      
      if ( !isset($this->attr['id']) ){
  			$this->attr['id'] = bbn\str::genpwd(20,15);
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
    return $this;
	}
	
	/**
	 * Returns the element with its label and inside a div
   * 
   * @return string
	 */
	public function html_with_label($with_script=1)
	{
		$s = $this->html();
    if ( !empty($s) ){
			if ( BBN_IS_DEV ){
        $title = str_replace('"', '', print_r (bbn\str::make_readable($this->cfg), true));
			}
			else if ( isset($this->attr['title']) ){
				$title = $this->attr['title'];
			}
      else{
				$title = isset($this->label) ? $this->label : '';
			}
      if ( !isset($this->cfg['field']) || $this->cfg['field'] !== 'hidden' ){
  			$s = '<label class="bbn-form-label" title="'.$title.'" for="'.$this->attr['id'].'">'.$this->label.'</label><div class="bbn-form-field">'.$s.'</div>';
      }
		}
		return $s;
	}
}
?>