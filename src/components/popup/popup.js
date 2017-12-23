/**
 * Created by BBN on 15/02/2017.
 */
(function($, bbn, kendo){
  "use strict";

  /**
   * Classic input with normalized appearance
   */
  Vue.component('bbn-popup', {
    mixins: [bbn.vue.basicComponent, bbn.vue.resizerComponent],
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
        items: []
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
      showPopup(){
        return this.items.length > 0;
      },
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
                let tmp = eval(r.script);
                if ( $.isFunction(tmp) ){
                  d.open = tmp;
                }
                // anonymous vuejs component initialization
                else if ( typeof(tmp) === 'object' ){
                  bbn.fn.extend(tmp, {
                    name: bbn.fn.randomString(20, 15).toLowerCase(),
                    template: '<div class="bbn-full-screen">' + (r.content || '') + '</div>',
                    props: ['source']
                  });
                  this.$options.components[tmp.name] = tmp;
                  d.component = this.$options.components[tmp.name];
                  d.source = r.data || [];
                }
              }
              $.extend(d, r);
              delete d.url;
              delete d.data;
              if ( !d.uid ){
                d.uid = 'bbn-popup-' + bbn.fn.timestamp().toString()
              }
              d.index = this.items.length;
              this.items.push(d);
              this.makeWindows();
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
<div class="bbn-100 bbn-flex-height">
  <div class="bbn-w-100 bbn-flex-fill">
    <div class="bbn-lpadded bbn-lg">` + o.content + `</div>
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
            //bbn.fn.center(ele);
            /*
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
                    this.selfEmit();
                  },
                  stop: () => {
                    this.center(idx);
                    this.selfEmit();
                  }
                });
              }
            }
            let scroll = this.getWindow(idx).$refs.scroll;
            if ( scroll ){
              if ( scroll[0] ){
                scroll[0].onResize();
              }
              else{
                scroll.onResize();
              }
            }
            */
          })
        }
      },

      makeWindows(){
        this.$forceUpdate();
        this.$nextTick(() => {
          $.each(this.items, (i, a) => {
            //this.center(i);
          })
        })
      },

      getWindow(idx){
        if ( this.popups.length ){
          if ( idx === undefined ){
            idx = this.popups.length - 1;
          }
          if ( this.popups[idx] ){
            //return bbn.vue.getChildByKey(this.$children[0], this.popups[idx].uid);
            return bbn.vue.getChildByKey(this, this.popups[idx].uid);
          }
        }
        return false;
      }
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
        mixins: [bbn.vue.basicComponent, bbn.vue.resizerComponent],
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
          footer: {
            type: [Function, String, Object]
          },
          beforeClose: {
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
          },
          draggable: {
            type: Boolean,
            default: true
          },
          resizable: {
            type: Boolean,
            default: true
          }
        },
        data(){
          let fns = [];
          if ( this.onClose ){
            fns.push(this.onClose);
          }
          return {
            isMaximized: this.maximized,
            widthUnit: (typeof this.width === 'string') && (this.width.substr(-1) === '%') ? '%' : 'px',
            realWidth: parseInt(this.width),
            heightUnit: (typeof this.height === 'string') && (this.height.substr(-1) === '%') ? '%' : 'px',
            realHeight: parseInt(this.height),
            closingFunctions: fns,
            popup: false
          }
        },

        computed: {
          top(){
            if ( !this.popup || this.isMaximized ){
              return 0;
            }
            if ( this.heightUnit === '%' ){
              return Math.round((100 - parseInt(this.realHeight)) / 2);
            }
            return Math.round((this.popup.lastKnownHeight - parseInt(this.realHeight)) / 2);
          },
          left(){
            if ( !this.popup || this.isMaximized ){
              return 0;
            }
            if ( this.widthUnit === '%' ){
              return Math.round((100 - parseInt(this.realWidth)) / 2);
            }
            return Math.round((this.popup.lastKnownWidth - parseInt(this.realWidth)) / 2);
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
              ev = $.Event('beforeClose');
              this.popup.$emit('beforeClose', ev, this);
              if ( ev.isDefaultPrevented() ){
                return;
              }
              if ( this.beforeClose && (this.beforeClose(this) === false) ){
                return;
              }
              $.each(this.closingFunctions, (i, a) => {
                a(this, ev);
              });
            }
            if ( !ev.isDefaultPrevented() ){
              this.$el.style.display = 'block';
              this.$nextTick(() => {
                this.$emit("close", this);
                if ( this.afterClose ){
                  this.afterClose(this);
                }
              })
            }
          },
          onShow(){
            this.selfEmit(true);
            if ( this.draggable ){
              $(this.$el).draggable({
                handle: ".bbn-popup-title",
                containment: ".bbn-popup"
              });
            }
            if ( this.resizable ){
              $(this.$el).resizable({
                handles: "se",
                containment: ".bbn-popup",
                resize: () => {
                  this.selfEmit();
                },
                stop: () => {
                  this.realWidth = parseFloat($(this.$el).css("width"));
                  this.realHeight = parseFloat($(this.$el).css("height"));
                  //this.center();
                  this.selfEmit();
                }
              });
            }
          }
        },
        created(){
          this.popup = bbn.vue.closest(this, 'bbn-popup');
        },
        mounted(){
          this.$el.style.display = 'block';
        },
        watch: {
          isMaximized(){
            //; $forceUpdate(); center(index)
          }
        }
      }
    }
  });

})(jQuery, bbn, kendo);
