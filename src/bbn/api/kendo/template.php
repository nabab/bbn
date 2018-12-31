<?php
/*
 * 
 */
namespace bbn\api\kendo;
use bbn;
class template // extends object
{
  private
    $ctrl,
    $prepath;

  public function __construct(bbn\mvc\controller $ctrl, $prepath = ''){
    $this->ctrl = $ctrl;
    $this->prepath = $prepath;
  }

  public function get($id){
    if ( $html = $this->ctrl->get_view($this->prepath."kendo/".$id) ){
      return '<script id="tpl-'.$id.'" type="text/x-kendo-template">'.$html.'</script>';
    }
  }
}
