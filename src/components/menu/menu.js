/**
 * Created by BBN on 15/02/2017.
 */
(function($, bbn, kendo){
  "use strict";

  /**
   * Classic input with normalized appearance
   */
  var mapper = function(ar){
    $.each(ar, function(i, a){
      a.encoded = false;
      if ( a.items ){
        a.items = mapper(a.items);
      }
    });
    return ar;
  };

  Vue.component('bbn-menu', {
    mixins: [bbn.vue.vueComponent],
    template: "#bbn-tpl-component-menu",
    props: {
      source: {
        type: Array
      },
      orientation: {},
      direction: {},
      opened: {},
      cfg: {
        type: Object,
        default: function(){
          return {
            dataSource: [],
            direction: "bottom right"
          };
        }
      }
    },
    methods: {
      getOptions: function(){
        var vm = this,
            cfg = bbn.vue.getOptions(vm);
        return cfg;
      }
    },
    computed: {
      dataSource: function(){
        if ( this.source ){
          return mapper(bbn.vue.transformDataSource(this));
        }
        return [];
      }
    },
    data: function(){
      return $.extend({
        widgetName: "kendoMenu",
      }, bbn.vue.treatData(this));
    },
    mounted: function(){
      var vm = this,
          cfg = vm.getOptions(),
          $ele = $(vm.$el);
      vm.widget = $ele.kendoMenu(cfg).data("kendoMenu");
    },
    watch:{
      source: function(newDataSource){
        bbn.fn.log("Changed DS", this.dataSource);
        this.widget.setDataSource(this.dataSource);
      }
    }
  });

})(jQuery, bbn, kendo);
