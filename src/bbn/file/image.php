<?php
/**
 * @package bbn\file
 */
namespace bbn\file;
/**
 * Image Class
 *
 *
 * This class is used to upload, delete and transform images, and create thumbnails.
 *
 * @author Thomas Nabet <thomas.nabet@gmail.com>
 * @copyright BBN Solutions
 * @since Apr 4, 2011, 23:23:55 +0000
 * @category  Files ressources
 * @license   http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @version 0.2r89
 * @todo Deal specifically with SVG
 * @todo Add a static function and var to check for available libraries (Imagick/GD)
 */
class image extends \bbn\file\file 
{
	/**
	 * @var array
	 */
	protected static
          $allowed_extensions = array('jpg','gif','jpeg','png','svg'),
          $max_width = 5000;

	/**
	 * @var bool
	 */
	protected static $exif=false;

	/**
	 * @var mixed
	 */
	protected $ext2;

	/**
	 * @var mixed
	 */
	protected $w;

	/**
	 * @var mixed
	 */
	protected $h;

	/**
	 * @var mixed
	 */
	protected $img;

/**
 * Converts one or more jpg image(s) to one pdf file.
 *
 * <code>
 * \bbn\file\image::jpg2pdf(["C:\test\image1.jpg", "C:\test\image1.jpg"], "C:\test\combined.pdf"); //Converts two jpg image to one pdf file"
 * \bbn\file\image::jpg2pdf(["C:\test\image.jpg"], "C:\test\image.pdf"); //Converts jpg image to pdf
 * </code>
 *
 * @param array $jpg The path of jpg file(s) to convert
 * @param string $pdf The destination pdf filename..
 * @return string|array
 */
public static function jpg2pdf($jpg, $pdf){
 if ( class_exists('\\Imagick') ) {
  if ( is_array($jpg) ) {
   $img = new \Imagick();
   $img->setResolution(200, 200);
   if ( count($jpg) === 1 ){
    $img->readImage($jpg[0]);
   }
   else {
    $img->readImages($jpg);
   }
   $img->setImageFormat('pdf');
   if ( count($jpg) === 1 ){
    $img->writeImage($pdf);
   }
   else {
    $img->writeImages($pdf, 1);
   }
   return $pdf;
  }
 }
 return false;
}
  
/**
   * Converts pdf file to jpg image(s).
   *
   * <code>
   * \bbn\file\image::pdf2jpg("C:\test\file.pdf"); //Converts the first page of pdf to "C:\test\file.jpg"
   * \bbn\file\image::pdf2jpg("C:\test\file.pdf", '', 'all'); //Converts all pages of pdf to "C:\test" with filename "file-0.jpg", "file-1.jpg", "file-2.jpg", ecc
   * \bbn\file\image::pdf2jpg("C:\test\file.pdf", "C:\test2\file.jpg", 2); //Converts the third page of pdf to "C:\test2\file.jpg"
   * </code>
   *
   * @param $pdf The path of pdf file to convert
   * @param $jpg The destination filename. If empty is used the same path of pdf. Default: empty.
   * @param $num The index page of pdf file to convert. If set 'all' all pages to convert. Default: 0(first page).
   * @return string|array
   */
  public static function pdf2jpg($pdf, $jpg='', $num=0){
    if ( class_exists('\\Imagick') ) {
      $img = new \Imagick();
      $img->setResolution(200, 200);
      $img->readImage($pdf);
      $img->setImageFormat('jpg');
      if ( empty($jpg) ) {
				$dir = dirname($pdf);
				if ( !empty($dir) ){
					$dir .= '/';
				}
				$f = \bbn\str::file_ext($pdf, 1);
        $jpg = $dir.$f[0].'.jpg';
      }
      if ( $num !== 'all' ) {
        $img->setIteratorIndex($num);
        if ( $img->writeImage($jpg) ) {
          return $jpg;
        }
      }
      else{
        if ( $img->writeImages($jpg, 1) ) {
					$i = 0;
					$r = [];
					$f = \bbn\str::file_ext($jpg, 1);
					$dir = dirname($jpg);
					if ( !empty($dir) ){
						$dir .= '/';
					}
					while ( file_exists($dir.$f[0].'-'.$i.'.'.$f[1]) ){
						array_push($r, $dir.$f[0].'-'.$i.'.'.$f[1]);
						$i++;
					}
          return $r;
        }
      }
    }
    return false;
  }

