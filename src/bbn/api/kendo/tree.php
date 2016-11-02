<?php
/*
 * 
 */
namespace bbn\api\kendo;
use bbn;

class tree // extends object
{
  
  public
          /** @var \Kendo\UI\Tree Tree Object */
          $tree,
          
          /** @var \Kendo\Data\DataSource DataSource Object */
          $dataSource,
          
          /** @var \Kendo\Data\DataSourceSchema Schema Object */
          $schema,
          
          /** @var \Kendo\Data\DataSourceSchemaModel Schema Model Object */
          $model,
          
          /** @var \Kendo\Data\DataSourceTransport DataSource Transport Object */
          $transport;
          
          
    
  public function __construct(array $cfg){
    
    if ( isset($cfg['id'], $cfg['primary']) ){

      $this->id = $cfg['id'];
      $this->primary = $cfg['primary'];


      $this->grid = new \kendo\UI\Grid($this->id);

      $this->dataSource = new \Kendo\Data\DataSource();

      $this->schema = new \Kendo\Data\DataSourceSchema();

      $this->model = new \Kendo\Data\DataSourceSchemaModel();
      $this->model->id($cfg['primary']);
      
      foreach ( $cfg['elements'] as $e ){
        $tmp = new \Kendo\Data\DataSourceSchemaModelField($e['attr']['name']);
        $this->model->addField($tmp);

        $tmp = new \Kendo\UI\GridColumn();
        $this->grid->addColumn($tmp);
      }

      
      if ( isset($cfg['url']) ){

        $this->transport = new \Kendo\Data\DataSourceTransport();

        $this->transport->parameterMap(new \Kendo\JavaScriptFunction('function(data) {
                          return kendo.stringify(data);
                      }'));

        $this->dataSource
                ->batch(true)
                ->serverSorting(true)
                ->serverFiltering(true)
                ->serverPaging(true);

        if ( isset($cfg['all']) ){
          $this->set_all($cfg['url']);
        }
        else{
          if ( isset($cfg['select']) ){
            $this->set_select($cfg['select'] === 1 ? $cfg['url'] : $cfg['select']);
          }
          if ( isset($cfg['insert']) ){
            $this->set_insert($cfg['insert'] === 1 ? $cfg['url'] : $cfg['insert']);
          }
          if ( isset($cfg['update']) ){
            $this->set_update($cfg['update'] === 1 ? $cfg['url'] : $cfg['update']);
          }
          if ( isset($cfg['delete']) ){
            $this->set_delete($cfg['delete'] === 1 ? $cfg['url'] : $cfg['delete']);
          }
        }
        $this->dataSource->transport($this->transport);
      }
      
      if ( isset($cfg['data']) ){
        $this->dataSource->data($cfg['data']);
      }
      
      $this->schema->model($this->model);
      
      $this->dataSource
              ->schema($this->schema)
              ->pageSize(20);

      $this->grid
              ->attr(["class","appui-full-height"])
              ->datasource($this->dataSource)
              ->schema($this->schema)
              ->editable(['confirmation' => i18n\fr::$editable_confirm])
              ->filterable([
                  'messages' => i18n\fr::$filterable_msgs,
                  'operators' => i18n\fr::$filterable_operators
              ])
              ->resizable(true)
              ->pageable(['messages' => i18n\fr::$pageable_msgs])
              ->columnMenu(['messages' => i18n\fr::$colmenu_msgs])
              ->edit(new \Kendo\JavaScriptFunction('function(){
                $(".k-edit-form-container").parent().css({
                  height:"auto",
                  width:720,
                  "max-height":appui.env.height-100
                }).restyle().data("kendoWindow").title("Formulaire de saisie").center();
               }'));

      
      $this->cfg['args'] = [$this->id];
      $this->cfg['datasource']['schema']['model']['id'] = $this->primary;

    }
  }
  
  public function add_field($f){
    array_push($this->cfg['datasource']['schema']['model']['addField'], $f);
  }
  
  public function add_column($c){
    array_push($this->cfg['addColumn'], $c);
  }

  public function set_all($url){
    $this->set_insert($url);
    $this->set_select($url);
    $this->set_update($url);
    $this->set_delete($url);
  }
  
  public function set_data($data){
    $this->cfg['datasource'] = [
        'fn' => '\\Kendo\\Data\\DataSource',
        'data' => $data,
        'pageSize' => 20,
    ];
    $this->cfg['groupable'] = [
        'messages' => [
            'empty' => 'Faites glisser l\'entête d\'une colonne ici pour grouper les résultats sur cette colonne'
        ]
    ];
  }
  
  public function set_detail(bbn\api\kendo\grid $grid){
    $g = new grid($grid);
    $this->grid->detailInit($g->grid);
  }
  
  public function set_insert($url, $with_button=1){
    $dst = new \Kendo\Data\DataSourceTransportCreate();
    $dst    ->url('insert/'.$url)
            ->contentType('application/json')  
            ->type('POST');
    $this->grid->transport->create($dst);

    if ( $with_button ){
      $this->add_table_button(['text' => 'Nouvelle entrée','name' => 'create']);
    }
  }

  public function set_select($url){
    $dst = new \Kendo\Data\DataSourceTransportRead();
    $dst    ->url('select/'.$url)
            ->contentType('application/json')  
            ->type('POST');
    $this->grid->transport->read($dst);
  }

  public function set_update($url){
    $dst = new \Kendo\Data\DataSourceTransportUpdate();
    $dst    ->url('update/'.$url)
            ->contentType('application/json')  
            ->type('POST');
    $this->grid->transport->update($dst);
  }

  public function set_delete($url){
    $dst = new \Kendo\Data\DataSourceTransportDestroy();
    $dst    ->url('delete/'.$url)
            ->contentType('application/json')  
            ->type('POST');
    $this->grid->transport->destroy($dst);
  }
  
  public function add_table_button(array $button){
    $tb = new \Kendo\UI\GridToolbarItem($button);
    $this->grid->addToolbarItem($tb);
  }
  
}
?>