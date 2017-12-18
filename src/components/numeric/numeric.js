/**
 * Created by BBN on 10/02/2017.
 */
(function($, bbn, kendo){
  "use strict";

  Vue.component('bbn-numeric', {
    mixins: [bbn.vue.basicComponent, bbn.vue.fullComponent],
    props: {
      decimals: {
        type: [Number, String]
      },
      format: {
        type: String,
        default: "n0"
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
      return {
        widgetName: "kendoNumericTextBox"
      };
    },
    mounted: function(){
      this.widget = $(this.$refs.element).kendoNumericTextBox($.extend(this.getOptions(), {
        value: this.value,
        spin: (e) => {
          this.$emit('input', e.sender.value());
        },
        change: (e) => {
          this.$emit('input', e.sender.value());
        }
      })).data("kendoNumericTextBox");
      this.$emit("ready", this.value);
    },
    watch: {
      min(newVal){
        this.widget.setOptions({min: newVal});
      },
      max(newVal){
        this.widget.setOptions({min: newVal});
      }
    }
  });

})(jQuery, bbn, kendo);
