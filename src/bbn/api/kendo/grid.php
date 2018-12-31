<?php
/*
 * 
 */
namespace bbn\api\kendo;
use bbn;

class grid // extends object
{
  
  private
          $_insert = false,
          $_update = false,
          $_delete = false,
          $_action_col = false;
  
  public
          /** @var \Kendo\UI\Grid Grid Object */
          $grid,
          
          /** @var \Kendo\Data\DataSource DataSource Object */
          $dataSource,
          
          /** @var \Kendo\Data\DataSourceSchema Schema Object */
          $schema,
          
          /** @var \Kendo\Data\DataSourceSchemaModel Schema Model Object */
          $model,
          
          /** @var \Kendo\Data\DataSourceTransport DataSource Transport Object */
          $transport,
          $builder;
          
          
    
  public function __construct(array $cfg){
    
    if ( isset($cfg['primary']) ){

      $this->id = isset($cfg['id']) ? $cfg['id'] : bbn\str::genpwd();
      $this->primary = $cfg['primary'];
      if ( !isset($cfg['builder']) ){
        $cfg['builder'] = new bbn\html\builder();
      }
      $this->builder = $cfg['builder'];

      $this->grid = new \Kendo\UI\Grid($this->id);

      $this->dataSource = new \Kendo\Data\DataSource();
      if ( isset($cfg['data']) ){
        $this->dataSource->data($cfg['data']);
      }

      $this->schema = new \Kendo\Data\DataSourceSchema();
      $this->schema->data('data');
      $this->schema->total('total');

      $this->model = new \Kendo\Data\DataSourceSchemaModel();
      $this->model->id($cfg['primary']);
      
      
      foreach ( $cfg['elements'] as $e ){
        
        if ( isset($e['attr']['name']) ){
          $field = new \Kendo\Data\DataSourceSchemaModelField($e['attr']['name']);
          if ( isset($e['attr']['name']) && isset($e['editable']) && $e['editable'] ){
            if ( isset($e['type']) ){
              $field->type($e['type']);
            }
            if ( isset($e['null']) && $e['null'] ){
              $field->nullable(true);
            }
            if ( isset($e['attr']['readonly']) && $e['attr']['readonly'] ){
              $field->editable(false);
            }
            else{
              if ( isset($e['validation']) ){
                $field->validation($e['validation']);
              }
            }
            $this->model->addField($field);
          }
          if ( empty($e['editable']) ){
            $field->editable(false);
          }
          if ( !empty($e['default']) ){
            $field->defaultValue($e['default']);
          }
        }
        $col = new \Kendo\UI\GridColumn();
        if ( !isset($e['field']) || $e['field'] !== 'hidden' ){
          if ( isset($e['editable']) && $e['editable'] ){
            /*
            if ( !isset($e['editor']) ){
              $input = $this->builder->input($e, 1);
              $sc = $input->ele_and_script();
              $e['editor'] = new \Kendo\JavaScriptFunction('function(container, options){
                '.$sc[0].'.appendTo(container)'.$sc[1].'
              }');
            }
            $col->editor($e['editor']);
             * 
             */
          }
          if ( isset($e['raw']) ){
            $col->encoded(false);
          }
          if ( isset($e['data']) ){
            $col->values($e['data']);
          }
          if ( isset($e['label']) ){
            $col->title($e['label']);
          }
          if ( isset($e['attr']['name']) ){
            $col->field($e['attr']['name']);
          }
          if ( isset($e['width']) ){
            $col->width((int)$e['width']);
          }
          if ( isset($e['format']) ){
            $col->format('{0:'.$e['format'].'}');
          }
          if ( isset($e['hidden']) ){
            $col->hidden(true);
          }
          if ( isset($e['template']) ){
            $col->template($e['template']);
          }
          if ( isset($e['editor']) ){
            $col->editor($e['editor']);
          }
          if ( isset($e['encoded']) ){
            $col->encoded($e['encoded']);
          }
          if ( isset($e['commands']) ){
            foreach ( $e['commands'] as $c ){
              if ( isset($c['click']) ){
                $c['click'] = new \Kendo\JavaScriptFunction($c['click']);
              }
              $col->addCommandItem($c);
            }
          }
          if ( \count(bbn\x::to_array($col)) > 0 ){
            $this->grid->addColumn($col);
          }
        }
      }

      
      if ( isset($cfg['url']) ){

        $this->transport = new \Kendo\Data\DataSourceTransport();

        if ( isset($cfg['all']) ){
          $this->set_all($cfg['url']);
        }
        else{
          if ( isset($cfg['select']) ){
            $this->set_select(
                    ($cfg['select'] === 1) || ($cfg['select'] === 'on') ?
                        'select/'.$cfg['url'] : $cfg['select']);
          }
          if ( isset($cfg['insert']) ){
            $this->set_insert(
                    ($cfg['insert'] === 1) || ($cfg['insert'] === 'on') ?
                        'insert/'.$cfg['url'] : $cfg['insert']);
          }
          if ( isset($cfg['update']) ){
            $this->set_update(
                    ($cfg['update'] === 1) || ($cfg['update'] === 'on') ?
                        'update/'.$cfg['url'] : $cfg['update']);
          }
          if ( isset($cfg['delete']) ){
            $this->set_delete(
                    ($cfg['delete'] === 1) || ($cfg['delete'] === 'on') ?
                        'delete/'.$cfg['url'] : $cfg['delete']);
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
              ->pageSize(50);

      $this->grid
              ->attr("class","bbn-full-height")
              ->datasource($this->dataSource)
              ->editable([
                  'mode' => 'popup',
              ])
              ->filterable(true)
              ->resizable(true)
              ->sortable(true)
              ->groupable(true)
              ->pageable(true)
              ->columnMenu(true)
              ->edit(new \Kendo\JavaScriptFunction('function(){
                $(".k-edit-form-container").parent().css({
                  height:"auto",
                  width:720,
                  "max-height":bbn.env.height-100
                }).data("kendoWindow").title("'.bbn\str::escape_dquotes($cfg['description']).'").center();
               }'));

      
      $this->cfg['args'] = [$this->id];
      $this->cfg['datasource']['schema']['model']['id'] = $this->primary;

    }
  }
  
  public function render()
  {
    return $this->grid->render();
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
  
  public function set_detail(bbn\api\kendo\grid $grid){
    $g = new grid($grid);
    $this->grid->detailInit($g->grid);
  }
  
  public function set_insert($url, $with_button=1){
    if ( isset($this->transport) ){
      $this->_insert = 1;
      $dst = new \Kendo\Data\DataSourceTransportCreate();
      $dst    ->url($url)
              ->type('POST');
      $this->transport->create($dst);

      if ( $with_button ){
        $this->grid->addToolbarItem([
            'name' => 'create',
            'text' => i18n\fr::$new_entry
        ]);
      }
    }
  }

  public function set_select($url){
    if ( isset($this->transport) ){
      $this->dataSource
              ->batch(true)
              ->serverSorting(true)
              ->serverFiltering(true)
              ->serverPaging(true);
      $dst = new \Kendo\Data\DataSourceTransportRead();
      $dst    ->url($url)
              ->type('POST');
      $this->transport->read($dst);
    }
  }

  public function set_update($url){
    if ( isset($this->transport) ){
      $this->_update = 1;
      $dst = new \Kendo\Data\DataSourceTransportUpdate();
      $dst    ->url($url)
              ->type('POST');
      $this->transport->update($dst);
    }
  }

  public function set_delete($url){
    if ( isset($this->transport) ){
      $this->_delete = 1;
      $dst = new \Kendo\Data\DataSourceTransportDestroy();
      $dst    ->url($url)
              ->type('POST');
      $this->transport->destroy($dst);
    }
  }
  
}
?>