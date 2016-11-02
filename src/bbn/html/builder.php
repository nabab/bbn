<?php
/**
 * @package html
 */
namespace bbn\html;
use bbn;

/**
 * This class generates html form elements with a predefined configuration
 *
 *
 * @author Thomas Nabet <thomas.nabet@gmail.com>
 * @copyright BBN Solutions
 * @since Dec 14, 2012, 04:23:55 +0000
 * @category  Appui
 * @license   http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @version 0.4
*/

class builder
{
	/** @var int The maximum number of values in a dropdown list */
	const max_values_at_once = 200;
  
	private 
          /** @var array The default field's configuration */
          $_defaults = [
            'tag' => 'input',
            'attr' => [
              'type' => 'text',
              'name' => false
            ],
            'lang' => 'fr',
            'data' => [
                'sql' => false,
                'db' => false
            ],
          ],

          /** @var array The current default configuration */
          $_current_cfg,
          $_elements = [],
          $_cfg = [],
          $_chainable = false,
          $_root_element = false,
          $_current_element = false;
	
	public static
          /** @var array All the known type's values for inputs */
          $types = ['text', 'password', 'radio', 'checkbox', 'hidden', 'file', 'color', 'date', 'datetime', 'email', 'datetime-local', 'email', 'month', 'number', 'range', 'search', 'tel', 'time', 'url', 'week'],
          
          /** 
           * @var array Kendo Widgets' function names and config properties
           *  @todo Think about moving this somewhere so we can change widget API
           */
          $widgets = [
              'calendar' => 'kendoCalendar',
              'date' => 'kendoDatePicker',
              'autocomplete' => 'kendoAutoComplete',
              'dropdown' => 'kendoDropDownList',
              'combo' => 'kendoComboBox',
              'numeric' => 'kendoNumericTextBox',
              'time' => 'kendoTimePicker',
              'datetime' => 'kendoDateTimePicker',
              'slider' => 'kendoSlider',
              'rangeslider' => 'kendoRangeSlider',
              'upload' => 'kendoUpload',
              'multivalue' => 'multivalue',
              'editor' => 'ckeditor',
          ],
          
          $specs = [
              'widgets' => []
          ],
          
          $label_class = 'appui-form-label',
          $space_class = 'appui-line-breaker',
          $field_class = 'appui-form-field',
          $button_class = 'k-button';
  
  public static function give_id(array &$cfg){
    if ( !isset($cfg['attr']) ){
      $cfg['attr'] = [];
    }
    if ( !isset($cfg['attr']['id']) ){
      $cfg['attr']['id'] = bbn\str::genpwd(30, 15);
    }
  }

  public static function specs($cat, $item)
  {
    if ( bbn\str::check_name($cat, $item) ){
      if ( !isset(self::$specs[$cat]) &&
              is_dir(__DIR__.'/specs/'.$cat) ){
        self::$specs[$cat] = [];
      }
      
      if ( isset(self::$specs[$cat]) ){
        if ( !isset(self::$specs[$cat][$item]) &&
                file_exists(__DIR__.'/specs/'.$cat.'/'.$item.'.php') ){
          self::$specs[$cat][$item] = include_once(__DIR__.'/specs/'.$cat.'/'.$item.'.php');
        }
        if ( isset(self::$specs[$cat][$item]) ){
          return self::$specs[$cat][$item];
        }
        self::$specs[$cat][$item] = false;
      }
    }
    return false;
  }

  private function record($method, $cfg){
    array_push($this->_cfg, ['method' => $method, 'cfg' => $cfg]);
    return $this;
  }
  

  /**
	 * @param array $cfg The default config for the elements
	 */
	public function __construct( array $cfg = null )
	{
		if ( is_array($cfg) ){
      $this->parameters = $cfg;
      $this->_defaults = bbn\x::merge_arrays($this->_defaults, $cfg);
		}
		$this->reset();
	}
	
	/**
	 * Sets the configuration back to its default value
	 */
	public function reset()
	{
		$this->_current_cfg = [];
		$this->_cfg = [];
    $this->_root_element = false;
    $this->_current_element = false;
		foreach ( $this->_defaults as $k => $v ){
			$this->_current_cfg[$k] = $v;
		}
    return $this;
	}
	
  public function chained()
  {
    $this->_chainable = 1;
    return $this;
  }
  
  public function unchained()
  {
    $this->_chainable = false;
    return $this;
  }
  

