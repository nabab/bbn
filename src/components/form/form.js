/**
 * Created by BBN on 10/02/2017.
 */
(function($, bbn, kendo){
  "use strict";

  Vue.component('bbn-form', {
    template: '#bbn-tpl-component-form',
    props: {
      autocomplete: {
        type: Boolean,
        default: false
      },
      disabled: {},
      script: {},
      fields: {},
      confirm: {
        type: [String, Function]
      },
      confirmLeave: {
        type: [Boolean, String, Function],
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
      successMessage: {
        type: [String, Function]
      },
      failureMessage: {
        type: [String, Function]
      },
      method: {
        type: String,
        default: 'post'
      },
      buttons: {
        type: Array,
        default(){
          return ['submit', 'cancel'];
        }
      },
      data: {
        type: Object
      },
      fixedFooter: {
        type: Boolean,
        default: true
      },
      // That will be a form schema generating the inputs
      source: {
        type: Object,
        default: function(){
          return {};
        }
      },
      // Sets if it is the data property which must be sent, or the content of the named fields
      // (in this case names are not necessary on form inputs)
      sendModel: {
        type: Boolean,
        default: true
      }
    },
    data(){
      return {
        modified: false,
        popup: false,
        popupIndex: false,
        tab: false,
        originalData: false,
        realButtons: (() => {
          let r = [];
          $.each(this.buttons.slice(), (i, a) => {
            let t = typeof(a);
            if ( t === 'string' ){
              switch ( a ){
                case 'submit':
                  r.push({
                    text: bbn._('Submit'),
                    icon: 'fa fa-check-circle',
                    command: this.submit
                  });
                  break;
                case 'cancel':
                  r.push({
                    text: bbn._('Cancel'),
                    icon: 'fa fa-times-circle',
                    command: this.cancel
                  });
                  break;
                case 'reset':
                  r.push({
                    text: bbn._('Reset'),
                    icon: 'fa fa-refresh',
                    command: this.reset
                  });
                  break;
              }
            }
            else if ( t === 'object' ){
              r.push(a);
            }
          });
          return r;
        })()
      };
    },
    computed: {
      hasFooter(){
        return !!((this.$slots.footer && this.$slots.footer.length) || this.realButtons.length);
      }
    },
    methods: {
      _getPopup(){
        if ( this.window ){
          return this.window.popup;
        }
        if ( this.tab.$refs.popup ){
          return this.tab.$refs.popup.length ? this.tab.$refs.popup[0] : this.tab.$refs.popup;
        }
        if ( this.$root.$refs.popup ){
          return this.$root.$refs.popup.length ? this.$root.$refs.popup[0] : this.$root.$refs.popup;
        }
        return false;
      },
      _post(){
        bbn.fn.post(this.action, this.data, (d) => {
          this.$emit('success', d);
          let p = this._getPopup();
          if ( this.successMessage && p ){
            p.alert(this.successMessage);
            bbn.fn.info(this.successMessage, p);
          }
          this.originalData = this.data;
          if ( p ){
            p.close();
          }
        }, (xhr, textStatus, errorThrown) => {
          this.$emit('failure', xhr, textStatus, errorThrown)
        });
      },
      getModifications(){
        let data = this.getData(this.$el) || {},
            res = {};
        for ( let n in data ){
          if ( (this.sendModel && (data[n] !== this.originalData[n])) || (!this.sendModel && (data[n] != this.originalData[n])) ){
            res[n] = data[n];
          }
        }
        return res;
      },
      getData(){
        return this.sendModel ? this.data : bbn.fn.formdata(this.$el);
      },
      isModified(){
        let data = this.getData(this.$el) || {};
        for ( let n in data ){
          if ( (this.sendModel && (data[n] !== this.originalData[n])) || (!this.sendModel && (data[n] != this.originalData[n])) ){
            return true;
          }
        }
        return false;
      },
      closePopup(window, ev){
        if ( this.window ){
          if ( this.confirmLeave && this.isModified() ){
            ev.preventDefault();
            this.window.popup.confirm(this.confirmLeave, () => {
              this.reset();
              this.window.close(true);
            })
          }
        }
      },
      closeTab(url, check){
        if ( this.tab && (url === this.tab.url) ){
          check.prevent = true;
          this.tab.popup.confirm(this.confirmLeave, () => {
            this.reset();
            this.tab.tabNav.close(this.tab.idx, true);
          });
        }
      },
      cancel(){
        this.reset();
      },
      submit(){
        let ok = true;
        $(this.$el).find("input,select,textarea").filter("[name]").each((i, a) => {
          let $a = $(a);
          if ( a.required && !$a.val() ){
            if ( $a.is(":visible") ){
              $a.focus();
            }
            else{
              $a.closest(":visible").focus();
            }
            ok = false;
            return false;
          }
        });
        if ( ok ){
          $.each((i, a) => {
            if ( $.isFunction(a.isValid) ){
              ok = false;
              return false;
            }
          });
        }
        if ( !ok ){
          return false;
        }
        let cf = false;
        if ( this.confirm ){
          if ( $.isFunction(this.confirm) ){
            cf = this.confirm(this);
          }
          else{
            cf = this.confirm;
          }
          if ( cf ){
            let popup = this._getPopup();
            if ( popup ){
              bbn.fn.info("POPUP!", popup);
              popup.confirm(cf, () => {
                popup.close();
                this._post();
              });
            }
          }
        }
        if ( !cf ){
          this._post();
        }
      },
      reset(){
        bbn.fn.log("reset");
        $.each(this.originalData, (name, val) => {
          this.$set(this.data, name, val);
        });
        this.$forceUpdate();
      },
      init(){
        if ( this.$options.propsData.script ){
          $(this.$el).data("script", this.$options.propsData.script);
        }
        this.originalData = $.extend({}, this.getData());
        this.$nextTick(() => {
          if ( !this.window ){
            this.window = bbn.vue.closest(this, "bbn-window");
            if ( this.window ){
              this.window.addClose(this.closePopup);
            }
            else if ( !this.tab ){
              this.tab = bbn.vue.closest(this, ".bbn-tab");
              this.tab.tabNav.$once("close", this.closeTab);
            }
          }
        });
      }
    },
    mounted(){
      this.init();
    },
    watch: {
      data: {
        deep: true,
        handler(newVal){
          this.$emit('input', newVal);
          this.modified = this.isModified();
        }
      }
    }
  });

})(jQuery, bbn, kendo);