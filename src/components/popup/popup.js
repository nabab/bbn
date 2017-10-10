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
      defaultWidth: {
        type: [String, Number],
        default: '70%'
      },
      defaultHeight: {
        type: [String, Number],
        default: '70%'
      },
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
        default: 10
      },
      alertTitle: {
        type: String,
        default: bbn._("Error")
      },
      alertText: {
        type: String,
        default: bbn._("There was a problem...")
      },
      confirmTitle: {
        type: String,
        default: bbn._("Confirmation request")
      },
      confirmText: {
        type: String,
        default: bbn._("Are you sure?")
      },
      okText: {
        type: String,
        default: bbn._("OK")
      },
      yesText: {
        type: String,
        default: bbn._("Yes")
      },
      noText: {
        type: String,
        default: bbn._("No")
      },
    },

    data: function(){
      return {
        items: [],
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
            else if ( bbn.fn.isDimension(arguments[i]) ){
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
          if ( !d.uid ){
            d.uid = 'bbn-popup-' + bbn.fn.timestamp().toString()
          }
          d.index = this.items.length;
          this.items.push(d);
          this.makeWindows();
          return d.uid;
        }
        else{
          new Error("You must give a title and either a content or a component to a popup")
        }
        return false;
      },

      load(obj){
        let d = {};
        if ( typeof(obj) !== 'object' ){
          for ( let i = 0; i < arguments.length; i++ ){
            if ( !d.url && (typeof(arguments[i]) === 'string') ){
              d.url = arguments[i];
            }
            else if ( bbn.fn.isDimension(arguments[i]) || (arguments[i] === 'auto') ){
              if ( !d.width ){
                d.width = arguments[i];
              }
              else if ( !d.height ){
                d.height = arguments[i];
              }
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
              if ( !d.data ){
                d.data = arguments[i];
              }
              else if ( !d.options ){
                d.options = arguments[i];
              }
            }
          }
          if ( !d.height ){
            d.height = false;
          }
        }
        else{
          d = obj;
        }
        if ( d.url ){
          bbn.fn.post(d.url, d.data || {}, (r) => {
            if ( r.content || r.title ){
              if ( r.script ){
                var tmp = eval(r.script);
                if ( $.isFunction(tmp) ){
                  d.open = tmp;
                }
              }
              $.extend(d, r);
              delete d.url;
              delete d.data;
              this.items.push(d);
            }
          })
        }
        else{
          new Error("You must give a URL in order to load a popup")
        }
      },

      getObject(from){
        let a = $.extend({}, from);
        if ( !a.uid ){
          a.uid = 'bbn-popup-' + bbn.fn.timestamp().toString()
        }
        if ( !a.title && this.untitled ){
          a.title = this.untitled;
        }
        if ( !a.component && !a.content ){
          a.content = ' ';
        }
        if ( !a.width ){
          a.width = this.defaultWidth;
        }
        else if ( typeof(a.width) === 'number' ){
          a.width = a.width.toString() + 'px';
        }
        if ( !a.height ){
          a.height = this.defaultHeight;
        }
        else if ( typeof(a.height) === 'number' ){
          a.height = a.height.toString() + 'px';
        }
        return a;
      },

      close(idx, force){
        if ( idx === undefined ){
          idx = this.items.length - 1;
        }
        let win = this.getWindow(idx);
        if ( this.items[idx] && win ){
          win.close(idx, force);
          this.$forceUpdate();
        }
      },

      getIndexByUID(uid){
        return bbn.fn.search(this.items, {uid: uid});
      },

      alert(o){
        if ( typeof(arguments[0]) !== 'object' ){
          let options = {},
              has_msg = false,
              has_title = false,
              has_width = false,
              has_callback = false,
              i;
          o = {};
          for ( i = 0; i < arguments.length; i++ ){
            if ( !has_msg && (typeof(arguments[i]) === 'string') ){
              o.content = arguments[i];
              has_msg = 1;
            }
            else if ( bbn.fn.isDimension(arguments[i]) || (arguments[i] === 'auto') ){
              if ( has_width ){
                o.height = arguments[i];
              }
              else{
                o.width = arguments[i];
                has_width = 1;
              }
            }
            else if ( !has_title && (typeof arguments[i] === 'string') ){
              o.title = arguments[i];
            }
            else if ( typeof arguments[i] === 'string' ){
              o.okText = arguments[i];
            }
            else if ( $.isFunction(arguments[i]) ){
              if ( has_callback ){
                o.close = arguments[i];
              }
              else{
                o.open = arguments[i];
                has_callback = 1;
              }
            }
            else if ( typeof arguments[i] === 'object' ){
              o.options = arguments[i];
            }
          }
        }
        if ( typeof(o) === 'object' ){
          if ( !o.content ){
            o.content = this.alertText;
          }
          if ( !o.title ){
            o.title = this.alertTitle;
          }
          if ( !o.okText ){
            o.okText = this.okText;
          }
          o.content = '<div class="bbn-lpadded">' + o.content + '</div>';
          this.open($.extend(o, {
            maximizable: false,
            closable: false
          }));

        }
      },

      confirm(o){
        let onYes = false,
            onNo = false;
        if ( typeof(o) !== 'object' ){
          o = {};
          let options = {},
              has_msg = false,
              has_title = false,
              has_yes = false,
              has_width = false,
              i;
          for ( i = 0; i < arguments.length; i++ ){
            if ( !has_msg && (typeof(arguments[i]) === 'string') ){
              o.content = arguments[i];
              has_msg = 1;
            }
            else if ( bbn.fn.isDimension(arguments[i]) || (arguments[i] === 'auto') ){
              if ( has_width ){
                o.height = arguments[i];
              }
              else{
                o.width = arguments[i];
                has_width = 1;
              }
            }
            else if ( !has_title && (typeof arguments[i] === 'string') ){
              o.title = arguments[i];
            }
            else if ( !has_yes && (typeof arguments[i] === 'string') ){
              o.yesText = arguments[i];
            }
            else if ( typeof(arguments[i]) === 'string' ){
              o.noText = arguments[i];
            }
            else if ( $.isFunction(arguments[i]) ){
              if ( onYes ){
                onNo = arguments[i];
              }
              else{
                onYes = arguments[i];
              }
            }
            else if ( typeof(arguments[i]) === 'object' ){
              options = arguments[i];
            }
          }
        }
        if ( typeof(o) === 'object' ){
          if ( !o.content ){
            o.content = this.confirmText;
          }
          if ( !o.title ){
            o.title = this.confirmTitle;
          }
          if ( !o.yesText ){
            o.yesText = this.yesText;
          }
          if ( !o.noText ){
            o.noText = this.noText;
          }
          if ( !o.width ){
            o.width = 400;
          }
          if ( !o.height ){
            o.height = 200;
          }
          o.component = {
            template: `
<div class="bbn-100 bbn-lg bbn-flex-height">
  <div class="bbn-w-100 bbn-flex-fill">
    <div class="bbn-lpadded">` + o.content + `</div>
  </div>
  <div class="bbn-popup-footer">
    <bbn-button class="bbn-bg-white bbn-black"
                @click="yes()"
                icon="fa fa-check-circle"
                text="` + o.yesText + `"
    ></bbn-button>
    <bbn-button class="bbn-bg-black bbn-white"
                @click="no()"
                icon="fa fa-times-circle"
                text="` + o.noText + `"
    ></bbn-button>
  </div>
</div>
`,
            data(){
              return {
                window: false
              }
            },
            methods: {
              yes(){
                bbn.fn.log(this.window);
                this.window.close(true);
                if ( onYes ){
                  onYes();
                }
              },
              no(){
                this.window.close(true);
                if ( onNo ){
                  onNo();
                }
              },
            },
            mounted(){
              this.window = bbn.vue.closest(this, 'bbn-window');
              $(this.$el).find(".bbn-button:last").focus();
            }
          };
          this.open($.extend(o, {
            resizable: false,
            maximizable: false,
            closable: false
          }));
        }
      },

      center(idx){
        if ( this.items[idx] ){
          this.$nextTick(() => {
            let ele = $(".bbn-popup-unit", this.$el).eq(idx);
            bbn.fn.center(ele);
            if ( !ele.hasClass("ui-draggable") ){
              if ( this.popups[idx].draggable !== false ){
                ele.draggable({
                  handle: ".bbn-popup-title > span",
                  containment: ".bbn-popup"
                });
              }
              if ( this.items[idx].resizable !== false ){
                ele.resizable({
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
            }
            let scroll = this.getWindow(idx).$refs.scroll;
            if ( scroll && scroll.length ){
              scroll[0].onResize();
            }
          })
        }
      },

      makeWindows(){
        this.$forceUpdate();
        this.$nextTick(() => {
          $.each(this.items, (i, a) => {
            this.center(i);
          })
        })
      },

      getWindow(idx){
        if ( this.popups.length ){
          if ( idx === undefined ){
            idx = this.popups.length - 1;
          }
          if ( this.popups[idx] ){
            return bbn.vue.getChildByKey(this.$children[0], this.popups[idx].uid);
          }
        }
        return false;
      }
    },

    computed: {
      popups(){
        let r = [];
        $.each(this.items, (i, a) => {
          r.push(this.getObject($.extend({index: i}, a)));
        });
        return r;
      },
    },

    mounted(){
      $.each(this.popups, (i, a) => {
        this.open(a);
      })
    },

    watch: {
      items: function(){
        this.makeWindows()
      }
    },

    components: {
      'bbn-window': {
        name: 'bbn-window',
        props: {
          width: {
            type: [String, Number]
          },
          height: {
            type: [String, Number]
          },
          maximizable: {
            type: Boolean,
            default: true
          },
          closable: {
            type: Boolean,
            default: true
          },
          maximized: {
            type: Boolean,
            default: false
          },
          onClose: {
            type: Function
          },
          afterClose: {
            type: Function
          },
          open: {
            type: Function
          },
          source: {
            type: Object,
            default(){
              return {};
            }
          },
          component: {
            type: [String, Function, Object]
          },
          title: {
            type: String,
            default: bbn._("Untitled")
          },
          index: {
            type: Number
          },
          uid: {
            type: String
          },
          content: {
            type: String
          }
        },
        data(){
          let fns = [];
          if ( this.onClose ){
            fns.push(this.onClose);
          }
          return {
            isMaximized: this.maximized,
            realWidth: typeof this.width === 'number' ? this.width + 'px' : this.width,
            realHeight: typeof this.height === 'number' ? this.height + 'px' : this.height,
            closingFunctions: fns,
            popup: false
          }
        },

        methods: {
          addClose(fn){
            for ( let i = 0; i < arguments.length; i++ ){
              if ( typeof arguments[i] === 'function' ){
                this.closingFunctions.push(arguments[i])
              }
            }
          },
          beforeClose(fn){
            for ( let i = 0; i < arguments.length; i++ ){
              if ( typeof arguments[i] === 'function' ){
                this.closingFunctions.unshift(arguments[i])
              }
            }
          },
          removeClose(fn){
            if ( !fn ){
              this.closingFunctions = [];
            }
            else{
              this.closingFunctions = $.grep(this.closingFunctions, (f) => {
                return fn !== f;
              })
            }
          },
          close(force){
            let ev = $.Event('close');
            if ( !force ){
              $.each(this.closingFunctions, (i, a) => {
                a(this, ev);
              });
            }
            if ( !ev.isDefaultPrevented() ){
              this.$emit("close", this);
              if ( this.afterClose ){
                this.afterClose(this);
              }
            }
          }
        },
        mounted(){
          this.popup = this.$parent.$parent;
          /*
          setTimeout(() => {
            if ( this.$refs.scroll ){
              this.$refs.scroll[0].onResize();
            };
            if ( this.open ){
              //this.open(this);
            }
          }, 1000)
          */
        }
      }
    }
  });

})(jQuery, bbn, kendo);
