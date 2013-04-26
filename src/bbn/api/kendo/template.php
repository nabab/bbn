<?php
/*
 * 
 */
namespace bbn\api\kendo;

class template // extends object
{
  private $mvc;
  
  public function __construct(\bbn\mvc $mvc) {
    $this->mvc = $mvc;
  }
  
  public function get($id){
    if ( $html = $this->mvc->get_view("kendo/".$id, "html") ){
      return '<script id="tpl-'.$id.'" type="text/x-kendo-template">'.$html.'</script>';
    }
  }
}
?>
