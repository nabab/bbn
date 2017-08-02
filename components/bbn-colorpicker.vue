<template>
<input v-bind:value="value" :name="name" ref="colorpicker" :disabled="disabled ? true : false" :required="required ? true : false">
    </template>
<script>
 export default {
  name:'bbn-colorpicker',

  mixins:[bbn.vue.vueComponent],
  props: {
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
    mounted: function(){
      this.widget = $(this.$el).kendoColorPicker(this.getOptions()).data("kendoColorPicker");
    },
    data: function(){
      return $.extend({
        widgetName: "kendoColorPicker"
      }, bbn.vue.treatData(this));
    },
  }
</script>
<style>
</style>