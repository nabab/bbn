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

  const
    editorOperators = {
      string: {
        'contains': bbn._('Contient'),
        'eq': bbn._('Est'),
        'neq': bbn._('N’est pas'),
        'startswith': bbn._('Commence par'),
        'doesnotcontain': bbn._('Ne contient pas'),
        'endswith': bbn._('Se termine par'),
        'isempty': bbn._('Est vide'),
        'isnotempty': bbn._('N’est pas vide')
      },
      number: {
        'eq': bbn._('Est égal à'),
        'neq': bbn._('N’est pas égal à'),
        'gte': bbn._('Est supérieur ou égal à'),
        'gt': bbn._('Est supérieur à'),
        'lte': bbn._('Est inférieur ou égal à'),
        'lt': bbn._('Est inférieur à'),
      },
      date: {
        'eq': bbn._('Est égal à'),
        'neq': bbn._('N’est pas égal à'),
        'gte': bbn._('Est postérieur ou égal à'),
        'gt': bbn._('Est postérieur à'),
        'lte': bbn._('Est antérieur ou égal à'),
        'lt': bbn._('Est antérieur à'),
      },
      enums: {
        'eq': bbn._('Est égal à'),
        'neq': bbn._('N’est pas égal à'),
      },
      boolean: {
        'istrue': bbn._('Est vrai'),
        'isfalse': bbn._('Est faux')
      }
    },
    editorNullOps = {
      'isnull': bbn._('Est nul'),
      'isnotnull': bbn._('N’est pas nul')
    },
    editorNoValueOperators = ['', 'isnull', 'isnotnull', 'isempty', 'isnotempty', 'istrue', 'isfalse'];

  bbn.vue = {
    defaultLocalURL: false,
    defaultLocalPrefix: '',
    localURL: false,
    isNodeJS: false,
    localPrefix: '',
    loadingComponents: [],
    loadedComponents: [],
    components: {
      autocomplete: {},
      button: {},
      chart: {},
      chart2: {},
      checkbox: {},
      code: {},
      colorpicker: {},
      combo: {},
      context: {},
      countdown: {},
      dashboard: {},
      datepicker: {},
      datetimepicker: {},
      dropdown: {},
      //dropdowntreeview: {},
      field:{},
      filter: {},
      fisheye: {},
      //footer: {},
      form: {},
      initial: {},
      input: {},
      'json-editor': {},
      list: {},
      loader: {},
      loading: {},
      markdown: {},
      masked: {},
      menu: {},
      'menu-button': {},
      message: {},
      multiselect: {},
      notification: {},
      numeric: {},
      operator: {},
      pane: {},
      popup: {},
      progressbar:{},
      radio: {},
      rte: {},
      scroll: {},
      'scroll-x': {},
      'scroll-y': {},
      search: {},
      slider: {},
      splitter: {},
      table: {},
      tabnav: {},
      textarea: {},
      timepicker: {},
      toolbar: {},
      tree: {},
      treemenu: {},
      'tree-input': {},
      upload: {},
      vlist: {}
    },
    /**
     * Makes the dataSource variable suitable to be used by the kendo UI widget
     * @param vm Vue object
     * @returns object
     */
    toKendoDataSource(vm){
      let text = vm.sourceText || vm.widgetOptions.dataTextField || 'text',
          value = vm.sourceValue || vm.widgetOptions.dataValueField || 'value',
          nullable = vm.nullable || false,
          res = [];
      let transform = (src) => {
        let type = typeof(src),
            isArray = Array.isArray(src);
        if ( (type === 'object') && !isArray ){
          $.each(src, (n, a) => {
            let tmp = {};
            tmp[text] = (typeof a) === 'string' ? a : n;
            tmp[value] = n;
            if ( vm.group && a[vm.group] ){
              tmp[vm.group] = a[vm.group];
            }
            res.push(tmp);
          });
        }
        else if ( isArray && src.length && (typeof(src[0]) !== 'object') ){
          res = $.map(src, (a) => {
            let tmp = {};
            tmp[text] = a;
            tmp[value] = a;
            return tmp;
          });
        }
        else{
          res = src;
        }
        if ( nullable && res.length ){
          let tmp = {};
          tmp[text] = '';
          tmp[value] = null;
          res.unshift(tmp)
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

    initDefaults(defaults, cpName){
      if ( !bbn.vue.components[cpName] ){
        throw new Error("Impossible to find the component " + cpName);
      }
      if ( typeof defaults !== 'object' ){
        throw new Error("The default object sent is not an object " + cpName);
      }
      bbn.vue.components[cpName].defaults = $.extend(true, defaults, bbn.vue.components[cpName].defaults);
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

    addComponent(name, mixins){
      if ( bbn.vue.localURL ){
        let componentName = bbn.fn.replaceAll("/", "-", name);
        if ( bbn.vue.localPrefix ){
          componentName = bbn.vue.localPrefix + '-' + componentName;
        }
        bbn.vue.announceComponent(componentName, bbn.vue.localURL + name, mixins);
      }
    },

    announceComponent(name, url, mixins){
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
              let res = eval(r.script);
              if ( typeof res === 'object' ){
                if ( !res.template ){
                  res.template = '#bbn-tpl-component-' + name;
                }
                if ( !res.props ){
                  res.props = ['source'];
                }
                if ( !res.name ){
                  res.name = name;
                }
                if ( mixins ){
                  if ( res.mixins ){
                    $.each(mixins, (i, a) => {
                      res.mixins.push(a);
                    })
                  }
                  else{
                    res.mixins = mixins;
                  }
                }
                Vue.component(name, res);
              }
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
        for ( let a in bbn.vue.components ){
          bbn.vue.loadedComponents.push('bbn-' + a);
          /** @var string bbn_root_url */
          /** @var string bbn_root_dir */
          Vue.component('bbn-' + a, (resolve, reject) => {
            let url = bbn_root_url + bbn_root_dir + 'components/' + a + "/?component=1";

            if ( bbn.env.isDev ){
              url += '&test=1';
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
        }
      }
    },

    basicComponent: {
      props: {
        componentClass: {
          type: Array,
          default(){
            return [];
          }
        },
      },
      beforeCreate(){
        if ( !this.$options.render ){
          this.$options.template = '#bbn-tpl-component-' + this.$options.name.slice(4);
        }
      },
      created(){
        this.componentClass.push(this.$options.name);
      },
      mounted(){
        this.$emit('mounted');
      }
    },

    localStorageComponent: {
      props: {
        storageName: {
          type: String,
          default: 'default'
        },
        storageFullName: {
          type: String
        },
        componentClass: {
          type: Array,
          default(){
            return [];
          }
        }
      },
      computed: {
        storage(){
          if ( window.store ){
            return {
              get(name){
                let tmp = window.store.get(name);
                if ( tmp ){
                  return tmp.value;
                }
              },
              set(name, value){
                return window.store.set(name, {
                  value: value,
                  time: (new Date()).getTime()
                });
              },
              time(name){
                let tmp = window.store.get(name);
                if ( tmp ){
                  return tmp.time;
                }
              },
              remove(name){
                return window.store.remove(name);
              }
            }
          }
          return false;
        },
      },
      methods: {
        _getStorageRealName(){
          return this.storageFullName ? this.storageFullName : this.$options.name + '-' + window.location.pathname.substr(1) + '-' + this.storageName;
        },
        hasStorage(){
          return !!this.storage;
        },
        getStorage(){
          if ( this.hasStorage() ){
            return this.storage.get(this._getStorageRealName())
          }
        },
        setStorage(value){
          if ( this.hasStorage() ){
            return this.storage.set(this._getStorageRealName(), value)
          }
        },
      },
      created(){
        this.componentClass.push('bbn-local-storage-component');
      },
    },

    dataEditorComponent: {
      props: {
        componentClass: {
          type: Array,
          default(){
            return [];
          }
        }
      },
      methods: {
        editorOperatorType(col){
          if ( col.field ){

          }
        },
        editorHasNoValue(operator){
          return $.inArray(operator, editorNoValueOperators) > -1;
        },
        editorGetComponentOptions(col){
          let o = {
            type: 'string',
            component: 'bbn-input',
            multi: false,
            componentOptions:  {}
          };
          if ( col && col.field ){
            o.field = col.field;
            if ( col.filter ){
              o.component = col.filter;
            }
            else if ( col.source ){
              o.type = 'enums';
              o.component = 'bbn-dropdown';
              o.componentOptions.source = col.source;
              o.componentOptions.placeholder = bbn._('Choose');
            }
            else if ( col.type ){
              switch ( col.type ){
                case 'number':
                case 'money':
                  o.type = 'number';
                  o.component = 'bbn-numeric';
                  break;
                case 'date':
                  o.type = 'date';
                  o.component = 'bbn-datepicker';
                  break;
                case 'time':
                  o.type = 'date';
                  o.component = 'bbn-timepicker';
                  break;
                case 'datetime':
                  o.type = 'date';
                  o.component = 'bbn-datetimepicker';
                  break;
              }
            }
            if ( col.componentOptions ){
              $.extend(o.componentOptions, col.componentOptions);
            }
            if ( o.type && this.editorOperators[o.type] ){
              o.operators = this.editorOperators[o.type];
            }
            o.fields = [col];
          }
          return o
        },

      },
      computed: {
        editorOperators(){
          return editorOperators;
        },
        editorNullOps(){
          return editorNullOps;
        },
        editorNoValueOperators(){
          return editorNoValueOperators;
        }
      },
      created(){
        this.componentClass.push('bbn-dataeditor-component');
      },
    },

    eventsComponent: {
      props: {
        componentClass: {
          type: Array,
          default(){
            return [];
          }
        }
      },
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
      },
      created(){
        this.componentClass.push('bbn-events-component');
      },
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
        },
        nullable: {
          type: Boolean,
          default: false
        },
        componentClass: {
          type: Array,
          default(){
            return [];
          }
        },
      },
      methods: {
        getOptions(obj){
          let cfg = bbn.vue.getOptions2(this, obj);
          cfg.dataTextField = this.sourceText || this.widgetOptions.dataTextField || 'text';
          cfg.dataValueField = this.sourceValue || this.widgetOptions.dataValueField || 'value';
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
      },
      created(){
        this.componentClass.push('bbn-datasource-component');
      },
    },

    memoryComponent: {
      props: {
        memory: {
          type: [Object, Function]
        },
        componentClass: {
          type: Array,
          default(){
            return [];
          }
        }
      },
      created(){
        this.componentClass.push('bbn-datasource-component');
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
          type: [Number, String]
        },
        maxlength: {
          type: [String, Number]
        },
        validation: {
          type: [Function]
        },
        type: {
          type: String
        },
        componentClass: {
          type: Array,
          default(){
            return [];
          }
        }
      },
      methods: {
        emitInput(val){
          this.$emit('input', val);
        },
        isValid(val){
          const elem = this.$refs.element,
                $elem = $(this.$el),
                customMessage = this.$el.hasAttribute('validationMessage') ? this.$el.getAttribute('validationMessage') : false;
          // Get validity
          if ( elem && elem.validity ){
            let validity = elem.validity,
                // Default message
                mess = bbn._('The value you entered for this field is invalid.');
            // If valid or disabled, return true
            if ( elem.disabled || validity.valid ){
              return true;
            }
            if ( !validity.valid ){
              // If field is required and empty
              if ( validity.valueMissing ){
                mess = bbn._('Please fill out this field.');
              }
              // If not the right type
              else if ( validity.typeMismatch ){
                switch ( elem.type ){
                  // Email
                  case 'email':
                    mess = bbn._('Please enter a valid email address.');
                    break;
                  // URL
                  case 'url':
                    mess = bbn._('Please enter a valid URL.');
                    break;
                }
              }
              // If too short
              else if ( validity.tooShort ){
                mess = bbn._('Please lengthen this text to ') + elem.getAttribute('minLength') + bbn._(' characters or more. You are currently using ') + elem.value.length + bbn._(' characters.');
              }
              // If too long
              else if ( validity.tooLong ){
                mess = bbn._('Please shorten this text to no more than ') + elem.getAttribute('maxLength') + bbn._(' characters. You are currently using ') + elem.value.length + bbn._(' characters.');
              }
              // If number input isn't a number
              else if ( validity.badInput ){
                mess = bbn._('Please enter a number.');
              }
              // If a number value doesn't match the step interval
              else if ( validity.stepMismatch ){
                mess = bbn._('Please select a valid value.');
              }
              // If a number field is over the max
              else if ( validity.rangeOverflow ){
                mess = bbn._('Please select a value that is no more than ') + elem.getAttribute('max') + '.';
              }
              // If a number field is below the min
              else if ( validity.rangeUnderflow ){
                mess = bbn._('Please select a value that is no less than ') + elem.getAttribute('min') + '.';
              }
              // If pattern doesn't match
              else if (validity.patternMismatch) {
                // If pattern info is included, return custom error
                mess = bbn._('Please match the requested format.');
              }
              bbn.fn.alert(customMessage || mess, bbn._('Attention'), () => {
                $elem.css('border', '1px solid red');
              }, () => {
                $elem.css('border', 'none');
                $(elem).focus();
              });
              return false;
            }
          }
          return true;
        }
      },
      created(){
        this.componentClass.push('bbn-input-component');
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
      props: {
        componentClass: {
          type: Array,
          default(){
            return [];
          }
        }
      },
      methods: {
        getOptions(){
          return bbn.vue.getOptions(this);
        },
      },
      created(){
        this.componentClass.push('bbn-option-component');
      },
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
        },
        componentClass: {
          type: Array,
          default(){
            return [];
          }
        }
      },
      created(){
        this.componentClass.push('bbn-widget-component');
      },
      beforeDestroy(){
        //bbn.fn.log("Default destroy");
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
          bbn.fn.info("CLASSIC BUILD " );
        },
        getWidgetCfg(){
          const vm = this;
        },
      }
    },

    // These components will emit a resize event when their closest parent of the same kind gets really resized
    resizerComponent: {
      props: {
        componentClass: {
          type: Array,
          default(){
            return [];
          }
        }
      },
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
          this.parentResizer = bbn.vue.closest(this, ".bbn-resize-emitter", true);
          // Setting initial dimensions
          this.lastKnownHeight = this.parentResizer ? Math.round($(this.parentResizer.$el).innerHeight()) : bbn.env.height;
          this.lastKnownWidth = this.parentResizer ? Math.round($(this.parentResizer.$el).innerWidth()) : bbn.env.width;
          // Creating the callback function which will be used in the timeout in the listener
          this.resizeEmitter = (force) => {
            // Removing previous timeout
            clearTimeout(resizeTimeout);
            // Creating a new one
            resizeTimeout = setTimeout(() => {
              if ( $(this.$el).is(":visible") ){
                // Checking if the parent hasn't changed (case where the child is mounted before)
                let tmp = bbn.vue.closest(this, ".bbn-resize-emitter", true);
                if ( tmp !== this.parentResizer ){
                  // In that case we reset
                  this.unsetResizeEvent();
                  this.setResizeEvent();
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
                if ( resize || force ){
                  this.onResize();
                  this.$emit("resize", force);
                }
              }
            }, 0);
          };
          if ( this.parentResizer ){
            //bbn.fn.log("SETTING EVENT FOR PARENT", this.$el, this.parentResizer);
            this.parentResizer.$on("resize", (force) => {
              this.resizeEmitter(force)
            });
          }
          else{
            //bbn.fn.log("SETTING EVENT FOR WINDOW", this.$el);
            $(window).on("resize", (force) => {
              this.resizeEmitter(force)
            });
          }
          this.resizeEmitter();
        },

        unsetResizeEvent(){
          return;
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

        selfEmit(force){
          if ( this.parentResizer ){
            this.parentResizer.$emit("resize", force);
          }
        }
      },
      created(){
        this.componentClass.push('bbn-resizer-component');
      },
      mounted(){
        this.setResizeEvent();
      },
      beforeDestroy(){
        this.unsetResizeEvent();
      }
    },

    closeComponent: {
      props: {
        componentClass: {
          type: Array,
          default(){
            return [];
          }
        }
      },
      created(){
        this.componentClass.push('bbn-close-component');
      },
      computed: {
        canClose(){
          return !this.isUnsaved;
        }
      },
      methods: {

      }
    },

    fieldProperties: {
      width: {
        type: [String, Number],
      },
      render: {
        type: [String, Function]
      },
      title: {
        type: [String, Number],
        default: bbn._("Untitled")
      },
      ftitle: {
        type: String
      },
      tcomponent: {
        type: [String, Object]
      },
      icon: {
        type: String
      },
      cls: {
        type: String
      },
      type: {
        type: String
      },
      field: {
        type: String
      },
      fixed: {
        type: [Boolean, String],
        default: false
      },
      hidden: {
        type: Boolean
      },
      encoded: {
        type: Boolean,
        default: false
      },
      sortable: {
        type: Boolean,
        default: true
      },
      editable: {
        type: Boolean,
        default: true
      },
      filterable: {
        type: Boolean,
        default: true
      },
      resizable: {
        type: Boolean,
        default: true
      },
      showable: {
        type: Boolean,
        default: true
      },
      nullable: {
        type: Boolean,
      },
      buttons: {
        type: [Array, Function]
      },
      source: {
        type: [Array, Object, String]
      },
      required: {
        type: Boolean,
      },
      options: {
        type: [Object, Function],
        default(){
          return {};
        }
      },
      editor: {
        type: [String, Object]
      },
      maxLength: {
        type: Number
      },
      sqlType: {
        type: String
      },
      aggregate: {
        type: [String, Array]
      },
      component: {
        type: [String, Object]
      },
      mapper: {
        type: Function
      },
      group: {
        type: String
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

    closest(vm, selector, checkEle){
      let test = vm.$el;
      while ( vm && vm.$parent && (vm !== vm.$parent) ){
        if ( bbn.vue.is(vm.$parent, selector) ){
          if ( !checkEle || (test !== vm.$parent.$el) ){
            return vm.$parent;
          }
        }
        vm = vm.$parent;
      }
      return false;
    },

    getChildByKey(vm, key, selector){
      if ( vm.$children ){
        for ( let i = 0; i < vm.$children.length; i++ ){
          let obj = vm.$children[i];
          if (
            obj.$el &&
            obj.$vnode &&
            obj.$vnode.data &&
            obj.$vnode.data.key &&
            (obj.$vnode.data.key === key)
          ){
            if ( selector && bbn.vue.is(obj, selector) ){
              return obj;
            }
            else{
              return obj;
            }
          }
        }
      }
      return false;
    },

    findByKey(vm, key, selector, ar){
      let tmp = bbn.vue.getChildByKey(vm, key, selector);
      if ( !tmp && vm.$children ){
        for ( let i = 0; i < vm.$children.length; i++ ){
          if ( tmp = bbn.vue.findByKey(vm.$children[i], key, selector, ar) ){
            if ( $.isArray(ar) ){
              ar.push(tmp);
            }
            else{
              break;
            }
          }
        }
      }
      return tmp;
    },

    findAllByKey(vm, key, selector){
      let ar = [];
      bbn.vue.findByKey(vm, key, selector, ar);
      return ar;
    },

    find(vm, selector, index){
      let vms = bbn.vue.getComponents(vm);
      let realIdx = 0;
      index = parseInt(index);
      if ( vms ){
        for ( let i = 0; i < vms.length; i++ ){
          if ( bbn.vue.is(vms[i], selector) ){
            if ( realIdx === index ){
              return vms[i];
            }
            realIdx++;
          }
        }
      }
    },

    findAll(vm, selector, only_children){
      let vms = bbn.vue.getComponents(vm, only_children),
          res = [];
      for ( let i = 0; i < vms.length; i++ ){
        if (
          $(vms[i].$el).is(selector) ||
          (vms[i].$vnode.componentOptions && (vms[i].$vnode.componentOptions.tag === selector))
        ){
          res.push(vms[i]);
        }
      }
      return res;
    },

    getComponents(vm, ar, only_children){
      if ( !Array.isArray(ar) ){
        ar = [];
      }
      $.each(vm.$children, function(i, obj){
        ar.push(obj)
        if ( !only_children && obj.$children ){
          bbn.vue.getComponents(obj, ar);
        }
      });
      return ar;
    },

    makeUID(){
      return bbn.fn.randomString(32);
    },

    getRoot(vm){
      let e = vm;
      while ( e.$parent ){
        e = e.$parent;
      }
      return e;
    },
  };

  bbn.vue.fullComponent = bbn.fn.extend({}, bbn.vue.inputComponent, bbn.vue.optionComponent, bbn.vue.eventsComponent, bbn.vue.widgetComponent);

  bbn.vue.defineComponents()

})(jQuery, bbn, kendo);
