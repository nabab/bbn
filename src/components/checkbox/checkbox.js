/**
 * Created by BBN on 13/02/2017.
 */
(($, bbn, kendo) => {
  "use strict";
  Vue.component('bbn-checkbox', {
    mixins: [bbn.vue.basicComponent, bbn.vue.inputComponent, bbn.vue.eventsComponent],
    props: {
      value: {
        default: true
      },
      novalue: {
        default: null
      },
      name: {
        type: String,
        default: null
      },
      id: {
        type: String,
        default(){
          return bbn.fn.randomString(10, 25);
        }
      },
      modelValue: {
        type: [String, Boolean, Number],
        default: undefined
      },
      required: {
        type: Boolean,
        default: false
      },
      disabled: {
        type: Boolean,
        default: false
      },
      readonly: {
        type: Boolean,
        default: false
      },
      label: {
        type: String,
      },
      checked: {
        type: Boolean,
        default: false
      }
    },
    model: {
      prop: 'modelValue',
      event: 'input'
    },
    data(){
      return {
        valueToSet: this.value
      }
    },
    computed: {
      state(){
        if ( this.checked && (this.modelValue === undefined) ){
          return true;
        }
        if ( this.checked && (this.modelValue != this.valueToSet) ){
          return false;
        }
        return (this.modelValue == this.valueToSet) || this.checked;
      }
    },
    methods: {
      toggle(){
        let emitVal = !this.state ? this.valueToSet : this.novalue;
        this.$emit('input', emitVal);
        this.$emit('change', emitVal, this);
      },
      check(){
        this.$emit('input', this.valueToSet);
        this.$emit('change', this.valueToSet, this);
      },
      unCheck(){
        this.$emit('input', this.novalue);
        this.$emit('change', this.novalue, this);
      }
    },
    watch: {
      checked(newValue){
        if ( newValue !== this.state ){
          this.toggle();
        }
      }
    },
    mounted(){
      if ( this.checked && !this.state ){
        this.toggle();
      }
      if ( !this.checked && !this.state ){
        this.$emit('input', this.novalue);
      }
    }
  });
})(jQuery, bbn, kendo);