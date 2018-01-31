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
    mixins: [bbn.vue.basicComponent, bbn.vue.fullComponent],
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
      ivalue(){
        return kendo.toString(kendo.parseDate(this.value, "yyyy-MM-dd HH:mm:ss"), "dd/MM/yyyy HH:mm");
      }
    },
    data(){
      return $.extend({
        widgetName: "kendoMaskedDateTimePicker"
      }, bbn.vue.treatData(this));
    },
    mounted(){
      let vm = this;
      vm.widget = $(vm.$refs.element)
        .kendoMaskedDateTimePicker($.extend(vm.getOptions(), {
          min: vm.min ? ( (typeof vm.min === 'string') ? new Date(vm.min) : vm.min) : undefined,
          max: vm.max ? ( (typeof vm.max === 'string') ? new Date(vm.max) : vm.max) : undefined,
          change: () => {
            vm.emitInput(kendo.toString(vm.widget.value(), "yyyy-MM-dd HH:mm:ss"));
            return true;
          }
        }))
        .data("kendoDateTimePicker");
      this.$emit("ready", this.value);
    },
    watch: {
      min(newVal){
        if ( newVal ){
          if ( typeof newVal === 'string' ){
            newVal = new Date(newVal);
          }
          this.widget.setOptions({
            min: newVal
          });
        }
      },
      max(newVal){
        if ( newVal ){
          if ( typeof newVal === 'string' ){
            newVal = new Date(newVal);
          }
          this.widget.setOptions({
            max: newVal
          });
        }
      }
    }
  });

})(jQuery, bbn, kendo);
