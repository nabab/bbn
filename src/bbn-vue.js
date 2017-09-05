/**
 * Created by BBN on 10/02/2017.
 */
(function($, bbn, kendo){
  "use strict";

  Vue.mixin({
    methods: {
      _: bbn._
    }
  });

  Vue.directive('bbn-fill-height', {
    bind(el, binding, vnode, oldVnode){
      //bbn.fn.log("BOUNMD!!!", el, "FROM");
    },
    inserted(el, binding, vnode){
      bbn.vue.setResizeDirective(binding.name, el, vnode);
    },
    updated(el, binding, vnode, oldVnode){
      //bbn.fn.log("UPDATED FILL HEIGHT");
      //bbn.fn.fillHeight(el);
    },
    componentUpdated(el, binding, vnode, oldVnode){
      //bbn.fn.log("UPDATED COMPONENT FILL HEIGHT");
      //bbn.fn.fillHeight(el);
    },
    unbind(el, binding, vnode, oldVnode){
      bbn.vue.unsetResizeDirective(binding.name, el, vnode);
    }
  });

  Vue.directive('bbn-fill-width', {
    inserted(el, binding, vnode, oldVnode){
      bbn.vue.setResizeDirective(binding.name, el, vnode);
    },
    updated(el, binding, vnode, oldVnode){
      //bbn.fn.log("UPDATED FILL WIDTH");
      //bbn.fn.fillWidth(el);
    },
    componentUpdated(el, binding, vnode, oldVnode){
      //bbn.fn.log("UPDATED COMPONENT FILL WIDTH");
      //bbn.fn.fillWidth(el);
    },
    unbind(el, binding, vnode, oldVnode){
      //bbn.fn.log("UNBOUND FILL WIDTH");
      if ( vnode.componentInstance ){
        let fn = $(el).data("bbnVueFillWidth");
        if ( fn ){
          /** We pick the closest resizable element, i.e. one which  */
          let closestResizable = bbn.vue.is(vnode.componentInstance, ".bbn-resize-emitter") ? vnode.componentInstance : bbn.vue.closest(vnode.componentInstance, ".bbn-resize-emitter");
          if ( closestResizable ){
            /** We put the listener */
            closestResizable.$off("resize", fn);
          }
          else{
            $(window).off("resize", fn);
          }
        }
      }
    }
  });

  /*
  Vue.directive('bbn-resizable', {
    inserted(el, binding, vnode, oldVnode){
      $(el).addClass(".bbn-resizable");
      let closestResizable = false;
      if ( vnode.componentInstance ){
        closestResizable = bbn.vue.is(vnode.componentInstance, ".bbn-resizable") ? vnode.componentInstance : bbn.vue.closest(vnode.componentInstance, ".bbn-resizable");
        if ( closestResizable ){
          closestResizable.$on("resize", () => {
            bbn.fn.log("Emiting resize", el);
            this.$emit("resize");
          })
        }
      }
      bbn.fn.log("INSERTED RESIZABLE", el, binding, vnode, oldVnode, rt);
    }
  });
*/

  bbn.vue = {
    defaultLocalURL: false,
    defaultLocalPrefix: '',
    localURL: false,
    isNodeJS: false,
    localPrefix: '',
    loadingComponents: [],
    loadedComponents: [],
    existingComponents: [
      'autocomplete',
      'button',
      'chart',
      'checkbox',
      'code',
      'colorpicker',
      'combo',
      'context',
      'dashboard',
      'datepicker',
      'datetimepicker',
      'dropdown',
      'dropdowntreeview',
      'fisheye',
      'footer',
      'form',
      'initial',
      'input',
      'json-editor',
      'list',
      'loader',
      'loading',
      'markdown',
      'masked',
      'menu',
      'menu-button',
      'message',
      'multiselect',
      'notification',
      'numeric',
      'popup',
      'popup-footer',
      'radio',
      'rte',
      'scroll',
      'scroll-x',
      'scroll-y',
      'search',
      'slider',
      'splitter',
      'tab',
      'table',
      'tabnav',
      'textarea',
      'timepicker',
      'toolbar',
      'tree',
      'tree-node',
      'treemenu',
      'tree-input',
      'upload',
      'vlist',
      'widget'
    ],
    /**
     * Makes the dataSource variable suitable to be used by the kendo UI widget
     * @param vm Vue object
     * @returns object
     */
    toKendoDataSource(vm){
      let text = vm.widgetOptions.dataTextField || vm.sourceText,
          value = vm.widgetOptions.dataValueField || vm.sourceValue;
      let transform = (src) => {
        let type = typeof(src),
            isArray = Array.isArray(src);
        if ( (type === 'object') && !isArray ){
          let tmp = [];
          $.each(src, (n, a) => {
            let tmp2 = {};
            tmp2[text] = (typeof a) === 'string' ? a : n;
            tmp2[value] = n;
            tmp.push(tmp2);
          });
          return tmp;
        }
        else if ( isArray && src.length && (typeof(src[0]) !== 'object') ){
          return $.map(src, (a) => {
            let tmp = {};
            tmp[text] = a;
            tmp[value] = a;
            return tmp;
          });
        }
        return src;
      };
      if ( typeof(vm.source) === 'string' ){
        if ( vm.$options.propsData.filterValue && !vm._isFilterValueWatched ){
          vm.$watch("filterValue", () => {
            vm.widget.dataSource.read();
          }, {deep: true});
          vm._isFilterValueWatched = true;
        }
        return new kendo.data.DataSource({
          transport:{
            read(e){
              let dt;
              if ( vm.filterValue !== undefined ){
                dt = {};
                if ( vm.param ){
                  dt[param] = vm.filterValue;
                }
                else if ( typeof(vm.filterValue) === 'object' ){
                  $.extend(dt, vm.filterValue);
                }
                else{
                  dt.value = vm.filterValue;
                }
              }
              else {
                dt = e.data;
              }
              bbn.fn.post(vm.source, dt, (d) => {
                if ( d.data ){
                  e.success(transform(d.data));
                }
                else if ( d ){
                  e.success(transform(d));
                }
              });
            }
          }
        });
      }
      else if ( text && value ){
        return transform(vm.source);
      }
      else{
        return [];
      }
    },

    isKendo(vm){
      return (vm.widgetName.indexOf("kendo") === 0);
    },

    /**
     * Supposed to give the data in an appropriate way
     * @todo Remove or do something
     * @param vm Vue object
     * @returns {{}}
     */
    treatData(vm){

      let cfg = {};
      if ( vm.$options.props.cfg && (vm.$options.props.cfg.default !== undefined) ){
        $.extend(cfg, $.isFunction(vm.$options.props.cfg.default) ? vm.$options.props.cfg.default() : vm.$options.props.cfg.default);
      }
      $.each(vm.$options.propsData, (n, a) => {
        cfg[n] = a;
      });
      if ( vm.$options.propsData.cfg ){
        $.extend(cfg,
          typeof(vm.$options.propsData.cfg) === 'string' ?
            JSON.parse(vm.$options.propsData.cfg) :
            vm.$options.propsData.cfg
        );
      }
      return {
        widgetCfg: cfg
      };
    },

    getOptions2(vm, obj){
      if ( !obj || (typeof(obj) !== 'object') ){
        obj = {};
      }
      let r = {};
      bbn.fn.log("getOptioons2");
      return $.extend(obj, r, this.widgetOptions);
    },

    /**
     *
     * @param vm Vue object
     * @returns {{}}
     */
    getOptions(vm, obj){
      if ( !obj || (typeof(obj) !== 'object') ){
        obj = {};
      }
      let tmp = bbn.vue.treatData(vm),
          r = tmp.widgetCfg;
      if ( r.source && vm.widgetName && (vm.widgetName.indexOf("kendo") === 0) ){
        r.dataSource = vm.dataSource;
        delete r.source;
      }
      if ( r.ivalue ){
        delete r.ivalue;
      }
      if ( r.name ){
        delete r.name;
      }
      return $.extend(obj, r);
    },

    setComponentRule(url, prefix){
      if ( url ){
        bbn.vue.localURL = url;
        if ( bbn.vue.localURL.substr(-1) !== '/' ){
          bbn.vue.localURL += '/';
        }
        bbn.vue.localPrefix = prefix || '';
      }
    },

    setDefaultComponentRule(url, prefix){
      if ( url ){
        bbn.vue.defaultLocalURL = url;
        bbn.vue.defaultLocalPrefix = prefix || '';
        bbn.vue.setComponentRule(url, prefix);
      }
    },

    unsetComponentRule(){
      bbn.vue.localURL = bbn.vue.defaultLocalURL;
      bbn.vue.localPrefix = bbn.vue.defaultLocalPrefix;
    },

    addComponent(name){
      if ( bbn.vue.localURL ){
        let componentName = bbn.fn.replaceAll("/", "-", name);
        if ( bbn.vue.localPrefix ){
          componentName = bbn.vue.localPrefix + '-' + componentName;
        }
        bbn.vue.announceComponent(componentName, bbn.vue.localURL + name);
      }
    },

    announceComponent(name, url){
      if ( !bbn.vue.isNodeJS && (typeof(name) === 'string') && (Vue.options.components[name] === undefined) ){
        Vue.component(name, (resolve, reject) => {
          bbn.fn.post(url, (r) => {
            if ( r.script ){
              if ( r.css ){
                $(document.head).append('<style>' + r.css + '</style>');
              }
              if ( r.content ){
                $(document.body).append('<script type="text/x-template" id="bbn-tpl-component-' + name + '">' + r.content + '</script>');
              }
              //let data = r.data || {};
              eval(r.script);
              resolve('ok');
              return;
            }
            reject();
          })
        });
      }
    },

    defineComponents(){
      if ( !bbn.vue.loadedComponents.length && !bbn.vue.isNodeJS ){
        $.each(bbn.vue.existingComponents, (i, a) => {
          bbn.vue.loadedComponents.push('bbn-' + a);
          /** @var string bbn_root_url */
          /** @var string bbn_root_dir */
          Vue.component('bbn-' + a, (resolve, reject) => {
            let url = bbn_root_url + bbn_root_dir + 'components/' + a + "/?component=1";

            if ( bbn.env.isDev ){
              url += '&test';
            }
            bbn.fn.ajax(url, "script")
              .then((res) => {
                let prom = typeof(res) === 'string' ? eval(res) : res;
                prom.then((r) => {
                  // r is the answer!
                  if ( (typeof(r) === 'object') && r.script ){
                    if ( r.html && r.html.length ){
                      $.each(r.html, (j, h) => {
                        if ( h && h.content ){
                          let id = 'bbn-tpl-component-' + a + (h.name === a ? '' : '-' + h.name),
                              $tpl = $('<script type="text/x-template" id="' + id + '"></script>');
                          $tpl.html(h.content);
                          document.body.appendChild($tpl[0]);
                        }
                      })
                    }
                    if ( r.css ){
                      $(document.head).append('<style>' + r.css + '</style>');
                    }
                    setTimeout(() => {
                      r.script();
                      if ( resolve !== undefined ){
                        resolve(prom);
                      }
                    }, 0);
                    return prom;
                  }
                  reject();
                })
              })
          });
        });
      }
    },

    eventsComponent: {
      methods: {
        click(e){
          this.$emit('click', e)
        },
        blur(e){
          this.$emit('blur', e)
        },
        focus(e){
          this.$emit('focus', e)
        },
        keyup(e){
          e.stopImmediatePropagation();
          this.$emit('keyup', e)
        },
        keydown(e){
          e.stopImmediatePropagation();
          this.$emit('keydown', e)
        },
        change(e){
          this.$emit('change', e)
        },
        over(e){
          this.$emit('over', e);
          setTimeout(() => {
            this.$emit('hover', true, e);
          }, 0)
        },
        out(e){
          this.$emit('out', e);
          setTimeout(() => {
            this.$emit('hover', false, e);
          }, 0)
        },
      }
    },

    dataSourceComponent: {
      props: {
        source: {
          type: [Array, Object, String],
          default(){
            return [];
          }
        },
        sourceText: {
          type: String,
          default: "text"
        },
        sourceValue: {
          type: String,
          default: "value"
        }
      },
      methods: {
        getOptions(obj){
          let cfg = bbn.vue.getOptions2(this, obj);
          if ( this.widgetOptions.dataTextField || this.sourceText ){
            cfg.dataTextField = this.widgetOptions.dataTextField || this.sourceText;
          }
          if ( this.widgetOptions.dataValueField || this.sourceValue ){
            cfg.dataValueField = this.widgetOptions.dataValueField || this.sourceValue;
          }
          cfg.dataSource = this.dataSource;
          return cfg;
        },
        getOptions2(obj){
          let cfg = bbn.vue.getOptions2(this, obj);
          if ( this.widgetOptions.dataTextField || this.sourceText ){
            cfg.dataTextField = this.widgetOptions.dataTextField || this.sourceText;
          }
          if ( this.widgetOptions.dataValueField || this.sourceValue ){
            cfg.dataValueField = this.widgetOptions.dataValueField || this.sourceValue;
          }
          cfg.dataSource = this.dataSource;
          return cfg;
        }
      },
      computed: {
        dataSource(){
          return bbn.vue.toKendoDataSource(this)
        }
      },
      watch:{
        source: function(newDataSource){
          if ( this.widget ){
            this.widget.setDataSource(this.dataSource);
          }
        }
      }
    },

    memoryComponent: {
      props: {
        memory: {
          type: [Object, Function]
        },

      }
    },

    inputComponent: {
      props: {
        value: {},
        name: {
          type: String
        },
        placeholder: {
          type: String
        },
        required: {
          type: Boolean,
          default: false
        },
        disabled: {
          type: Boolean,
          default: false
        },
        readonly: {
          type: Boolean,
          default: false
        },
        size: {
          type: Number
        },
        maxlength: {
          type: [String, Number]
        },
      },
      methods: {
        emitInput(val){
          this.$emit('input', val);
        }
      },
      mounted(){
        this.$emit("ready");
      },
      watch:{
        disabled(newVal){
          if ( this.widget && $.isFunction(this.widget.enable) ){
            this.widget.enable(!newVal);
          }
          else if ( this.widget && this.widgetName && $.isFunction(this.widget[this.widgetName]) ){
            this.widget[this.widgetName](newVal ? "disable" : "enable");
          }
          else if ( $(this.$el).is("input") ){
            if ( newVal ){
              $(this.$el).attr("disabled", true).addClass("k-state-disabled");
            }
            else{
              $(this.$el).attr("disabled", false).removeClass("k-state-disabled");
            }
          }
          else if ( this.$refs.input ){
            if ( newVal ){
              $(this.$refs.input).attr("disabled", true).addClass("k-state-disabled");
            }
            else{
              $(this.$refs.input).attr("disabled", false).removeClass("k-state-disabled");
            }
          }
        },
        value(newVal){
          if ( this.widget && (this.widget.value !== undefined) ){
            bbn.fn.log("Widget change");
            if ( $.isFunction(this.widget.value) ){
              if ( this.widget.value() !== newVal ){
                this.widget.value(newVal);
              }
            }
            else{
              if ( this.widget.value !== newVal ){
                this.widget.value = newVal;
              }
            }
          }
        },
        cfg(){

        }
      }
    },

    optionComponent: {
      methods: {
        getOptions(){
          return bbn.vue.getOptions(this);
        },
      }
    },

    widgetComponent: {
      props: {
        cfg: {
          type: Object,
          default(){
            return {};
          }
        },
        widgetOptions: {
          type: Object,
          default(){
            return {};
          }
        }
      },
      beforeDestroy(){
        bbn.fn.log("Default destroy");
        //this.destroy();
      },
      methods: {
        destroy(){
          const vm = this;
          /*
          if ( vm.widget && $.isFunction(vm.widget.destroy) ){
            vm.widget.destroy();
            vm.widget = false;
            if ( vm.$refs.element ){
              let $ele = $(vm.$refs.element).removeAttr("style");
              while ( $ele.parent()[0] !== vm.$el ){
                $ele.unwrap();
              }
              bbn.fn.log("Moving element", $ele);
              if ( vm.widgetName ){
                $ele.removeAttr("data-role").removeAttr("style").removeData(this.widgetName);
              }
            }
            else if ( this.widgetName ){
              $(this.$el).removeData(this.widgetName);
            }
            if ( this.$refs.input ){
              $(this.$refs.input).appendTo(this.$el)
            }
            $(this.$el).children().not("[class^='bbn-']").remove();
          }
          */
        },
        build(){
          bbn.fn.log("Default build");
        },
        getWidgetCfg(){
          const vm = this;
        },
      }
    },

    // These components will emit a resize event when their closest parent of the same kind gets really resized
    resizerComponent: {
      data(){
        return {
          // The closest resizer parent
          parentResizer: false,
          // The listener on the closest resizer parent
          resizeEmitter: false,
          // Height
          lastKnownHeight: false,
          // Width
          lastKnownWidth: false
        };
      },
      methods: {
        // a function can be executed just before the resize event is emitted
        onResize(){
          return;
        },
        setResizeEvent(){
          // The timeout used in the listener
          let resizeTimeout;
          // This class will allow to recognize the element to listen to
          $(this.$el).addClass("bbn-resize-emitter");
          this.parentResizer = bbn.vue.closest(this, ".bbn-resize-emitter");
          // Setting initial dimensions
          this.lastKnownHeight = this.parentResizer ? Math.round($(this.parentResizer.$el).innerHeight()) : bbn.env.height;
          this.lastKnownWidth = this.parentResizer ? Math.round($(this.parentResizer.$el).innerWidth()) : bbn.env.width;
          // Creating the callback function which will be used in the timeout in the listener
          this.resizeEmitter = () => {
            // Removing previous timeout
            clearTimeout(resizeTimeout);
            // Creating a new one
            resizeTimeout = setTimeout(() => {
              if ( $(this.$el).is(":visible") ){
                // Checking if the parent hasn't changed (case where the child is mounted before)
                let tmp = bbn.vue.closest(this, ".bbn-resize-emitter");
                if ( tmp !== this.parentResizer ){
                  // In that case we reset
                  this.unsetResizeEvent();
                  this.setResizeEvent();
                  tmp.$emit("resize");
                  bbn.fn.log("Emitting from new element", tmp.$el);
                  return;
                }
                let resize = false,
                    h      = this.parentResizer ? Math.round($(this.parentResizer.$el).innerHeight()) : bbn.env.height,
                    w      = this.parentResizer ? Math.round($(this.parentResizer.$el).innerWidth()) : bbn.env.width;
                if ( h && (this.lastKnownHeight !== h) ){
                  this.lastKnownHeight = h;
                  resize = 1;
                }
                if ( w && (this.lastKnownWidth !== w) ){
                  this.lastKnownWidth = w;
                  resize = 1;
                }
                if ( $.isFunction(this.onResize) ){
                  this.onResize();
                }
                if ( resize ){
                  this.$emit("resize");
                  bbn.fn.log("EMITTING FROM RESIZE EMITTER", this.$el);
                }
              }
            }, 0);
          };
          if ( this.parentResizer ){
            //bbn.fn.log("SETTING EVENT FOR PARENT", this.$el, this.parentResizer);
            this.parentResizer.$on("resize", this.resizeEmitter);
          }
          else{
            //bbn.fn.log("SETTING EVENT FOR WINDOW", this.$el);
            $(window).on("resize", this.resizeEmitter);
          }
          this.resizeEmitter();
        },

        unsetResizeEvent(){
          if ( this.resizeEmitter ){
            if ( this.parentResizer ){
              //bbn.fn.log("UNSETTING EVENT FOR PARENT", this.$el, this.parentResizer);
              this.parentResizer.$off("resize", this.resizeEmitter);
            }
            else{
              //bbn.fn.log("UNSETTING EVENT FOR WINDOW", this.$el);
              $(window).off("resize", this.resizeEmitter);
            }
          }
        },

        selfEmit(){
          if ( this.parentResizer ){
            this.parentResizer.$emit("resize");
          }
        }
      },
      mounted(){
        this.setResizeEvent();
      },
      beforeDestroy(){
        this.unsetResizeEvent();
      }
    },

    retrieveRef(vm, path){
      let bits = path.split("."),
          target = vm,
          prop;
      while ( bits.length ){
        prop = bits.shift();
        target = target[prop];
        if ( target === undefined ){
          bbn.fn.log("Impossible to find the target " + path + "(blocking on " + prop + ")");
          break;
        }
      }
      if ( target && (target !== vm) ){
        return target;
      }
      return false;
    },

    is(vm, selector){
      if ( selector && vm ){
        if ( vm.$el && $(vm.$el).is(selector) ){
          return true;
        }
        if ( vm.$vnode && vm.$vnode.componentOptions && (vm.$vnode.componentOptions.tag === selector) ){
          return true;
        }
      }
      return false;
    },

    closest(vm, selector){
      while ( vm && vm.$parent && (vm !== vm.$parent) ){
        if ( bbn.vue.is(vm.$parent, selector) ){
          return vm.$parent;
        }
        vm = vm.$parent;
      }
      return false;
    },

    getChildByKey(vm, key, selector){
      for ( var i = 0; i < vm.$children.length; i++ ){
        let obj = vm.$children[i];
        if (
          obj.$el &&
          obj.$vnode &&
          obj.$vnode.data &&
          obj.$vnode.data.key &&
          (obj.$vnode.data.key === key)
        ){
          if ( selector ){
            return bbn.vue.is(obj, selector) ? obj : false;
          }
          return obj;
        }
      }
      return false;
    },

    find(cp, selector){
      let cps = bbn.vue.getComponents(cp);
      for ( let i = 0; i < cps.length; i++ ){
        if ( bbn.vue.is(cps[i], selector) ){
          return cps[i];
        }
      }
    },

    findAll(cp, selector){
      let cps = bbn.vue.getComponents(cp),
          res = [];
      for ( let i = 0; i < cps.length; i++ ){
        if (
          $(cps[i].$el).is(selector) ||
          (cps[i].$vnode.componentOptions && (cps[i].$vnode.componentOptions.tag === selector))
        ){
          res.push(cps[i]);
        }
      }
      return res;
    },

    getComponents(cp, ar){
      if ( !Array.isArray(ar) ){
        ar = [];
      }
      $.each(cp.$children, function(i, obj){
        ar.push(obj)
        if ( obj.$children ){
          bbn.vue.getComponents(obj, ar);
        }
      });
      return ar;
    },

    setResizeDirective(name, el, vnode){
      let fName,
          dName;
      if ( name === 'bbn-fill-height' ){
        fName = 'fillHeight';
        dName = 'bbnVueFillHeight';
      }
      else if ( name === 'bbn-fill-width' ){
        fName = 'fillWidth';
        dName = 'bbnVueFillWidth';
      }
      // The function we'll use on el
      if ( fName ){
        // We add the directive's name as class
        $(el).addClass(name);
        // vnode.context is the object
        if ( vnode.context ){
          // Executing directive's function
          $(el).data(dName, () => {
            if ( bbn.fn[fName](el, true) ){
              bbn.fn.log("Emitting from vnode", vnode.context.$el);
              vnode.context.$emit("resize")
            }
          });
          /** We pick the closest resizable element, i.e. one which  */
          let closestResizable = bbn.vue.is(vnode.context, ".bbn-resize-emitter") ? vnode.context : bbn.vue.closest(vnode.context, ".bbn-resize-emitter");
          $(el).data(dName + 'Parent', closestResizable);
          if ( closestResizable ){
            /** We put the listener */
            closestResizable.$on("resize", () => {
              let tmp = bbn.vue.is(vnode.context, ".bbn-resize-emitter") ? vnode.context : bbn.vue.closest(vnode.context, ".bbn-resize-emitter");
              if ( tmp !== $(el).data(dName + 'Parent') ){
                let fn = $(el).data(dName);
                if ( fn ){
                  /** We put the listener */
                  $(el).data(dName + 'Parent').$off("resize", fn);
                }
                bbn.vue.setResizeDirective(name, el, vnode);
                bbn.fn.log("Emitting from DIRECTIVE PARENT", vnode.context.$el);
                tmp.$emit("resize");
                return;
              }
              $(el).data(dName)();
            });
            setTimeout(() => {
              closestResizable.$emit("resize");
            }, 10)
          }
          else{
            $(window).on("resize", () => {
              let tmp = bbn.vue.is(vnode.context, ".bbn-resize-emitter") ? vnode.context : bbn.vue.closest(vnode.context, ".bbn-resize-emitter");
              if ( tmp ){
                let fn = $(el).data(dName);
                if ( fn ){
                  /** We put the listener */
                  $(window).off("resize", fn);

                }
                bbn.vue.setResizeDirective(name, el, vnode);
                bbn.fn.log("EMITTING FROM NEW DIRECTIVE", tmp.$el)
                tmp.$emit("resize");
                return;
              }
              //bbn.fn.log("resizing", el, "FROM WINDOW");
              $(el).data(dName)();
            });
            setTimeout(() => {
              $(window).trigger("resize");
            }, 10)
          }
        }
        else{
          bbn.fn[fName](el);
        }

      }
    },

    unsetResizeDirective(name, el, vnode, target){
      let dName;
      if ( name === 'bbn-fill-height' ){
        dName = 'bbnVueFillHeight';
      }
      else if ( name === 'bbn-fill-width' ){
        dName = 'bbnVueFillWidth';
      }
      if ( dName && vnode.context ){
        let fn = $(el).data(dName);
        if ( fn ){
          /** We pick the closest resizable element, i.e. one which  */
          let closestResizable = bbn.vue.is(vnode.context, ".bbn-resize-emitter") ? vnode.context: bbn.vue.closest(vnode.context, ".bbn-resize-emitter");
          if ( closestResizable ){
            /** We put the listener */
            closestResizable.$off("resize", fn);
          }
          else{
            $(window).off("resize", fn);
          }
        }
      }
    },

    makeUID(){
      return bbn.fn.randomString(32);
    }
  };

  bbn.vue.fullComponent = bbn.fn.extend({}, bbn.vue.inputComponent, bbn.vue.optionComponent, bbn.vue.eventsComponent, bbn.vue.widgetComponent);

  bbn.vue.defineComponents()

})(jQuery, bbn, kendo);
