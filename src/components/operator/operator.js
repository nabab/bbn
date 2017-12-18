/**
 * Created by BBN on 10/02/2017.
 */
(function($, bbn, kendo){
  "use strict";
  Vue.component('bbn-operator', {
    mixins: [bbn.vue.basicComponent, bbn.vue.dataEditorComponent, bbn.vue.inputComponent],
    name: 'bbn-operator',
    props: {
      type: {
        type: String
      },
      isNull: {
        type: Boolean,
        default: false
      }
    },
    data: function(){
      return {
        currentValue: this.value
      };
    },
    computed: {
      operators(){
        let ops = this.type && this.editorOperators[this.type] ? this.editorOperators[this.type] : {};
        if ( this.isNull ){
          $.extend(ops, this.editorNullOps);
        }
        return ops;
      },
    },
    mounted(){
      if ( !this.currentValue && bbn.fn.countProperties(this.operators) ){
        this.$nextTick(() => {
          this.currentValue = Object.keys(this.operators)[0];
          this.$emit("input", this.currentValue);
        })
      }
    },
    watch: {
      type(newVal){
        this.$nextTick(() => {
          this.currentValue = bbn.fn.countProperties(this.operators) ?
            Object.keys(this.operators)[0] :
            ''
        })
      },
      currentValue(newVal){
        this.$emit("input", newVal);
      }
    }
  });

})(jQuery, bbn, kendo);