	/**
	 * Returns the current configuration
	 * @return array
	 */
	public function export_config()
	{
    if ( isset($this->_root_element) ){
      return bbn\str::make_readable($this->_root_element->get_config());
    }
		return bbn\str::make_readable($this->_cfg);
	}

  public function save_config($cfg)
  {
    return $this->_chainable ? $this : $cfg;
  }
	
  public function load_config($cfg)
  {
    if ( $this->data['db'] && isset($cfg['data']['db']) && is_string($cfg['data']['db']) ){
      $cfg['data']['db'] = $this->data['db'];
    }
    foreach ( $cfg as $c ){
      $this->$c['method']($c['cfg']);
    }
    return $this->_chainable ? $this : $this->export_config();
  }
  
  public function html($with_js=false)
  {
    if ( isset($this->_root_element) ){
      return $this->_root_element->html($with_js);
    }
    return '';
  }
  
  public function script($with_ele=1)
  {
    if ( isset($this->_root_element) ){
      return $this->_root_element->script();
    }
    return '';
  }
  
	/**
	 * Change an option in the current configuration - Chainable
	 * @param array|string $opt Either an array with the param name and value, or 2 strings in the same order
	 * @return bbn\html\builder
	 */
	public function option($opt)
	{
		$args = func_get_args();
		if ( is_array($opt) && isset($opt[0], $this->_defaults[$opt[0]]) ){
			$this->_current_cfg[$opt[0]] = $opt[1];
		}
		else if ( isset($args[0], $args[1], $this->_defaults[$args[0]]) ){
			$this->_current_cfg[$args[0]] = $args[1];
		}
		else{
			throw new InvalidArgumentException('This configuration argument is imaginary... Sorry! :)');
		}
    return $this;
	}
	
  
  public function append($ele)
  {
    if ( $this->_current_element ){
      $this->_current_element->append($ele);
    }
    return $this;
  }
  
  public function hidden(array $cfg, $force=false)
  {
    $this->record('hidden', $cfg);
    $r = [];
    $i = 0;
    foreach ( $cfg as $k => $v ){
      $r[$i] = $this->input([
          'field' => 'hidden',
          'attr' => [
              'name' => $k,
              'value' => $v
          ]
      ], 1);
      $this->append($r[$i]);
      $i++;
    }
    return $this->_chainable && !$force ? $this : [$label, $container];
  }
  public function label_input($cfg, $force=false)
  {
    $this->record('label_input', $cfg);
    $ele = $this->input($cfg);
    $label = $this->label($ele->get_config());
    $container = new bbn\html\element([
        'tag' => 'div',
        'attr' => [
            'class' => self::$field_class
        ]
    ]);
    $container->append($ele);

    $this->append($label)->append($container);

    return $this->_chainable && !$force ? $this : [$label, $container];
  }
  
  public function central_input($cfg, $force=false)
  {
    $this->record('central_input', $cfg);
    $ele = $this->input($cfg);
    $container = new bbn\html\element([
        'tag' => 'div',
        'attr' => [
            'class' => self::$space_class.' appui-c'
        ]
    ]);
    $container->append($ele);
    $this->append($container);
    return $this->_chainable && !$force ? $this : $container;
  }
  
  public function fake_label(array $cfg, $force=false)
  {
    $this->record('fake_label', $cfg);
    $ele = new bbn\html\element($cfg);
    $label = $this->label($cfg);
    $container = new bbn\html\element([
        'tag' => 'div',
        'attr' => [
            'class' => self::$field_class
        ]
    ]);
    $container->append($ele);
    $this->append($label)->append($container);
    return $this->_chainable && !$force ? $this : [$label, $container];
  }
  
  public function space($cfg=null, $force=false)
  {
    $this->record('space', $cfg);
    if ( !is_null($cfg) ){
      if ( is_string($cfg) ){
        $cfg = [
            'text' => $cfg
        ];
      }
    }
    if ( !is_array($cfg) ){
      $cfg = [];
    }
    $cfg['tag'] = 'div';
    if ( !isset($cfg['attr']) ){
      $cfg['attr'] = [];
    }
    $cfg['attr']['class'] = self::$space_class;
    if ( !isset($cfg['text']) && !isset($cfg['content']) ){
      $cfg['content'] = '&nbsp;';
    }
    $space = new bbn\html\element($cfg);
    $this->append($space);
    return $this->_chainable && !$force ? $this : $space;
  }
	
