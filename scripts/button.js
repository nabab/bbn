/**
 * Created by BBN on 10/02/2017.
 */
(function($, bbn, kendo){
  "use strict";

  Vue.component('bbn-button', {
    mixins: [bbn.vue.vueComponent],
    template: '<button class="k-button" ref="button" v-on:click="click($event)" :type="type" :disabled="disabled ? true : false"><i v-if="icon" :class="icon"> </i><slot></slot></button>',
    props: {
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
