/**
 * Created by BBN on 10/02/2017.
 */
(function($, bbn, kendo){
  "use strict";

  Vue.mixin({
    methods: {
      _: bbn.fn
    }
  });

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
      'config-editor',
      'dashboard',
      'datepicker',
      'datetimepicker',
      'dropdown',
      'dropdowntreeview',
      'fisheye',
      'form',
      'initial',
      'input',
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
      'radio',
      'rte',
      'search',
      'shortcuts',
      'slider',
      'splitter',
      'tab',
      'table',
      'tabnav',
      'textarea',
      'timepicker',
      'toolbar',
      'tree',
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
    transformDataSource(vm){
      let transform = (src) => {
        let type = typeof(src),
            isArray = $.isArray(src);
        if ( (type === 'object') && !isArray ){
          let tmp = [];
          $.each(src, (n, a) => {
            let tmp2 = {};
            tmp2[vm.widgetCfg.dataTextField] = (typeof a) === 'string' ? a : n;
            tmp2[vm.widgetCfg.dataValueField] = n;
            tmp.push(tmp2);
          });
          return tmp;
        }
        else if ( isArray && src.length && (typeof(src[0]) !== 'object') ){
          return $.map(src, (a) => {
            let tmp = {};
            tmp[vm.widgetCfg.dataTextField] = a;
            tmp[vm.widgetCfg.dataValueField] = a;
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
      return transform(vm.source);
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
      if ( vm.$options.props.cfg ){
        $.extend(cfg, vm.$options.props.cfg.default());
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

    /**
     *
     * @param vm Vue object
     * @returns {{}}
     */
    getOptions(vm){
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
      return r;
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
              if ( r.content ){
                $(document.body).append('<script type="text/x-template" id="bbn-tpl-component-' + name + '">' + r.content + '</script>');
              }
              if ( r.css ){
                $(document.head).append('<style>' + r.css + '</style>');
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
      if ( !bbn.vue.loadedComponents.length ){
        $.each(bbn.vue.existingComponents, (i, a) => {
          bbn.vue.loadedComponents.push('bbn-' + a);
          /** @var string bbn_root_url */
          /** @var string bbn_root_dir */
          Vue.component('bbn-' + a, (resolve, reject) => {
            let url = bbn_root_url + bbn_root_dir + a + "/?component=1";

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
                      resolve('ok');
                    }, 0);
                    return;
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
          this.$emit('keyup', e)
        },
        keydown(e){
          this.$emit('keydown', e)
        },
        change(e){
          this.$emit('change', e)
        },
      }
    },

    inputComponent: {
      props: ['value', 'name', 'placeholder', 'required', 'disabled', 'id', 'readonly', 'maxlength', 'pattern', 'size'],
      methods: {
        update(val){
          this.$emit('input', val);
        }
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
      beforeDestroy(){
        if ( this.widget && $.isFunction(this.widget.destroy) ){
          this.widget.destroy();
          $(this.$el).empty();
        }
      },
      methods: {
        getOptions(){
          return bbn.vue.getOptions(this);
        },
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

    closest(vm, selector){
      while ( vm && vm.$parent ){
        if ( vm.$parent.$el && $(vm.$parent.$el).is(selector) ){
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
          (obj.$vnode.data.key=== key)
        ){
          if ( selector ){
            return $(obj.$el).is(selector) ||
              (obj.$vnode.componentOptions && (obj.$vnode.componentOptions.tag === selector)) ? obj : false;
          }
          return obj;
        }
      }
      return false;
    },

    makeUID(){
      return bbn.fn.randomString(32);
    }
  };

  bbn.vue.vueComponent = bbn.fn.extend({}, bbn.vue.inputComponent, bbn.vue.optionComponent, bbn.vue.eventsComponent);

  bbn.vue.defineComponents()

})(jQuery, bbn, kendo);