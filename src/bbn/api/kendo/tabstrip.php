<?php
/*
 * 
 */

namespace bbn\api\kendo;

class grid extends object
{

  protected static $default_config = [
      'fn' => '\\Kendo\\UI\\Grid',
      'datasource' => [
        'fn' => '\\Kendo\\Data\\DataSource',
        'transport' => [
          'fn' => '\\Kendo\\Data\\DataSourceTransport',
          'parameterMap' => 'function(data) {
                    return kendo.stringify(data);
                }'
        ],
        'batch' => true,
        'pageSize' => 20,
        'serverSorting' => true,
        'serverFiltering' => true,
        'serverPaging' => true,
        'schema' => [
          'fn' => '\\Kendo\\Data\\DataSourceSchema',
          'model' => [
            'fn' => '\\Kendo\\Data\\DataSourceSchemaModel',
            'addField' => [
              'fn' => '\\Kendo\\Data\\DataSourceSchemaModelField',
            ]
          ],
          'data' => 'data',
          'errors' => 'errors',
          'total' => 'total',
        ]

      ],
      'addColumn' => [
          'fn' => '\\Kendo\\UI\\GridColumn'
      ],
      'addToolbarItem' => [
          'fn' => '\\Kendo\\UI\\GridToolbarItem',
          'text' => 'Nouvelle entrée',
          'name' => 'create'
      ],
      'attr' => [
        'args' => ["class","appui-full-height"]
      ],
      'editable' => [
          'confirmation' => 'Etes vous sur de vouloir supprimer cette entrée?',
          'mode' => 'popup',
          //'template' => '<div style="width:1100px"></div>'
      ],
      'pageable' => true,
      'sortable' => true,
      'filterable' => true,
      'resizable' => true,
      'columnMenu' => true,
      'edit' => 'function(){
        $(".k-edit-form-container").parent().css({height:"auto",maxWidth:appui.v.width-200,maxHeight:appui.v.height-100,textAlign:"center"}).restyle().data("kendoWindow").title("Modification").center();
        appui.f.log(arguments);
       }'
    ];
  
  public function __construct(array $cfg){
    
    if ( isset($cfg['id'], $cfg['primary']) ){
      $this->id = $cfg['id'];
      $this->primary = $cfg['primary'];
      $this->cfg = self::$default_config;
      $this->cfg['args'] = [$this->id];
      $this->cfg['datasource']['schema']['model']['id'] = $this->primary;
      if ( isset($cfg['url']) ){
        $this->set_all($cfg['url']);
      }
      if ( isset($cfg['fields']) ){
        foreach ( $cfg['fields'] as $f ){
          $this->add_field($f);
        }
      }
      if ( isset($cfg['columns']) ){
        foreach ( $cfg['columns'] as $c ){
          $this->add_column($c);
        }
      }
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
  
  public function set_insert($url){
    $this->cfg['datasource']['transport']['create'] = [
        'fn' => '\\Kendo\Data\DataSourceTransportCreate',
        'url' => 'json/insert/'.$url,
        'contentType' => 'application/json',
        'type' => 'POST'
    ];
  }

  public function set_select($url){
    $this->cfg['datasource']['transport']['read'] = [
        'fn' => '\\Kendo\Data\DataSourceTransportRead',
        'url' => 'json/select/'.$url,
        'contentType' => 'application/json',
        'type' => 'POST'
    ];
  }

  public function set_update($url){
    $this->cfg['datasource']['transport']['update'] = [
        'fn' => '\\Kendo\Data\DataSourceTransportUpdate',
        'url' => 'json/update/'.$url,
        'contentType' => 'application/json',
        'type' => 'POST'
    ];
  }

  public function set_delete($url){
    $this->cfg['datasource']['transport']['destroy'] = [
        'fn' => '\\Kendo\Data\DataSourceTransportDestroy',
        'url' => 'json/delete/'.$url,
        'contentType' => 'application/json',
        'type' => 'POST'
    ];
  }
  
}
?>