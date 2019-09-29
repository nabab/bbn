<?php
/**
 * @package file
 */
namespace bbn\file;
use bbn;
/**
 * Image Class
 *
 *
 * This class is used to upload, delete and transform images, and create thumbnails.
 *
 * @author Thomas Nabet <thomas.nabet@gmail.com>
 * @copyright BBN Solutions
 * @since Apr 4, 2011, 23:23:55 +0000
 * @category Files ressources
 * @license http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @version 0.2r89
 * @package bbn\file
 * @todo Deal specifically with SVG
 * @todo Add a static function and var to check for available libraries (Imagick/GD)
 */
class image extends bbn\file
{
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
	 * @var array
	 */
	protected static
		$allowed_extensions = ['jpg','gif','jpeg','png','svg'],
		$max_width = 5000;

  /**
   * Removes the alpha channel from an image (Imagick)
   *
   * @param \Imagick $img The Imagick object
   * @return \Imagick
   */
  private static function remove_alpha_imagick(\Imagick $img){
    if ( $img->getImageAlphaChannel() ){
      $img->setImageBackgroundColor('#FFFFFF');
      $img->setImageAlphaChannel(11);
      //$img->mergeImageLayers(\Imagick::LAYERMETHOD_FLATTEN);
      //$img->transformImageColorspace(\Imagick::COLORSPACE_RGB);
    }
    return $img;
  }

