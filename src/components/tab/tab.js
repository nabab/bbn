/**
 * Created by BBN on 15/02/2017.
 */
(function($, bbn){
  "use strict";

  /**
   * Classic input with normalized appearance
   */
  Vue.component('bbn-tab', {
    //template: '#bbn-tpl-component-tab',
    props: {
      title: {
        type: [String, Number],
        default: bbn._("Untitled")
      },
      componentAttributes: {
        type: Object
      },
      idx: {},
      component: {},
      icon: {
        type: String
      },
      content: {
        type: String,
        default: ""
      },
      load: {
        type: Boolean,
        default: false
      },
      selected: {
        type: [Boolean, Number],
        default: false
      },
      css: {
        type: String,
        default: ""
      },
      source: {
        type: [Array, Object],
        default: function(){
          return {};
        }
      },
      script: {},
      static: {
        type: [Boolean, Number],
        default: false
      },
      pinned: {
        type: [Boolean, Number],
        default: false
      },
      url: {
        type: [String, Number]
      },
      current: {
        type: [String, Number]
      },
      real: {
        type: String
      },
    },

    methods: {
      setCurrent(url){
        const vm = this;
        if ( url.indexOf(vm.url) === 0 ){
          vm.tabNav.activate(url);
        }
      },
      getSubTabNav(ele){
        if ( !ele ){
          ele = this;
        }
        var recurse = function(el){
              if ( el.$options && el.$options._componentTag && (el.$options._componentTag === "bbn-tabnav") ){
                return el;
              }
              if ( el.$children ){
                for ( var i = 0; i < el.$children.length; i++ ){
                  var r = recurse(el.$children[i]);
                  if ( r ){
                    return r;
                  }
                }
              }
              return false;
            };
        return recurse(ele);
      },
      addMenu(obj){
        var vm = this;
        if (
          (vm.idx > -1) &&
          obj.text &&
          vm.$parent.tabs &&
          vm.$parent.tabs[vm.idx]
        ){
          var menu = vm.$parent.tabs[vm.idx].menu || [];
          if ( !obj.key ){
            obj.key = bbn.fn.randomInt(99,99999999999);
          }
          menu.push(obj);
          vm.$parent.$set(vm.$parent.tabs[vm.idx], "menu", menu);
          return obj.key;
        }
        return false;
      },
      deleteMenu(key){
        var vm = this;
        if (
          (vm.idx > -1) &&
          vm.$parent.tabs &&
          vm.$parent.tabs[vm.idx]
        ){
          var menu = vm.$parent.tabs[vm.idx].menu || [],
              idx = bbn.fn.search(menu, "key", key);
          if ( idx > -1 ){
            menu.splice(idx, 1);
            vm.$parent.$set(vm.$parent.tabs[vm.idx], "menu", menu);
            return true;
          }
        }
        return false;
      }
    },

    data: function(){
      var vm = this,
          r = bbn.vue.treatData(vm).widgetCfg || {};
      if ( vm.$options && vm.$options.props ){
        for ( var n in r ){
          if ( vm.$options.props[n] !== undefined ){
            delete r[n];
          }
        }
      }
      r.tabNav = null;
      r.isComponent = null;
      r.name = bbn.fn.randomString(20, 15).toLowerCase();
      r.isMounted = false;
      return r;
    },

    render: function(createElement){
      var vm = this,
          ele = {
            'class': {
              'bbn-tab': true,
              'k-content': true,
              'bbn-full-height': true,
              'bbn-w-100': true,
              'bbn-tab-selected': !!vm.selected
            }
          },
          children = [],
          res = null;
      if ( vm.isComponent === null ){
        vm.onMount = function(){
          return false;
        };
        if ( vm.script ){
          res = eval(vm.script);
          if ( $.isFunction(res) ){
            vm.onMount = res;
            vm.isComponent = false;
          }
          else if ( typeof(res) === 'object' ){
            vm.isComponent = true;
          }
        }
        if ( vm.isComponent ){
          $.extend(res, {
            name: vm.name,
            template: '<div class="bbn-100">' + vm.content + '</div>',
            props: ['source']
          });
          vm.$options.components[vm.name] = res;
        }
        else{
          vm.isComponent = false;
        }
      }
      if ( vm.isComponent ){
        children.push(createElement(vm.name, {
          props: {
            source: vm.source
          }
        }));
        if ( vm.css ){
          children.push(createElement('style', {
            domProps: {
              innerHTML: vm.css
            }
          }));
        }
      }
      else if ( !vm.content && vm.component ){
        children.push(createElement(vm.component, {
          props: vm.componentAttributes ? vm.componentAttributes : {source: vm.source}
        }));
      }
      else{
        children.push(createElement('div', {
          'class': {
            'bbn-100': true
          },
          domProps: {
            innerHTML: (vm.css ? '<style>' + vm.css + '</style>' : '') + vm.content
          }
        }));
      }
      return createElement('div', ele, children)
    },

    mounted: function(){
      const vm = this;
      vm.tabNav = bbn.vue.closest(vm, ".bbn-tabnav");
      bbn.fn.analyzeContent(vm.$parent.$el);
      if ( !vm.isComponent ){
        vm.onMount(vm.$el, vm.source);
      }
      bbn.fn.analyzeContent(this.$el, true);
    },
    watch: {
      selected: function(newVal, oldVal){
        if ( newVal && !oldVal ){
          var vm = this;
          if ( vm.load ){
            vm.$parent.load(vm.url);
          }
          else{
            bbn.fn.log("TabNav selected has changed - old: " + oldVal + " new: " + newVal + " for URL " + vm.url);
            bbn.fn.analyzeContent(vm.$el, true);
          }
        }
      },
      content: function(){
        var ele = this.$el;
        bbn.fn.analyzeContent(ele, true);
      },
      source: {
        deep: true,
        handler: function(){
          this.$forceUpdate();
        }
      }
    }
  });

})(jQuery, bbn, kendo);
