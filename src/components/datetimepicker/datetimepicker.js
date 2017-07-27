/**
 * Created by BBN on 10/02/2017.
 */
(function($, bbn, kendo){
  "use strict";

  var ui = kendo.ui,
      MaskedDateTimePicker = ui.Widget.extend({
        init: function (element, options) {
          var that = this;
          ui.Widget.fn.init.call(this, element, options);

          $(element).kendoMaskedTextBox({ mask: that.options.dateOptions.mask || "00/00/0000 00:00" })
            .kendoDateTimePicker({
              format: that.options.dateOptions.format || "dd/MM/yyyy HH:mm",
              parseFormats: that.options.dateOptions.parseFormats || ["yyyy-MM-dd HH:mm:ss", "dd/MM/yyyy HH:mm"]
            })
            .closest(".k-datetimepicker")
            .add(element)
            .removeClass("k-textbox");

          that.element.data("kendoDateTimePicker").bind("change", function() {
            that.trigger('change');
          });
        },
        options: {
          name: "MaskedDateTimePicker",
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
          var datetimepicker = this.element.data("kendoDateTimePicker");

          if (value === undefined) {
            return datetimepicker.value();
          }

          datetimepicker.value(value);
        }
      });
  ui.plugin(MaskedDateTimePicker);


  Vue.component('bbn-datetimepicker', {
    mixins: [bbn.vue.vueComponent],
    template: '#bbn-tpl-component-datetimepicker',
    props: {
      cfg: {
        type: Object,
        default: function(){
          return {
            format: 'dd/MM/yyyy HH:mm',
            parseFormats: ['yyyy-MM-dd HH:mm:ss', 'dd/MM/yyyy HH:mm'],
            mask: '00/00/0000 00:00'
          }
        }
      },
      max: {
        type: [Date, String]
      },
      min: {
        type: [Date, String]
      },
      culture: {
        type: String
      },
      dates: {
        type: Array
      },
      depth: {
        type: String
      },
      disableDates: {
        type: [Array, Function]
      },
    },
    computed: {
      ivalue: function(){
        return kendo.toString(kendo.parseDate(this.value, "yyyy-MM-dd HH:mm:ss"), "dd/MM/yyyy HH:mm");
      }
    },
    data: function(){
      return $.extend({
        widgetName: "kendoMaskedDateTimePicker"
      }, bbn.vue.treatData(this));
    },
    mounted: function(){
      var vm = this;
      vm.widget = $(this.$refs.element)
        .kendoMaskedDateTimePicker($.extend(vm.getOptions(), {
          change: function(e){
            vm.update(kendo.toString(vm.widget.value(), "yyyy-MM-dd HH:mm:ss"));
            return true;
          }
        }))
        .data("kendoDateTimePicker");
    }
  });

})(jQuery, bbn, kendo);
