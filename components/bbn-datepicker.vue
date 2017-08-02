<template>
  <span>
    <input v-bind:value="ivalue" ref="datepicker" :disabled="disabled ? true : false" :required="required ? true : false" :placeholder="placeholder">
    <input v-bind:value="value" ref="idatepicker" :name="name" :disabled="disabled ? true : false" type="hidden">
  </span>
</template>
<script>
  export default {
    name:'bbn-datepicker',
    mixins:[bbn.vue.vueComponent],
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
      vm.widget = $(vm.$refs.datepicker)
        .kendoMaskedDatePicker($.extend(vm.getOptions(), {
          change: function(e){
            vm.update(kendo.toString(vm.widget.value(), "yyyy-MM-dd"));
            return true;
          }
        }))
        .data("kendoDatePicker");
    }
  }
</script>
<style>
</style>
