/**
 * Created by BBN on 15/02/2017.
 */
(function($, bbn, kendo){
  "use strict";

  /**
   * Classic input with normalized appearance
   */
  let isClicked = false;
  Vue.component('bbn-vlist', {
    template: '#bbn-tpl-component-vlist',
    props: {
      source: {
        type: [Function, Array]
      },
      maxHeight: {
        type: String,
        default: '100%'
      },
      unique: {
        type: Boolean,
        default: false
      },
      mode: {
        type: String,
        default: "free"
      },
      parent: {
        default: false
      },
      noIcon: {
        default: false
      },
      left: {},
      right: {},
      top: {},
      bottom: {}
    },
    data(){
      return {
        items: $.isFunction(this.source) ? this.source() : this.source.slice(),
        currentIndex: false
      };
    },
    methods: {
      getStyles(){
        return {
          left: this.right > 0 ? '' : this.left + 'px',
          right: this.right > 0 ? this.right + 'px' : '',
          top: this.bottom > 0 ? '' : this.top + 'px',
          bottom: this.bottom > 0 ? this.bottom + 'px' : '',
          maxHeight: this.maxHeight
        };
      },
      leaveList: function(e){
        if ( !isClicked ){
          this.close();
        }
      },
      beforeClick(){
        isClicked = true;
      },
      afterClick(){
        setTimeout(function(){
          isClicked = false;
        })
      },

      over(idx){
        if ( this.currentIndex !== idx ){
              this.currentIndex = idx;
          if ( this.items[idx].items ){
            var $item = $(this.$el).find(" > ul > li").eq(idx),
                offset = $item.offset(),
                h = $(this.$root.$el).height(),
                w = $(this.$root.$el).width();
            this.$set(this.items[idx], "right", offset.left > (w * 0.6) ? Math.round(w - offset.left) : '');
            this.$set(this.items[idx], "left", offset.left <= (w * 0.6) ? Math.round(offset.left + $item[0].clientWidth) : '');
            this.$set(this.items[idx], "bottom", offset.top > (h * 0.6) ? Math.round(offset.top + $item[0].clientHeight) : '');
            this.$set(this.items[idx], "top", offset.top <= (h * 0.6) ? Math.round(offset.top) : '');
            this.$set(this.items[idx], "maxHeight", (offset.top > (h * 0.6) ? Math.round(offset.top + $item[0].clientHeight) : Math.round(h - offset.top)) + 'px');
          }
        }
      },
      close(e){
        this.currentIndex = false;
      },
      closeAll(){
        this.close();
        if ( this.$parent ){
          this.$emit("closeall");
        }
      },
      select(e, idx){
        bbn.fn.log("SELECT");
        if ( e ){
          e.preventDefault();
          e.stopImmediatePropagation();
        }
        if ( !this.items[idx].items ){
          if ( this.mode === 'options' ){
            this.$set(this.items[idx], "selected", this.items[idx].selected ? false : true);
          }
          else if ( (this.mode === 'selection') && !this.items[idx].selected ){
            var prev = bbn.fn.search(this.items, "selected", true);
            if ( prev > -1 ){
              this.$set(this.items[prev], "selected", false);
            }
            this.$set(this.items[idx], "selected", true);

          }
          if ( this.items[idx].click ){
            if ( typeof(this.items[idx].click) === 'string' ){
              bbn.fn.log("CLICK IS STRING", this);
            }
            else if ( $.isFunction(this.items[idx].click) ){
              bbn.fn.log("CLICK IS FUNCTION", this);
              this.items[idx].click(e, idx, JSON.parse(JSON.stringify(this.items[idx])));
            }
          }
          if ( this.mode !== 'options' ){
            this.close();
            if ( this.parent ){
              this.$emit("closeall");
            }
          }
        }
      }
    },
    mounted(){
      this.$nextTick(() => {
        let style = {},
            h = $(this.$el).children().height();
        if ( this.bottom ){
          if ( this.bottom - h < 0 ){
            style.top = '0px';
          }
          else{
            style.top = Math.round(this.bottom - h) + 'px';
          }
          style.height = Math.round(h + 2) + 'px';
          $(this.$el).css(style)
        }
      })
    },
    watch:{
      currentIndex(newVal){
        if ( (newVal === false) && !this.parent ){
          this.$emit("close");
        }
      }
    }
  });

})(jQuery, bbn, kendo);
