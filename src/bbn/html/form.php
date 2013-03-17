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

class form
{
	/**
	 * The maximum number of values in a dropdown list
	 * @var int
	 */
	const max_values_at_once = 200;
	/**
	 * The current items registered in the object
	 * @var array
	 */
	private $items = [],
	/**
	 * The current form's configuration
	 * @var array
	 */
          $cfg = [
              'action' => '',
              'title' => false,
              'method' => 'post',
              'builder' => false,
              'id' => false,
              'no_form' => false,
              'elements' => [],
              'submit' => 'Envoyer',
              'icon' => false,
              'buttonClass' => 'k-button'
          ],
          $opened_fieldset = false;
	
	
	/**
	 * Array with every html element of the form
	 * @var array
	 */
	public $elements = [],
          $action,
          $method,
          $builder,
          $id,
          $submit,
          $no_form;

	/**
	 * This will call the initial build for a new instance. It should be called only once from within the script. All subsequent calls to controllers should be done through $this->add($path).
	 *
	 * @param array $cfg The default config for the elements
	 */
	
	public function __construct( array $cfg = null )
	{
    if ( $cfg ){
      foreach ( $this->cfg as $k => $v ){
        if ( isset($cfg[$k]) ){
          $this->$k = $cfg[$k];
        }
        else{
          $this->$k = $v;
        }
      }
		}
    if ( !$this->builder ){
      $this->builder = new builder();
    }
    else if ( is_array($this->builder) ){
      $this->builder = new builder($this->builder);
    }
    foreach ( $this->elements as $i => $e ){
      if ( is_array($e) && !isset($e['fieldset'], $e['end_fieldset']) ){
        $this->elements[$i] = $this->builder->get_input($e);
      }
    }
	}
	
	/**
	 * Returns an input object according to the combination of passed and default configurations
	 * @param array $cfg The input's config
	 * @return \bbn\html\input
	 */
	public function input($cfg=[])
	{
		array_push($this->elements, $this->builder->get_input($cfg));
	}
	
	public function fieldset($title='')
  {
    array_push($this->elements, ["fieldset"=>$title]);
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
		$s = '<form action="'.$this->action.'" method="post" id="'.$this->id.'">';
		foreach ( $this->elements as $it ){
      if ( is_array($it) ){
        reset($it);
        switch( key($it) ){
          case "fieldset":
            if ( $this->opened_fieldset ){
              $s .= '</fieldset>';
            }
            $s .= '<fieldset>';
            if ( !empty($title) ){
              $s .= '<legend>'.$title.'</legend>';
            }
            $this->opened_fieldset = 1;
            break;
          case "end_fieldset":
            if ( $this->opened_fieldset ){
              $s .= '</fieldset>';
            }
            break;
        }
      }
      else if ( is_object($it) ){
  			$s .= $it->get_label_input();
      }
		}
    if ( $this->opened_fieldset ){
      $s .= '</fieldset>';
    }
		$s .= '<div class="appui-form-label"> </div><button class="appui-form-field '.$this->cfg['buttonClass'].'">'.$this->submit.'</button></form>';
    if ( $with_js ){
      $s .= '<script type="text/javascript">'.$this->get_script().'</script>';
    }

    return $s;
	}
	
	/**
	 * Returns the JavaScript from all the registered inputs, including the one for the form
	 * @return string
	 */
	public function get_script()
	{
		$st = '';
		foreach ( $this->elements as $it ){
      if (is_object($it) ){
  			$st .= $it->get_script();
      }
		}
		$st .= '$("#'.$this->id.'").validate({errorElement: "em"});';
		return $st;
	}
  
  public function get_config()
  {
    $r = [
          'action' => $this->action,
          'method' => $this->method,
          'builder' => $this->builder,
          'id' => $this->id,
          'no_form' => $this->no_form,
          'elements' => []
    ];
    foreach ( $this->elements as $e ){
      if ( is_object($e) ){
        array_push($r['elements'], $e->get_config());
      }
      else{
        array_push($r['elements'], $e);
      }
    }
    return $r;
  }
  
  public function show_config()
  {
    return \bbn\str\text::make_readable($this->get_config());
  }
}		
?>