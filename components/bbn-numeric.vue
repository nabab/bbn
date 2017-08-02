<template>
<span class="bbn-numeric">
  <input v-bind:value="value"
         :name="name"
         ref="element"
         type="number"
         :disabled="!!disabled"
         :required="!!required"
  >
</span>
</template>

<script>

  export default {
    name:'bbn-numeric',
mixins: [bbn.vue.vueComponent],
    template: '#bbn-tpl-component-numeric',
    

    props: {
      decimals: {
        type: [Number, String]
      },
      format: {
        type: String
      },
      max: {
        type: [Number, String]
      },
      min: {
        type: [Number, String]
      },
      round: {
        type: Boolean
      },
      step: {
        type: [Number, String]
      },
      cfg: {
        type: Object,
        default: function(){
          return {
            format: "n0"
          };
        }
      }
    },
    data: function(){
      return $.extend({
        widgetName: "kendoNumericTextBox"
      }, bbn.vue.treatData(this));
    },
    mounted: function(){
      var vm = this;
      vm.widget = $(vm.$refs.element).kendoNumericTextBox($.extend(vm.getOptions(), {
        spin: function(e){
          vm.$emit('input', e.sender.value());
        }
      })).data("kendoNumericTextBox");
    }
  }
</script>
<style></style>