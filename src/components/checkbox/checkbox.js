/**
 * Created by BBN on 13/02/2017.
 */
(function($, bbn, kendo){
  "use strict";
  Vue.component('bbn-checkbox', {
    template: '#bbn-tpl-component-checkbox',
    props: {
      disabled: {},
      label: {
        type: String,
      },
    }
  });
})(jQuery, bbn, kendo);
