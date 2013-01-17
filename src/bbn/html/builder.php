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
	const max_values_at_once = 200;
	private $_defaults = [
		'tag' => 'input',
		'cssclass' => false,
		'placeholder' => false,
		'script' => false,
		'options' => [
			'type' => 'text',
			'maxlength' => 100,
			'size' => 30,
			'db' => false,
			'cols' => 30,
			'rows' => 6
		],
		'css' => [],
		'xhtml' => false,
		'lang' => 'en'
	],
	$_current,
	$items = [],
	$kendo = [
		'Calendar' => ['name','value','min','max','dates','url','culture','footer','format','month','start','depth','animation'],
		'DatePicker' => ['name','value','footer','format','culture','parseFormats','min','max','start','depth','animation','month','dates','ARIATemplate'],
		'AutoComplete' => ['name','enable','suggest','template','dataTextField','minLength','delay','height','filter','ignoreCase','highlightFirst','separator','placeholder','animation'],
		'DropDownList' => ['name','enable','index','autoBind','text','template','delay','height','dataTextField','dataValueField','optionLabel','cascadeFrom','ignoreCase','animation','dataSource'],
		'ComboBox' => ['name','enable','index','autoBind','delay','dataTextField','dataValueField','minLength','height','highlightFirst','template','filter','placeholder','suggest','ignoreCase','animation'],
		'NumericTextBox' => ['name','decimals','min','max','value','step','culture','format','spinners','placeholder','upArrowText','downArrowText'],
		'TimePicker' => ['name','min','max','format','dates','parseFormats','value','interval','height','animation'],
		'DateTimePicker' => ['name','value','format','timeFormat','culture','parseFormats','dates','min','max','interval','height','footer','start','depth','animation','month','ARIATemplate'],
		'Slider' => ['enabled','min','max','smallStep','largeStep','orientation','tickPlacement','tooltip','name','showButtons','increaseButtonTitle','decreaseButtonTitle','dragHandleTitle'],
		'RangeSlider' => ['enabled','min','max','smallStep','largeStep','orientation','tickPlacement','tooltip','name','leftDragHandleTitle','rightDragHandleTitle'],
		'Upload' => ['name','enabled','multiple','showFileList','async','localization']
	];
	
	public $global_cfg = [];

	public function __construct( array $cfg = null )
	{
		if ( is_array($cfg) ){
			foreach ( $cfg as $k => $v ){
				if ( is_array($v) ){
					foreach ( $v as $k1 => $v1 ){
						if ( isset($this->_defaults[$k][$k1]) ){
							$this->_defaults[$k][$k1] = $v1;
						}
					}
				}
				else if ( isset($this->_defaults[$k]) ){
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
		$this->global_cfg['setting'] = $this->_defaults;
		$this->global_cfg['elements'] = [];
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
			foreach ( $cfg as $k => $v ){
				if ( isset($this->_current[$k]) ){
					if ( is_array($v) ){
						foreach ( $v as $k1 => $v1 ){
							if ( isset($this->_current[$k][$k1]) && $this->_current[$k][$k1] === $v1 ){
								unset($cfg[$k][$k1]);
							}
						}
					}
					else if ( $this->_current[$k] === $v ){
						unset($cfg[$k]);
					}
				}
			}
			// Global config creates a var (simplest as possible) to recreate forms and fields
			array_push($this->global_cfg['elements'], array_filter(array_map(function($a){
				if ( is_object($a) ){
					return get_class($a);
				}
				else if ( is_array($a) ){
					foreach($a as $i => $aa ){
						if ( is_object($aa) ){
							$a[$i] = get_class($aa);
						}
						else if ( is_string($aa) && empty($aa) ){
							unset($a[$i]);
						}
						else if ( is_array($aa) && count($aa) === 0 ){
							unset($a[$i]);
						}
					}
				}
				return $a;
			},$cfg), function($a){
				return ( is_string($a) && !empty($a) ) || ( is_array($a) && count($a) > 0 ) || ( !is_string($a) && !is_array($a) );
			}));
			
			$tmp = $this->_current;
			$tmp['id'] = isset($cfg['id']) ? $cfg['id'] : \bbn\str\text::genpwd(20,15);
			if ( !isset($cfg['options']) ){
				$cfg['options'] = array();
			}
			if ( isset($cfg['options']['sql'], $cfg['options']['db']) && strlen($cfg['options']['sql']) > 5 ){
				$cfg['options']['dataSource'] = array();
				$r = $cfg['options']['db']->query($cfg['options']['sql']);
				$count = $r->count();
				if ( $count <= self::max_values_at_once ){
					if ( $ds = $cfg['options']['db']->get_irows($cfg['options']['sql']) ){
						foreach ( $ds as $d ){
							array_push($cfg['options']['dataSource'], array('value' => $d[0], 'text' => $d[1]));
						}
					}
				}
				else{
					$cfg['field'] = 'autocomplete';
					//$cfg['options']['dataSource']['']
				}
			}
			if ( isset($cfg['field']) ) {
				switch ( $cfg['field'] )
				{
					case 'datepicker':
						$tmp['tag'] = 'input';
						$tmp['options']['type'] = 'date';
						$tmp['options']['maxlength'] = 10;
						$tmp['options']['size'] = 10;
						$tmp['options']['culture'] = $tmp['lang'].'-'.strtoupper($tmp['lang']);
						$tmp['options']['format'] = "yyyy-MM-dd";
						break;
					case 'timepicker':
						$tmp['tag'] = 'input';
						$tmp['options']['type'] = 'time';
						$tmp['options']['maxlength'] = 8;
						$tmp['options']['size'] = 8;
						$tmp['options']['culture'] = $tmp['lang'].'-'.strtoupper($tmp['lang']);
						break;
					case 'datetimepicker':
						$tmp['tag'] = 'input';
						$tmp['options']['type'] = 'datetime';
						$tmp['options']['maxlength'] = 19;
						$tmp['options']['size'] = 20;
						$tmp['options']['culture'] = $tmp['lang'].'-'.strtoupper($tmp['lang']);
						break;
					case 'rte':
						$tmp['tag'] = 'textarea';
						$tmp['options']['rows'] = 6;
						$tmp['options']['cols'] = 20;
						break;
					case 'dropdownlist':
						$tmp['tag'] = 'input';
						$tmp['options']['type'] = 'text';
						$tmp['options']['dataSource'] = array();
						$tmp['options']['dataTextField'] = "text";
						$tmp['options']['dataValueField'] = "value";
						$tmp['options']['change'] = '';
						$tmp['options']['size'] = false;
						$tmp['options']['css']['width'] = 'auto';
						break;
					case 'checkbox':
						$tmp['tag'] = 'input';
						$tmp['value'] = 1;
						$tmp['options']['type'] = 'checkbox';
						break;
					case 'radio':
						$tmp['tag'] = 'input';
						$tmp['options']['type'] = 'radio';
						$tmp['options']['value'] = 1;
						break;
					case 'hidden':
						$tmp['tag'] = 'input';
						$tmp['options']['type'] = 'hidden';
						break;
					case 'text':
						$tmp['tag'] = 'input';
						$tmp['options']['type'] = 'text';
						break;
					case 'numerictextbox':
						$tmp['tag'] = 'input';
						$tmp['options']['type'] = 'number';
						$tmp['options']['min'] = 0;
						$tmp['options']['max'] = 100;
						$tmp['options']['format'] = "n";
						$tmp['options']['decimals'] = 0;
						$tmp['options']['step'] = 1;
						$tmp['options']['culture'] = $tmp['lang'].'-'.strtoupper($tmp['lang']);
						if ( !isset($cfg['options']['max']) && isset($cfg['options']['maxlength']) ){
							$max = '';
							$max_length = $cfg['options']['maxlength'];
							if ( isset($cfg['options']['decimals']) && $cfg['options']['decimals'] > 0 ){
								$max_length -= ( $cfg['options']['decimals'] + 1 );
							}
							for ( $i = 0; $i < $max_length; $i++ ){
								$max .= '9';
							}
							$cfg['options']['max'] = ( (float)$max > (int)$max ) ? (float)$max : (int)$max;
						}
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
			//var_dump($cfg);
			$cfg = array_merge($tmp, $cfg);
			//var_dump($cfg);
			if ( isset($cfg['field']) && !$cfg['script'] ){
				$kkeys = array_keys($this->kendo);
				if ( ( $i = array_search($cfg['field'], array_map(function($a){return strtolower($a);}, $kkeys)) ) !== false ){
					$i = $kkeys[$i];
					$widget_cfg = array();
					foreach ( $this->kendo[$i] as $o ){
						if ( isset($cfg['options'][$o]) ){
							$widget_cfg[$o] = $cfg['options'][$o];
						}
					}
					//var_dump($widget_cfg);
					$cfg['script'] = '$("#'.$cfg['id'].'").kendo'.$i.'('.json_encode((object)$widget_cfg).');';
				}
				else{
					switch ( $cfg['field'] )
					{
						case 'rte':
						$cfg['script'] = 'CKEDITOR.replace("'.$cfg['id'].'");';
						// autoParagraph: inline = true
						// autogrow: true|false minheight/maxheight
						// baseHref: prendre de bbn_sites
						// bodyClass
						// bodyId
						// 
						break;

						case 'text':
						if ( ( strpos($cfg['name'] , 'tel') === 0 ) || ( strpos($cfg['name'] , 'fax') === 0 ) || strpos($cfg['name'] , 'phone') !== false ){
							$cfg['script'] = '$("#'.$cfg['id'].'").mask("99 99 99 99 99");';
						}
						break;
					}
				}
			}
			$t = new \bbn\html\input($cfg);
			array_push($this->items, $t);
			return $t;
		}
		return false;
	}
	
}		
?>