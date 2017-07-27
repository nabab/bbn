/**
 * Created by BBN on 10/02/2017.
 */
(function($, bbn, kendo){
  "use strict";

  var ui = kendo.ui,
      MaskedDatePicker = ui.Widget.extend({
        init: function (element, options) {
          var that = this;
          ui.Widget.fn.init.call(this, element, options);

          $(element).kendoMaskedTextBox({ mask: that.options.dateOptions.mask || "00/00/0000" })
            .kendoDatePicker({
              format: that.options.dateOptions.format || "dd/MM/yyyy",
              parseFormats: that.options.dateOptions.parseFormats || ["yyyy-MM-dd", "dd/MM/yyyy"]
            })
            .closest(".k-datepicker")
            .add(element)
            .removeClass("k-textbox");

          that.element.data("kendoDatePicker").bind("change", function() {
            that.trigger('change');
          });
        },
        options: {
          name: "MaskedDatePicker",
          dateOptions: {}
        },
        events: [
          'change'
        ],
        destroy: function () {
          var that = this;
          ui.Widget.fn.destroy.call(that);

          kendo.destroy(that.element);
        },
        value: function(value) {
          var datepicker = this.element.data("kendoDatePicker");

          if (value === undefined) {
            return datepicker.value();
          }

          datepicker.value(value);
        }
      });
  ui.plugin(MaskedDatePicker);

  Vue.component('bbn-datepicker', {
    mixins: [bbn.vue.vueComponent],
    template: '#bbn-tpl-component-datepicker',
    props: {
      cfg: {
        type: Object,
        default: function(){
          return {
            format: 'dd/MM/yyyy',
            parseFormats: ['yyyy-MM-dd', 'dd/MM/yyyy'],
            mask: '00/00/0000'
          }
        }
      },
      format: {
        type: String
      },
      mask: {
        type: String
      },
      max: {
        type: [Date, String]
      },
      min: {
        type: [Date, String]
      },
      dates: {
        type: Array
      },
      depth: {
        type: String
      },
      disableDates: {
        type: [Array, Function]
      }
    },
    computed: {
      ivalue: function(){
        return kendo.toString(kendo.parseDate(this.value, "yyyy-MM-dd"), "dd/MM/yyyy");
      }
    },
    data: function(){
      return $.extend({
        widgetName: "kendoMaskedDatePicker"
      }, bbn.vue.treatData(this));
    },
    mounted: function(){
      var vm = this,
          cfg = $.extend(vm.getOptions(), {
            change: function(e){
              vm.update(kendo.toString(vm.widget.value(), "yyyy-MM-dd"));
              return true;
            }
          });
      vm.widget = $(vm.$refs.element)
        .kendoMaskedDatePicker($.extend(vm.getOptions(), {
          change: function(e){
            vm.update(kendo.toString(vm.widget.value(), "yyyy-MM-dd"));
            return true;
          }
        }))
        .data("kendoDatePicker");
    }
  });

})(jQuery, bbn, kendo);
