<?php
/**
 * @package bbn\html
 */
namespace bbn\html;

/**
 * This class generates html elements with defined configuration
 *
 *
 * @author Thomas Nabet <thomas.nabet@gmail.com>
 * @copyright BBN Solutions
 * @since Dec 14, 2012, 04:23:55 +0000
 * @category  Appui
 * @license   http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @version 0.2r89
*/

class builder
{
		
	private $_defaults = array(
		'tag' => 'input',
		'cssclass' => false,
		'placeholder' => false,
		'script' => false,
		'options' => array(
			'type' => 'text',
			'maxlength' => 100,
			'size' => 30,
			'db' => false,
			'cols' => 30,
			'rows' => 6
		),
		'xhtml' => false,
		'lang' => 'en'
	),
	$_current,
	$items = array();
		

	public function __construct( array $cfg = null )
	{
		if ( is_array($cfg) ){
			foreach ( $cfg as $k => $v ){
				if ( isset($this->_defaults[$k]) ){
					$this->_defaults[$k] = $v;
				}
			}
		}
		$this->reset();
	}
	
	public function reset()
	{
		$this->_current = array();
		foreach ( $this->_defaults as $k => $v ){
			$this->_current[$k] = $v;
		}
		$this->items = array();
		$this->id = \bbn\str\text::genpwd(20,15);
	}
	
	public function set_option($opt_val)
	{
		$args = func_get_args();
		if ( is_array($opt_val) && isset($opt_val[0], $this->_defaults[$opt_val[0]]) ){
			$this->_current[$opt_val[0]] = $opt_val[1];
		}
		else if ( isset($args[0], $args[1], $this->_defaults[$args[0]]) ){
			$this->_current[$args[0]] = $args[1];
		}
		else{
			throw new InvalidArgumentException('This configuration argument is imaginary... Sorry! :)');
		}
	}
	
	public function get_form($action)
	{
		$s = '<form action="'.$action.'" method="post" id="'.$this->id.'"><fieldset>';
		foreach ( $this->items as $it ){
			$s .= $it->get_label_input();
		}
		$s .= '<div class="appui-form-label"> </div><div class="appui-form-field"><input type="submit"></div></fieldset></form>';
		return $s;
	}
	
	public function get_input($cfg=array())
	{
		return new \bbn\html\input(array_merge($this->_current,$cfg));
	}
	
	public function get_config()
	{
		$r = [];
		foreach ( $this->items as $it ){
			$r[] = $it->get_config();
		}
		return $r;
	}
	public function get_html()
	{
		$st = '';
		foreach ( $this->items as $it ){
			$st .= $it->get_html();
		}
		return $st;
	}
	
	public function get_script()
	{
		$st = '';
		foreach ( $this->items as $it ){
			$st .= $it->get_script();
		}
		$st .= '$("#'.$this->id.'").validate();';
		return $st;
	}
	