	/**
	 * @return void 
	 */
	public function __construct($file)
	{
		parent::__construct($file);
		if ( !in_array($this->get_extension(),\bbn\file\image::$allowed_extensions) )
		{
			$this->name = false;
			$this->path = false;
			$this->file = false;
			$this->size = false;
			$this->title = false;
		}
		return $this;
	}

	/**
   * Returns the file image extension.
   * 
   * <code>
   * $img = new \bbn\file\image("C:\test\img.jpg");
   * $img->get_extension(); //Returns "jpg"
   * </code>
   * 
	 * @return string 
	 */
	public function get_extension()
	{
		if ( !$this->ext2 && $this->file )
		{
			if ( function_exists('exif_imagetype') )
			{
				if ( $r = exif_imagetype($this->file) )
				{
					if ( array_key_exists($r,\bbn\file\image::$allowed_extensions) )
						$this->ext = \bbn\file\image::$allowed_extensions[$r];
					else
						$this->ext = false;
				}
				else
					$this->ext = false;
			}
			else
				parent::get_extension();
			if ( $this->ext )
			{
				$this->ext2 = $this->ext;
				if ( $this->ext2 == 'jpg' )
					$this->ext2 = 'jpeg';
			}
		}
		return $this->ext;
	}

	/**
   * Tests if the object is a image.
   * 
   * <code>
   * $img = new \bbn\file\image("C:\test\img.jpg");
   * $img->test(); //Returns "true"
   * </code>
   * 
	 * @return boolean 
	 */
	public function test()
	{
		if ( $this->make() )
		{
			if ( $this->error ){
				return false;
			}
			return true;
		}
		return false;
	}

	/**
	 * @return void 
	 */
	protected function make()
	{
		parent::make();
		/* For images as string - to implement 
		if ( class_exists('\\Imagick') )
		{
			$this->img = new \Imagick();
			$this->img->readImageBlob(base64_decode($this->file));
		}
		else if ( function_exists('imagecreatefromstring') )
			$this->img = imagecreatefromstring($this->file);
		*/
		if ( $this->file ){
			if ( !$this->img ){
				if ( class_exists('\\Imagick') ){
					try{
						$this->img = new \Imagick($this->file);
						$this->img->setInterlaceScheme(\Imagick::INTERLACE_PLANE);
						$this->w = $this->img->getImageWidth();
						$this->h = $this->img->getImageHeight();
					}
					catch ( \Exception $e ){
						$this->img = false;
						$this->error = defined('BBN_THERE_HAS_BEEN_A_PROBLEM') ? 
							BBN_THERE_HAS_BEEN_A_PROBLEM : 'There has been a problem';
					}
				}
				else if ( function_exists('imagecreatefrom'.$this->ext2) ){
					if ( $this->img = call_user_func('imagecreatefrom'.$this->ext2,$this->file) ){
						imageinterlace($this->img, true);
						$this->w = imagesx($this->img);
						$this->h = imagesy($this->img);
						if ( imagealphablending($this->img,true) ){
							imagesavealpha($this->img,true);
						}
					}
					else{
						$this->error = defined('BBN_THERE_HAS_BEEN_A_PROBLEM') ? 
							BBN_THERE_HAS_BEEN_A_PROBLEM : 'There has been a problem';
					}
				}
				else{
					$this->error = defined('BBN_THERE_HAS_BEEN_A_PROBLEM') ? 
						BBN_THERE_HAS_BEEN_A_PROBLEM : 'There has been a problem';
				}
			}
		}
		else{
			$this->error = defined('BBN_THERE_HAS_BEEN_A_PROBLEM') ? 
				BBN_THERE_HAS_BEEN_A_PROBLEM : 'There has been a problem';
		}
		return $this;
	}

	/**
   * Sends the image with Content-Type.
   *  
	 */
	public function display()
	{
		if ( $this->test() ){
			if ( !headers_sent() ){
				header('Content-Type: image/'.$this->ext2);
			}
			if ( class_exists('\\Imagick') ){
				echo $this->img;
				$this->img->clear();
				$this->img->destroy();
			}
			else{
				call_user_func('image'.$this->ext2, $this->img);
				imagedestroy($this->img);
			}
		}
		return $this;
	}

