/**
 * Created by BBN on 10/02/2017.
 */
(function($, bbn, kendo){
  "use strict";

  kendo.ui.DropDownList.prototype.options.autoWidth = true;

  Vue.component('bbn-dropdown', {
    mixins: [bbn.vue.vueComponent],
    template: '#bbn-tpl-component-dropdown',
    props: {
      source: {
        type: [String, Array, Object]
      },
      filterValue: {},
      template: {},
      valueTemplate: {},
      cfg: {
        type: Object,
        default: function(){
          return {
            dataTextField: 'text',
            dataValueField: 'value',
            dataSource: []
          };
        }
      }
    },
    methods: {
      getOptions: function(){
        var vm = this,
            cfg = bbn.vue.getOptions(vm);
        cfg.change = function(e){
          bbn.fn.log(e);
          vm.$emit("input", e.sender.value());
					if ( $.isFunction(vm.change) ){
						vm.change();
					}
        };

        if ( cfg.template ){
          var tmp = cfg.template;
          cfg.template = function(e){
            return tmp(e);
          }
        }
        if ( cfg.valueTemplate ){
          var tmp = cfg.valueTemplate;
          cfg.valueTemplate = function(e){
            return tmp(e);
          }
        }
        return cfg;
      }
    },
    data: function(){
      return $.extend({
        widgetName: "kendoDropDownList"
      }, bbn.vue.treatData(this));
    },
    mounted: function(){
      var vm = this,
          cfg = vm.getOptions();
      if ( vm.disabled ){
        cfg.enable = false;
      }
      if ( vm.placeholder ){
        cfg.optionLabel = vm.placeholder;
      }
      vm.widget = $(vm.$refs.element).kendoDropDownList(cfg).data("kendoDropDownList");
      if ( !cfg.optionLabel && cfg.dataSource.length && !vm.value ){
        vm.widget.select(0);
        vm.widget.trigger("change");
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
    watch:{
      source: function(newDataSource){
        bbn.fn.log("Changed DS", this.dataSource);
        this.widget.setDataSource(this.dataSource);
      }
    }
  });

})(jQuery, bbn, kendo);
