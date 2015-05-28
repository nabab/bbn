<?php
/*
 * 
 */
namespace bbn\api\kendo;
class template // extends object
{
  private $ctrl;

  public function __construct(\bbn\mvc\controller $ctrl) {
    $this->ctrl = $ctrl;
  }

  public function get($id){
    if ( $html = $this->ctrl->get_view("kendo/".$id, "html") ){
      return '<script id="tpl-'.$id.'" type="text/x-kendo-template">'.$html.'</script>';
    }
  }
}
