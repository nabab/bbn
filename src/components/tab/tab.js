/**
 * Created by BBN on 15/02/2017.
 */
(function($, bbn, kendo){
  "use strict";

  /**
   * @component
   * @param {string} url - The URL on which the tabNav will be initialized.
   * @param {boolean} autoload - Defines if the tab will be automatically loaded based on URLs. False by default
   * except if it is true for the parent.
   * @param {string} orientation - The position of the tabs' titles: top (default) or bottom.
   * @param {string} root - The root URL of the tabNav, will be only taken into account for the top parents'
   * tabNav, will be automatically calculated for the children.
   * @param {boolean} scrollable - Sets if the tabs' titles will be scrollable in case they have a greater width
   * than the page (true), or if they will be shown multilines (false, default).
   * @param {array} source - The tabs shown at init.
   * @param {string} currentURL - The URL to which the tabnav currently corresponds (its selected tab).
   * @param {string} baseURL - The parent TabNav's URL (if any) on top of which the tabNav has been built.
   * @param {array} parents - The tabs shown at init.
   * @param {array} tabs - The tabs configuration and state.
   * @param {boolean} parentTab - If the tabNav has a tabNav parent, the tab Vue object in which it stands, false
   * otherwise.
   * @param {boolean|number} selected - The index of the currently selected tab, and false otherwise.
   */
  Vue.component('bbn-tab', {
    template: '#bbn-tpl-component-tab',
    mixins: [bbn.vue.resizerComponent],
    props: {
      title: {
        type: [String, Number],
        default: bbn._("Untitled")
      },
      hasPopups: {
        type: Boolean,
        default: false
      },
      componentAttributes: {
        type: Object,
        default(){
          return {}
        }
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
      popup(){
        this.$refs.popup.open.apply(this.$refs.popup, arguments)
      },
      getComponent(){
        return this.$children[1] || this.$children[0]
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
      return {
        tabNav: null,
        isComponent: null,
        name: bbn.fn.randomString(20, 15).toLowerCase(),
        isMounted: false,
        popups: []
      };
    },

    created(){
      if ( this.isComponent === null ){
        this.onMount = () => {
          return false;
        };
        let res;
        if ( this.script ){
          res = typeof this.script === 'string' ? eval(this.script) : this.script;
          if ( $.isFunction(res) ){
            this.onMount = res;
            this.isComponent = false;
          }
          else if ( typeof(res) === 'object' ){
            this.isComponent = true;
          }
        }
        if ( this.isComponent ){
          bbn.fn.extend(res ? res : {}, {
            name: this.name,
            template: '<div class="bbn-100">' + this.content + '</div>',
            methods: {
              getTab: () => {
                return this;
              },
              popup: this.popup,
              addMenu: this.addMenu,
              deleteMenu: this.deleteMenu
            },
            props: ['source']
          });
          this.$options.components[this.name] = res;
        }
        else{
          this.isComponent = false;
        }
      }
    },

    mounted: function(){
      this.tabNav = bbn.vue.closest(this, ".bbn-tabnav");
      if ( !this.isComponent ){
        this.onMount(this.$el, this.source);
      }
    },
    /* Is it useful????
    watch: {
      selected: function(newVal, oldVal){
        if ( newVal && !oldVal ){
          var vm = this;
          if ( vm.load ){
            vm.$parent.load(vm.url);
          }
          else{
            bbn.fn.log("TabNav selected has changed - old: " + oldVal + " new: " + newVal + " for URL " + vm.url);
          }
        }
      },
      source: {
        deep: true,
        handler: function(){
          this.$forceUpdate();
        }
      }
    }
    */
  });

})(jQuery, bbn, kendo);
