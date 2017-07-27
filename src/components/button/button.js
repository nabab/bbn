/**
 * Created by BBN on 10/02/2017.
 */
(function($, bbn, kendo){
  "use strict";

  Vue.component('bbn-button', {
    mixins: [bbn.vue.vueComponent],
    template: '#bbn-tpl-component-button',
    props: {
      title: {
        type: String,
        default: ''
      },
      text: {
        type: String,
      },
      notext: {
        type: Boolean,
        default: false
      },
      icon: {
        type: String,
      },
      type: {
        type: String,
      },
      disabled: {}
    },
  });

})(jQuery, bbn, kendo);
