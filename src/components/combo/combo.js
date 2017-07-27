/**
 * Created by BBN on 10/02/2017.
 */
(function($, bbn, kendo){
  "use strict";

  kendo.ui.ComboBox.prototype.options.autoWidth = true;

  Vue.component('bbn-combo', {
    mixins: [bbn.vue.vueComponent],
    template: '#bbn-tpl-component-combo',
    props: {
      animation: {
        type: [Boolean, Object]
      },
      source: {
        type: [String, Object, Array]
      },
      cfg: {
        type: Object,
        default: function(){
          return {
            dataTextField: 'text',
            dataValueField: 'value',
            delay: 200,
            highlightFirst: true
          };
        }
      }
    },
    data: function(){
      return $.extend({
        widgetName: "kendoComboBox"
      }, bbn.vue.treatData(this));
    },
    mounted: function(){
      this.widget = $(this.$ref.element).kendoComboBox(this.getOptions()).data("kendoComboBox");
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
        this.widget.setDataSource(this.dataSource);
      }
    }
  });

})(jQuery, bbn, kendo);
