/**
 * Created by BBN on 10/02/2017.
 */
(function($, bbn){
  "use strict";

  kendo.ui.AutoComplete.prototype.options.autoWidth = true;

  Vue.component('bbn-scrolly', {
    template: '#bbn-tpl-component-scrolly',
    props: {
      content: {
        type: Object,
        default: 0
      },
      container: {
        type: Object,
        default: 0
      },
      scrolling: {
        type: Object,
        default: { v: 0, h: 0 }
      },
      show: {
        type: Boolean,
        default: false
      },
      draggingFromParent: {
        type: Boolean,
        default: false
      },
      onChangePosition: {
        type: Function,
        default: () => {}
      },
    },
    data() {
      return {
        height: 0,
        dragging: false,
        start: 0,
      }
    },
    watch: {
      'container.height'(val,old) {
        if (val != old) this.calculateSize()
      },
    },
    methods: {
      calculateSize() {
        this.height = this.container.height / this.content.height * 100
      },
      startDrag(e) {
        e.preventDefault()
        e.stopPropagation()
        e = e.changedTouches ? e.changedTouches[0] : e
        this.dragging = true
        this.start = e.pageY
      },
      onDrag(e) {
        if (this.dragging) {
          e.preventDefault()
          e.stopPropagation()
          e = e.changedTouches ? e.changedTouches[0] : e
          let yMovement = e.pageY - this.start
          let yMovementPercentage = yMovement / this.container.height * 100
          this.start = e.pageY
          let next = this.scrolling.v + yMovementPercentage
          this.$parent.dragging = true
          this.onChangePosition(next, 'vertical')
          this.normalize()
        }
      },
      stopDrag(e) {
        this.dragging = false
        this.$parent.dragging = false
      },
      normalize() {
        this.$emit('vertical')
      },
      jump(e) {
        let isRail = e.target === this.$refs.scrollRail
        if (isRail) {
          let position = this.$refs.scrollSlider.getBoundingClientRect()
          let yMovement = e.pageY - position.top
          let centerize = this.height / 2
          let yMovementPercentage = yMovement / this.container.height * 100 - centerize
          this.start = e.pageY
          let next = this.scrolling.v + yMovementPercentage
          this.onChangePosition(next, 'vertical')
          this.normalize()
        }
      },
    },
    mounted() {
      this.calculateSize()
      document.addEventListener("mousemove", this.onDrag)
      document.addEventListener("touchmove", this.onDrag)
      document.addEventListener("mouseup", this.stopDrag)
      document.addEventListener("touchend", this.stopDrag)
    },
    beforeDestroy() {
      document.removeEventListener("mousemove", this.onDrag)
      document.removeEventListener("touchmove", this.onDrag)
      document.removeEventListener("mouseup", this.stopDrag)
      document.removeEventListener("touchend", this.stopDrag)
    }
  });

})(jQuery, bbn);
