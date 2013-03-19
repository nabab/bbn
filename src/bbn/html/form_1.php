<?php
/**
 * @package bbn\html
 */
namespace bbn\html;

/**
 * This class generates html form items with defined configuration
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
          $rules = [],
          $builder = false,
          $submit = 'OK',
          $no_form;

	/**
	 * This will call the initial build for a new instance. It should be called only once from within the script. All subsequent calls to controllers should be done through $this->add($path).
	 *
	 * @param array $cfg The default config for the items
	 */
	
	public function __construct( array $cfg = null )
	{
    if ( $cfg ){
      $this->tag = 'form';
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
      if ( !$this->builder ){
        $this->builder = new builder();
      }
      else if ( is_array($this->builder) ){
        $this->builder = new builder($this->builder);
      }
      foreach ( $this->items as $i => $e ){
        if ( is_array($e) && !isset($e['fieldset']) && !isset($e['end_fieldset']) ){
          $this->items[$i] = $this->builder->get_input($e);
        }
      }
      var_dump($this);
		}
	}
	
	/**
	 * Returns an input object according to the combination of passed and default configurations
	 * @param array $cfg The input's config
	 * @return \bbn\html\input
	 */
	public function input($cfg=[])
	{
		array_push($this->items, $this->builder->get_input($cfg));
	}
	
	public function fieldset($title='')
  {
    $c = ["tag"=>"fieldset", "items"=>[]];
    array_push($this->items, new element("fieldset"));
    if ( !empty($title) ){
      array_push($this->items, ["tag"=>"legend", "content"=>$title]);
    }
  }

 	public function end_fieldset()
  {
    array_push($this->items, ["end_fieldset"=>1]);
  }
  
	/**
	 * Returns the complete HTML of the current form (with all its items)
	 * @param string $action The form's action
	 * @return void
	 */
	public function get_html($with_js = 1)
	{
		$s = parent::get_html();
		foreach ( $this->items as $it ){
      if ( is_object($it) ){
  			$s .= $it->get_label_input();
      }
		}
		$s .= '<div class="appui-form-label"> </div>'.
            '<button class="appui-form-field'.
            ( isset($this->cfg['buttonClass']) ? ' '.$this->cfg['buttonClass'] : '' ).
            '">'.$this->submit.'</button></form>';
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
    $st = parent::get_script();
    if ( isset($this->attr['id']) ){
      $st .= '(function(){
              var $f = $("#'.$this->attr['id'].'");
              kappui.tabstrip.set("'.$this->attr['id'].'", $f.serializeArray(), $f);
            });';
      $var = [];
    }
    foreach ( $this->items as $it ){
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
        'items' => [],
        'rules' => []
    ];
    foreach ( $this->items as $e ){
      if ( is_object($e) ){
        array_push($r['items'], $e->get_config());
      }
      else{
        array_push($r['items'], $e);
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