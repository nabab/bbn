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

class builder
{
	/**
	 * The maximum number of values in a dropdown list
	 * @var int
	 */
	const max_values_at_once = 200;
	/**
	 * The default field's configuration
	 * @var array
	 */
	private $_defaults = [
		'tag' => 'input',
		'attr' => [
			'type' => 'text',
      'name' => false
		],
		'lang' => 'fr'
	],
	/**
	 * The current default configuration
	 * @var array
	 */
	$_current;
	
	
	/**
	 * This array will hold all the current configuration, i.e. the defaults values (in 'settings' index), and each registered item's configuration too (in 'elements' index)
	 * @var array
	 */
	public static
          $tags = ['input', 'select', 'textarea'], // 'keygen'
          $types = ['text', 'password', 'radio', 'checkbox', 'hidden', 'file', 'color', 'date', 'datetime', 'email', 'datetime-local', 'email', 'month', 'number', 'range', 'search', 'tel', 'time', 'url', 'week'],
	/**
	 * Javascript Widgets' properties
	 * @var array
	 */
          $widgets = [
		'calendar' => [
        'fn' => 'kendoCalendar',
        'opt' =>['name','value','min','max','dates','url','culture','footer','format','month','start','depth','animation']
    ],
		'date' => [
        'fn' => 'kendoDatePicker',
        'opt' => ['name','value','footer','format','culture','parseFormats','min','max','start','depth','animation','month','dates','ARIATemplate']
    ],
    'autocomplete' => [
        'fn' => 'kendoAutoComplete',
        'opt' => ['name','enable','suggest','template','dataTextField','minLength','delay','height','filter','ignoreCase','highlightFirst','separator','placeholder','animation']
    ],
    'dropdown' => [
        'fn' => 'kendoDropDownList',
        'opt' => ['name','enable','index','autoBind','text','template','delay','height','dataTextField','dataValueField','optionLabel','cascadeFrom','ignoreCase','animation','dataSource'],
    ],
    'combo' => [
        'fn' => 'kendoComboBox',
        'opt' => ['name','enable','index','autoBind','delay','dataTextField','dataValueField','minLength','height','highlightFirst','template','filter','placeholder','suggest','ignoreCase','animation']
    ],
    'numeric' => [
        'fn' => 'kendoNumericTextBox',
        'opt' => ['name','decimals','min','max','value','step','culture','format','spinners','placeholder','upArrowText','downArrowText']
    ],
    'time' => [
        'fn' => 'kendoTimePicker',
        'opt' => ['name','min','max','format','dates','parseFormats','value','interval','height','animation']
    ],
    'datetime' => [
        'fn' => 'kendoDateTimePicker',
        'opt' => ['name','value','format','timeFormat','culture','parseFormats','dates','min','max','interval','height','footer','start','depth','animation','month','ARIATemplate']
    ],
    'slider' => [
        'fn' => 'kendoSlider',
        'opt' => ['enabled','min','max','smallStep','largeStep','orientation','tickPlacement','tooltip','name','showButtons','increaseButtonTitle','decreaseButtonTitle','dragHandleTitle']
    ],
    'rangeslider' => [
        'fn' => 'kendoRangeSlider',
        'opt' => ['enabled','min','max','smallStep','largeStep','orientation','tickPlacement','tooltip','name','leftDragHandleTitle','rightDragHandleTitle'],
    ],
    'upload' => [
        'fn' => 'kendoUpload',
        'opt' => ['name','enabled','multiple','showFileList','async','localization']
    ],
    'multivalue' => [
        'fn' => 'multivalue',
        'opt' => ['import']
    ],
    'editor' => [
        'fn' => 'ckeditor',
        'opt' => ["allowedContent", "autoGrow_bottomSpace", "autoGrow_maxHeight", "autoGrow_minHeight", "autoGrow_onStartup", "autoParagraph", "autoUpdateElement", "baseFloatZIndex", "baseHref", "basicEntities", "blockedKeystrokes", "bodyClass", "bodyId", "browserContextMenuOnCtrl", "clipboard_defaultContentType", "colorButton_backStyle", "colorButton_colors", "colorButton_enableMore", "colorButton_foreStyle", "contentsCss", "contentsLangDirection", "contentsLanguage", "coreStyles_bold", "coreStyles_italic", "coreStyles_strike", "coreStyles_subscript", "coreStyles_superscript", "coreStyles_underline", "customConfig", "dataIndentationChars", "defaultLanguage", "devtools_styles", "devtools_textCallback", "dialog_backgroundCoverColor", "dialog_backgroundCoverOpacity", "dialog_buttonsOrder", "dialog_magnetDistance", "dialog_startupFocusTab", "disableNativeSpellChecker", "disableNativeTableHandles", "disableObjectResizing", "disableReadonlyStyling", "div_wrapTable", "docType", "emailProtection", "enableTabKeyTools", "enterMode", "entities", "entities_additional", "entities_greek", "entities_latin", "entities_processNumerical", "extraAllowedContent", "extraPlugins", "filebrowserBrowseUrl", "filebrowserFlashBrowseUrl", "filebrowserFlashUploadUrl", "filebrowserImageBrowseLinkUrl", "filebrowserImageBrowseUrl", "filebrowserImageUploadUrl", "filebrowserUploadUrl", "filebrowserWindowFeatures", "filebrowserWindowHeight", "filebrowserWindowWidth", "fillEmptyBlocks", "find_highlight", "flashAddEmbedTag", "flashConvertOnEdit", "flashEmbedTagOnly", "floatSpaceDockedOffsetX", "floatSpaceDockedOffsetY", "floatSpacePinnedOffsetX", "floatSpacePinnedOffsetY", "fontSize_defaultLabel", "fontSize_sizes", "fontSize_style", "font_defaultLabel", "font_names", "font_style", "forceEnterMode", "forcePasteAsPlainText", "forceSimpleAmpersand", "format_address", "format_div", "format_h1", "format_h2", "format_h3", "format_h4", "format_h5", "format_h6", "format_p", "format_pre", "format_tags", "fullPage", "height", "htmlEncodeOutput", "ignoreEmptyParagraph", "image_previewText", "image_removeLinkByEmptyURL", "indentClasses", "indentOffset", "indentUnit", "justifyClasses", "keystrokes", "language", "linkShowAdvancedTab", "linkShowTargetTab", "magicline_color", "magicline_holdDistance", "magicline_keystrokeNext", "magicline_keystrokePrevious", "magicline_putEverywhere", "magicline_triggerOffset", "menu_groups", "menu_subMenuDelay", "newpage_html", "on", "pasteFromWordCleanupFile", "pasteFromWordNumberedHeadingToList", "pasteFromWordPromptCleanup", "pasteFromWordRemoveFontStyles", "pasteFromWordRemoveStyles", "plugins", "protectedSource", "readOnly", "removeButtons", "removeDialogTabs", "removeFormatAttributes", "removeFormatTags", "removePlugins", "resize_dir", "resize_enabled", "resize_maxHeight", "resize_maxWidth", "resize_minHeight", "resize_minWidth", "scayt_autoStartup", "scayt_contextCommands", "scayt_contextMenuItemsOrder", "scayt_customDictionaryIds", "scayt_customerid", "scayt_maxSuggestions", "scayt_moreSuggestions", "scayt_sLang", "scayt_srcUrl", "scayt_uiTabs", "scayt_userDictionaryName", "sharedSpaces", "shiftEnterMode", "skin", "smiley_columns", "smiley_descriptions", "smiley_images", "smiley_path", "sourceAreaTabSize", "specialChars", "startupFocus", "startupMode", "startupOutlineBlocks", "startupShowBorders", "stylesSet", "stylesheetParser_skipSelectors", "stylesheetParser_validSelectors", "tabIndex", "tabSpaces", "templates", "templates_files", "templates_replaceContent", "toolbar", "toolbarCanCollapse", "toolbarGroupCycling", "toolbarGroups", "toolbarLocation", "toolbarStartupExpanded", "uiColor", "undoStackSize", "useComputedState", "width"]
    ]
	];

