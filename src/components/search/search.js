/**
 * Created by BBN on 10/02/2017.
 */
(function($, bbn, kendo){
  "use strict";

  /**
   * Classic input with normalized appearance
   */
  Vue.component('bbn-search', {
    mixins: [bbn.vue.vueComponent],
    template: '#bbn-tpl-component-search',
    props: {
      action: {},
      icon: {
        type: String
      },
      cfg:{
        type: Object,
        default: function(){
          return {
            placeholder: bbn._("Filter tree"),
            icon: "fa fa-search"
          }
        }
      },
    },
    methods: {
      clear: function(){
        this.update('');
      }
    },
    data: function(){
      return $.extend({
        widgetName: "search",
      }, bbn.vue.treatData(this));
    },
    mounted: function(){
      var vm = this,
          $ele = $(vm.$refs.element),
          cfg = vm.getOptions();
    }
  });

})(jQuery, bbn, kendo);
