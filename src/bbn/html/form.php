<?php
/**
 * @package html
 */
namespace bbn\html;
use bbn;

/**
 * Generates form and its elements
 *
 *
 * @author Thomas Nabet <thomas.nabet@gmail.com>
 * @copyright BBN Solutions
 * @since Dec 14, 2013, 04:23:55 +0000
 * @category  Appui
 * @license   http://www.opensource.org/licenses/mit-license.php MIT
 * @version 0.4
*/

class form extends element
{

  public 
  /**
	 * Array with every html element of the form
	 * @var array
	 */
          $rules = [];

	/**
	 * This will call the initial build for a new instance. It should be called only once from within the script. All subsequent calls to controllers should be done through $this->add($path).
	 *
	 * @param array $cfg The default config for the elements
	 */
	public function __construct($cfg)
	{
    if ( $cfg ){
      if (\is_string($cfg) ){
        $cfg = [
            'tag' => 'form',
            'attr' => [
              'action' => $cfg,
              'method' => 'post'
            ]
        ];
      }
      else{
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
      }
      parent::__construct($cfg);
		}
	}
	
	public function script($with_ele=1)
	{
    $st = parent::script($with_ele);
    if ( isset($this->attr['id']) ){
      /** @todo Check it out! */
      //$st .= 'kendo.bind("#'.$this->attr['id'].' *", appui.tabstrip.obs[appui.tabstrip.selected].info);';
    }
    return $st;
	}
  
  
}		
?>