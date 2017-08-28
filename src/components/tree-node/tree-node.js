/**
 * Created by BBN on 10/02/2017.
 */
(function($, bbn){
  "use strict";

  Vue.component('bbn-tree-node', {
    template: '#bbn-tpl-component-tree2',
    props: {
      selected:{
        type: Boolean,
        default: false
      },
      expanded:{
        type: Boolean
      },
      extraClasses:{
        type: String
      },
      tooltip: {
        type: String
      },
      code: {
        type: String
      },
      icon:{
        type: String
      },
      selectable: {
        type: Boolean,
        default: true
      },
      text: {
        type: String
      },
      data: {
        type: Object
      },

    },
    data: function(){
      return {
        sourceValue: false,
        tree: false,
        items: []
      }
    },
    methods: {

    },
    created(){
      this.tree = bbn.vue.closest("bbn-tree2");
      this.isAjax = typeof(this.tree.source) === 'string';
      this.sourceValue = tree.sourceValue;
    },
    mounted: function(){
      this.build();
    }
  });

})(jQuery, bbn);