	public function make_field($cfg=null)
	{
		if ( is_array($cfg) && isset($cfg['name']) ){
			$tmp = $this->_current;
			$tmp['id'] = isset($cfg['id']) ? $cfg['id'] : \bbn\str\text::genpwd(20,15);
			if ( isset($cfg['field']) ) {
				switch ( $cfg['field'] )
				{
					case 'datepicker':
						$tmp['tag'] = 'input';
						$tmp['options']['type'] = 'text';
						$tmp['options']['maxlength'] = 10;
						$tmp['options']['size'] = 10;
						$tmp['options']['culture'] = $tmp['lang'].'-'.strtoupper($tmp['lang']);
						break;
					case 'timepicker':
						$tmp['tag'] = 'input';
						$tmp['options']['type'] = 'text';
						$tmp['options']['maxlength'] = 8;
						$tmp['options']['size'] = 8;
						$tmp['options']['culture'] = $tmp['lang'].'-'.strtoupper($tmp['lang']);
						break;
					case 'datetimepicker':
						$tmp['tag'] = 'input';
						$tmp['options']['type'] = 'text';
						$tmp['options']['maxlength'] = 19;
						$tmp['options']['size'] = 20;
						$tmp['options']['culture'] = $tmp['lang'].'-'.strtoupper($tmp['lang']);
						break;
					case 'rte':
						$tmp['tag'] = 'textarea';
						$tmp['options']['rows'] = 6;
						$tmp['options']['cols'] = 20;
						break;
					case 'dropdown':
						$tmp['tag'] = 'input';
						$tmp['options']['type'] = 'text';
						$tmp['options']['data'] = array();
						break;
					case 'checkbox':
						$tmp['tag'] = 'input';
						$tmp['value'] = 1;
						$tmp['options']['type'] = 'checkbox';
						break;
					case 'radio':
						$tmp['tag'] = 'input';
						$tmp['options']['type'] = 'radio';
						break;
					case 'hidden':
						$tmp['tag'] = 'input';
						$tmp['options']['type'] = 'hidden';
						break;
					case 'text':
						$tmp['tag'] = 'input';
						$tmp['options']['type'] = 'text';
						break;
					case 'quantity':
						$tmp['tag'] = 'input';
						$tmp['options']['type'] = 'hidden';
						break;
				}
			}
			// Size calculation
			if ( isset($cfg['options']['maxlength']) && !isset($cfg['options']['size']) ){
				if ( $cfg['options']['maxlength'] <= 20 ){
					$cfg['options']['size'] = $cfg['options']['maxlength'];
				}
				else if ( $cfg['options']['maxlength'] <= 50 ){
					$cfg['options']['size'] = 20 + floor( ( $cfg['options']['maxlength'] - 20 ) / 2 );
				}
				else if ( $cfg['options']['maxlength'] <= 200 ){
					$cfg['options']['size'] = floor($cfg['options']['maxlength']/2);
				}
				else{
					$cfg['options']['size'] = 100;
				}
			}
			if ( isset($cfg['options']['size'], $cfg['options']['minlength']) && $cfg['options']['size'] < $cfg['options']['minlength']){
				$cfg['options']['size'] = $cfg['options']['minlength'];
			}
			if ( isset($cfg['options']) ){
				$cfg['options'] = array_merge($tmp['options'], $cfg['options']);
			}
			$cfg = array_merge($tmp, $cfg);
			if ( isset($cfg['field']) && !$cfg['script'] ){
				switch ( $cfg['field'] )
				{
					case 'datepicker':
						$cfg['script'] = '$("#'.$cfg['id'].'").kendoDatePicker({
							"format":"yyyy-MM-dd",
							"culture": "'.(	isset($cfg['options']['culture']) ? $cfg['options']['culture'] : $cfg['lang'].'-'.strtoupper($cfg['lang']) ).'"
						});';
						// format
						// dates
						// start
						// end
						// before
						// after
						break;
					case 'timepicker':
						$cfg['script'] = '$("#'.$cfg['id'].'").kendoTimePicker({
							"format":"HH:mm:tt",
							"culture": "'.(	isset($cfg['options']['culture']) ? $cfg['options']['culture'] : $cfg['lang'].'-'.strtoupper($cfg['lang']) ).'"
						});';
						// format
						// dates
						break;
					case 'datetimepicker':
						$cfg['script'] = '$("#'.$cfg['id'].'").kendoDateTimePicker({
							"format":"yyyy-MM-dd hh:mm:ss",
							"culture": "'.(	isset($cfg['options']['culture']) ? $cfg['options']['culture'] : $cfg['lang'].'-'.strtoupper($cfg['lang']) ).'"
						});';
						// format
						// dates
						break;
					case 'rte':
						$cfg['script'] = 'CKEDITOR.replace("'.$cfg['id'].'");';
						// autoParagraph: inline = true
						// autogrow: true|false minheight/maxheight
						// baseHref: prendre de bbn_sites
						// bodyClass
						// bodyId
						// 
						break;
					case 'dropdown':
						if ( isset($cfg['options']['sql'], $cfg['options']['db']) ){
							$cfg['options']['data'] = array();
							if ( $ds = $cfg['options']['db']->get_irows($cfg['options']['sql']) ){
								foreach ( $ds as $d ){
									array_push($cfg['options']['data'], array('value' => $d[0], 'text' => $d[1]));
								}
							}
						}
						$cfg['script'] = '$("#'.$cfg['id'].'").kendoDropDownList({
							dataTextField: "text",
							dataValueField: "value",
							dataSource: '.json_encode($cfg['options']['data']).'
						});';
						unset($cfg['options']['data']);
						// onchange
						// dataTextField
						// dataValueField
						break;
					case 'checkbox':
						break;
					case 'radio':
						break;
					case 'hidden':
						break;
					case 'text':
						break;
					case 'quantity':
						break;
				}
			}
			$t = new \bbn\html\input($cfg);
			array_push($this->items, $t);
			return $t;
		}
		return false;
	}
	
	public function make_field_from_id($id, $cfg)
	{
		$tmp = array();
		$field = '';
		if ( !is_array($cfg) ){
			$cfg = [];
		}
		switch ( $id )
		{
			case 1:
			$tmp['field'] = 'datepicker';
			break;

			case 2:
			// email
			$tmp['tag'] = 'input';
			$tmp['options']['type'] = 'text';
			$tmp['options']['maxlength'] = 50;
			$tmp['options']['size'] = 25;
			$tmp['options']['culture'] = $this->_current['lang'];
			$tmp['options']['email'] = 1;
			// param1 === 'yes ? multiple : single
			break;
			
			case 3:
			// rich text
			$tmp['field'] = 'rte';
			// Type: Text
			break;
			
			case 4:
			// price (float)
			$tmp['tag'] = 'input';
			$tmp['options']['type'] = 'text';
			$tmp['options']['number'] = 1;
			$tmp['options']['maxlength'] = 15;
			$tmp['options']['size'] = 10;
			// Type float
			break;
			
			case 5:
			// relation
			// var_dump($cfg);
			$tmp['field'] = 'dropdown';
			if ( isset($cfg['options']['db'],$cfg['params'][0]) && is_object($cfg['options']['db']) ){
				$p = explode('.', $cfg['params'][0]);
				if ( count($p) === 2 ){
					$table = $p[0];
					$field = $p[1];
					$select = "`$table`.`$field`";
					$order = "`$table`.`$field`";
					if ( isset($cfg['params'][1]) ){
						$p = explode('.', $cfg['params'][1]);
						$table = $p[0];
						$field = $p[1];
						$select = "CONCAT ($select,' ',`$table`.`$field`)";
						$order .= ",`$table`.`$field`";
					}
					$cfg['options']['sql'] = "SELECT id, $select FROM $table ORDER BY $order LIMIT 100";
				}
			}
			break;
			
			case 8:
			// rich small text
			$tmp['field'] = 'rte';
			$tmp['options']['rows'] = 3;
			$tmp['options']['cols'] = 15;
			// type Tinytext
			break;

			case 9:
			$tmp['field'] = 'rte';
			$tmp['options']['rows'] = 6;
			$tmp['options']['cols'] = 20;
			// type Text
			break;
				
			case 10:
				// hidden field
				$tmp['tag'] = 'input';
				$tmp['options']['type'] = 'hidden';
				break;
			
			case 11:
				// checkbox
				$tmp['tag'] = 'input';
				$tmp['options']['type'] = 'checkbox';
				// tinyint (0/1)
				break;
			
			case 12:
				// quantity dropdown
				// param1 = min
				// param2 = max
				break;
			
			case 16:
				$tmp['field'] = 'dropdown';
				$tmp['options']['data'] = [];
				$a1 = explode(',', $cfg['params'][0]);
				$a2 = explode(',', $cfg['params'][1]);
				foreach ( $a1 as $i => $a ){
					if ( strpos($a, "'") === 0 ){
						$a = substr($a, 1, -1);
					}
					if ( strpos($a2[$i], "'") === 0 ){
						$a2[$i] = substr($a2[$i], 1, -1);
					}
					array_push($tmp['options']['data'], array('text'=>$a, 'value'=>$a2[$i]));
				}
			break;
			
			case 18:
				$tmp['tag'] = 'input';
				$tmp['options']['type'] = 'text';
				break;
			
			case 20:
				$tmp['tag'] = 'input';
				$tmp['options']['type'] = 'text';
				break;
			
			case 24:
			$tmp['tag'] = 'input';
			$tmp['options']['type'] = 'text';
			break;
			
			case 25:
			$tmp['tag'] = 'input';
			$tmp['options']['type'] = 'text';
			break;
			
			case 27:
			$tmp['tag'] = 'input';
			$tmp['options']['type'] = 'text';
			break;
			
			case 28:
			$tmp['tag'] = 'input';
			$tmp['options']['type'] = 'text';
			break;
			
			case 29:
			$tmp['tag'] = 'input';
			$tmp['options']['type'] = 'text';
			break;
			
			case 30:
			$tmp['tag'] = 'input';
			$tmp['options']['type'] = 'text';
			break;
			
			case 32:
			$tmp['tag'] = 'input';
			$tmp['options']['type'] = 'text';
			break;
			
			case 33:
			$tmp['tag'] = 'input';
			$tmp['options']['type'] = 'text';
			break;
			
			case 35:
			$tmp['tag'] = 'input';
			$tmp['options']['type'] = 'text';
			break;
			
			case 36:
			$tmp['tag'] = 'input';
			$tmp['options']['type'] = 'text';
			break;

			case 39:
			$tmp['tag'] = 'input';
			$tmp['options']['type'] = 'text';
			break;

			case 40:
			$tmp['tag'] = 'input';
			$tmp['options']['type'] = 'text';
			break;
			
			case 41:
			$tmp['tag'] = 'input';
			$tmp['options']['type'] = 'text';
			break;
			
			case 42:
			$tmp['tag'] = 'input';
			$tmp['options']['type'] = 'text';
			break;
			
			case 46:
			$tmp['tag'] = 'input';
			$tmp['options']['type'] = 'text';
			break;
			
			case 47:
			$tmp['tag'] = 'input';
			$tmp['options']['type'] = 'text';
			break;
			
			case 49:
			$tmp['tag'] = 'input';
			$tmp['options']['type'] = 'text';
			break;
			
			case 50:
			$tmp['tag'] = 'input';
			$tmp['options']['type'] = 'text';
			break;
			
			case 79879:
			$tmp['tag'] = 'input';
			$tmp['options']['type'] = 'text';
			break;
			
			case 367904:
			$tmp['tag'] = 'input';
			$tmp['options']['type'] = 'text';
			break;
			
			case 564643:
			$tmp['tag'] = 'input';
			$tmp['options']['type'] = 'text';
			break;
			
			case 564809:
			$tmp['tag'] = 'input';
			$tmp['options']['type'] = 'text';
			break;
			
			case 657376:
			$tmp['tag'] = 'input';
			$tmp['options']['type'] = 'text';
			break;
			
			case 5345344:
			$tmp['tag'] = 'input';
			$tmp['options']['type'] = 'text';
			break;
			
			case 5391538:
			$tmp['tag'] = 'input';
			$tmp['options']['type'] = 'text';
			break;
			
			case 9085466:
			$tmp['tag'] = 'input';
			$tmp['options']['type'] = 'text';
			break;
			
			case 29098681:
			$tmp['tag'] = 'input';
			$tmp['options']['type'] = 'text';
			break;
			
			case 74682674:
			$tmp['tag'] = 'input';
			$tmp['options']['type'] = 'text';
			break;
			
			case 113051193:
			$tmp['tag'] = 'input';
			$tmp['options']['type'] = 'text';
			break;
			
			case 148751228:
			$tmp['tag'] = 'input';
			$tmp['options']['type'] = 'text';
			break;
			
			case 196779967:
			$tmp['tag'] = 'input';
			$tmp['options']['type'] = 'text';
			break;
			
			case 231858415:
			$tmp['tag'] = 'input';
			$tmp['options']['type'] = 'text';
			break;
			
			case 261368416:
			$tmp['tag'] = 'input';
			$tmp['options']['type'] = 'text';
			break;
			
			case 290886418:
			$tmp['tag'] = 'input';
			$tmp['options']['type'] = 'text';
			break;
			
			case 436730913:
			$tmp['tag'] = 'input';
			$tmp['options']['type'] = 'text';
			break;
			
			case 470057584:
			$tmp['tag'] = 'input';
			$tmp['options']['type'] = 'text';
			break;
			
			case 494886673:
			$tmp['tag'] = 'input';
			$tmp['options']['type'] = 'text';
			break;
			
			case 504909832:
			$tmp['tag'] = 'input';
			$tmp['options']['type'] = 'text';
			break;
			
			case 560916497:
			$tmp['tag'] = 'input';
			$tmp['options']['type'] = 'text';
			break;
			
			case 640910535:
			$tmp['tag'] = 'input';
			$tmp['options']['type'] = 'text';
			break;
			
			case 673747416:
			$tmp['tag'] = 'input';
			$tmp['options']['type'] = 'text';
			$tmp['options']['maxlength'] = $cfg['params'][0];
			if ( isset($cfg['params'][1]) ){
				$tmp['options']['minlength'] = $cfg['params'][1];
			}
			break;
			
			case 743318065:
			$tmp['tag'] = 'input';
			$tmp['options']['type'] = 'text';
			break;
			
			case 782446287:
			$tmp['tag'] = 'input';
			$tmp['options']['type'] = 'text';
			break;
			
			case 929232328:
			$tmp['tag'] = 'input';
			$tmp['options']['type'] = 'text';
			break;
			
			case 945255014:
			$tmp['tag'] = 'input';
			$tmp['options']['type'] = 'text';
			break;
			
			case 945255015:
			$tmp['tag'] = 'input';
			$tmp['options']['type'] = 'text';
			break;
			
			case 945255016:
			$tmp['tag'] = 'input';
			$tmp['options']['type'] = 'text';
			break;
			
			case 945255017:
			$tmp['tag'] = 'input';
			$tmp['options']['type'] = 'text';
			break;
			
		}
		if ( isset($cfg['options'], $tmp['options']) ){
			$cfg['options'] = array_merge($tmp['options'], $cfg['options']);
		}
		$cfg = array_merge($tmp, $cfg);
		return $this->make_field($cfg, $field);
	}
}		
?>