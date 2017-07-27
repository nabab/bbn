/**
 * Created by BBN on 10/02/2017.
 */
(function($, bbn, kendo){
  "use strict";

  Vue.component('bbn-form', {
    template: '#bbn-tpl-component-form',
    props: {
      autocomplete: {},
      disabled: {},
      script: {},
      fields: {},
      action: {
        type: String,
        default: '.'
      },
      success: {
        type: Function
      },
      failure: {
        type: Function
      },
      method: {
        type: String,
        default: 'post'
      },
      cfg: {
        type: Object,
        default: function(){
          return {
            autocomplete: false,
            method: "POST",
            action: "."
          };
        }
      },
      source: {
        type: Object,
        default: function(){
          return {};
        }
      }
    },
    data(){
      return {
        originalData: false
      };
    },
    computed: {
      isModified(){
        let vm = this,
            data = bbn.fn.formdata();
        if ( vm.originalData === false ){
          return false;
        }
        for ( var n in data ){
          if ( data[n] !== vm.originalData[n] ){
            return true;
          }
        }
        return false;
      }
    },
    methods: {
      cancel(){

        return bbn.fn.cancel(this.$el);
      },
      change(prop, value){
        let vm = this;
        vm.$set(vm.source, prop, value);
        vm.$emit('change', prop, value);
      },
      submit: function(){
        return bbn.fn.submit(this.$el);
      },
      reset: function(){
        let vm = this;
      }
    },
    mounted(){
      var vm = this;
      if ( this.$options.propsData.script ){
        $(this.$el).data("script", this.$options.propsData.script);
      }
      vm.$nextTick(() => {
        $(vm.$el).on('input', ':input[name]', function(e){
          vm.change(this.name, this.value);
          if ( !vm.isModified && (this.value !== vm.originalData[this.name]) ){
            vm.isModified = true;
          }
        });
        vm.originalData = bbn.fn.formdata(vm.$el);
      });
    }
  });

})(jQuery, bbn, kendo);