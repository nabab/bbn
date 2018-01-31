/**
 * Created by BBN on 10/07/2017.
 */
(function($, bbn, Vue){
  "use strict";

  Vue.component('bbn-scroll-y', {
    mixins: [bbn.vue.basicComponent],
    props: {
      /* Must be an instance of bbn-scroll */
      scroller: {
        type: Vue,
        default(){
          let tmp = bbn.vue.closest(this, 'bbn-scroll');
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
        scroll: this.initial
      };
    },
    computed: {
      realHeight(){
        return this.containerHeight ? this.containerHeight / 100 * this.height : 0;
      }
    },
    methods: {
      // Sets the top position
      _changePosition(next, animate, force, origin){
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
          this.scrollContainer(top, animate, origin);
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
        if ( this.realContainer && this.dragging && this.containerHeight ){
          e.preventDefault();
          e.stopPropagation();
          e = e.changedTouches ? e.changedTouches[0] : e;
          let yMovement = e.pageY - this.start;
          let yMovementPercentage = yMovement ? Math.round(yMovement / this.containerHeight * 1000000) / 10000 : 0;
          this.start = e.pageY;
          if ( yMovementPercentage ){
            this._changePosition(this.top + yMovementPercentage);
          }
        }
      },

      stopDrag() {
        this.dragging = false;
      },

      // Effectively change the scroll and bar position and sets variables
      scrollContainer(top, animate, origin){
        if ( this.realContainer && this.contentHeight ){
          this.currentScroll = top ? Math.round(this.contentHeight * top / 100 * 10000) / 10000 : 0;
          if ( animate ){
            $.each(this.scrollableElements(), (i, a) => {
              if ( (a !== this.realContainer) && (a !== origin) && (a.scrollTop !== this.currentScroll) ){
                $(a).animate({scrollTop: this.currentScroll}, "fast");
              }
            });
            if ( (origin !== this.realContainer) && (this.realContainer.scrollTop !== this.currentScroll) ){
              $(this.realContainer).animate({scrollTop: this.currentScroll}, "fast", () => {
                this.top = top;
                this.normalize();
              });
            }
          }
          else {
            $.each(this.scrollableElements(), (i, a) => {
              if ( (a !== this.realContainer) && (a !== origin) && (a.scrollTop !== this.currentScroll) ){
                a.scrollTop = this.currentScroll;
              }
            });
            if ( (origin !== this.realContainer) && (this.realContainer.scrollTop !== this.currentScroll) ){
              this.realContainer.scrollTop = this.currentScroll;
            }
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
            let centerize = 0;
            if ( Math.abs(yMovement) > (this.realHeight - 20) ){
              yMovement = yMovement > 0 ? (this.realHeight - 20) : - (this.realHeight - 20);
            }
            else{
              centerize = (yMovement > 0 ? 1 : -1) * this.height / 2;
            }
            let yMovementPercentage = yMovement / this.containerHeight * 100 + centerize;
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
        bbn.fn.info("ON RESIZE!");
        if ( this.realContainer ){
          bbn.fn.log("real");
          let tmp1 = $(this.realContainer).height() - 18,
              tmp2 = this.realContainer.children[0] ? this.realContainer.children[0].clientHeight : this.containerHeight - 18;
          if ( (tmp1 !== this.containerHeight) || (tmp2 !== this.contentHeight) ){
            this.containerHeight = tmp1;
            this.contentHeight = tmp2;
            // The scrollbar is only visible if needed, i.e. the content is larger than the container
            if ( this.contentHeight - this.tolerance > this.containerHeight ){
              let old = this.height;
              this.height = this.containerHeight / this.contentHeight * 100;
              this._changePosition(old ? Math.round(this.top * (old/this.height) * 10000)/10000 : 0);
            }
            else{
              this.height = 0;
            }
          }
        }
        else{
          bbn.fn.log("not real");
          this.initContainer();
        }
      },

      // Sets the variables when the content is scrolled with mouse
      adjust(e){
        if (
          this.realContainer &&
          !this.dragging &&
          (e.target.scrollTop !== this.currentScroll)
        ){
          if ( e.target.scrollTop ){
            this._changePosition(Math.round(e.target.scrollTop / this.contentHeight * 1000000)/10000, false, false, e.target);
          }
          else{
            this._changePosition(0);
          }
        }
        this.overContent();
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
          this.scroller.$off("scroll", this.adjust);
          this.scrollTo(this.initial);
          this.scroller.$on("scroll", this.adjust);
          this.scroller.$off("mousemove", this.overContent);
          this.scroller.$on("mousemove", this.overContent);
          $.each(this.scrollableElements(), (i, a) => {
            $(a).off("scroll", this.adjust);
            $(a).off("mousemove", this.overContent);
            $(a).scroll(this.adjust);
            $(a).mousemove(this.overContent);
          });
        }
      },

      // When the mouse is over the content
      overContent(){
        clearTimeout(this.moveTimeout);
        if ( !this.show ){
          this.show = true;
        }
        this.moveTimeout = setTimeout(() => {
          if ( !this.isOverSlider ){
            this.hideSlider();
          }
        }, 1000);
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
            height: this.height + '%',
            top: this.top + '%'
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
          this.animateBar();
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

})(jQuery, bbn, Vue);
