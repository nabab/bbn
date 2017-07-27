/**
 * Created by BBN on 14/02/2017.
 */
(function($, bbn){
  "use strict";

  /**
   * Classic input with normalized appearance
   */
  Vue.component('bbn-list', {
    mixins: [bbn.vue.optionComponent],
    template: '#bbn-tpl-component-list',
    props: {
      expandMode: {},
      source: {},
      select: {},
      itemClass: {},
      cfg: {
        type: Object,
        default: function(){
          return {
            loadOnDemand: false,
            // spriteCssClass: "rootfolder",
            select:{},
            // template:'<span><b></b></span>',
            expandMode: "single",
            dataSource: [],
            dataTextField: 'text',
            dataUrlField: 'url',
            animation: {
              expand: {
                duration: 400,
                effects: "expandVertical"
              }
            },
          };
        }
      }
    },
    data: function(){
      return $.extend({
        widgetName: "kendoPanelBar",
      }, bbn.vue.treatData(this));
    },
    methods: {
      drawItem: function(obj, e){
        var vm = this,
            data = obj.item,
            cfg = bbn.vue.getOptions(vm),
            cls = cfg.itemClass(data),
            tpl = '';
        if ( cls ){
          tpl += '<span class="' + cls + '">';
        }
        if ( cfg.dataUrlField && data[cfg.dataUrlField] ){
          tpl += '<a href="' + data[cfg.dataUrlField] + '">';
        }
        if ( !$.isArray(cfg.dataTextField) ){
          cfg.dataTextField = [cfg.dataTextField];
        }
        $.each(cfg.dataTextField, function(i, v){
          tpl += data[v] ? data[v] : ' ';
        });
        if ( cfg.dataUrlField && data[cfg.dataUrlField] ){
          tpl += '</a>';
        }
        if ( cls ){
          tpl += '</span>';
        }
        return tpl;
      },
      getOptions: function(){
        var vm = this,
            cfg = bbn.vue.getOptions(vm);
        delete cfg.source;
        cfg.template = vm.drawItem;
        return cfg;
      }
    },
    computed: {
      dataSource: function(){
        if ( this.source ){
          return bbn.vue.transformDataSource(this);
        }
        return [];
      }
    },
    mounted: function(){
      var vm = this,
          cfg = this.getOptions();
      vm.widget = $(this.$el).kendoPanelBar(cfg).data("kendoPanelBar");

    },
    watch: {
      source: function(newSource){
        this.widget.setDataSource(this.dataSource);
      },
      cfg: function(){

      }
    }
  });

})(jQuery, bbn);
