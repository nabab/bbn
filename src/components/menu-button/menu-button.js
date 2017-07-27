/**
 * Created by BBN on 02/03/2017.
 */
(function($, bbn, kendo){
  "use strict";

  /**
   * Classic input with normalized appearance
   */
  Vue.component('bbn-menu-button', {
    template: '#bbn-tpl-component-menu-button',
    props: {
      icon: {
        type: String
      },
      menu: {},
      cfg: {
        type: Object,
        default: function(){
          return {
            icon: "fa fa-navicon",
          }
        }
      }
    },
    data: function(){
      return $.extend({
        index: false,
        opened: false,
        key: false,
        menuVue: false
      }, bbn.vue.treatData(this));
    },
    methods: {
      toggle: function(){
        var vm = this;
        if ( vm.menuVue ){
          bbn.fn.log(vm.menuVue);
          vm.menuVue.toggle();
        }
      }
    },
    mounted: function(){
      var vm = this;
      var cfg = bbn.vue.getOptions(vm);
      if ( cfg.icon ){
        vm.$refs.icon.className = cfg.icon;
      }
      if ( cfg.menu ){
        setTimeout(function(){
          vm.menuVue = bbn.vue.retrieveRef(vm, cfg.menu);
        }, 1000)
      }
    },
  });

})(jQuery, bbn, kendo);
