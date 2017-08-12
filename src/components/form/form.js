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
      confirmLeave: {
        type: [Boolean, String],
        default: bbn._("Are you sure you want to discard the changes you made in this form?")
      },
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
        popup: false,
        popupIndex: false,
        tab: false,
        originalData: false,
        components: []
      };
    },
    methods: {
      isModified(){
        let data = bbn.fn.formdata(this.$el);
        for ( var n in data ){
          if ( data[n] !== this.originalData[n] ){
            bbn.fn.log("modified", n, data[n], this.originalData[n]);
            return true;
          }
        }
        return false;
      },
      closePopup(){
        if ( this.popup ){
          this.reset();
          this.popup.close(this.popupIndex)
        }
      },
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
      reset(){
        $.each(this.originalData, (name, val) => {
          this.$set(this.source, name, val);
        });
        this.$forceUpdate();
      }
    },
    mounted(){
      if ( this.$options.propsData.script ){
        $(this.$el).data("script", this.$options.propsData.script);
      }
      this.$nextTick(() => {
        /** @todo check every object and get unloaded components
        let vm = this.$el;
        while ( vm.children ){

        }
         */
        let popup = bbn.vue.closest(this, ".bbn-popup"),
            tab = bbn.vue.closest(this, ".bbn-tab");
        this.originalData = bbn.fn.formdata(this.$el);
        $.each(bbn.vue.getComponents(this), (i, cp) => {
          cp.$on("ready", )
        });

        //bbn.fn.log("C'e popup? ", popup);
        if ( popup ){
          this.popup = popup;
          this.popupIndex = $(this.$el).closest("div.k-window").index();
          this.$set(popup.source[this.popupIndex], "close", (popup, idx) => {
            if ( this.isModified() ){
              if ( this.confirmLeave ){
                bbn.fn.confirm(typeof(this.confirmLeave) === 'string' ? this.confirmLeave : bbn._("Are you sure you wanna discard your changes?"), () => {
                  this.reset();
                  popup.close(idx, true);
                })
              }
              else{
                this.reset();
                popup.close(idx, true);
              }
              return false;
            }
          })
        }
        else if ( tab ){
          this.tab = tab;
        }
        $(this.$el).on('input', ':input[name]', (e) => {
          bbn.fn.log("INPUT", e);
          //vm.change(this.name, this.value);
          this.$forceUpdate();
        });
      });
    }
  });

})(jQuery, bbn, kendo);