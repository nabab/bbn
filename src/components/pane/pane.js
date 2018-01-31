/**
 * Created by BBN on 15/02/2017.
 */
(function($, bbn){
  "use strict";

  /**
   * Classic input with normalized appearance
   */
  Vue.component('bbn-pane', {
    mixins: [bbn.vue.basicComponent, bbn.vue.resizerComponent],
    props: {
      overflow: {
        type: String,
        default: 'hidden'
      },
      size: {
        type: [String, Number],
        default: ''
      },
      resizable: {
        type: Boolean
      },
      collapsible: {
        type: Boolean
      },
      collapsed: {
        type: Boolean,
        default: false
      },
      scrollable: {
        type: Boolean,
        default: false
      },
      min: {
        type: Number,
        default: 20
      },
      max: {
        type: Number,
        default: 10000
      }
    },
    data(){
      return {
        currentHidden: this.collapsed,
        checker: false
      };
    },
    watch:{
      collapsed(val){
        this.currentHidden = val;
      }
    },
    beforeMount(){
      this.$parent.init();
    },
  });

})(jQuery, bbn);
