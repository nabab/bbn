/**
 * Created by BBN on 10/02/2017.
 */
(function($, bbn, kendo){
  "use strict";

  Vue.component('bbn-multiselect', {
    mixins: [bbn.vue.fullComponent, bbn.vue.dataSourceComponent],
    template: '#bbn-tpl-component-multiselect',
    props: {
      source: {
        type: [String, Array, Object]
      },
      sortable: {
        type: Boolean
      },
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
    data: function(){
      return $.extend({
        widgetName: "kendoMultiSelect"
      }, bbn.vue.treatData(this));
    },
    mounted: function(){
      let cfg = this.getOptions();
      if ( this.disabled ){
        cfg.enable = false;
      }
      if ( this.template ){
        cfg.template = e => {
          return this.template(e);
        };
      }
      this.widget = $(this.$refs.element).kendoMultiSelect(cfg).data("kendoMultiSelect");
      if ( cfg.sortable ){
        this.widget.tagList.kendoSortable({
          hint:function(element) {
            return element.clone().addClass("hint");
          },
          placeholder:function(element) {
            return element.clone().addClass("placeholder").html('<div style="width:50px; text-align: center; padding: 0"> ... </div>');
          }
        });
      }
      this.$emit("ready", this.value);
    },
    computed: {
      dataSource: function(){
        if ( this.source ){
          return bbn.vue.toKendoDataSource(this);
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
