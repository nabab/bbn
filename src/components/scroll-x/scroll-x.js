/**
 * Created by BBN on 10/07/2017.
 */
(function($, bbn){
  "use strict";

  Vue.component('bbn-scroll-x', {
    mixins: [bbn.vue.basicComponent],
    props: {
      /* Must be an instance of bbn-scroll */
      scroller: {
        type: Vue,
        default(){
          let tmp = bbn.vue.closest(this, "bbn-scroll");
          return tmp ? tmp : null;
        }
      },
      container: {
        type: HTMLElement
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
      },
      initial: {
        type: [Number, Object],
        default: 0
      }
    },
    data() {
      return {
        realContainer: this.container ?
          this.container :
          (this.scroller ? this.scroller.$refs.scrollContainer : false),
        containerWidth: 0,
        contentWidth: 0,
        dragging: false,
        width: 100,
        start: 0,
        left: this.scrolling,
        currentScroll: 0,
        moveTimeout: 0,
        show: this.hidden === 'auto' ? false : !this.hidden,
        scroll: this.initial
      }
    },
    methods: {
      // Sets the left position
      _changePosition(next, animate, force, origin){
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
          ((left !== this.left) || force)
        ){
          this.scrollContainer(left, animate, origin);
        }
      },

      startDrag(e) {
        if ( this.realContainer && this.realContainer ){
          e.preventDefault();
          e.stopPropagation();
          e = e.changedTouches ? e.changedTouches[0] : e;
          this.dragging = true;
          this.start = e.pageX;
        }
      },

      onDrag(e) {
        if ( this.realContainer && this.dragging && this.containerWidth ){
          e.preventDefault();
          e.stopPropagation();
          e = e.changedTouches ? e.changedTouches[0] : e;
          let xMovement = e.pageX - this.start;
          let xMovementPercentage = xMovement ? Math.round(xMovement / this.containerWidth * 1000000) / 10000 : 0;
          this.start = e.pageX;
          if ( xMovementPercentage ){
            this._changePosition(this.left + xMovementPercentage);
          }
        }
      },

      stopDrag(e) {
        this.dragging = false
      },

      // Effectively change the scroll and bar position and sets variables
      scrollContainer(left, animate, origin){
        if ( this.realContainer && this.contentWidth ){
          this.currentScroll = left ? Math.round(this.contentWidth * left / 100 * 10000) / 10000 : 0;
          if ( animate ){
            $.each(this.scrollableElements(), (i, a) => {
              if ( (a !== this.realContainer) && (a !== origin) && (a.scrollTop !== this.currentScroll) ){
                $(a).animate({scrollLeft: this.currentScroll}, "fast");
              }
            });
            if ( (origin !== this.realContainer) && (this.realContainer.scrollLeft !== this.currentScroll) ){
              $(this.realContainer).animate({scrollLeft: this.currentScroll}, "fast", () => {
                this.left = left;
                this.normalize();
              });
            }
          }
          else{
            $.each(this.scrollableElements(), (i, a) => {
              if ( (a !== this.realContainer) && (a !== origin) && (a.scrollLeft !== this.currentScroll) ){
                a.scrollLeft = this.currentScroll;
              }
            });
            if ( (origin !== this.realContainer) && (this.realContainer.scrollLeft !== this.currentScroll) ){
              this.realContainer.scrollLeft = this.currentScroll;
            }
            this.left = left;
            this.normalize();
          }
        }
      },

      // When the users jumps by clicking the scrollbar
      jump(e) {
        if ( this.realContainer ){
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

      // Emits scroll event
      normalize(){
        this.$emit('scroll');
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
      onResize(){
        if ( this.realContainer ){
          let tmp1 = $(this.realContainer).width() - 18,
              tmp2 = this.realContainer.children[0] ? this.realContainer.children[0].clientWidth : this.containerWidth - 18;
          if ( (tmp1 !== this.containerWidth) || (tmp2 !== this.contentWidth) ){
            this.containerWidth = tmp1;
            this.contentWidth = tmp2;
            // The scrollbar is only visible if needed, i.e. the content is larger than the container
            if ( this.contentWidth - this.tolerance > this.containerWidth ){
              let old = this.width;
              this.width = this.containerWidth / this.contentWidth * 100;
              this._changePosition(old ? Math.round(this.left * (old / this.width) * 10000) / 10000 : 0);
            }
            else{
              this.width = 0;
            }
          }
        }
        else{
          this.initContainer();
        }
      },

      // Sets the variables when the content is scrolled with mouse
      adjust(e){
        if (
          this.realContainer &&
          !this.dragging &&
          (e.target.scrollLeft !== this.currentScroll)
        ){
          if ( e.target.scrollLeft ){
            this._changePosition(Math.round(e.target.scrollLeft / this.contentWidth * 1000000)/10000, false, false, e.target);
          }
          else{
            this._changePosition(0);
          }
        }
        this.overContent();
      },

      adjustBar(){

      },

      // Sets all event listeners
      initContainer(){
        if ( !this.realContainer && this.scroller ){
          this.realContainer = this.scroller.$refs.scrollContainer || false;
        }
        if ( this.realContainer && this.scroller ){
          this.onResize();
          let $cont = $(this.realContainer);
          this.scroller.$off("resize", this.onResize);
          this.scroller.$on("resize", this.onResize);
          $cont.off("scroll", this.adjust);
          $cont.off("mousemove", this.overContent);
          this.scrollTo(this.initial);
          $cont.scroll(this.adjust);
          $cont.mousemove(this.overContent);
          $.each(this.scrollableElements(), (i, a) => {
            $(a).off("scroll", this.adjustBar);
            $(a).off("mousemove", this.overContent);
            $(a).scroll(this.adjustBar);
            $(a).mousemove(this.overContent);
          });
        }
      },

      // When the mouse is over the content
      overContent(e){
        clearTimeout(this.moveTimeout);
        if ( !this.show ){
          this.show = true;
        }
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

      animateBar(){
        if ( this.$refs.scrollSlider ){
          //this.dragging = true;
          $(this.$refs.scrollSlider).animate({
            width: this.width + '%',
            left: this.left + '%'
          }, () => {
            //this.dragging = false;
          })
        }
      },
      scrollTo(val, animate){
        let num = null;
        if ( typeof(val) === 'number' ){
          num = val;
        }
        else if ( val instanceof HTMLElement ){
          let $container = $(val).offsetParent();
          num = $(val).position().left;
          while ( $container[0] !== this.scroller.$refs.scrollContent ){
            num += $container.position().left;
            $container = $container.offsetParent();
          }
          num -= 20;
        }
        if ( num !== null ){
          if ( num < 0 ){
            num = 0;
          }
          this._changePosition(100 / this.contentWidth * num, animate);
        }
      }
    },
    watch: {
      container(){
        this.initContainer();
      },
      width(newVal){
        if ( newVal ){
          this.animateBar();
        }
      }
    },
    mounted() {
      this.initContainer();
      document.addEventListener("mousemove", this.onDrag);
      document.addEventListener("touchmove", this.onDrag);
      document.addEventListener("mouseup", this.stopDrag);
      document.addEventListener("touchend", this.stopDrag);
      this.onResize();
      this.$emit('ready');
    },
    beforeDestroy() {
      $(this.realContainer).off("scroll", this.adjust);
      $(this.realContainer).off("mousemove", this.overContent);
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
