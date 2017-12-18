/**
 * Created by BBN on 10/02/2017.
 */
(function($, bbn, kendo){
  "use strict";

  Vue.component('bbn-progressbar', {
    mixins: [bbn.vue.basicComponent, bbn.vue.fullComponent],
    props: {
      value: {
        type: Number
      },
      max: {
        type: Number,
        default: 100
      },
      min: {
        type: Number,
        default: 0
      },
      step: {
        type: Number,
        default: 1
      },
      orientation: {
        type: String,
        default: 'horizontal'
      },
      reverse: {
        type: Boolean
      },
      showStatus: {
        type: Boolean,
        default: true
      },
      type: {
        type: String,
        default: 'value' //allowed 'value', 'percent', 'chunk' ('chunk' divides the bar into boxes to show the
        // progress)
      },
      cfg: {
        type: Object,
        default: function(){
          return {
            value: 0
          };
        }
      }
    },
    data: function(){
      return $.extend({
        widgetName: "kendoProgressBar"
      }, bbn.vue.treatData(this));
    },
    computed: {
      stepTotal(){
        return Math.round(this.max / this.step);
      }
    },
    mounted: function(){
      this.widget = $(this.$refs.element).kendoProgressBar(this.getOptions()).data("kendoProgressBar");
      this.$emit("ready", this.value);
    }
  });

})(jQuery, bbn, kendo);