	/**
	 * This will call the initial build for a new instance. It should be called only once from within the script. All subsequent calls to controllers should be done through $this->add($path).
	 *
	 * @param array $cfg The default config for the elements
	 */
	
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
		$this->id = \bbn\str\text::genpwd(20,15);
	}
	
	/**
	 * Change an option in the current configuration
	 * @param array | string $opt_val Either an array with the param name and value, or 2 strings in the same order
	 * @return void
	 */
	public function set_option($opt)
	{
		$args = func_get_args();
		if ( is_array($opt) && isset($opt[0], $this->_defaults[$opt[0]]) ){
			$this->_current[$opt[0]] = $opt[1];
		}
		else if ( isset($args[0], $args[1], $this->_defaults[$args[0]]) ){
			$this->_current[$args[0]] = $args[1];
		}
		else{
			throw new InvalidArgumentException('This configuration argument is imaginary... Sorry! :)');
		}
	}
	
	/**
	 * Returns an array of the all the current registered inputs' configurations
	 * @return array
	 */
	public function get_config()
	{
		return $this->_defaults;
	}
	
	/**
	 * Generates a whole input configuration array by combining the passed and default configurations
	 * @param array $cfg The input's config
	 * @return array
	 */
	public function get_input($cfg=null)
	{
		if ( is_array($cfg) && isset($cfg['attr']['name']) ){
      /*
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
       * 
       */
			
			$tmp = $this->_current;
			$tmp['attr']['id'] = isset($cfg['attr']['id']) ? $cfg['attr']['id'] : \bbn\str\text::genpwd(20,15);
			if ( !isset($cfg['data']) ){
				$cfg['data'] = array();
			}
      
      if ( isset($cfg['field']) ){
        if ( isset(self::$widgets[strtolower($cfg['field'])]) ){
          $wid = self::$widgets[strtolower($cfg['field'])];
          $tmp['widget'] = [
              "name" => $wid['fn'],
              "options" => []
          ];
        }
				switch ( $cfg['field'] )
				{
					case 'date':
						$tmp['tag'] = 'input';
						$tmp['attr']['type'] = 'date';
						$tmp['attr']['maxlength'] = 10;
						$tmp['attr']['size'] = 10;
            $tmp['widget']["options"]["culture"] = $tmp['lang'].'-'.strtoupper($tmp['lang']);
            $tmp['widget']["options"]["format"] = "yyyy-MM-dd";
						break;
					case 'time':
						$tmp['tag'] = 'input';
						$tmp['attr']['type'] = 'time';
						$tmp['attr']['maxlength'] = 8;
						$tmp['attr']['size'] = 8;
            $tmp['widget']["options"]["culture"] = $tmp['lang'].'-'.strtoupper($tmp['lang']);
						break;
					case 'datetime':
						$tmp['tag'] = 'input';
						$tmp['attr']['type'] = 'datetime';
						$tmp['attr']['maxlength'] = 19;
						$tmp['attr']['size'] = 20;
            $tmp['widget']["options"]["culture"] = $tmp['lang'].'-'.strtoupper($tmp['lang']);
						break;
					case 'multivalue':
						$tmp['tag'] = 'input';
						$tmp['attr']['type'] = 'text';
						break;
					case 'dropdown':
						$tmp['tag'] = 'input';
						$tmp['attr']['type'] = 'text';
						$tmp['widget']['options']['dataSource'] = [];
						$tmp['css'] = ['width' => 'auto'];
						break;
					case 'checkbox':
						$tmp['tag'] = 'input';
						$tmp['value'] = 1;
						$tmp['attr']['type'] = 'checkbox';
						break;
					case 'radio':
						$tmp['tag'] = 'input';
						$tmp['attr']['type'] = 'radio';
						$tmp['attr']['value'] = 1;
						break;
					case 'hidden':
						$tmp['tag'] = 'input';
						$tmp['attr']['type'] = 'hidden';
						break;
					case 'text':
						$tmp['tag'] = 'input';
						$tmp['attr']['type'] = 'text';
            $tmp['attr']['class'] = 'k-textbox';
						break;
					case 'numeric':
						$tmp['tag'] = 'input';
						$tmp['attr']['type'] = 'number';
            $tmp['widget']["options"]["culture"] = $tmp['lang'].'-'.strtoupper($tmp['lang']);
            $tmp['widget']["options"]["min"] = 0;
            $tmp['widget']["options"]["max"] = 100;
            $tmp['widget']["options"]["format"] = "#";
            $tmp['widget']["options"]["step"] = 1;
						if ( !isset($cfg['widget']['options']['max']) && isset($cfg['attr']['maxlength']) ){
							$max = '';
							$max_length = (int)$cfg['attr']['maxlength'];
							if ( isset($cfg['widget']['options']['decimals']) && $cfg['widget']['options']['decimals'] > 0 ){
								$max_length -= ( $cfg['widget']['options']['decimals'] + 1 );
							}
							for ( $i = 0; $i < $max_length; $i++ ){
								$max .= '9';
							}
							$cfg['widget']['options']['max'] = ( (float)$max > (int)$max ) ? (float)$max : (int)$max;
						}
						break;
					case 'editor':
						$tmp['tag'] = 'textarea';
            $tmp['attr']['cols'] = 80;
            $tmp['attr']['rows'] = 20;
            $tmp['widget']['options']['language'] = $tmp['lang'];
            $tmp['widget']['options']['toolbar'] = 'Custom';
						break;
				}
			}

      if ( isset($cfg['data']['sql'], $cfg['data']['db']) && $cfg['data']['db'] && strlen($cfg['data']['sql']) > 5 ){
        
        $db =& $cfg['data']['db'];
        
        if ( !isset($cfg['widget']['options']['dataSource']) ){
  				$cfg['widget']['options']['dataSource'] = array();
        }
				$count = ( $r = $db->query($cfg['data']['sql']) ) ? $r->count() : 0;
				if ( $count <= self::max_values_at_once ){
					if ( $ds = $db->get_irows($cfg['data']['sql']) ){
						foreach ( $ds as $d ){
							array_push($cfg['widget']['options']['dataSource'], array('value' => $d[0], 'text' => $d[1]));
						}
            $cfg['widget']['options']['dataTextField'] = 'text';
            $cfg['widget']['options']['dataValueField'] = 'value';
					}
				}
				else{
					$cfg['field'] = 'autocomplete';
					//$cfg['options']['dataSource']['']
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
        
        

        $cfg = \bbn\tools::merge_arrays($tmp, $cfg);
        /*
        if ( isset($wid) ){
          foreach ( $wid['opt'] as $o ){
            if ( isset($cfg['widget']['options'][$o]) ){
              $cfg['widget']['options'][$o] = $cfg['widget']['options'][$o];
            }
          }
        }
         * 
         */
        $cfg = array_filter($cfg, function($a){
          return !( is_array($a) && count($a) === 0 );
        });

        $t = new \bbn\html\input($cfg);
        return $t;
      }
		}
		return false;
	}
}		
?>