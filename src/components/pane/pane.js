/**
 * Created by BBN on 15/02/2017.
 */
(function($, bbn, kendo){
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
        type: [String, Number]
      },
      resizable: {
        type: Boolean,
        default: false
      },
      collapsible: {
        type: Boolean,
        default: false
      },
      collapsed: {
        type: Boolean,
        default: false
      },
      scrollable: {
        type: Boolean,
        default: false
      }
    },
    data(){
      return {
        resizeTimeout: false,
        currentHidden: this.collapsed,
        checker: false
      };
    },
    computed: {
      currentSize(){
        if ( typeof(this.size) === 'number' ){
          return this.size + 'px'
        }
        return this.size;
      }
    },
    methods: {
    },
    mounted(){
      this.selfEmit(true);
    },
    updated(){
      this.selfEmit(true);
    },
    watch: {
    }
  });

})(jQuery, bbn, kendo);
