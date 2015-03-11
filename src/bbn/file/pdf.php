<?php
/**
 * @package bbn\file
 */
namespace bbn\file;

/**
 * This class generates PDF with the mPDF class
 *
 *
 * @author Thomas Nabet <thomas.nabet@gmail.com>
 * @copyright BBN Solutions
 * @since Dec 14, 2012, 04:23:55 +0000
 * @category  Appui
 * @license   http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @version 0.2r89
*/

class pdf
{
  private static $default_cfg = [
      'mode' => 'utf-8-s',
      'format' => 'A4',
      'size' => 8,
      'font' => 'times',
      'mgl' => 15,
      'mgr' => 15,
      'mgt' => 15,
      'mgb' => 15,
      'mgh' => 10,
      'mgf' => 10,
      'orientation' => 'P',
      'head' => <<<EOF
<html>
  <head>
    <title>PDF Doc</title>
  </head>
  <body>
    <table width="100%" border="0">
      <tr>
        <td width="40%" style="vertical-align:top; font-size:0.8em; color:#666">Your logo here</td>
        <td width="60%">&nbsp;</td>
      </tr>
    </table>
EOF
      ,
      'foot' => <<<EOF
    <div align="center" style="text-align:justify; color:#666; font-size:0.8em">
      Your<br>Adress<br>Here
    </div>
  </body>
</html>
EOF
      ,
      'title_tpl' => '<div style="background-color:#DDD; text-align:center; font-size:large; font-weight:bold; border-bottom-color:#000; border-width:3px; padding:20px; border-style:solid; text-transform:uppercase; margin-bottom:30px">%s</div>',
      'text_tpl' => '<div style="text-align:justify; margin-top:30px; margin-bottom:30px">%s</div>',
      'signature' => '<div style="text-align:right">Your signing here</div>'
  ];
  public $cfg;
	private $pdf = false;
 
  public static function set_default(array $cfg){
    self::$default_cfg = \bbn\tools::merge_arrays(self::$default_cfg, $cfg);
  }
  
	private function check()
  {
    return ( get_class($this->pdf) === 'mPDF' );
  }
  public function __construct($cfg=null)
	{
    $this->reset_config($cfg);

    $this->pdf = new \mPDF(
			$this->cfg['mode'],
			$this->cfg['format'],
			$this->cfg['size'],
			$this->cfg['font'],
			$this->cfg['mgl'],
			$this->cfg['mgr'],
			$this->cfg['mgt'],
			$this->cfg['mgb'],
			$this->cfg['mgh'],
			$this->cfg['mgf'],
			$this->cfg['orientation']);
    
    $this->pdf->SetImportUse();
    
    if ( is_string($cfg) ){
      $this->add_page($cfg);
    }
    return $this;
	}
  
  
  public function get_config(array $cfg=null){
    if ( $cfg ){
      return \bbn\tools::merge_arrays($this->cfg, $cfg);
    }
    return $this->cfg;
  }
  
  public function reset_config($cfg){
    if ( is_array($cfg) ){
      $this->cfg = \bbn\tools::merge_arrays(self::$default_cfg, $cfg);
    }
    else{
      $this->cfg = self::$default_cfg;
    }
    return $this;
  }
 
	public function add_page($html, $cfg=null, $sign=false)
	{
		if ( $this->check() ){
      
      $cfg = $this->get_config($cfg);
      $this->pdf->defHTMLHeaderByName('head', $cfg['head']);
      $this->pdf->defHTMLFooterByName('foot', $cfg['foot']);

      if ( isset($cfg['template']) && is_file($cfg['template']) ){
        $src = $this->pdf->SetSourceFile($cfg['template']);
        $tpl = $this->pdf->ImportPage($src);
        $this->pdf->SetPageTemplate($tpl);
      }

      $this->pdf->AddPageByArray(array(
        'orientation' => $cfg['orientation'],
        'mgl' => $cfg['mgl'],
        'mgr' => $cfg['mgr'],
        'mgt' => $cfg['mgt'],
        'mgb' => $cfg['mgb'],
        'mgh' => $cfg['mgh'],
        'mgf' => $cfg['mgf'],
				'ohname' => 'head',
				'ofname' => 'foot',
        'ohvalue' => 1,
        'ofvalue' => 1
			));
			if ( $sign ){
				$this->pdf->WriteHTML($html.$this->cfg['signature']);
      }
			else{
        //die(var_dump($html, $cfg['head'], $cfg['foot']));
				$this->pdf->WriteHTML($html);
      }
		}
		return $this;
	}
  
  public function add_css($file)
  {
    $this->pdf->WriteHTML(file_get_contents($file), 1);
    return $this;
  }

  public function show($file='MyPDF.pdf')
	{
		if ( $this->check() )
		{
			$this->pdf->Output($file, "I");
      die();
		}
	}
  
	public function makeAttachment()
	{
		if ( $this->check() )
		{
			$pdfdoc = $this->pdf->Output("", "S");
			return chunk_split(base64_encode($pdfdoc));
		}
	}
}
?>
