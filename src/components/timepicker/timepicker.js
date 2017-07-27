/**
 * Created by BBN on 10/02/2017.
 */
(function($, bbn, kendo){
  "use strict";

  var ui = kendo.ui,
      MaskedTimePicker = ui.Widget.extend({
        init: function (element, options) {
          var that = this;
          ui.Widget.fn.init.call(this, element, options);

          $(element).kendoMaskedTextBox({ mask: that.options.dateOptions.mask || "00:00" })
            .kendoTimePicker({
              format: that.options.dateOptions.format || "HH:mm",
              parseFormats: that.options.dateOptions.parseFormats || ["HH:mm:ss", "HH:mm"]
            })
            .closest(".k-timepicker")
            .add(element)
            .removeClass("k-textbox");

          that.element.data("kendoTimePicker").bind("change", function() {
            that.trigger('change');
          });
        },
        options: {
          name: "MaskedTimePicker",
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
          var timepicker = this.element.data("kendoTimePicker");

          if (value === undefined) {
            return timepicker.value();
          }

          timepicker.value(value);
        }
      });
  ui.plugin(MaskedTimePicker);


  Vue.component('bbn-timepicker', {
    mixins: [bbn.vue.vueComponent],
    template: '#bbn-tpl-component-timepicker',
    props: {
      cfg: {
        type: Object,
        default: function(){
          return {
            format: 'HH:mm',
            parseFormats: ['HH:mm:ss', 'HH:mm'],
            mask: '00:00'
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
        bbn.fn.log("VALUE IS ", this.value, kendo.parseDate(this.value));
        return kendo.toString(kendo.parseDate(this.value, "HH:mm:ss"), "HH:mm");
      }
    },
    data: function(){
      return $.extend({
        widgetName: "kendoMaskedTimePicker"
      }, bbn.vue.treatData(this));
    },
    mounted: function(){
      var vm = this;
      vm.widget = $(this.$refs.element)
        .kendoMaskedTimePicker($.extend(vm.getOptions(), {
          change: function(e){
            vm.update(kendo.toString(vm.widget.value(), "HH:mm:ss"));
            return true;
          }
        }))
        .data("kendoTimePicker");
    }
  });

})(jQuery, bbn, kendo);