  /**
   * Converts one or more jpg image(s) to a pdf file. If the pdf file doesn't exist will be created.
   *
   * ```php
   * bbn\x::dump(bbn\file\image::jpg2pdf(["/home/data/test/two.jpg","/home/data/test/one.jpeg"], "/home/data/test/doc.pdf"));
   * // (string) "/home/data/test/doc.pdf"
   * ```
   *
   * @param array $jpg The path of jpg file(s) to convert
   * @param string $pdf The path of the pdf file
   * @return string|false
   */
  public static function jpg2pdf($jpg, $pdf){
    if ( class_exists('\\Imagick') ){
      if ( \is_array($jpg) ){
        $img = new \Imagick();
        $img->setResolution(200, 200);
        if ( \count($jpg) === 1 ){
          $img->readImage($jpg[0]);
        }
        else {
          $img->readImages($jpg);
        }
        $img->setImageFormat('pdf');
        if ( \count($jpg) === 1 ){
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
	 * ```php
	 * bbn\x::dump(bbn\file\image::pdf2jpg("/home/data/test/doc.pdf"));
	 * // (string)  "/home/data/test/doc.jpg"
	 * bbn\x::dump(bbn\file\image::pdf2jpg("/home/data/test/doc.pdf",'', all));
	 * // (array) ["/home/data/test/doc-0.jpg","/home/data/test/doc-1.jpg"]
	 * bbn\x::dump(bbn\file\image::pdf2jpg("/home/data/test/doc.pdf",'/home/data/test/Folder/image.jpg', all));
   * // (array) ["/home/data/test/Folder/image-0.jpg", "/home/data/test/Folder/image-1.jpg"],
	 * ```
   *
   * @param string $pdf The path of pdf file to convert
   * @param string $jpg The destination filename. If empty is used the same path of pdf. Default: empty.
   * @param int $num The index page of pdf file to convert. If set 'all' all pages to convert. Default: 0(first page).
   * @return string|array
   */
  public static function pdf2jpg($pdf, $jpg='', $num=0){
    if ( class_exists('\\Imagick') ){
      $img = new \Imagick();
      $img->setResolution(200, 200);
      $img->readImage($pdf);
      $img->setFormat('jpg');
      if ( empty($jpg) ){
        $dir = dirname($pdf);
        if ( !empty($dir) ){
          $dir .= '/';
        }
        $f = bbn\str::file_ext($pdf, 1);
        $jpg = $dir.$f[0].'.jpg';
      }
      if ( $num !== 'all' ){
        $img->setIteratorIndex($num);
        $img = self::remove_alpha_imagick($img);
        if ( $img->writeImage($jpg) ){
          return $jpg;
        }
      }
      else {
        $pages_number = $img->getNumberImages();
        $f = bbn\str::file_ext($jpg, 1);
        $dir = dirname($jpg);
        $r = [];
        if ( !empty($dir) ){
          $dir .= '/';
        }
        for ( $i = 0; $i < $pages_number; $i++ ){
          $img->setIteratorIndex($i);
          $img = self::remove_alpha_imagick($img);
          $filename = $dir.$f[0];
          if ( $pages_number > 1 ){
            $l = \strlen((string)$i);
            if ( $l < $pages_number ){
              $filename .= '-'.str_repeat('0', \strlen($pages_number) - $l).$i;
            }
          }
          $filename .= '.'.$f[1];
          if ( $img->writeImage($filename) ){
            array_push($r, $filename);
          }
        }
        if ( \count($r) === $pages_number ){
          return $r;
        }
      }
    }
    return false;
  }

  /**
	 * Construct
	 * @return void
	 */
	public function __construct($file, system $fs = null)
	{
		parent::__construct($file, $fs);
		if ( !\in_array($this->get_extension(),bbn\file\image::$allowed_extensions) )
		{
			$this->name = false;
			$this->path = false;
			$this->file = false;
			$this->size = false;
			$this->title = false;
		}
	}

	/**
   * Returns the extension of the image. If the file has jpg extension will return 'jpeg'.
   *
	 * ```php
   * $img = new bbn\file\image("/home/data/test/image.jpg");
   * bbn\x::dump($img->get_extension());
	 * // (string) "jpeg"
   * ```
   *
	 * @return string
	 */
	public function get_extension(){
		parent::get_extension();
		if ( !$this->ext2 && $this->file ){
			if ( function_exists('exif_imagetype') ){
				if ( $r = exif_imagetype($this->file) ){
          if ( !array_key_exists($r, bbn\file\image::$allowed_extensions) ){
            $this->ext = false;
          }
				}
				else{
          $this->ext = false;
        }
			}
			if ( $this->ext ){
				$this->ext2 = $this->ext;
				if ( $this->ext2 === 'jpg' ){
          $this->ext2 = 'jpeg';
        }
			}
		}
		return $this->ext;
	}

	/**
   * Tests if the object is a image.
   *
   * ```php
   * $img = new bbn\file\image("/home/data/test/image.jpg");
   * bbn\x::dump($img->test());
	 * // (bool) true
	 * $img = new bbn\file\image("/home/data/test/file.doc");
   * bbn\x::dump($img->test());
	 * // (bool) false
   * ```
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
	 * 
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
            switch ( $this->get_extension() ) {
              case 'gif':
                $this->img->setInterlaceScheme(Imagick::INTERLACE_GIF);
                break;

              case 'jpeg':
              case 'jpg':
                $this->img->setInterlaceScheme(\Imagick::INTERLACE_JPEG);
                break;

              case 'png':
                $this->img->setInterlaceScheme(\Imagick::INTERLACE_PNG);
                break;

              default:
                $this->img->setInterlaceScheme(\Imagick::INTERLACE_UNDEFINED);
            }
            $this->w = $this->img->getImageWidth();
            $this->h = $this->img->getImageHeight();
          }
          catch ( \Exception $e ){
            $this->img = false;
            $this->error = \defined('BBN_THERE_HAS_BEEN_A_PROBLEM') ?
              BBN_THERE_HAS_BEEN_A_PROBLEM : 'There has been a problem';
          }
        }
        else if ( function_exists('imagecreatefrom'.$this->ext2) ){
          if ( $this->img = \call_user_func('imagecreatefrom'.$this->ext2,$this->file) ){
            imageinterlace($this->img, true);
            $this->w = imagesx($this->img);
            $this->h = imagesy($this->img);
            if ( imagealphablending($this->img,true) ){
              imagesavealpha($this->img,true);
            }
          }
          else{
            $this->error = \defined('BBN_THERE_HAS_BEEN_A_PROBLEM') ?
              BBN_THERE_HAS_BEEN_A_PROBLEM : 'There has been a problem';
          }
        }
        else{
          $this->error = \defined('BBN_THERE_HAS_BEEN_A_PROBLEM') ?
            BBN_THERE_HAS_BEEN_A_PROBLEM : 'There has been a problem';
        }
      }
    }
    else{
      $this->error = \defined('BBN_THERE_HAS_BEEN_A_PROBLEM') ?
        BBN_THERE_HAS_BEEN_A_PROBLEM : 'There has been a problem';
    }
    return $this;
  }

	/**
   * Sends the image with Content-Type.
	 *
	 * ```php
   * $img = new bbn\file\image("/home/data/test/image.jpg");
   * $img->display();
	 * ```
	 *
	 * @return image
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
				\call_user_func('image'.$this->ext2, $this->img);
				imagedestroy($this->img);
			}
		}
		return $this;
	}

	/**
	 * Save the image in a new destination if given or overwrite the file (default).
	 *
	 * ```php
	 * $new_file="/home/data/test/Folder_test/image_test.jpeg";
	 * $img2=new bbn\file\image($new_file);
	 * bbn\x::dump($img2->test());
	 * // (bool) false
	 * bbn\x::dump($img->save($new_file));
	 * bbn\x::dump($img2->test());
	 * // (bool) true
	 * ```
	 *
	 * @param string $dest The destination of the file to save. Default = false, the file will overwrited.
	 * @return image
	 */
	public function save($dest=false)
	{
		if ( $this->test() ){
			if ( !$dest ){
				$dest = $this->file;
			}
			if ( class_exists('\\Imagick') ){
        try{
					$this->img->writeImage($dest);
				}
				catch ( \Exception $e ){
					die(var_dump($dest, $this->file));
          $this->error = \defined('BBN_THERE_HAS_BEEN_A_PROBLEM') ?
            BBN_THERE_HAS_BEEN_A_PROBLEM : 'There has been a problem';
        }
      }
			else if ( function_exists('image'.$this->ext2) ){
        if ( !\call_user_func('image'.$this->ext2, $this->img, $dest) ){
          $this->error = \defined('BBN_THERE_HAS_BEEN_A_PROBLEM') ?
            BBN_THERE_HAS_BEEN_A_PROBLEM : 'There has been a problem';
        }
			}
		}
		return $this;
	}

	/**
   * If the file is an image will return its width in pixel.
   *
	 * ```php
	 * $img = new bbn\file\image("/home/data/test/image.jpg");
	 * bbn\x::dump($img->get_width());
	 * // (int) 265
   * ```
   *
	 * @return int | false
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
   * If the file is an image will return its height in pixel.
   *
	 * ```php
	 * $img = new bbn\file\image("/home/data/test/image.jpg");
	 * bbn\x::dump($img->get_height());
	 * // (int) 190
   * ```
   *
	 * @return int|false
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
   * Resize the width and the height of the image. If is given only width or height the other dimension will be set on auto.
	 *
	 * @todo $max_h and $max_w doesn't work.
	 *
   * ```php
	 * $img = new bbn\file\image("/home/data/test/image.jpg");
	 * bbn\x::hdump($img->get_width(),$img->get_height());
	 * // (int) 345  146
	 * bbn\x::hdump($img->resize(200,"" ));
	 * bbn\x::hdump($img->get_width(),$img->get_height());
	 * // (int) 200  84
	 * bbn\x::hdump($img->get_width(),$img->get_height());
	 * // (int) 345  146
	 * bbn\x::dump($img->resize(205, 100, 1));
   * bbn\x::dump($img->get_width(),$img->get_height());
   * // (int) 205  100
   * ```
   *
   * @param int|bool $w The new width.
   * @param int|bool $h The new height.
   * @param boolean $crop If cropping the image. Default = false.
   * @param int|bool $max_w The maximum value for new width.
   * @param int|bool $max_h The maximum valure for new height.
   * @return image
	 */
	public function resize($w=false, $h=false, $crop=false, $max_w=false, $max_h=false)
	{
		$max_w = false;
		$max_h = false;
		if ( \is_array($w) ){
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
						$this->error = \defined('BBN_THERE_HAS_BEEN_A_PROBLEM') ?
							BBN_THERE_HAS_BEEN_A_PROBLEM : 'There has been a problem';
					}
				}
				else{
					$this->error = \defined('BBN_THERE_HAS_BEEN_A_PROBLEM') ?
						BBN_THERE_HAS_BEEN_A_PROBLEM : 'There has been a problem';
				}
			}
		}
		return $this;
	}

	/**
   * Resize the image with constant values, if the width is not given it will be set to auto.
	 * @todo BBN_MAX_WIDTH and BBN_MAX_HEIGHT ?
	 *
	 * ```php
	 * $img = new bbn\file\image("/home/data/test/image.jpg");
	 * bbn\x::dump($img->get_width(),$img->get_height());
	 * // (int) 345  146
	 * $img->autoresize("", 100);
	 * bbn\x::dump($img->get_width(),$img->get_height());
	 * // (int) 236  100
	 * ```
	 *
	 * @param integer $w default  BBN_MAX_WIDTH
   * @param integer $h default BBN_MAX_HEIGHT
   * @return image
	 */
	public function autoresize($w=BBN_MAX_WIDTH, $h=BBN_MAX_HEIGHT)
	{
		if ( !$w ){
			$w = \defined('BBN_MAX_WIDTH') ? BBN_MAX_WIDTH : self::$max_width;
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
			$this->error = \defined('BBN_ARGUMENTS_MUST_BE_NUMERIC') ?
				BBN_ARGUMENTS_MUST_BE_NUMERIC : 'Arguments must be numeric';
		}
		return $this;
	}

	/**
   * Returns a crop of the image.
   *
   * ```php
	 * $img = new bbn\file\image("/home/data/test/image.jpg");
	 * bbn\x::dump($img->get_width(),$img->get_height());
	 * // (int) 345  146
	 * $img->crop(10, 10, 30, 30)->save("/home/data/test/img2.jpeg");
	 * $img2 = new \bbn\file\image("/home/data/test/img2.jpeg");
	 * bbn\x::hdump($img2->get_width(),$img2->get_height());
   * // (int) 10  10
	 * ```
   *
   * @param integer $w the new width
   * @param integer $h the new height
   * @param integer $x X coordinate
   * @param integer $y Y coordinate
   * @return image|false
	 */
	public function crop($w, $h, $x, $y)
	{
		if ( $this->test() ){
			$args = \func_get_args();
			foreach ( $args as $arg ){
				if ( !is_numeric($arg) ){
					$this->error = \defined('BBN_ARGUMENTS_MUST_BE_NUMERIC') ?
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
					$this->error = \defined('BBN_THERE_HAS_BEEN_A_PROBLEM') ?
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
					$this->error = \defined('BBN_THERE_HAS_BEEN_A_PROBLEM') ?
						BBN_THERE_HAS_BEEN_A_PROBLEM : 'There has been a problem';
				}
			}
		}
		return $this;
	}

	/**
   * Rotates the image.
   *_
   * ```php
   * $img = new bbn\file\image("/home/data/test/image.jpg");
   * $img->rotate( 90 )->save();
   * ```
   *
   * @param integer $angle The angle of rotation.
   * @return image
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
				$this->error = \defined('BBN_THERE_HAS_BEEN_A_PROBLEM') ?
					BBN_THERE_HAS_BEEN_A_PROBLEM : 'There has been a problem';
			}
		}
		return $this;
	}

	/**
   * Flips the image.
   *
   * ```php
   * $img = new bbn\file\image("/home/data/test/image.jpg");
   * $img->flip()->save();
   * $img->flip("h")->save();
	 * $img->flip()->save();
   * ```
	 *
   * @param string $mode Vertical ("v") or Horizontal ("h") flip, default: "v".
   * @return image
	 */
  public function flip($mode='v'){
		if ( $this->test() )
		{
			if ( class_exists('\\Imagick') )
			{
				if ( $mode == 'v' )
				{
					if ( !$this->img->flipImage() ){
						$this->error = \defined('BBN_THERE_HAS_BEEN_A_PROBLEM') ?
							BBN_THERE_HAS_BEEN_A_PROBLEM : 'There has been a problem';
					}
				}
				else if ( !$this->img->flopImage() ){
					$this->error = \defined('BBN_THERE_HAS_BEEN_A_PROBLEM') ?
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
   * Compresses and sets the image's quality (JPEG image only).
   *
	 * ```php
   * $img = new bbn\file\image("/home/data/test/image.jpg");
   * $img->quality(60, 6)->save();
   * ```
	 *
   * @param int $q The quality level (0-100)
   * @param int $comp The compression type
   * @return image
   */
  public function quality(int $q = 80, int $comp = 8){
    if ( $this->test() &&
      ((strtolower($this->get_extension()) === 'jpg') ||
        (strtolower($this->get_extension()) === 'jpeg'))
    ){
      if ( class_exists('\\Imagick') ){
        $this->img->setImageCompression($comp);
        $this->img->setImageCompressionQuality($q);
        $this->img->stripImage();
      }
    }
    return $this;
  }

  /**
   * Adjusts the image's brightness.
   *
	 * ```php
   * $img = new bbn\file\image("/home/data/test/image.jpg");
   * $img->brightness();
   * $img->brightness("-");
   * ```
   *
   * @param string $val The value "+" (default) increases the brightness, the value ("-") reduces it.
   * @return image
	 */
	public function brightness($val='+')
	{
		if ( $this->test() )
		{
			if ( class_exists('\\Imagick') )
			{
				$p = ( $val == '-' ) ? 90 : 110;
				if ( !$this->img->modulateImage($p,100,100) ){
					$this->error = \defined('BBN_THERE_HAS_BEEN_A_PROBLEM') ?
						BBN_THERE_HAS_BEEN_A_PROBLEM : 'There has been a problem';
				}
			}
			else if ( function_exists('imagefilter') )
			{
				$p = ( $val == '-' ) ? -20 : 20;
				if ( !imagefilter($this->img,IMG_FILTER_BRIGHTNESS,-20) ){
					$this->error = \defined('BBN_THERE_HAS_BEEN_A_PROBLEM') ?
						BBN_THERE_HAS_BEEN_A_PROBLEM : 'There has been a problem';
				}
			}
		}
		return $this;
	}

	/**
   * Adjusts the image contrast.
   *
   * ```php
   * $img = new bbn\file\image("/home/data/test/image.jpg");
   * $img->contrast("-");
   * $img->contrast();
   * ```
   *
   * @param string $val The value "+" (default), increases the contrast, the value ("-") reduces it.
   * @return image
	 */
	public function contrast($val='+')
	{
		if ( $this->test() )
		{
			if ( class_exists('\\Imagick') )
			{
				$p = ( $val == '-' ) ? 0 : 1;
				if ( !$this->img->contrastImage($p) ){
					$this->error = \defined('BBN_THERE_HAS_BEEN_A_PROBLEM') ?
						BBN_THERE_HAS_BEEN_A_PROBLEM : 'There has been a problem';
				}
			}
			else if ( function_exists('imagefilter') )
			{
				$p = ( $val == '-' ) ? -20 : 20;
				if ( !imagefilter($this->img,IMG_FILTER_CONTRAST,-20) ){
					$this->error = \defined('BBN_THERE_HAS_BEEN_A_PROBLEM') ?
						BBN_THERE_HAS_BEEN_A_PROBLEM : 'There has been a problem';
				}
			}
		}
		return $this;
	}

	/**
   * Converts the image's color to grayscale.
   *
   * ```php
   * $img = new bbn\file\image("/home/data/test/image.jpg");
   * $img->grayscale()->save();
   * ```
   *
	 * @return image
	 */
	public function grayscale()
	{
		if ( $this->test() )
		{
			if ( class_exists('\\Imagick') )
			{
				if ( !$this->img->modulateImage(100,0,100) ){
					$this->error = \defined('BBN_THERE_HAS_BEEN_A_PROBLEM') ?
						BBN_THERE_HAS_BEEN_A_PROBLEM : 'There has been a problem';
				}
			}
			else if ( function_exists('imagefilter') )
			{
				if ( !imagefilter($this->img,IMG_FILTER_GRAYSCALE) ){
					$this->error = \defined('BBN_THERE_HAS_BEEN_A_PROBLEM') ?
						BBN_THERE_HAS_BEEN_A_PROBLEM : 'There has been a problem';
				}
			}
		}
		return $this;
	}

	/**
   * Converts the image's color to negative.
   *
   * ```php
	 * $img = new bbn\file\image("/home/data/test/image.jpg");
   * $img->negate();
   * ```
   *
	 * @return image
	 */
	public function negate()
	{
		if ( $this->test() )
		{
			if ( class_exists('\\Imagick') )
			{
				if ( !$this->img->negateImage(false) ){
					$this->error = \defined('BBN_THERE_HAS_BEEN_A_PROBLEM') ?
						BBN_THERE_HAS_BEEN_A_PROBLEM : 'There has been a problem';
				}
			}
			else if ( function_exists('imagefilter') )
			{
				if ( !imagefilter($this->img,IMG_FILTER_NEGATE) ){
					$this->error = \defined('BBN_THERE_HAS_BEEN_A_PROBLEM') ?
						BBN_THERE_HAS_BEEN_A_PROBLEM : 'There has been a problem';
				}
			}
		}
		return $this;
	}

	/**
   * Converts the image's color with polaroid filter.
   *
   * ```php
   * $img = new bbn\file\image("/home/data/test/image.jpg");
   * $img->polaroid()->save();
   * ```
   *
	 * @return image
   * @todo Transparency of png files.
	 */
	public function polaroid()
	{
		if ( $this->test() ){
			if ( class_exists('\\Imagick') ){
				if ( !$this->img->polaroidImage(new \ImagickDraw(), 0) ){
					$this->error = \defined('BBN_THERE_HAS_BEEN_A_PROBLEM') ?
						BBN_THERE_HAS_BEEN_A_PROBLEM : 'There has been a problem';
				}
			}
		}
		return $this;
	}

	/**
   * Creates miniature of the image
   *
   * ```php
   * $img = new bbn\file\image("/home/data/test/image.jpg");
   * $img->thumbs()->save(/home/data/test/image_test.jpg");
   * ```
   *
	 * @return image|false
   */
	public function thumbs($dest = '.', $sizes = [[false, 960], [false, 480], [false, 192], [false, 96], [false, 48]], $mask = '_%s', $crop = false, $bigger = false){
		if ( $this->test() && is_dir($dest) ){
      $this->get_extension();
			$w = $this->get_width();
			$h = $this->get_height();
			$d = $w >= $h ? 'w' : 'h';
      $res = [];
      if ( bbn\str::is_integer($sizes) ){
        $sizes = [[$sizes, false]];
      }
			if ( $$d / ($d === 'w' ? $h : $w) < 5 ){
				$mask = ($dest === '.' ? '' : $dest.'/').$this->title.$mask.'.'.$this->ext;
        //die(var_dump($mask));
				foreach ( $sizes as $s ){
          if ( bbn\str::is_integer($s) ){
            $s = [$s, false];
          }
          if (
            (!empty($s[0]) && ($w > $s[0])) ||
            (!empty($s[1]) && ($h > $s[1])) ||
            $bigger
          ){
            $smask = (empty($s[0]) ? '' : 'w'.$s[0]).(empty($s[1]) ? '' : 'h'.$s[1]);
            $fn = sprintf($mask, $smask);
            if ( $s[0] && $s[1] ){
              if ( $crop ){
                $this->resize($s[0], $s[1], true);
              }
              else{
                $this->resize($d === 'w' ? $s[0] : false, $d === 'h' ? $s[1] : false, false, $s[0], $s[1]);
              }
            }
            else{
              $this->resize($s[0], $s[1], $crop);
            }
            $this->save($fn);
            $res[$smask] = $fn;
          }
				}
				return $res;
			}
		}
    return false;
	}

	/**
   * Return the image as string.
   *
	 * ```php
	 * $img = new bbn\file\image("/home/data/test/image.jpg");
   * bbn\x::hdump($img->toString());
   * // (string)
	 * ```
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
				\call_user_func('image'.$this->ext2,$this->img);
				$m = ob_get_contents();
				ob_end_clean();
			}
			return 'data:image/'.$this->ext.';base64,'.base64_encode($m);
		}
	}

}
