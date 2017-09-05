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
      },
      x: {
        type: [Number, Object],
        default: 0
      },
      y: {
        type: [Number, Object],
        default: 0
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
      },
      scrollTo(x, y, animate){
        if ( this.$refs.xScroller ){
          //this.$refs.xScroller.scrollTo(x, animate);
        }
        if ( this.$refs.yScroller ){
          this.$refs.yScroller.scrollTo(y, animate);
        }
      }
    },
    mounted(){
      this.scrollTo(0, false, true);
      this.onResize();
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
