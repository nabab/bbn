/**
 * Created by BBN on 10/02/2017.
 */
(function($, bbn, kendo){
  "use strict";

  Vue.component('bbn-numeric', {
    mixins: [bbn.vue.fullComponent],
    template: '#bbn-tpl-component-numeric',
    props: {
      decimals: {
        type: [Number, String]
      },
      format: {
        type: String
      },
      max: {
        type: [Number, String]
      },
      min: {
        type: [Number, String]
      },
      round: {
        type: Boolean
      },
      step: {
        type: [Number, String]
      },
      cfg: {
        type: Object,
        default: function(){
          return {
            format: "n0"
          };
        }
      }
    },
    data: function(){
      return $.extend({
        widgetName: "kendoNumericTextBox"
      }, bbn.vue.treatData(this));
    },
    mounted: function(){
      this.widget = $(this.$refs.element).kendoNumericTextBox($.extend(vm.getOptions(), {
        spin: (e) => {
          this.$emit('input', e.sender.value());
        }
      })).data("kendoNumericTextBox");
      this.$emit("ready", this.value);
    }
  });

})(jQuery, bbn, kendo);
