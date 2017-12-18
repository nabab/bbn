/**
 * Created by BBN on 10/02/2017.
 */
(function($, bbn, kendo){
  "use strict";

  Vue.component('bbn-button', {
    mixins: [bbn.vue.basicComponent, bbn.vue.eventsComponent],
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
      url: {
        type: String
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
    },
    computed: {
      isDisabled(){
        return typeof(this.disabled) === 'function' ?
          this.disabled() : this.disabled
      }
    },
    methods: {
      click(e){
        if ( this.url ){
          bbn.fn.link(this.url);
        }
        else{
          this.$emit('click', e);
        }
      }
    }
  });

})(jQuery, bbn, kendo);
