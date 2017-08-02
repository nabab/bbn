<template>
<input v-bind:value="value" :name="name" ref="autocomplete" v-on:input="autocompleteSearch" v-on:click="click($event)" v-on:focus="focus($event)" v-on:blur="blur($event)" v-on:change="change($event)" v-on:keydown="keydown($event)" v-on:keyup="keyup($event)" :disabled="disabled ? true : false" :required="required ? true : false" :placeholder="placeholder">
    </template>
<script>
 export default {
  name:'bbn-autocomplete',

  mixins:[bbn.vue.inputComponent, bbn.vue.optionComponent, bbn.vue.eventsComponent],
  props: {
      animation: {
        type: [Boolean, Object]
      },
      source: {
        type: [String, Object, Array]
      },
      template: {},
      select: {},
      cfg: {
        type: Object,
        default: function(){
          return {
            dataTextField: 'text',
            delay: 200,
            highlightFirst: true
          };
        }
      }
    },
    methods: {
      autocompleteSearch: function(e){
        bbn.fn.log("VAL", e.target.value);
        this.filterValue = e.target.value;
        this.update(this.filterValue);
      },
      listHeight: function(){
        var vm = this,
            $ele = $(vm.$el),
            pos = $ele.offset(),
            h = $ele.height();
        return $(window).height() - pos.top - h - 30;
      },
      getOptions: function(){
        var vm = this,
            cfg = bbn.vue.getOptions(vm);
        if ( cfg.template ){
          var tmp = cfg.template;
          cfg.template = function(e){
            return tmp(e);
          }
        }
        if ( cfg.dataSource && !$.isArray(cfg.dataSource) ){
          cfg.dataSource.options.serverFiltering = true;
          cfg.dataSource.options.serverGrouping = true;
        }
        cfg.select = function(e){
          var data = e.dataItem.toJSON();
          vm.$emit('select', e.dataItem.toJSON(), e);
        };
        if ( !cfg.height ){
          cfg.height = vm.listHeight();
        }
        else{
          bbn.fn.log("Height is defined: " + cfg.height);
        }
        return cfg;
      }
    },
    data: function(){
      return $.extend({
        widgetName: 'kendoAutoComplete',
        filterValue: '',
      }, bbn.vue.treatData(this));
    },
    mounted: function(){
      var vm = this,
          $ele = $(vm.$el);
      vm.widget = $ele.kendoAutoComplete(vm.getOptions()).data("kendoAutoComplete");
      $(window).resize(function(){
        vm.widget.setOptions({
          height: vm.listHeight()
        });
      });
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
      dataSource: function(newDataSource){
        this.widget.setDataSource(newDataSource);
      }
    }
  }
</script>
<style>
</style>