  public function label_button($cfg, $force=false){
    $this->record('label_button', $cfg);
    $cont = new element('div');
    $ele = $this->button($cfg, 1);
    // Submit by default!
    if ( $this->_root_element && !is_string($ele->attr('type')) ){
      $ele->attr('type', 'submit');
    }
    $cont->add_class(self::$field_class)->append($ele);
    $label = $this->label(' ');
    $this->append($label)->append($cont);
    return $this->_chainable && !$force ? $this : [$label, $cont];
  }

  public function form($cfg)
  {
    $this->record('form', $cfg);
    $e = new bbn\html\form($cfg, $force=false);
    $this->_root_element =& $e;
    $this->_current_element =& $e;
    return $this->_chainable && !$force ? $this : $this->_root_element;
  }
  
  public function fieldset($title=null, $force=false)
  {
    $this->record('fieldset', $title);
    if ( is_array($title) ){

      $title['tag'] = 'fieldset';
      if ( isset($title['legend']) ){
        $legend_txt = $title['legend'];
        unset($title['legend']);
      }
      $fieldset = new bbn\html\element($title);
      $fieldset->add_class("appui-section");

      if ( isset($legend_txt) ){
        $legend = new bbn\html\element('legend');
        $legend->text($legend_txt);
        $fieldset->append($legend);
      }
    }
    else{

      $fieldset = new bbn\html\element('fieldset');
      $fieldset->add_class("appui-section");

      if ( !is_null($title) ){
        $legend = new bbn\html\element('legend');
        $legend->text($title);
        $fieldset->append($legend);
      }
    }
    if ( $this->_root_element ){
      $this->_root_element->append($fieldset);
    }
    else{
      $this->_root_element =& $fieldset;
    }
    $this->_current_element =& $fieldset;
    return $this->_chainable && !$force ? $this : $fieldset;
  }
  
  public function end_fieldset(){
    $this->record('end_fieldset', []);
    if ( $this->_current_element->tag === 'fieldset' ){
      $this->_current_element =& $this->_root_element;
    }
    return $this;
  }

  public function label($cfg)
  {
    if ( isset($cfg['null']) && $cfg['null'] ){
      $label = [
          'tag' => 'div',
          'attr' => [
              'class' => self::$label_class
          ],
          'content' => []
      ];
      $tmp = [
          'tag' => 'label',
          'text' => isset($cfg['label']) ? $cfg['label'] : ' '
      ];
      if ( isset($cfg['attr']['id']) ){
        $tmp['attr'] = [
            'for' => $cfg['attr']['id']
        ];
      }
      array_push($label['content'], $tmp);
      
      $label_content = [
          'tag' => 'div',
          'css' => [
              'display' => 'block',
              'position' => 'absolute',
              'right' => '0px',
              'top' => '3px'
          ],
          'content' => [
              [
                  'tag' => 'span',
                  'text' => 'Null?  '
              ],
              [
                  'tag' => 'input',
                  'attr' => [
                      'id' => bbn\str::genpwd(),
                      'type' => 'checkbox',
                  ],
                  'events' => [
                      'click' => 'function(e){
                        $("#'.$cfg['attr']['id'].'").prop("disabled", $(this).is(":checked"));'.
                      ( isset($cfg['widget']['name']) ? '
                        appui.fn.log("'.$cfg['widget']['name'].'");
                        try{
                          $("#'.$cfg['attr']['id'].'").'.$cfg['widget']['name'].'("enable", (!$(this).is(":checked")));
                        }
                        catch(err){
                          appui.fn.log(err);
                        }' : '' ).
                      '
                      }'
                  ]
              ]
          ]
      ];
      if ( empty($cfg['attr']['value']) ){
        $label_content['content'][1]['attr']['checked'] = true;
      }
      
      array_push($label['content'], $label_content);
    }
    else{
      $label = [
          'tag' => 'label',
          'text' => (isset($cfg['label']) ? $cfg['label'] : ' '),
          'attr' => [
              'class' => self::$label_class
          ]
      ];
      if ( isset($cfg['attr']['id']) ){
        $label['attr']['for'] = $cfg['attr']['id'];
      }
    }
    return new bbn\html\element($label);
  }
  
