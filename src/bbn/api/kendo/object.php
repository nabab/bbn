<?php
/*
 * 
 */

namespace bbn\api\kendo;
use bbn;

class object
{
  protected static $default_config = [];

  public 
          $cfg,
          $obj = false;
  
  protected function _execute(array $cfg){
    if ( isset($cfg['fn']) ){
      foreach ( $cfg as $i => $v ){
        if ( ($i !== 'fn') && ($i !== 'args') ){
          if ( \is_array($v) && isset($v['fn']) && !isset($v[0]) ){
            $cfg[$i] = $this->_execute($v);
          }
        }
      }

      $r = $this->_build($cfg);
      
      foreach ( $cfg as $i => $v ){
        if ( ($i !== 'fn') && ($i !== 'args') ){
          if ( \is_array($v) && isset($v['fn']) && isset($v[0]) ){
            $tmp = [];
            $a = 0;
            
            foreach ( $v as $k => $w ){
              if ( ($k !== 'fn') && ($k !== 'args') ){
                
                // Merging the parameters in case there's some args
                $tmp[$a] = $this->_build(array_merge($v, $w));
                
                if ( \is_array($w) ){
                  foreach ( $w as $j => $x ){
                    if ( ($j !== 'fn') && ($j !== 'args') ){
                      if ( \is_array($x) && isset($x['fn']) ){
                        $x = $this->_execute($x);
                      }
                      if ( \is_array($x) && isset($x[0]) ){
                        if ( $j === 'values' ){
                          $tmp[$a]->$j($x);
                        }
                        else{
                          foreach ( $x as $z ){
                            $tmp[$a]->$j($z);
                          }
                        }
                      }
                      else{
                        if ( !\is_string($j) ){
                          die(print_r($w,1).var_dump($a,$j));
                        }
                        $tmp[$a]->$j($x);
                      }
                    }
                  }
                }
                $r->$i($tmp[$a]);
                $a++;
              }
            }
          }
          else{
            if ( \is_array($v) && isset($v[0]) ){
              if ( ($i === 'values') || ($i === 'data') ){
                $r->$i($v);
              }
              else{
                foreach ( $v as $w ){
                  $r->$i($w);
                }
              }
            }
            else if ( \is_array($v) && isset($v['args']) ){
              \call_user_func_array([$r, $i], $v['args']);
            }
            else{
              $r->$i($v);
            }
          }
        }
      }
    }
    return $r;
  }
  
  protected function _build(array $cfg){
    if ( isset($cfg['fn']) ){
      if ( isset($cfg['args']) ){
        $rc = new \ReflectionClass($cfg['fn']);
        $r = $rc->newInstanceArgs($cfg['args']);
        unset($cfg['args']);
      }
      else{
        $r = new $cfg['fn']();
      }
    }
    return $r;
    
  }

  protected function __construct(array $cfg){
    
  }

  public function render()
  {
    if ( !$this->obj ){
      $this->obj = $this->_execute($this->cfg);
    }
    return $this->obj->render();
  }
}
?>