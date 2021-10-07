<?php
/**
 * @package file
 */
namespace bbn\File;
use bbn;
use bbn\X;
use bbn\Str;
use bbn\Mvc;
use bbn\File\Dir;

/**
 * This class generates PDF with the mPDF class
 *
 *
 * @author Thomas Nabet <thomas.nabet@gmail.com>
 * @copyright BBN Solutions
 * @since Dec 14, 2012, 04:23:55 +0000
 * @category  Appui
 * @license   http://www.opensource.org/licenses/mit-license.php MIT
 * @version 0.2r89
*/

class Pdf {
  private static
    $default_cfg = [
      'mode' => 'ISO-8859-2',
      'format' => 'A4',
      'default_font_size' => 8,
      'default_font' => 'Times',
      'margin_left' => 15,
      'margin_right' => 15,
      'margin_top' => 15,
      'margin_bottom' => 15,
      'margin_header' => 10,
      'margin_footer' => 10,
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

  private
    $pdf = false,
    $last_cfg = [];

  public $cfg;

  private function check(){
    return ( \get_class($this->pdf) === 'Mpdf\Mpdf' );
  }

  private function fixCfg(array $cfg){
    if ( \is_array($cfg) ){
      $to_check = [
        'size' => 'default_font_size',
        'font' => 'default_font',
        'mgl' => 'margin_left',
        'mgr' => 'margin_right',
        'mgt' => 'margin_top',
        'mgb' => 'margin_bottom',
        'mgh' => 'margin_header',
        'mgf' => 'margin_footer'
      ];
      foreach ( $cfg as $i => $c ){
        if ( isset($to_check[$i]) ){
          $cfg[$to_check[$i]] = $c;
          unset($cfg[$i]);
        }
      }
    }
    return $cfg;
  }

  public static function setDefault(array $cfg){
    self::$default_cfg = X::mergeArrays(self::$default_cfg, $cfg);
  }

  public function __construct($cfg = null){
    // Temp path for PDF generation (MPDF)
    if (!defined('_MPDF_TEMP_PATH') && defined('BBN_DATA_PATH')) {
      define('_MPDF_TEMP_PATH', BBN_DATA_PATH . 'tmp/');
    }
    $this->resetConfig($cfg);
    $this->pdf = new \Mpdf\Mpdf($this->cfg);
    //$this->pdf->SetImportUse();
    if ( \is_string($cfg) ){
      $this->addPage($cfg);
    }
	}
  
  public function getObj(){
    return $this->pdf;
  }
  
  public function getConfig(array $cfg = null){
    if ( \is_array($cfg) ){
      return X::mergeArrays($this->cfg, $this->fixCfg($cfg));
    }
    return $this->cfg;
  }
  
  public function resetConfig($cfg){
    if ( \is_array($cfg) ){
      $this->cfg = X::mergeArrays(self::$default_cfg, $this->fixCfg($cfg));
    }
    else{
      $this->cfg = self::$default_cfg;
    }
    if (
      empty($this->cfg['tempDir']) &&
      ($tmp = Mvc::getTmpPath()) &&
      ($path = Dir::createPath($tmp))
    ){
      $this->cfg['tempDir'] = $path;
    }
    return $this;
  }
 
	public function addPage($html, $cfg = null, $sign = false){
		if ( $this->check() ){
      if ( $this->last_cfg !== $cfg ){
        $this->last_cfg = $cfg;
        $cfg = $this->getConfig($cfg);
        if ( isset($cfg['template']) && is_file($cfg['template']) ){
          $src = $this->pdf->SetSourceFile($cfg['template']);
          $tpl = $this->pdf->importPage($src);
          $this->pdf->SetPageTemplate($tpl);
        }
        else{
          $this->pdf->DefHTMLHeaderByName('head', $this->cfg['head']);
          $this->pdf->DefHTMLFooterByName('foot', $this->cfg['foot']);
        }
      }
      $this->pdf->AddPageByArray([
        'orientation' => $this->cfg['orientation'],
        'margin-left' => $this->cfg['margin_left'],
        'margin-right' => $this->cfg['margin_right'],
        'margin-top' => $this->cfg['margin_top'],
        'margin-bottom' => $this->cfg['margin_bottom'],
        'margin-header' => $this->cfg['margin_header'],
        'margin-footer' => $this->cfg['margin_footer'],
				'odd-header-name' => 'head',
				'odd-footer-name' => 'foot',
        'odd-header-value' => 1,
        'odd-footer-value' => 1
      ]);
			if ( $sign ){
				$this->pdf->WriteHTML($html.$this->cfg['signature']);
      }
			else{
				$this->pdf->WriteHTML($html);
      }
		}
		return $this;
	}
  
  public function addCss($file){
    $this->pdf->WriteHTML(file_get_contents($file), 1);
    return $this;
  }

  public function show($file = 'MyPDF.pdf'){
		if ( $this->check() ){
			$this->pdf->Output($file, \Mpdf\Output\Destination::INLINE);
      die();
		}
	}

  public function download($file = 'MyPDF.pdf'){
		if ( $this->check() ){
			$this->pdf->Output($file, \Mpdf\Output\Destination::DOWNLOAD);
      die();
		}
	}

  public function setPageSize($size){
    if ($this->check()) {
      $orientation = $this->pdf->CurOrientation;
      $this->pdf->_setPageSize($size, $orientation);
    }
    return $this;
  }

  public function getCurrentPageContentHeight(){
    if ($this->check()) {
      return $this->pdf->y;
    }
    return 0;
  }

  public function getCurrentPageHeight(){
    if ($this->check()) {
      return $this->pdf->h;
    }
    return 0;
  }

  public function setDpi(int $dpi) {
    if ($this->check()) {
      $this->pdf->dpi = $dpi;
      $this->pdf->img_dpi = $dpi;
    }
    return $this;
  }
  
	public function makeAttachment(){
		if ( $this->check() ){
			$pdf = $this->pdf->Output("", \Mpdf\Output\Destination::STRING_RETURN);
			return chunk_split(base64_encode($pdf));
		}
	}

  public function save($filename){
    if ( $this->check() ){
      $filename = Str::parsePath($filename, true);
      if ( !is_dir(X::dirname($filename)) ){
        die("Error! No destination directory");
      }
      $this->pdf->Output($filename, \Mpdf\Output\Destination::FILE);
      return is_file($filename);
    }
  }

  public function import($files){
    if ( $this->check() ){
      if ( !\is_array($files) ){
        $files = [$files];
      }
      //$this->pdf->SetImportUse();
      foreach ( $files as $f ){
        if ( is_file($f) ){
          $pagecount = $this->pdf->SetSourceFile($f);
          for ( $i = 1; $i <= $pagecount; $i++ ){
            $import_page = $this->pdf->importPage($i);
            $this->pdf->UseTemplate($import_page);
            $this->pdf->addPage();
          }
        }
      }
    }
    return $this;
  }

  public function importPage($file, $page){
    if ( $this->check() ){
      //$this->pdf->SetImportUse();
      if ( is_file($file) ){
        $pagecount = $this->pdf->SetSourceFile($file);
        if ( ($page > 0) && ($page < $pagecount) ){
          $import_page = $this->pdf->importPage($page);
          $this->pdf->UseTemplate($import_page);
          $this->pdf->addPage();
        }
      }
    }
    return $this;
  }

  /**
   * Adds custom fonts
   *
   * $pdf->addFonts([
   *  'dawningofanewday' => [
   *    'R' => BBN_DATA_PATH.'files/DawningofaNewDay.ttf'
   *   ]
   * ]);
   *
   * @param array $fonts
   */
  public function addFonts(array $fonts){
    if ( !\defined('BBN_LIB_PATH') ){
      die('You must define BBN_LIB_PATH!');
    }
    if ( !is_dir(BBN_LIB_PATH . 'mpdf/mpdf/ttfonts/') ){
      die("You don't have the mpdf/mpdf/ttfonts directory.");
    }
    foreach ($fonts as $f => $fs) {
      // add to available fonts array
      foreach ( $fs as $i => $v ){
        if ( !empty($v) ){
          // check if file exists in mpdf/ttfonts directory
          if ( !is_file(BBN_LIB_PATH . 'mpdf/mpdf/ttfonts/' . X::basename($v)) ){
            Dir::copy($v, BBN_LIB_PATH . 'mpdf/mpdf/ttfonts/' . X::basename($v));
          }
          $fs[$i] = X::basename($v);
          if ( $i === 'R' ){
            array_push($this->pdf->available_unifonts, $f);
          }
          else {
            array_push($this->pdf->available_unifonts, $f.$i);
          }
        }
        else {
          unset($fs[$i]);
        }
      }
      // add to fontdata array
      $this->pdf->fontdata[$f] = $fs;
    }
    $this->pdf->default_available_fonts = $this->pdf->available_unifonts;
  }
  
}
