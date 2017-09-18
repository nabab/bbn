/**
 * Created by BBN on 10/02/2017.
 */
(function($, bbn, kendo){
  "use strict";

  kendo.ui.DropDownList.prototype.options.autoWidth = true;

  Vue.component('bbn-dropdown', {
    template: '#bbn-tpl-component-dropdown',
    mixins: [bbn.vue.fullComponent, bbn.vue.dataSourceComponent],
    props: {
      filterValue: {},
      template: {},
      valueTemplate: {},
      cfg: {
        type: Object,
        default(){
          return {
            dataTextField: 'text',
            dataValueField: 'value',
            dataSource: []
          };
        }
      }
    },
    methods: {
      getOptions(){
        var vm = this,
            cfg = bbn.vue.getOptions(vm);
        cfg.change = function(e){
          bbn.fn.log(e);
          vm.$emit("input", e.sender.value());
					if ( $.isFunction(vm.change) ){
						vm.change(e.sender.value());
					}
        };
        if ( this.template ){
          cfg.template = e => {
            return this.template(e);
          };
        }
        if ( this.valueTemplate ){
          cfg.valueTemplate = e => {
            return this.valueTemplate(e)
          }
        }
        return cfg;
      }
    },
    data(){
      return $.extend({
        widgetName: "kendoDropDownList"
      }, bbn.vue.treatData(this));
    },
    mounted(){
      let cfg = this.getOptions();
      if ( this.disabled ){
        cfg.enable = false;
      }
      if ( this.placeholder ){
        cfg.optionLabel = this.placeholder;
      }
      this.widget = $(this.$refs.element).kendoDropDownList(cfg).data("kendoDropDownList");
      if ( !cfg.optionLabel && cfg.dataSource.length && !this.value ){
        this.widget.select(0);
        this.widget.trigger("change");
      }
      this.$emit("ready", this.value);
    },
    computed: {
      dataSource(){
        if ( this.source ){
          return bbn.vue.toKendoDataSource(this);
        }
        return [];
      }
    },
    watch:{
      source(newDataSource){
        bbn.fn.log("Changed DS", this.dataSource);
        this.widget.setDataSource(this.dataSource);
      }
    }
  });

})(jQuery, bbn, kendo);