	/**
	 * @return void 
	 */
	public function save($dest=false)
	{
		if ( $this->test() ){
			if ( !$dest ){
				$dest = $this->file;
			}
			if ( class_exists('\\Imagick') ){
        if ( !$this->img->writeImage($dest) ){
          $this->error = defined('BBN_THERE_HAS_BEEN_A_PROBLEM') ? 
            BBN_THERE_HAS_BEEN_A_PROBLEM : 'There has been a problem';
        }
      }
			else if ( function_exists('image'.$this->ext2) ){
        if ( !call_user_func('image'.$this->ext2, $this->img, $dest) ){
          $this->error = defined('BBN_THERE_HAS_BEEN_A_PROBLEM') ? 
            BBN_THERE_HAS_BEEN_A_PROBLEM : 'There has been a problem';
        }
			}
		}
		return $this;
	}

	/**
   * Returns the file image width.
   * 
   * <code>
   * $img = new \bbn\file\image("C:\test\img.jpg");
   * $img->get_width(); //Returns "512"
   * </code>
   * 
	 * @return integer 
	 */
	public function get_width()
	{
		if ( $this->test() ){
			if ( isset($this->w) ){
				return $this->w;
			}
		}
		return 0;
	}

	/**
   * Returns the file image height.
   * 
   * <code>
   * $img = new \bbn\file\image("C:\test\img.jpg");
   * $img->get_height(); //Returns "512"
   * </code>
   * 
	 * @return integer 
	 */
	public function get_height()
	{
		if ( $this->test() ){
			if ( isset($this->h) ){
				return $this->h;
			}
		}
		return 0;
	}

	/**
   * Resize the image.
   * 
   * <code>
   * $img = new \bbn\file\image("C:\test\img.jpg");
   * $img->resize(150, 150); //Resizes image  150x150px
   * $img->resize(0, 150, 1); //Resizes and cuts the image 
   * </code>
   * 
   * @param integer $w The new width.
   * @param integer $h The new height.
   * @param boolean $crop If cropping the image.
   * @param integer $max_w The maximum value for new width.
   * @param integer $max_h The maximum valure for new height.
   * 
	 * @return \bbn\file\image 
	 */
	public function resize($w=false, $h=false, $crop=false, $max_w=false, $max_h=false)
	{
		$max_w = false;
		$max_h = false;
		if ( is_array($w) ){
			$max_w = isset($w['max_w']) ? $w['max_w'] : false;
			$max_h = isset($w['max_h']) ? $w['max_h'] : false;
			$crop = isset($w['crop']) ? $w['crop'] : false;
			$h = isset($w['h']) ? $w['h'] : false;
			$w = isset($w['w']) ? $w['w'] : false;
		}
		if ( ( $w || $h ) && $this->test() ){
			if ( $w && $h ){
				if ( $crop && ( ( $this->w / $this->h ) != ( $w / $h ) ) ){
					if ( ( $this->w / $this->h ) < ( $w / $h ) ){
						$w2 = $w;
						$h2 = floor(($w2*$this->h)/$this->w);
						$x = 0;
						$y = floor(($h2-$h)/2);
					}
					else if ( ( $this->w / $this->h ) > ( $w / $h ) ){
						$h2 = $h;
						$w2 = floor(($h2*$this->w)/$this->h);
						$y = 0;
						$x = floor(($w2-$w)/2);
					}
					if ( class_exists('\\Imagick') ){
						$res = $this->img->resizeImage($w2,$h2,\Imagick::FILTER_LANCZOS,1);
					}
					else{
						$image = imagecreatetruecolor($w2,$h2);
            if ( $this->ext == 'png' || $this->ext == 'gif' || $this->ext == 'svg' ){
              imageColorAllocateAlpha($image, 0, 0, 0, 127);
              imagealphablending($image, false);
              imagesavealpha($image, true);
            }
            $res = imagecopyresampled($image,$this->img,0,0,0,0,$w2,$h2,$this->w,$this->h);
						$this->img = $image;
					}
					if ( $res === true ){
						$this->w = $w2;
						$this->h = $h2;
						if ( $this->crop($w,$h,$x,$y) ){
							$this->w = $w;
							$this->h = $h;
						}
					}
				}
				else{
					$w2 = $w;
					$h2 = $h;
					if ( class_exists('\\Imagick') ){
						$res = $this->img->resizeImage($w2,$h2,\Imagick::FILTER_LANCZOS,1);
					}
					else{
						$image = imagecreatetruecolor($w2,$h2);
            if ( $this->ext == 'png' || $this->ext == 'gif' || $this->ext == 'svg' ){
              imageColorAllocateAlpha($image, 0, 0, 0, 127);
              imagealphablending($image, false);
              imagesavealpha($image, true);
            }
						$res = imagecopyresampled($image,$this->img,0,0,0,0,$w2,$h2,$this->w,$this->h);
						$this->img = $image;
					}
				}
			}
			else{
				if ( $w > 0 ){
					$w2 = $w;
					$h2 = floor(($w2*$this->h)/$this->w);
				}
				if ( $h > 0 ){
					if ( isset($h2) ){
						if ( $h2 > $h ){
							$h2 = $h;
							$w2 = floor(($h2*$this->w)/$this->h);
						}
					}
					else{
						$h2 = $h;
						$w2 = floor(($h2*$this->w)/$this->h);
					}
				}
				if ( isset($w2,$h2) ){
					if ( class_exists('\\Imagick') ){
						$res = $this->img->resizeImage($w2,$h2,\Imagick::FILTER_LANCZOS,1);
					}
					else{
						$image = imagecreatetruecolor($w2,$h2);
            if ( $this->ext == 'png' || $this->ext == 'gif' || $this->ext == 'svg' ){
              imageColorAllocateAlpha($image, 0, 0, 0, 127);
              imagealphablending($image, false);
              imagesavealpha($image, true);
            }
						$res = imagecopyresampled($image,$this->img,0,0,0,0,$w2,$h2,$this->w,$this->h);
						$this->img = $image;
					}
					if ( $res === true ){
						$this->w = $w2;
						$this->h = $h2;
					}
					else{
						$this->error = defined('BBN_THERE_HAS_BEEN_A_PROBLEM') ? 
							BBN_THERE_HAS_BEEN_A_PROBLEM : 'There has been a problem';
					}
				}
				else{
					$this->error = defined('BBN_THERE_HAS_BEEN_A_PROBLEM') ? 
						BBN_THERE_HAS_BEEN_A_PROBLEM : 'There has been a problem';
				}
			}
		}
		return $this;
	}

