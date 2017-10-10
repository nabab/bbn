/**
 * Created by BBN on 12/06/2017.
 */
(function($, bbn, kendo){
  "use strict";

  /**
   * Classic textarea with normalized appearance
   */
  Vue.component('bbn-textarea', {
    mixins: [bbn.vue.inputComponent, bbn.vue.eventsComponent],
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
        this.emitInput('');
      }
    },
    mounted: function(){
      let $ele = $(this.$el);
      if ( this.disabled ){
        $ele.addClass("k-state-disabled");
      }
      this.$emit("ready", this.value);
    }
  });

})(jQuery, bbn, kendo);
