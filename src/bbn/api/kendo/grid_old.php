<?php
/*
 * 
 */

namespace bbn\api\kendo;

class grid_old extends object
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
      'attr' => [
        'args' => ["class","appui-full-height"]
      ],
      'editable' => [
          'confirmation' => 'Etes vous sur de vouloir supprimer cette entrée?',
          'mode' => 'popup',
          //'template' => '<div style="width:1100px"></div>'
      ],
      'pageable' => [
          'messages' => [
              'display' => "{0} - {1} de {2} éléments",
              'empty' => "Aucun élément à afficher",
              'page' => "Page",
              'of' => "de {0}",
              'itemsPerPage' => "éléments par page",
              'first' => "Aller à la première page",
              'previous' => "Aller à la page précédente",
              'next' => "Aller à la page suivante",
              'last' => "Aller à la denière page",
              'refresh' => "Recharger la liste"
          ]
      ],
      'sortable' => true,
      'filterable' => [
          'messages'  => [
              'info' => "Voir les éléments correspondant aux critères suivants:", // sets the text on top of the filter menu
              'filter' => "Filtrer", // sets the text for the "Filter" button
              'clear' => "Enlever les filtres", // sets the text for the "Clear" button

              // when filtering boolean numbers
              'isTrue' => "est vrai", // sets the text for "isTrue" radio button
              'isFalse' => "est faux", // sets the text for "isFalse" radio button

              //changes the text of the "And" and "Or" of the filter menu
              'and' => "et",
              'or' => "ou bien",
              'selectValue' => "- Choisir -"
          ],
          'operators' => [
              'string' => [
                  'contains' => "contient",
                  'eq' => "est",
                  'doesnotcontain' => "ne contient pas",
                  'neq' => "n'est pas",
                  'startswith' => "commence par",
                  'endswith' => "se termine par"
              ],
              'number' => [
                  'eq' => "est égal à",
                  'neq' => "est différent de",
                  'gte' => "est supérieur ou égal",
                  'gt' => "est supérieur",
                  'lte' => "est inférieur ou égal",
                  'lt' => "est inférieur"
              ],
              'date' => [
                  'eq' => "est",
                  'neq' => "n'est pas",
                  'gte' => "est après ou est",
                  'gt' => "est après",
                  'lte' => "est avant ou est",
                  'lt' => "est avant"
              ],
              'enums' => [
                  'eq' => "est",
                  'neq' => "n'est pas"
              ],
          ]
      ],
      'resizable' => true,
      'columnMenu' => [
          'messages'=> [
              'sortAscending' => "Trier par ordre croissant",
              'sortDescending' => "Trier par ordre décroissant",
              'filter' => "Filtre",
              'columns' => "Colonnes"
          ]
      ],
      'edit' => 'function(){
        $(".k-edit-form-container").parent().css({
          height:"auto",
          width:720,
          "max-height":appui.env.height-100
        }).restyle().data("kendoWindow").title("Formulaire de saisie").center();
        appui.fn.log(arguments);
       }'
    ];
  
  public $original_cfg;
  
  public function __construct(array $cfg){
    
    if ( isset($cfg['id'], $cfg['primary']) ){
      $this->id = $cfg['id'];
      $this->original_cfg = $cfg;
      $this->primary = $cfg['primary'];
      $this->cfg = self::$default_config;
      $this->cfg['args'] = [$this->id];
      $this->cfg['datasource']['schema']['model']['id'] = $this->primary;
      if ( isset($cfg['url']) ){
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
      }
      if ( isset($cfg['elements']) ){
        foreach ( $cfg['elements'] as $f ){
          if ( isset($f['fields']) ){
            $this->add_field($f['fields']);
          }
          if ( isset($f['columns']) ){
            $this->add_column($f['columns']);
          }
        }
      }
      //$this->add_table_button(['text' => 'Wooo','name' => 'Wooloo']);
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
  
  public function set_detail($grid){
    $g = new grid($grid);
    $this->cfg['detailInit'] = $g->cfg;
  }
  public function set_insert($url, $with_button=1){
    $this->cfg['datasource']['transport']['create'] = [
        'fn' => '\\Kendo\Data\DataSourceTransportCreate',
        'url' => 'json/insert/'.$url,
        'contentType' => 'application/json',
        'type' => 'POST'
    ];
    if ( $with_button ){
      $this->add_table_button(['text' => 'Nouvelle entrée','name' => 'create']);
    }
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
  
  public function add_table_button(array $button){
    if ( !isset($this->cfg['addToolbarItem']) ){
      $this->cfg['addToolbarItem'] = [
          'fn' => '\\Kendo\\UI\\GridToolbarItem'
      ];
    }
    if ( (isset($button['name']) && isset($button['text'])) || isset($button['template']) ){
      array_push($this->cfg['addToolbarItem'], $button);
    }
  }
  
}
?>