	/**
   * Resizes the image with constant values.
   * 
   * @param integer $w BBN_MAX_WIDTH
   * @param integer $h BBN_MAX_HEIGHT
   * 
   * @return \bbn\file\image
   *  
   * @todo BBN_MAX_WIDTH and BBN_MAX_HEIGHT
	 */
	public function autoresize($w=BBN_MAX_WIDTH, $h=BBN_MAX_HEIGHT)
	{
		if ( !$w ){
			$w = defined('BBN_MAX_WIDTH') ? BBN_MAX_WIDTH : self::$max_width;
		}
		if ( $this->test() && is_numeric($w) && is_numeric($h) )
		{
			if ( $this->w > $w ){
				$this->resize($w);
			}
			if ( $this->h > $h ){
				$this->resize(false,$h);
			}
		}
		else{
			$this->error = defined('BBN_ARGUMENTS_MUST_BE_NUMERIC') ? 
				BBN_ARGUMENTS_MUST_BE_NUMERIC : 'Arguments must be numeric';
		}
		return $this;
	}

	/**
   * Returns a crop of the image.
   * 
   * <code>
   * $img = new \bbn\file\image("C:\test\img.jpg");
   * $img->crop(150, 150, 300, 300)';
   * </code>
   * 
   * @param integer $w Width
   * @param integer $h Height
   * @param integer $x X coordinate
   * @param integer $y Y coordinate
   * 
	 * @return \bbn\file\image
	 */
	public function crop($w, $h, $x, $y)
	{
		if ( $this->test() ){
			$args = func_get_args();
			foreach ( $args as $arg ){
				if ( !is_numeric($arg) ){
					$this->error = defined('BBN_ARGUMENTS_MUST_BE_NUMERIC') ? 
						BBN_ARGUMENTS_MUST_BE_NUMERIC : 'Arguments must be numeric';
				}
			}
			if ( $w + $x > $this->w ){
				return false;
			}
			if ( $h + $y > $this->h ){
				return false;
			}
			if ( class_exists('\\Imagick') ){
				if ( !$this->img->cropImage($w,$h,$x,$y) ){
					$this->error = defined('BBN_THERE_HAS_BEEN_A_PROBLEM') ? 
						BBN_THERE_HAS_BEEN_A_PROBLEM : 'There has been a problem';
				}
			}
			else
			{
				$img = imagecreatetruecolor($w,$h);
        if ( $this->ext == 'png' || $this->ext == 'gif' || $this->ext == 'svg' ){
          imageColorAllocateAlpha($img, 0, 0, 0, 127);
          imagealphablending($img, false);
          imagesavealpha($img, true);
        }
				if ( imagecopyresampled($img,$this->img,0,0,$x,$y,$w,$h,$w,$h) ){
					$this->img = $img;
				}
				else{
					$this->error = defined('BBN_THERE_HAS_BEEN_A_PROBLEM') ? 
						BBN_THERE_HAS_BEEN_A_PROBLEM : 'There has been a problem';
				}
			}
		}
		return $this;
	}

