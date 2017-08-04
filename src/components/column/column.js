/**
 * Created by BBN on 15/02/2017.
 */
(function($, bbn){
  "use strict";

  /**
   * Classic input with normalized appearance
   */
  Vue.component('bbn-column', {
    //template: '#bbn-tpl-component-column',
    props: {
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
        type: Boolean
      },
      encoded: {
        type: Boolean
      },
      buttons: {
        type: [Array, Function]
      },
      source: {
        type: [Array, Object, String]
      }
    },

    methods: {
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
      r.table = null;
      r.isComponent = null;
      r.name = bbn.fn.randomString(20, 15).toLowerCase();
      r.isMounted = false;
      return r;
    },

    render: function(createElement){
      var vm = this;
      return createElement("div", {
        'class': {
          'bbn-column': true
        }
      });
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