	/**
	 * Generates a whole input configuration array by combining the passed and default configurations
	 * @param array $cfg The input's config
	 * @return array
	 */
	public function input($cfg=array())
	{
		if ( is_array($cfg) && isset($cfg['attr']['name']) ){
      
      self::give_id($cfg);
      if ( isset($cfg['field']) ){
        $cfg = bbn\x::merge_arrays(self::specs('fields', $cfg['field']), $cfg);
      }
      
      /*
      if ( !isset($cfg['tag']) ){
        $cfg['tag'] = 'input';
      }
      if ( !isset($cfg['attr']['type']) ){
        $cfg['attr']['type'] = 'text';
      }
      */

      if ( isset($cfg['data']) ){
        if ( isset($cfg['data']['sql'], $this->_current_cfg['data']['db']) ){
          $db =& $this->_current_cfg['data']['db'];
          if ( !isset($cfg['widget']['options']['dataSource']) ){
            $cfg['widget']['options']['dataSource'] = [];
          }
          $count = ( $r = $db->query($cfg['data']['sql']) ) ? $r->count() : 0;
          if ( $count <= self::max_values_at_once ){
            if ( $ds = $db->get_irows($cfg['data']['sql']) ){
              foreach ( $ds as $d ){
                if ( count($d) > 1 ){
                  array_push($cfg['widget']['options']['dataSource'], [
                      'value' => $d[0],
                      'text' => $d[1]
                  ]);
                }
                else{
                  array_push($cfg['widget']['options']['dataSource'], $d[0]);
                }
              }
            }
          }
          else{
            $cfg['field'] = 'autocomplete';
          }
        }
        else if ( is_array($cfg['data']) && (count($cfg['data']) > 0) ){
          if ( isset($cfg['data'][0]) ){
            $cfg['widget']['options']['dataSource'] = $cfg['data'];
          }
          else{
            $cfg['widget']['options']['dataSource'] = [];
            foreach ( $cfg['data'] as $k => $v ){
              array_push($cfg['widget']['options']['dataSource'], [
                  'value' => $k,
                  'text' => $v
              ]);
            }
          }
        }
        if ( isset($cfg['widget']['options']['dataSource'][0]['text']) ){
          $cfg['widget']['options']['dataTextField'] = 'text';
          $cfg['widget']['options']['dataValueField'] = 'value';
        }
			}

      if ( is_array($cfg) ){
        // Size calculation
        if ( isset($cfg['attr']['maxlength']) && !isset($cfg['attr']['size']) ){
          if ( $cfg['attr']['maxlength'] <= 20 ){
            $cfg['attr']['size'] = (int)$cfg['attr']['maxlength'];
          }
        }
        if ( isset($cfg['attr']['size'], $cfg['attr']['minlength']) && $cfg['attr']['size'] < $cfg['attr']['minlength']){
          $cfg['attr']['size'] = (int)$cfg['attr']['minlength'];
        }
        
        


        $cfg = array_filter($cfg, function($a){
          return !( is_array($a) && count($a) === 0 );
        });

      }
      
      if ( isset($cfg['null']) && $cfg['null'] ){
        if ( empty($cfg['attr']['value']) ){
          $cfg['attr']['value'] = null;
          $cfg['attr']['disabled'] = true;
          if ( isset($cfg['widget']) ){
            if ( !isset($cfg['script']) ){
              $cfg['script'] = '';
            }
            /*
            $cfg['script'] .= 'if ( $("#'.$cfg['attr']['id'].'").data("'.$cfg['widget']['name'].'") ){
              $("#'.$cfg['attr']['id'].'").data("'.$cfg['widget']['name'].'").enable(false);
            }
            else if ( $("#'.$cfg['attr']['id'].'").'.$cfg['widget']['name'].' ){
              $("#'.$cfg['attr']['id'].'").'.$cfg['widget']['name'].'("enable", false);
            }';
             * 
             */
          }
        }
      }
      $t = new bbn\html\input($cfg);
      return $t;
		}
		return false;
	}
  
  public function button($cfg, $force=false)
  {
    if ( is_string($cfg) ){
      $cfg = [
          'text' => $cfg
      ];
      if ( !isset($cfg['attr']) ){
        $cfg['attr'] = [];
      }
    }
    if ( !isset($cfg['attr']['class']) ){
      $cfg['attr']['class'] = self::$button_class;
    }
    else{
      $cfg['attr']['class'] .= ' '.self::$button_class;
    }
    self::give_id($cfg);
    $cfg['tag'] = 'button';
    $e = new bbn\html\element($cfg);
    return $this->_chainable && !$force ? $this : $e;
  }
}		
?>