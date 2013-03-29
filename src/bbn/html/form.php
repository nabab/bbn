<?php
/**
 * @package bbn\html
 */
namespace bbn\html;

/**
 * This class generates html form elements with defined configuration
 *
 *
 * @author Thomas Nabet <thomas.nabet@gmail.com>
 * @copyright BBN Solutions
 * @since Dec 14, 2012, 04:23:55 +0000
 * @category  Appui
 * @license   http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @version 0.2r89
*/

class form extends element
{
  private $opened_fieldset = false;

  public 
  /**
	 * Array with every html element of the form
	 * @var array
	 */
          $elements = [],
          $rules = [],
          $builder = false,
          $submit = 'OK',
          $buttonClass = 'k-button',
          $no_form;

	/**
	 * This will call the initial build for a new instance. It should be called only once from within the script. All subsequent calls to controllers should be done through $this->add($path).
	 *
	 * @param array $cfg The default config for the elements
	 */
	protected function update() {
    parent::update();
    if ( !$this->builder || is_string($this->builder) ){
      $this->builder = new builder();
    }
    else if ( is_array($this->builder) ){
      $this->builder = new builder($this->builder);
    }
    foreach ( $this->elements as $k => $it ){
      if ( is_array($it) && ( isset($it['tag']) || isset($it['field']) ) ){
        $this->elements[$k] = $this->builder->get_input($it);
      }
    }
  }
	public function __construct( array $cfg = null )
	{
    if ( $cfg ){
      $cfg['tag'] = 'form';
      if ( !isset($cfg['attr']) ){
        $cfg['attr'] = [];
      }
      if ( !isset($cfg['attr']['action']) ){
        $cfg['attr']['action'] = '.';
      }
      if ( !isset($cfg['attr']['method']) ){
        $cfg['attr']['method'] = 'post';
      }
      parent::__construct($cfg);
		}
	}
	
	/**
	 * Returns an input object according to the combination of passed and default configurations
	 * @param array $cfg The input's config
	 * @return \bbn\html\input
	 */
	public function input(array $cfg=null)
	{
    if ( is_object($this->builder) ){
      array_push($this->elements, $this->builder->get_input($cfg));
    }
    $this->update();
	}
	
	public function fieldset($title='', $idx=false)
  {
    if ( $idx === false ){
      array_push($this->elements, ["fieldset"=>$title]);
    }
    else{
      array_splice($this->elements, $idx, 0, [["fieldset"=>$title]]);
    }
  }

 	public function end_fieldset()
  {
    array_push($this->elements, ["end_fieldset"=>1]);
  }
  
	/**
	 * Returns the complete HTML of the current form (with all its elements)
	 * @param string $action The form's action
	 * @return void
	 */
	public function get_html($with_js = 1)
	{
		$html = '';
    $full_fieldset = false;
    
		foreach ( $this->elements as $i => $it ){
      if ( is_array($it) ){
        if ( isset($it['fieldset']) ){
          if ( $i === 0 ){
            $full_fieldset = 1;
          }
          else if ( $full_fieldset === 1 ){
            $full_fieldset = false;
          }
          if ( $this->opened_fieldset ){
            $html .= '</fieldset>';
          }
          $html .= '<fieldset'.
                  ( isset($it['css']) ? $this->add_css($it['css']) : '' ).
                  ( isset($it['attr']['class']) ? ' class="'.$it['attr']['class'].'"' : '' ).
                  '>';
          if ( is_string($it['fieldset']) && !empty($it['fieldset']) ){
            $html .= '<legend>'.$it['fieldset'].'</legend>';
          }
          $this->opened_fieldset = 1;
        }
        else if ( isset($it['end_fieldset']) && $this->opened_fieldset ){
          $html .= '</fieldset>';
        }
      }
      else if ( is_object($it) ){
        if ( isset($it->attr['name']) ){
          $it->attr['data-bind'] = "value: ".$it->attr['name'];
        }
  			$html .= $it->get_label_input();
      }
		}
    
    if ( $this->opened_fieldset && !$full_fieldset ){
      $html .= '</fieldset>';
    }
		$html .= '<div class="appui-form-label"> </div>'.
            '<button class="appui-form-field'.
            ( isset($this->cfg['buttonClass']) ? ' '.$this->cfg['buttonClass'] : '' ).
            '">'.$this->submit.'</button>';
    
    if ( $this->opened_fieldset && $full_fieldset ){
      $html .= '</fieldset>';
    }
    $this->content = $html;
    $html = parent::get_html();
    unset($this->content);
    
    if ( $with_js ){
      $html .= '<script type="text/javascript">'.$this->get_script().'</script>';
    }

    return $html;
	}
	
	/**
	 * Returns the JavaScript from all the registered inputs, including the one for the form
	 * @return string
	 */
	public function get_script()
	{
    $st = parent::get_script();
    if ( isset($this->attr['id']) ){
      $st .= 'kendo.bind("#'.$this->attr['id'].' *", kappui.tabstrip.obs[kappui.tabstrip.selected].info);';
    }
    foreach ( $this->elements as $it ){
      if ( is_object($it) ){
        $st .= $it->get_script();
      }
    }
    return $st;
	}
  
  
  public function get_config()
  {
    $r = [
        'attr' => $this->attr,
        'builder' => $this->builder,
        'no_form' => $this->no_form,
        'elements' => [],
        'rules' => []
    ];
    foreach ( $this->elements as $e ){
      if ( is_object($e) && method_exists($e, 'get_config') ){
        array_push($r['elements'], $e->get_config());
      }
      else{
        array_push($r['elements'], $e);
      }
    }
    return $r;
  }
}		
?>