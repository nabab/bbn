/**
 * Created by BBN on 13/02/2017.
 */
(function($, bbn, kendo){
  "use strict";
  Vue.component('bbn-radio', {
    mixins: [bbn.vue.inputComponent],
    template: '#bbn-tpl-component-radio',
    props: {
      separator: {
        type: String,
        default: '<span style="margin-left: 2em">&nbsp;</span>'
      },
      source: {
        default: function(){
          return [{
            text: bbn._("Yes"),
            value: 1
          }, {
            text: bbn._("No"),
            value: 0
          }];
        }
      },
      label: {
        type: String,
      }
    },
    computed: {
      dataSource: function(){
        if ( this.source ){
          return bbn.vue.toKendoDataSource(this);
        }
        return [];
      }
    },
    mounted(){
      this.$emit("ready", this.checked);
    }
  });

})(jQuery, bbn, kendo);
