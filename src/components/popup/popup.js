/**
 * Created by BBN on 15/02/2017.
 */
(function($, bbn, kendo){
  "use strict";

  /**
   * Classic input with normalized appearance
   */
  Vue.component('bbn-popup', {
    template: "#bbn-tpl-component-popup",
    mixins: [bbn.vue.resizerComponent],
    props: {
      untitled: {
        type: String,
        default: bbn._("Untitled")
      },
      source: {
        type: Array,
        default: function(){
          return [];
        }
      },
      zIndex: {
        type: Number,
        default: 1
      }
    },

    data: function(){
      return {
        num: this.source.length,
        showPopup: false
      }
    },

    methods: {
      open(obj){
        let d = {};
        if ( typeof(obj) !== 'object' ){
          for ( let i = 0; i < arguments.length; i++ ){
            if ( !d.content && (typeof(arguments[i]) === 'string') ){
              d.content = arguments[i];
            }
            else if ( bbn.fn.isDimension(arguments[i]) || (arguments[i] === 'auto') ){
              if ( !d.width ){
                d.width = arguments[i];
              }
              else if ( !d.height ){
                d.height = arguments[i];
              }
            }
            else if ( !d.title && (typeof(arguments[i]) === 'string') ){
              d.title = arguments[i];
            }
            else if ( $.isFunction(arguments[i]) ){
              if ( !d.open ){
                d.open = arguments[i];
              }
              else if ( !d.close ){
                d.close = arguments[i];
              }
            }
            else if ( typeof(arguments[i]) === 'object' ){
              d.options = arguments[i];
            }
          }
          if ( !d.height ){
            d.height = false;
          }
        }
        else{
          d = obj;
        }
        if ( d ){
          if ( !d.ref ){
            d.ref = 'bbn-popup-' + bbn.fn.timestamp().toString()
          }
          this.source.push(d);
          this.makeWindows();
          return d.ref;
        }
        else{
          new Error("You must give a title and either a content or a component to a popup")
        }
        return false;
      },

      getObject(from){
        let a = $.extend({}, from);
        if ( !a.ref ){
          a.ref = 'bbn-popup-' + bbn.fn.timestamp().toString()
        }
        if ( !a.title && this.untitled ){
          a.title = this.untitled;
        }
        if ( !a.component && !a.content ){
          a.content = ' ';
        }
        if ( !a.width ){
          a.width = 'auto';
        }
        else if ( typeof(a.width) === 'number' ){
          a.width = a.width.toString() + 'px';
        }
        if ( !a.height ){
          a.height = 'auto';
        }
        else if ( typeof(a.height) === 'number' ){
          a.height = a.height.toString() + 'px';
        }
        return a;
      },

      close(idx, force){
        if ( this.popups[idx] ){
          let ok = true;
          if ( !force && $.isFunction(this.popups[idx].close) ){
            ((ele, data) => {
              ok = this.popups[idx].close(this, idx);
            })($(".k-window", this.$el).eq(idx), this.popups[idx].data || {});
          }
          if ( ok !== false ){
            this.source.splice(idx, 1);
          }
        }
      },

      center(idx){
        if ( this.popups[idx] ){
          this.$nextTick(() => {
            bbn.fn.log("CENTERING " + idx.toString());
            let ele = $(".k-window", this.$el).eq(idx);
            bbn.fn.center(ele);
            if ( !ele.hasClass("ui-draggable") ){
              ele.draggable({
                handle: ".k-window-title",
                containment: ".bbn-popup"
              }).resizable({
                handles: "se",
                containment: ".bbn-popup",
                resize: () => {
                  bbn.fn.redraw(ele, true);
                  this.$emit("resize");
                },
                stop: () => {
                  this.center(idx);
                  this.$emit("resize");
                }
              });
            }
          })
        }
      },

      makeWindows(){
        this.$forceUpdate();
        this.$nextTick(() => {
          $.each(this.popups, (i, a) => {
            this.center(i);
          })
        })
      }
    },

    computed: {
      popups(){
        let r = [];
        $.each(this.source, (i, a) => {
          r.push(this.getObject($.extend({index: i}, a)));
        });
        return r;
      },
    },

    mounted(){
      $.each(this.popups, (i, a) => {
        this.makeWindow(a);
      })
    },

    watch: {
      source: function(){
        this.num = this.source.length;
        this.makeWindows()
      }
    },

    updated(){}
  });

})(jQuery, bbn, kendo);
