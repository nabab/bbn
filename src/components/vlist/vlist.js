/**
 * Created by BBN on 15/02/2017.
 */
(function($, bbn, kendo){
  "use strict";

  /**
   * Classic input with normalized appearance
   */
  var isClicked = false;
  Vue.component('bbn-vlist', {
    template: '#bbn-tpl-component-vlist',
    props: {
      items: {
        type: Array
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
    data: function(){
      return {
        menu: this.items,
        currentIndex: false
      };
    },
    methods: {
      getStyles: function(){
        var vm = this;
        return {
          left: vm.right > 0 ? '' : vm.left + 'px',
          right: vm.right > 0 ? vm.right + 'px' : '',
          top: vm.bottom > 0 ? '' : vm.top + 'px',
          bottom: vm.bottom > 0 ? vm.bottom + 'px' : '',
          maxHeight: vm.maxHeight
        };
      },
      leaveList: function(e){
        if ( e ){
          e.preventDefault();
          e.stopImmediatePropagation();
        }
        if ( !isClicked ){
          this.close();
        }
      },
      beforeClick: function(){
        isClicked = true;
      },
      afterClick: function(){
        var vm = this;
        setTimeout(function(){
          isClicked = false;
        })
      },

      over: function(idx){
        var vm = this;
        if ( vm.currentIndex !== idx ){
              this.currentIndex = idx;
          if ( vm.items[idx].items ){
            var $item = $(vm.$el).find(" > ul > li").eq(idx),
                offset = $item.offset(),
                h = $(vm.$root.$el).height(),
                w = $(vm.$root.$el).width();
            vm.$set(vm.menu[idx], "right", offset.left > (w * 0.6) ? Math.round(w - offset.left) : '');
            vm.$set(vm.menu[idx], "left", offset.left <= (w * 0.6) ? Math.round(offset.left + $item[0].clientWidth) : '');
            vm.$set(vm.menu[idx], "bottom", offset.top > (h * 0.6) ? Math.round(offset.top + $item[0].clientHeight) : '');
            vm.$set(vm.menu[idx], "top", offset.top <= (h * 0.6) ? Math.round(offset.top) : '');
            vm.$set(vm.menu[idx], "maxHeight", (offset.top > (h * 0.6) ? Math.round(offset.top + $item[0].clientHeight) : Math.round(h - offset.top)) + 'px');
          }
        }
      },
      close: function(e){
        this.currentIndex = false;
      },
      closeAll: function(){
        this.close();
        if ( this.$parent ){
          this.$emit("closeall");
        }
      },
      select: function(e, idx){
        var vm = this;
        bbn.fn.log("SELECT");
        if ( e ){
          e.preventDefault();
          e.stopImmediatePropagation();
        }
        if ( !vm.menu[idx].items ){
          if ( vm.mode === 'options' ){
            vm.$set(vm.items[idx], "selected", vm.items[idx].selected ? false : true);
          }
          else if ( (vm.mode === 'selection') && !vm.items[idx].selected ){
            var prev = bbn.fn.search(vm.items, "selected", true);
            if ( prev > -1 ){
              vm.$set(vm.items[prev], "selected", false);
            }
            vm.$set(vm.items[idx], "selected", true);

          }
          if ( vm.menu[idx].click ){
            if ( typeof(vm.menu[idx].click) === 'string' ){
              bbn.fn.log("CLICK IS STRING", vm);
            }
            else if ( $.isFunction(vm.menu[idx].click) ){
              vm.menu[idx].click(e, idx, JSON.parse(JSON.stringify(vm.menu[idx])));
            }
          }
          if ( vm.mode !== 'options' ){
            vm.close();
            if ( vm.parent ){
              vm.$emit("closeall");
            }
          }
        }
      }
    },
    mounted: function(){
      var vm = this,
          offset;
      vm.$nextTick(function(){
        var offset = $(vm.$el).offset(),
            style = {},
            h = $(vm.$el).children().height();
        bbn.fn.log("OFFSET", offset, h);
        if ( vm.bottom ){
          if ( vm.bottom - h < 0 ){
            style.top = '0px';
          }
          else{
            style.top = Math.round(vm.bottom - h) + 'px';
          }
          style.height = Math.round(h + 2) + 'px';
          $(vm.$el).css(style)
        }
      })
      //var vm = this;
    },
    watch:{
      currentIndex: function(newVal){
        if ( (newVal === false) && !this.parent ){
          this.$emit("close");
        }
      }
    }
  });

})(jQuery, bbn, kendo);
