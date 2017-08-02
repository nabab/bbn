<template></template>
<script>
  var fancytree =  require('fancytree');


  /**
   * Classic input with normalized appearance
   */
  var mapper = function(ar){
    var r = [];
    $.each(ar, function(i, a){
      r[i] = {
        encoded: false,
        text: '<span class="bbn-context-li' +
          (a.disabled ? ' disabled' : '') +
          (a.hidden ? ' hidden' : '') +
          '">' +
          (a.icon || a.selected ? '<i class="' + ( a.icon ? a.icon : 'fa fa-check') + '"></i>' : '<i class="fa"> </i>' ) +
          a.text +
          '</span>',
      };
      if ( a.click ){
        r[i].click = a.click;
      }
      if ( a.items ){
        r[i].items = mapper(a.items);
      }
    });
    return r;
  };

  export default {
    name:'bbn-context',
    props: {
      source: {
        type: Array
      },
      tag: {
        type: String,
        default: 'span'
      },
      context: {
        type: Boolean,
        default: false
      }
    },
    computed: {
      dataSource: function(){
        if ( this.source ){
          return mapper(this.source);
        }
        return [];
      }
    },
    data: function(){
      return $.extend({
        widgetName: "kendoContextMenu",
      }, bbn.vue.treatData(this));
    },
    methods: {
      paint: function(x, y){
        var vm = this;
        if ( vm.contextMenu ){
          vm.widget.close();
          vm.contextMenu.trigger("close");
          return;
        }
        if ( vm.dataSource.length ){
          vm.contextMenu = $('<ul class="bbn-context-menu"/>');
          $(document.body).append(vm.contextMenu);
          vm.widget = vm.contextMenu.kendoContextMenu({
            close: function(e){
              if ( e.item[0] === vm.contextMenu[0] ){
                vm.widget.destroy();
                vm.contextMenu.remove();
                setTimeout(function(){
                  vm.contextMenu = false;
                }, 5);
              }
            },
            dataSource: vm.dataSource,
            select: function(e){
              var indexes = [],
                  li = $(e.item),
                  ul = li.closest("ul.k-group"),
                  res = false;
              while ( ul.length ){
                indexes.unshift(ul.children("li").index(li));
                li = ul.closest("li");
                ul = li.closest("ul.k-group");
              }
              if ( indexes.length ){
                var ds = vm.dataSource;
                $.each(indexes, function(i, a){
                  if ( ds[a] !== undefined ){
                    if ( i === (indexes.length - 1) ){
                      res = ds[a];
                    }
                    if ( ds[a].items ){
                      ds = ds[a].items;
                    }
                  }
                });
                if ( res && $.isFunction(res.click) ){
                  res.click(e, indexes.pop(), res);
                }
              }
            }
          }).data("kendoContextMenu");
          vm.widget.open(x, y);
        }
        else{
          bbn.fn.log("No item");
        }

      }
    },
    render: function(createElement){
      var vm = this,
          res = {
            data: {},
            children: []
          };
      if ( vm.$slots.default ){
        for ( var node of vm.$slots.default ){
          if ( node.tag ){
            res = node;
            break;
          }
        }
      }
      if ( !res.tag ){
        res.tag = vm.tag;
      }
      return createElement(
        res.tag,
        $.extend(res.data, {
          "class": {
            "bbn-context": true
          },
          on: {
            click: function(e){
              e.preventDefault();
              e.stopImmediatePropagation();
              if ( vm.source && (vm.$root.vlist !== undefined) ){
                vm.$root.vlist.push({
                  items: vm.source,
                  left: e.clientX ? e.clientX : vm.$el.offsetLeft,
                  top: e.clientY ? e.clientY : vm.$el.offsetTop
                });
              }
            }
          }
        }, true),
        res.children
      );
    },
    mounted: function(){
      //bbn.fn.log("CONTEXT MOUNTED", this.$el);
    },
    watch:{
      source: function(newDataSource){
        bbn.fn.log("Changed DS in context", this.dataSource);
      }
    }
  }
</script>
<style>
.bbn-context-menu{
  .bbn-context-li{
    i{
      margin-right: 1em;
    }
    &.disabled i{
      opacity: 0.5;
    }
    &.hidden i{
      opacity: 0 !important;
    }
  }
}
</style>