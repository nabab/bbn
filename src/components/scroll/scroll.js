/**
 * Created by BBN on 10/02/2017.
 */
(function($, bbn){
  "use strict";

  Vue.component('bbn-scroll', {
    mixins: [bbn.vue.basicComponent, bbn.vue.resizerComponent],
    props: {
      classes: {
        type: String,
        default: ""
      },
      speed: {
        type: Number,
        default: 50
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
      scrollTo(x, y, animate){
        if ( !y && (typeof x === HTMLElement) ){
          y = x;
        }
        if ( this.$refs.xScroller ){
          this.$refs.xScroller.scrollTo(x, animate);
        }
        if ( this.$refs.yScroller ){
          this.$refs.yScroller.scrollTo(y, animate);
        }
      },
      onResize() {
        if ( this.$refs.xScroller ){
          this.$refs.xScroller.onResize();
        }
        if ( this.$refs.yScroller ){
          this.$refs.yScroller.onResize();
        }
      }
    },
    mounted(){
      /** @todo WTF?? Obliged to execute the following hack to not have scrollLeft and scrollTop when we open a
       *  popup a 2nd time.
       */
      this.$refs.scrollContainer.style.position = 'relative';
      setTimeout(() => {
        this.$refs.scrollContainer.style.position = 'absolute';
      }, 0)
      this.$emit('ready');
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
