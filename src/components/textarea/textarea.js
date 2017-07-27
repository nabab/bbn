/**
 * Created by BBN on 12/06/2017.
 */
(function($, bbn, kendo){
  "use strict";

  /**
   * Classic textarea with normalized appearance
   */
  Vue.component('bbn-textarea', {
    mixins: [bbn.vue.vueComponent],
    template: '#bbn-tpl-component-textarea',
    props: {
			rows: {
				type: Number
			},
			cols: {
				type: Number
			},
			maxlength: {
				type: Number
			},
      cfg:{
				type: Object,
        default: function(){
          return {}
				}
			}
    },
    methods: {
      clear: function(){
        this.update('');
      }
    },
    data: function(){
      return $.extend({
        widgetName: "textarea",
      }, bbn.vue.treatData(this));
    },
    mounted: function(){
      var vm = this,
          $ele = $(vm.$el),
          cfg = vm.getOptions();

      if ( this.disabled ){
        $ele.addClass("k-state-disabled");
      }
    }
  });

})(jQuery, bbn, kendo);