	/**
   * Rotates the image.
   * 
   * <code>
   * $img = new \bbn\file\image("C:\test\img.jpg");
   * $img->rotate(90); //Rotates the image 90°
   * </code>
   * 
   * @param integer $angle The angle of rotation.
   * 
	 * @return \bbn\file\image 
	 */
	public function rotate($angle)
	{
		$ok = false;
		if ( $this->test() ){
			if ( class_exists('\\Imagick') ){
				if ( $this->img->rotateImage(new \ImagickPixel(),$angle) ){
					$ok = 1;
        }
			}
			else if ( function_exists('imagerotate') ){
				if ( $this->img = imagerotate($this->img, $angle, 0) ){
          if ( $this->ext == 'png' || $this->ext == 'gif' || $this->ext == 'svg' ){
            imageColorAllocateAlpha($this->img, 0, 0, 0, 127);
            imagealphablending($this->img, false);
            imagesavealpha($this->img, true);
          }
					$ok = 1;
				}
			}
			if ( $ok ){
				if ( $angle == 90 || $angle == 270 ){
					$h = $this->h;
					$this->h = $this->w;
					$this->w = $h;
				}
			}
			else{
				$this->error = defined('BBN_THERE_HAS_BEEN_A_PROBLEM') ? 
					BBN_THERE_HAS_BEEN_A_PROBLEM : 'There has been a problem';
			} 
		}
		return $this;
	}

	/**
   * Flips the image.
   * 
   * <code>
   * $img = new \bbn\file\image("C:\test\img.jpg");
   * $img->flip(); //Vertical flipping
   * $img->flip(); //Horizontal flipping
   * </code>
   * 
   * @param string $mode Vertical ("v") or Horizontal ("h") flip, default: "v".
   * 
	 * @return \bbn\file\image 
	 */
public function flip($mode='v')
	{
		if ( $this->test() )
		{
			if ( class_exists('\\Imagick') )
			{
				if ( $mode == 'v' )
				{
					if ( !$this->img->flipImage() ){
						$this->error = defined('BBN_THERE_HAS_BEEN_A_PROBLEM') ? 
							BBN_THERE_HAS_BEEN_A_PROBLEM : 'There has been a problem';
					}
				}
				else if ( !$this->img->flopImage() ){
					$this->error = defined('BBN_THERE_HAS_BEEN_A_PROBLEM') ? 
						BBN_THERE_HAS_BEEN_A_PROBLEM : 'There has been a problem';
				}
			}
			else
			{
				$w = imagesx($this->img);
				$h = imagesy($this->img);
				if ( $mode == 'v' ){
					imageflip($this->img, IMG_FLIP_VERTICAL);
				}
				else{
					imageflip($this->img, IMG_FLIP_HORIZONTAL);
				}
			}
		}
		return $this;
	}
  
	/**
   * Adjusts the image brightness.
   * 
   * <code>
   * $img = new \bbn\file\image("C:\test\img.jpg");
   * $img->brightness(); //Increases the brightness
   * $img->brightness("-"); //Reduces the brightness
   * </code>
   * 
   * @param string $val The value "+" (default) increases the brightness, the value ("-") reduces it.
   *  
	 * @return \bbn\file\image 
	 */
	public function brightness($val='+')
	{
		if ( $this->test() )
		{
			if ( class_exists('\\Imagick') )
			{
				$p = ( $val == '-' ) ? 90 : 110;
				if ( !$this->img->modulateImage($p,100,100) ){
					$this->error = defined('BBN_THERE_HAS_BEEN_A_PROBLEM') ? 
						BBN_THERE_HAS_BEEN_A_PROBLEM : 'There has been a problem';
				}
			}
			else if ( function_exists('imagefilter') )
			{
				$p = ( $val == '-' ) ? -20 : 20;
				if ( !imagefilter($this->img,IMG_FILTER_BRIGHTNESS,-20) ){
					$this->error = defined('BBN_THERE_HAS_BEEN_A_PROBLEM') ? 
						BBN_THERE_HAS_BEEN_A_PROBLEM : 'There has been a problem';
				}
			}
		}
		return $this;
	}

