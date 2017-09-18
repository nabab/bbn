/**
 * Created by BBN on 10/07/2017.
 */
(function($, bbn){
  "use strict";

  Vue.component('bbn-scroll-y', {
    template: '#bbn-tpl-component-scroll-y',
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
        containerHeight: 0,
        contentHeight: 0,
        dragging: false,
        height: 100,
        start: 0,
        top: this.scrolling,
        currentScroll: 0,
        moveTimeout: 0,
        show: this.hidden === 'auto' ? false : !this.hidden,
        scroll: this.initial,
        lastAdjust: 0
      }
    },
    methods: {
      // Sets the top position
      _changePosition(next, animate, force){
        let top;
        if ( next < 0 ){
          top = 0;
        }
        else if ( next > (100 - this.height) ){
          top = 100 - this.height;
        }
        else{
          top = next;
        }
        if (
          (typeof(top) === 'number') &&
          ((top !== this.top) || force)
        ){
          this.scrollContainer(top, animate);
        }
      },

      startDrag(e) {
        if ( this.realContainer && this.realContainer ){
          e.preventDefault();
          e.stopPropagation();
          e = e.changedTouches ? e.changedTouches[0] : e;
          this.dragging = true;
          this.start = e.pageY;
        }
      },

      onDrag(e) {
        if ( this.realContainer && this.dragging ){
          e.preventDefault();
          e.stopPropagation();
          e = e.changedTouches ? e.changedTouches[0] : e;
          let yMovement = e.pageY - this.start;
          let yMovementPercentage = yMovement / this.containerHeight * 100;
          this.start = e.pageY;
          this._changePosition(this.top + yMovementPercentage);
        }
      },

      stopDrag(e) {
        this.dragging = false
      },

      // Effectively change the scroll and bar position and sets variables
      scrollContainer(top, animate){
        if ( this.realContainer ){
          this.currentScroll = Math.round(this.contentHeight * top / 100);
          if ( animate && (this.realContainer.scrollTop !== this.currentScroll) ){
            $.each(this.scrollableElements(), (i, a) => {
              if ( a !== this.realContainer ){
                $(a).animate({scrollTop: this.currentScroll}, "fast");
              }
            });
            $(this.realContainer).animate({scrollTop: this.currentScroll}, "fast", () => {
              this.top = top;
              this.normalize();
            });
          }
          else{
            this.realContainer.scrollTop = this.currentScroll;
            $.each(this.scrollableElements(), (i, a) => {
              if ( a !== this.realContainer ){
                a.scrollTop = this.currentScroll;
              }
            });
            this.top = top;
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
            let yMovement = e.pageY - position.top;
            let centerize = this.height / 2;
            let yMovementPercentage = yMovement / this.containerHeight * 100 - centerize;
            this._changePosition(this.top + yMovementPercentage, true);
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
        if ( this.realContainer ){
          this.containerHeight = $(this.realContainer).height();
          this.contentHeight = this.realContainer.children[0] ? this.realContainer.children[0].clientHeight : this.containerHeight;
          // The scrollbar is only visible if needed, i.e. the content is larger than the container
          if ( this.contentHeight - this.tolerance > this.containerHeight ){
            this.height = this.containerHeight / this.contentHeight * 100;
          }
          else{
            this.height = 0;
          }
        }
        else{
          this.initContainer();
        }
      },

      // Sets the variables when the content is scrolled with mouse
      adjust(e){
        let now = (new Date()).getTime();
        if (
          ((now - this.lastAdjust) > 20) &&
          this.realContainer &&
          !this.dragging &&
          (e.target.scrollTop !== this.currentScroll)
        ){
          this.lastAdjust = now;
          this._changePosition(Math.round(e.target.scrollTop / this.contentHeight * 100));
        }
        this.overContent();
      },

      // Sets all event listeners
      initContainer(){
        if ( this.realContainer ){
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

      animateBar(){
        if ( this.$refs.scrollSlider ){
          this.dragging = true;
          $(this.$refs.scrollSlider).animate({
            height: this.height + '%',
            top: this.top + '%'
          }, () => {
            this.dragging = false;
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
          num = $(val).position().top;
          while ( $container[0] !== this.scroller.$refs.scrollContent ){
            num += $container.position().top;
            $container = $container.offsetParent();
          }
          num -= 20;
        }
        if ( num !== null ){
          if ( num < 0 ){
            num = 0;
          }
          bbn.fn.log("scrollToY", num);
          this._changePosition(100 / this.contentHeight * num, animate);
        }
      }
    },
    watch: {
      container(){
        this.initContainer();
      },
      height(newVal){
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
