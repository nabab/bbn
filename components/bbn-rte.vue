<template>
  <textarea class="bbn-rte"
          :value="value"
          :name="name"
          :required="required"
  ></textarea>
</template>
<script>
  var $= require('jquery');
    var trumbowyg = require('trumbowyg');
    console.log(trumbowyg, require('trumbowyg'));
  export default{
    name:'bbn-rte',
    mixins: [bbn.vue.vueComponent],
    props: {
      pinned: {},
      top: {},
      left: {},
      bottom: {},
      right: {},
      height:{},
      cfg: {
        type: Object,
        default: function(){
          return {
            pinned: true,
            top: null,
            left: null,
            bottom: 5,
            right: 5,
          }
        }
      }
    },
    data: function(){
      return bbn.vue.treatData(this);
    },
    methods: {
    },
    mounted: function(){
      var vm = this,
          cfg = vm.getOptions(),
          $ele = $(vm.$el);
          console.log("re", $, $ele, trumbowyg(), $ele.trumbowyg );
      vm.widget = $ele.trumbowyg({
        lang: 'fr',
        resetCss: true
      });
      console.log(vm.widget);
      $ele.on("tbwchange", function(ev){
        vm.update(ev.target.value)
      })
    }
  }
</script>
<style lang="less" scoped>
  .bbn-rte{
    min-height: 150px;
    overflow: auto;
    display: block;
    padding: 0;
    margin: 0;
  }
</style>
