<?php
/**
 * @package bbn\html
 */
namespace bbn\html;
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
		"click": {
			"type":"string",
			"id": "click",
      "description": "onClick event function",
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
		"submit": {
			"type":"boolean",
      "description": "Submit",
			"id": "submit",
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
    if ( is_string($cfg) ){
      $cfg = [
          'type' => 'submit',
          'text' => $cfg
      ];
    }
    $cfg['tag'] = 'button';
    parent::__construct($cfg);
    return $this;
	}
	
}
?>