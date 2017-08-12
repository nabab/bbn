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
        default: 1
      },
      name: {
        type: String
      },
      id: {
        type: String
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
    },
    data(){
      return {
        initialValue: this.value ? this.value : false,
        checked: false
      };
    },
    methods: {
      click(e){
        bbn.fn.log("CLICK", this);
        this.checked = this.checked ? false : true;
        this.$forceUpdate();
        this.$emit("change", this.checked ? this.initialValue : false);
        this.$emit("input", this.checked ? this.initialValue : false);
      }
    },
    beforeMount(){
      bbn.fn.log(this);
    },
    mounted(){
      if ( $.isArray(this.value) ){
        this.checked = $.inArray(this.initialValue, this.value) > -1 ? true : false;
        this.$emit("input", this.checked);
      }
      else if ( typeof(this.value) === 'boolean' ){
        this.checked = this.value;
      }
      else{
        this.checked = this.initialValue === this.value;
        this.$emit("input", this.initialValue);
      }
      this.$forceUpdate();
      this.$emit("ready", this.checked);
    }
  });
})(jQuery, bbn, kendo);
