<?php
namespace bbn\html;
/**
 * Model View Controller Class
 *
 *
 * This class will route a request to the according model and/or view through its controller.
 * A model and a view can be automatically associated if located in the same directory branch with the same name than the controller in their respective locations
 * A view can be directly imported in the controller through this very class
 *
 * @author Thomas Nabet <thomas.nabet@gmail.com>
 * @copyright BBN Solutions
 * @since Apr 4, 2011, 23:23:55 +0000
 * @category  MVC
 * @license   http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @version 0.2r89
 * @todo Merge the output objects and combine JS strings.
 * @todo Stop to rely only on sqlite and offer file-based or any db-based solution.
 * @todo Look into the check function and divide it
 */
class input
{
	public
	/**
	 * Is set to null while not routed, then 1 if routing was sucessful, and false otherwise.
	 * @var null|boolean
	 */
		$tag = 'input',
	/**
	 * Is set to null while not routed, then 1 if routing was sucessful, and false otherwise.
	 * @var null|boolean
	 */
	 	$id,
	/**
	 * The controller file (with full path)
	 * @var null|string
	 */
		$name,
	/**
	 * The controller file (with full path)
	 * @var null|string
	 */
		$label,
	/**
	 * The mode of the output (dom, html, json, txt, xml...)
	 * @var null|string
	 */
		$value = '';
	/**
	 * The mode of the output (dom, html, json, txt, xml...)
	 * @var null|string
	 */
	private $cfg,
		$options = array(),
		$html = '',
		$script;
	/**
	 * This will call the initial build a new instance. It should be called only once from within the script. All subsequent calls to controllers should be done through $this->add($path).
	 *
	 * @param object | string &$appui The $appui object in the first call and the controller path in the calls within the class (through Add)<em>(e.g books/466565 or html/home)</em>
	 * @param string | object $parent The parent controller</em>
	 * @return bool
	 */
	public function __construct(array $cfg = null)
	{
		if ( isset($cfg['name'], $cfg['tag']) )
		{
			$mandatory_opt = array();
			$possible_opt = array("required", "width", "placeholder", "cssclass", "title");
			$this->cfg = $cfg;
			$this->tag = strtolower($cfg['tag']);
			$this->name = $cfg['name'];
			$this->id = isset($cfg['id']) ? $cfg['id'] : \bbn\str\text::genpwd(20,15);
			$this->label = isset($cfg['label']) ? $cfg['label'] : str_replace(']', ')',str_replace('[',' (',str_replace('_', ' ', $cfg['name'])));
			$this->required = isset($cfg['required']) ? $cfg['required'] : false;
			$this->options = isset($cfg['options']) ? $cfg['options'] : array();
			$this->script = isset($cfg['script']) ? $cfg['script'] : '';
			$this->value = isset($cfg['value']) ? $cfg['value'] : '';
			switch ( $this->tag )
			{
				case "input":
					array_push($mandatory_opt, "type");
					array_push($possible_opt, "minlength", "maxlength", "size", "rangelength", "min", "max", "range", "email", "url", "date", "number", "digits", "creditcard", "equalTo");
					break;
				case "textarea":
					array_push($mandatory_opt, "cols", "rows");
					array_push($possible_opt, "minlength", "maxlength", "rangelength", "height");
					break;
				case "select":
					array_push($mandatory_opt, "options");
					array_push($possible_opt, "multiple");
					break;
				case "file":
					array_push($possible_opt, "multiple", "accept");
					break;
			}
			foreach ( $mandatory_opt as $m ){
				if ( isset($cfg['options'][$m]) ){
					$this->options[$m] = $cfg['options'][$m];
				}
				else{
					die("Argument $m is missing in your config... Sorry!");
				}
			}
			foreach ( $cfg['options'] as $k => $v ){
				if ( in_array($k, $possible_opt) ){
					$this->options[$k] = $v;
				}
			}
		}
	}
	
	public function get_config()
	{
		return $this->cfg;
	}
	public function get_label_input()
	{
		$s = $this->get_html();
		if ( !empty($s) ){
			$s = '<label class="appui-form-label">'.$this->label.'</label><div class="appui-form-field">'.$s.'</div>';
		}
		return $s;
	}
	public function get_html()
	{
		if ( empty($this->html) && $this->name ){
			
			$this->html .= '<'.$this->tag.' name="'.$this->name.'"';
			
			if ( isset($this->id) ){
				$this->html .= ' id="'.$this->id.'"';
			}
			
			if ( $this->tag === 'input' && isset($this->options['type']) ){
				$this->html .= ' type="'.$this->options['type'].'"';
				
				if ( $this->options['type'] === 'text' ){
					if ( isset($this->options['maxlength']) && ( $this->options['maxlength'] > 0 ) && $this->options['maxlength'] <= 1000 ){
						$this->html .= ' maxlength="'.$this->options['maxlength'].'"';
					}
					if ( isset($this->options['size']) && ( $this->options['size'] > 0 ) && $this->options['size'] <= 100 ){
						$this->html .= ' size="'.$this->options['size'].'"';
					}
				}
				
				$this->html .= ' value="'.$this->value.'"';
			}
			
			if ( isset($this->options['title']) ){
				$this->html .= ' title="'.$this->options['title'].'"';
			}
			
			$class = '';
			if ( isset($this->options['cssclass']) ){
				$class .= $this->options['cssclass'].' ';
			}
			if ( isset($this->options['email']) ){
				$class .= 'email ';
			}
			if ( isset($this->options['url']) ){
				$class .= 'url ';
			}
			if ( isset($this->options['number']) ){
				$class .= 'number ';
			}
			if ( isset($this->options['digits']) ){
				$class .= 'digits ';
			}
			if ( isset($this->options['creditcard']) ){
				$class .= 'creditcard ';
			}
			
			if ( !empty($class) ){
				$this->html .= ' class="'.trim($class).'"';
			}
			
			$this->html .= '>';
			
			if ( $this->tag === 'select' || $this->tag === 'textarea' ){
				$this->html .= '</'.$this->tag.'>';
			}
			
			if ( isset($this->options['placeholder']) && strpos($this->options['placeholder'],'%s') !== false ){
				$this->html = sprintf($this->options['placeholder'], $this->html);
			}
		}
		return $this->html;
	}
	
	public function get_script()
	{
		$r = '';
		if ( $this->name ){
			/*
			if ( $this->value && $this->id ){
				$r .= '$("#'.$this->id.'").val("'.$this->value.'");';
			}
			*/
			if ( $this->script ){
				$r .= $this->script;
			}
		}
		return $r;
	}
	
}
?>