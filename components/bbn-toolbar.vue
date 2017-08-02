<template>
<div class="bbn-toolbar">
  <div class="slot">
    <slot></slot>
  </div>
</div>


</template>

<script>

  export default {
    name:'bbn-toolbar',
    mixins: [bbn.vue.optionComponent],
    props: {
      items: {
        type: Array
      },
      cfg: {
        type: Object,
        default: function(){
          return {
            items: []
          };
        }
      }
    },
    data: function(){
      return $.extend({
        widgetName: "kendoToolBar"
      }, bbn.vue.treatData(this));
    },
    methods: {
    },
    mounted: function(){
      var vm = this,
          cfg = vm.getOptions(),
          $ele = $(this.$el),
          items = [],
          $elm,
          tmp,
          html;
      if ( $ele.find(".slot").length ){
        if ( !cfg.items.length && vm.$slots.default.length ){
          $.each(vm.$slots.default, function(i, a){
            if ( a.tag === 'div' ){
              $elm = $(a.elm);
              html = $elm.html();
              if ( !html ){
                cfg.items.push({type: 'separator'});
              }
              else{
                tmp = {
                  alias: bbn.fn.randomString(48),
                  ele: $elm
                };
                cfg.items.push({template: '<span class="' + tmp.alias + '"></span>'});
                items.push(tmp);
              }
            }
          });
        }
      }
      vm.widget = $ele.kendoToolBar(cfg).data("ui-toolbar");
      $.each(items, function(i, a){
        var target = $("." + a.alias).parent().empty();
        a.ele.appendTo(target);
      });
      $ele.find(".slot").remove();
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
        bbn.fn.log("Changed DS", this.dataSource);
        this.widget.setDataSource(this.dataSource);
      }
    }
  }
</script>
<style>
.bbn-toolbar{
  overflow: visible;
  .k-input:before{
    display: none !important;
  }
  width: 100%;
  .slot{
    display: none;
  }
}

</style>
