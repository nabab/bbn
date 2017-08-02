<template>
<span>
  <input v-bind:value="value"
         :name="name"
         ref="element"
  >
</span>
</template>

<script>

  export default {
    name:'bbn-multiselect',
mixins: [bbn.vue.vueComponent],
    template: '#bbn-tpl-component-multiselect',
    
    props: {
      source: {
        type: [String, Array, Object]
      },
      sortable: {
        type: Boolean
      },
      cfg: {
        type: Object,
        default: function(){
          return {
            dataTextField: 'text',
            dataValueField: 'value',
            dataSource: []
          };
        }
      }
    },
    data: function(){
      return $.extend({
        widgetName: "kendoMultiSelect"
      }, bbn.vue.treatData(this));
    },
    mounted: function(){
      var vm = this,
          cfg = vm.getOptions();
      if ( vm.disabled ){
        cfg.enable = false;
      }
      vm.widget = $(vm.$refs.element).kendoMultiSelect(cfg).data("kendoMultiSelect");
      if ( cfg.sortable ){
        vm.widget.tagList.kendoSortable({
          hint:function(element) {
            return element.clone().addClass("hint");
          },
          placeholder:function(element) {
            return element.clone().addClass("placeholder").html('<div style="width:50px; text-align: center; padding: 0"> ... </div>');
          }
        });
      }
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
<style></style>