	/**
   *  Adjusts the image contrast.
   * 
   * <code>
   * $img = new \bbn\file\image("C:\test\img.jpg");
   * $img->contrast(); //Increases the contrast
   * $img->contrast(); //Reduces the contrast
   * </code>
   * 
   * @param string $val The value "+" (default), increases the contrast, the value ("-") reduces it.
   * 
	 * @return \bbn\file\image
	 */
	public function contrast($val='+')
	{
		if ( $this->test() )
		{
			if ( class_exists('\\Imagick') )
			{
				$p = ( $val == '-' ) ? 0 : 1;
				if ( !$this->img->contrastImage($p) ){
					$this->error = defined('BBN_THERE_HAS_BEEN_A_PROBLEM') ? 
						BBN_THERE_HAS_BEEN_A_PROBLEM : 'There has been a problem';
				}
			}
			else if ( function_exists('imagefilter') )
			{
				$p = ( $val == '-' ) ? -20 : 20;
				if ( !imagefilter($this->img,IMG_FILTER_CONTRAST,-20) ){
					$this->error = defined('BBN_THERE_HAS_BEEN_A_PROBLEM') ? 
						BBN_THERE_HAS_BEEN_A_PROBLEM : 'There has been a problem';
				}
			}
		}
		return $this;
	}

	/**
   * Converts the color image to grayscale.
   * 
   * <code>
   * $img = new \bbn\file\image("C:\test\img.jpg");
   * $img->grayscale();
   * </code>
   * 
	 * @return \bbn\file\image
	 */
	public function grayscale()
	{
		if ( $this->test() )
		{
			if ( class_exists('\\Imagick') )
			{
				if ( !$this->img->modulateImage(100,0,100) ){
					$this->error = defined('BBN_THERE_HAS_BEEN_A_PROBLEM') ? 
						BBN_THERE_HAS_BEEN_A_PROBLEM : 'There has been a problem';
				}
			}
			else if ( function_exists('imagefilter') )
			{
				if ( !imagefilter($this->img,IMG_FILTER_GRAYSCALE) ){
					$this->error = defined('BBN_THERE_HAS_BEEN_A_PROBLEM') ? 
						BBN_THERE_HAS_BEEN_A_PROBLEM : 'There has been a problem';
				}
			}
		}
		return $this;
	}

	/**
   * Converts the color image to negative.
   * 
   * <code>
   * $img = new \bbn\file\image("C:\test\img.jpg");
   * $img->negate();
   * </code>
   *  
	 * @return \bbn\file\image
	 */
	public function negate()
	{
		if ( $this->test() )
		{
			if ( class_exists('\\Imagick') )
			{
				if ( !$this->img->negateImage(false) ){
					$this->error = defined('BBN_THERE_HAS_BEEN_A_PROBLEM') ? 
						BBN_THERE_HAS_BEEN_A_PROBLEM : 'There has been a problem';
				}
			}
			else if ( function_exists('imagefilter') )
			{
				if ( !imagefilter($this->img,IMG_FILTER_NEGATE) ){
					$this->error = defined('BBN_THERE_HAS_BEEN_A_PROBLEM') ? 
						BBN_THERE_HAS_BEEN_A_PROBLEM : 'There has been a problem';
				}
			}
		}
		return $this;
	}

	/**
   * Converts the color image to polaroid filter.
   * 
   * <code>
   * $img = new \bbn\file\image("C:\test\img.jpg");
   * $img->polaroid();
   * </code>
   * 
	 * @return \bbn\file\image
   * 
   * @todo Transparency of png files.
	 */
	public function polaroid()
	{
		if ( $this->test() )
		{
			if ( class_exists('\\Imagick') )
			{
				if ( !$this->img->polaroidImage(new \ImagickDraw(), 0) ){
					$this->error = defined('BBN_THERE_HAS_BEEN_A_PROBLEM') ? 
						BBN_THERE_HAS_BEEN_A_PROBLEM : 'There has been a problem';
				}
			}
		}
		return $this;
	}

	/**
   * Returns the image as string.
   * 
   * <code>
   * $img = new \bbn\file\image("C:\test\img.jpg");
   * $img->toString();
   * </code>
   * 
	 * @return string 
	 */
	public function toString()
	{
		if ( $this->test() )
		{
			if ( class_exists('\\Imagick') )
				$m = $this->img;
			else
			{
				ob_start();
				call_user_func('image'.$this->ext2,$this->img);
				$m = ob_get_contents();
				ob_end_clean();
			}
			return 'data:image/'.$this->ext.';base64,'.base64_encode($m);
		}
	}

}
?>