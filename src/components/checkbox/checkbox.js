/**
 * Created by BBN on 13/02/2017.
 */
(function($, bbn, kendo){
  "use strict";
  Vue.component('bbn-checkbox', {
    template: '#bbn-tpl-component-checkbox',
    mixins: [bbn.vue.eventsComponent],
    props: {
      value: {
        default: true
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
        type: [String, Boolean, Array, Number],
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
      label: {
        type: String,
      },
      checked: {
        type: Boolean,
        default: false
      },
      model: {}
    },
    model: {
      prop: 'modelValue',
      event: 'input'
    },
    computed: {
      state () {
        if ( this.modelValue === undefined ){
          return this.checked;
        }
        if ( Array.isArray(this.modelValue) ){
          return this.modelValue.indexOf(this.value) > -1;
        }
        return !!this.modelValue;
      }
    },
    methods: {
      change(){
        this.toggle();
      },
      toggle(){
        let value;
        if ( Array.isArray(this.modelValue) ){
          value = this.modelValue.slice(0);
          if ( this.state ){
            value.splice(value.indexOf(this.value), 1);
          }
          else {
            value.push(this.value);
          }
        }
        else {
          value = !this.state;
        }
        this.$emit('input', value);
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
    }
  });
})(jQuery, bbn, kendo);
