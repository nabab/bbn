/**
 * Created by BBN on 10/02/2017.
 */
(function($, bbn){
  "use strict";

  Vue.component('bbn-scroll', {
    template: '#bbn-tpl-component-scroll',
    mixins: [bbn.vue.resizerComponent],
    props: {
      classes: {
        type: String,
        default: ""
      },
      speed: {
        type: Number,
        default: 53
      },
      axis: {
        type: String,
        default: "both"
      },
      scrollAlso: {
        type: [HTMLElement, Array, Function],
        default(){
          return [];
        }
      }
    },
    data() {
      return {
        show: false
      }
    },
    methods: {
      onResize() {
        if ( this.$refs.xScroller ){
          this.$refs.xScroller.onResize();
        }
        if ( this.$refs.yScroller ){
          this.$refs.yScroller.onResize();
        }
      }
    },
    watch: {
      show(newVal, oldVal){
        if ( newVal != oldVal ){
          this.$emit(newVal ? "show" : "hide");
        }
      }
    }
  });

})(jQuery, bbn);
