/**
 * Created by BBN on 10/07/2017.
 */
(function($, bbn){
  "use strict";

  Vue.component('bbn-scroll-x', {
    template: '#bbn-tpl-component-scroll-x',
    props: {
      /* Must be an instance of bbn-scroll */
      container: {
        type: Vue,
        default(){
          return bbn.vue.closest(this, "bbn-scroll");
        }
      },
      hidden: {
        type: [String, Boolean],
        default: 'auto'
      },
      tolerance: {
        type: Number,
        default: 2
      },
      scrolling: {
        type: Number,
        default: 0
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
        containerWidth: 0,
        contentWidth: 0,
        dragging: false,
        width: 100,
        start: 0,
        left: this.scrolling,
        currentScroll: 0,
        moveTimeout: 0,
        show: this.hidden === 'auto' ? false : !this.hidden
      }
    },
    methods: {
      // Sets the left position
      _changePosition(next, animate){
        let left;
        if ( next < 0 ){
          left = 0;
        }
        else if ( next > (100 - this.width) ){
          left = 100 - this.width;
        }
        else{
          left = next;
        }
        if (
          (typeof(left) === 'number') &&
          (left !== this.left)
        ){
          this.scrollContainer(left, animate);
        }
      },

      startDrag(e) {
        if ( this.container && this.container.$refs.scrollContainer ){
          e.preventDefault();
          e.stopPropagation();
          e = e.changedTouches ? e.changedTouches[0] : e;
          this.dragging = true;
          this.start = e.pageX;
        }
      },

      onDrag(e) {
        if ( this.container && this.dragging ){
          e.preventDefault();
          e.stopPropagation();
          e = e.changedTouches ? e.changedTouches[0] : e;
          let xMovement = e.pageX - this.start;
          let xMovementPercentage = xMovement / this.containerWidth * 100;
          this.start = e.pageX;
          this._changePosition(this.left + xMovementPercentage);
        }
      },

      stopDrag(e) {
        this.dragging = false
      },

      // Effectively change the scroll and bar position and sets variables
      scrollContainer(left, animate){
        if ( this.container && this.container.$refs.scrollContainer ){
          this.currentScroll = Math.round(this.contentWidth * left / 100);
          if ( animate && (this.container.$refs.scrollContainer.scrollLeft !== this.currentScroll) ){
            $.each(this.scrollableElements(), (i, a) => {
              if ( a !== this.container.$refs.scrollContainer ){
                $(a).animate({scrollLeft: this.currentScroll}, "fast");
              }
            });
            $(this.container.$refs.scrollContainer).animate({scrollLeft: this.currentScroll}, "fast", () => {
              this.left = left;
              this.normalize();
            });
          }
          else{
            this.container.$refs.scrollContainer.scrollLeft = this.currentScroll;
            $.each(this.scrollableElements(), (i, a) => {
              if ( a !== this.container.$refs.scrollContainer ){
                a.scrollLeft = this.currentScroll;
              }
            });
            this.left = left;
            this.normalize();
          }
        }
      },

      // When the users jumps by clicking the scrollbar
      jump(e) {
        if ( this.container ){
          let isRail = e.target === this.$refs.scrollRail;
          if ( isRail ){
            let position = this.$refs.scrollSlider.getBoundingClientRect();
            // Calculate the horizontal Movement
            let xMovement = e.pageX - position.left;
            let centerize = this.width / 2;
            let xMovementPercentage = xMovement / this.containerWidth * 100 - centerize;
            this._changePosition(this.left + xMovementPercentage, true);
          }
        }
      },

      // Emits vertical event
      normalize(){
        this.$emit('vertical');
      },

      // Gets the array of scrollable elements according to scrollAlso attribute
      scrollableElements(){
        let tmp = this.scrollAlso;
        if ( $.isFunction(tmp) ){
          tmp = tmp();
        }
        else if ( !Array.isArray(tmp) ){
          tmp = [tmp];
        }
        return tmp;
      },

      // Calculates all the proportions based on content
      onResize() {
        if ( this.container && this.container.$refs.scrollContainer ){
          this.containerWidth = $(this.container.$refs.scrollContainer).width();
          this.contentWidth = this.container.$refs.scrollContent ? this.container.$refs.scrollContent.clientWidth : this.containerWidth;
          // The scrollbar is only visible if needed, i.e. the content is larger than the container
          if ( this.contentWidth - this.tolerance > this.containerWidth ){
            this.width = this.containerWidth / this.contentWidth * 100;
          }
          else{
            this.width = 0 ;
          }
        }
      },

      // Sets the variables when the content is scrolled with mouse
      adjust(e){
        if (
          this.container &&
          !this.dragging &&
          (e.target.scrollLeft !== this.currentScroll)
        ){
          this._changePosition(Math.round(e.target.scrollLeft / this.contentWidth * 100));
        }
        this.overContent();
      },

      // Sets all event listeners
      initContainer(){
        if ( this.container && this.container.$refs.scrollContainer ){
          this.onResize();
          let $cont = $(this.container.$refs.scrollContainer);
          this.container.$off("resize", this.onResize);
          this.container.$on("resize", this.onResize);
          $cont.off("scroll", this.adjust);
          $cont.off("mousemove", this.overContent);
          $cont.scroll(this.adjust);
          $cont.mousemove(this.overContent);
          $.each(this.scrollableElements(), (i, a) => {
            $(a).off("scroll", this.adjust);
            $(a).off("mousemove", this.overContent);
            $(a).scroll(this.adjust);
            $(a).mousemove(this.overContent);
          });
        }
      },

      // When the mouse is over the content
      overContent(e){
        clearTimeout(this.moveTimeout);
        this.show = true;
        this.moveTimeout = setTimeout(() => {
          if ( !this.isOverSlider ){
            this.hideSlider();
          }
        }, 1000)
      },

      // When the mouse enters over the slider
      inSlider(){
        if ( !this.isOverSlider ){
          this.isOverSlider = true;
          this.showSlider();
        }
      },

      // When the mouse leaves the slider
      outSlider(){
        if ( this.isOverSlider ){
          this.isOverSlider = false;
          this.overContent();
        }
      },

      showSlider() {
        clearTimeout(this.moveTimeout);
        if ( !this.show ){
          this.show = true;
        }
      },

      hideSlider() {
        if ( !this.dragging && this.show ){
          this.show = false;
        }
      },
    },
    watch: {
      container(){
        this.initContainer();
      }
    },
    mounted() {
      this.initContainer();
      document.addEventListener("mousemove", this.onDrag);
      document.addEventListener("touchmove", this.onDrag);
      document.addEventListener("mouseup", this.stopDrag);
      document.addEventListener("touchend", this.stopDrag);
    },
    beforeDestroy() {
      $(this.container.$refs.scrollContainer).off("scroll", this.adjust);
      $(this.container.$refs.scrollContainer).off("mousemove", this.overContent);
      $.each(this.scrollableElements(), (i, a) => {
        $(a).off("scroll", this.adjust);
        $(a).off("mousemove", this.overContent);
      });
      document.removeEventListener("mousemove", this.onDrag);
      document.removeEventListener("touchmove", this.onDrag);
      document.removeEventListener("mouseup", this.stopDrag);
      document.removeEventListener("touchend", this.stopDrag);
    },
  });

})(jQuery, bbn);
