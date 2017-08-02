<template>
  <span class="bbn-combo">
    <input v-bind:value="value"
           :name="name"
           ref="element"
           :disabled="disabled ? true : false"
           :required="required ? true : false"
    >
  </span>
</template>
<script>
  export default {
    name:'bbn-combo',
    mixins: [bbn.vue.vueComponent],
    props: {
      animation: {
        type: [Boolean, Object]
      },
      source: {
        type: [String, Object, Array]
      },
      cfg: {
        type: Object,
        default: function(){
          return {
            dataTextField: 'text',
            dataValueField: 'value',
            delay: 200,
            highlightFirst: true
          };
        }
      }
    },
    data: function(){
      return $.extend({
        widgetName: "kendoComboBox"
      }, bbn.vue.treatData(this));
    },
    mounted: function(){
      this.widget = $(this.$el).kendoComboBox(this.getOptions()).data("kendoComboBox");
    },
    computed: {
      dataSource: function(){
        if ( this.source ){
          return bbn.vue.transformDataSource(this);
        }
        return [];
      }
    },
    watch:{
      source: function(newDataSource){
        this.widget.setDataSource(this.dataSource);
      }
    }
  }
</script>
<style>
</style>
