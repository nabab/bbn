/**
 * Created by BBN on 10/02/2017.
 */
(function($, bbn, kendo){
  "use strict";

  Vue.component('bbn-colorpicker', {
    mixins: [bbn.vue.vueComponent],
    template: '#bbn-tpl-component-colorpicker',
    props: {
      value: {
        type: String
      },
      preview: {
        type: Boolean,
        default: true
      },
      buttons: {
        type: Boolean,
        default: true
      },
      clearButton: {
        type: Boolean,
        default: false
      },
      titleSize: {
        type: Object,
        default: function(){
          return {
            width: 14,
            height: 14
          }
        }
      },
      palette: {
        type: Array
      },
      cfg: {
        type: Object,
        default: function(){
          return {
            buttons: true,
            clearButton: false,
            tileSize: {
              width: 14,
              height: 14
            },
            palette: null,
            preview: true
          };
        }
      }
    },
    methods: {
      build(){
        bbn.fn.log("colorpicker builder", this.$refs.element);
      },
      getOptions: function(){
        const vm  = this;
        let cfg = bbn.vue.getOptions(vm);
        cfg.change = function (e){
          bbn.fn.log(e);
          vm.$emit("input", e.sender.value());
          if ( $.isFunction(vm.change) ){
            vm.change();
          }
        };
        return cfg;
      }
    },
    mounted: function(){
      this.widget = $(this.$refs.element)
        .kendoColorPicker(this.getOptions())
        .data("kendoColorPicker");
      bbn.fn.log("WIDGET", this.widget);
    },
    data: function(){
      return $.extend({
        widgetName: "kendoColorPicker"
      }, bbn.vue.treatData(this));
    },
  });

})(jQuery, bbn, kendo);
