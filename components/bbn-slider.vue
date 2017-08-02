<template>
<div class="bbn-slider"
     style="width: 100%; height: 100%; overflow: hidden; position: relative"
>
  <slot></slot>
</div>
</template>

<script>
  export default {
    name:'bbn-slider',
    props: {
      orientation: {},
      disabled: {},
      script: {},
      action: {
        type: String,
        default: '.'
      },
      method: {
        type: String,
        default: 'post'
      },
      cfg: {
        type: Object,
        default: function(){
          return {
            orientation: 'horizontal',
          };
        }
      }
    },
    mounted: function(){
      $(this.$children).each(function(){

      })
      if ( this.$options.propsData.script ){
        $(this.$el).data("script", this.$options.propsData.script);
      }
    }
  }
</script>
<style></style>
