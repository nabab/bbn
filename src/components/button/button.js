/**
 * Created by BBN on 10/02/2017.
 */
(function($, bbn, kendo){
  "use strict";

  Vue.component('bbn-button', {
    template: '#bbn-tpl-component-button',
    mixins: [bbn.vue.eventsComponent],
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
      disabled: {
        type: [Boolean, Function],
        default: false
      },
      command: {
        type: Function
      }
    },
    computed: {
      isDisabled(){
        return typeof(this.disabled) === 'function' ?
          this.disabled() : this.disabled
      }
    },
    methods: {
      click(e){
        if ( this.command ){
          this.command(e);
        }
        this.$emit('click', e);
      }
    }
  });

})(jQuery, bbn, kendo);
