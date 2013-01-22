<?php
$r = $this->db->change("bbn")->query("SELECT bbn_forms.id, bbn_forms.bbn_tit_en, bbn_db_support.bbn_mysql FROM bbn_forms JOIN bbn_db_support ON bbn_forms.bbn_id_req = bbn_db_support.id ORDER BY bbn_forms.id");
$path = '/_www/dev/square4u.com/square/app/fields/';
$switch = '';
ob_start();
while ( $form = $r->get_object() ){
  var_dump($form);
  if ( is_dir($path.$form->id) && is_file($path.$form->id.'/form.php') ){
    $switch .= "case ".$form->id.":\n\t\n\tbreak;\n";
    echo highlight_string(file_get_contents($path.$form->id.'/form.php'));
  }
}
$b = ob_get_contents();
ob_end_clean();
echo '<pre>'.$switch.'</pre>';
echo $b;
?>