/**
 * Created by BBN on 15/02/2017.
 */
(function($, bbn, kendo){
  "use strict";

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

  Vue.component('bbn-context', {
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
      clickItem(e){
        const vm = this;
        e.preventDefault();
        e.stopImmediatePropagation();
        let vlist = vm.$root.vlist || (window.appui ? window.appui.vlist : false);
        if ( vm.source && (vlist !== undefined) ){
          bbn.fn.log("context click", vm);
          vlist.push({
            items: vm.source,
            left: e.clientX ? e.clientX : vm.$el.offsetLeft,
            top: e.clientY ? e.clientY : vm.$el.offsetTop
          });
        }
      },
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
      let ev = {};
      if ( vm.context ){
        ev.contextmenu = vm.clickItem
      }
      else{
        ev.click = vm.clickItem;
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
          on: ev
        }, true),
        res.children
      );
    }
  });

})(jQuery, bbn, kendo);
