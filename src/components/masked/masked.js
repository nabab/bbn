/**
 * Created by BBN on 10/02/2017.
 */
(function($, bbn, kendo){
  "use strict";

  Vue.component('bbn-masked', {
    mixins: [bbn.vue.basicComponent, bbn.vue.fullComponent],
    props: {
      mask: {
        type: String
      },
      cfg: {
        type: Object,
        default: function(){
          return {
            promptChar: '_'
          };
        }
      }
    },
    data: function(){
      return $.extend({
        widgetName: "kendoMaskedTextBox"
      }, bbn.vue.treatData(this));
    },
    mounted: function(){
      this.widget = $(this.$refs.element).kendoMaskedTextBox(this.getOptions()).data("kendoMaskedTextBox");
      this.$emit("ready", this.value);
    }
  });

})(jQuery, bbn, kendo);
