/**
 * Created by BBN on 15/02/2017.
 */
(function($, bbn){
  "use strict";

  /**
   * Classic input with normalized appearance
   */
  Vue.component('bbn-loader', {
    template: '#bbn-tpl-component-loader',
    props: {
      source: {
        type: [Object, Array],
        default: function(){
          return {};
        }
      },
    }
  });

})(jQuery, bbn, kendo);
