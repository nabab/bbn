/**
 * Created by BBN on 10/02/2017.
 */
(function($, bbn, kendo){
  "use strict";

  kendo.ui.AutoComplete.prototype.options.autoWidth = true;

  Vue.component('bbn-autocomplete', {
    mixins: [bbn.vue.inputComponent, bbn.vue.optionComponent, bbn.vue.eventsComponent],
    template: '#bbn-tpl-component-autocomplete',
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
            $ele = $(vm.$refs.element),
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
          bbn.fn.log("SELECTE", e);
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
          $ele = $(vm.$refs.element);
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
  });

})(jQuery, bbn, kendo);
