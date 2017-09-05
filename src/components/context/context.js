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
        type: [Function, Array]
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
          //bbn.fn.log("source exists", this.source());
          return mapper($.isFunction(this.source) ? this.source() : this.source);
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
        bbn.fn.log("context click", vm);
        let vlist = vm.$root.vlist || (window.appui ? window.appui.vlist : false);
        if ( vm.dataSource && (vlist !== undefined) ){
          vlist.push({
            items: vm.dataSource,
            left: e.clientX ? e.clientX : vm.$el.offsetLeft,
            top: e.clientY ? e.clientY : vm.$el.offsetTop
          });
        }
      },
    },
    render: function(createElement){
      return createElement(
        this.tag,
        $.extend({
          "class": {
            "bbn-context": true
          },
          on: {
            click: (e) => {
              if ( !this.context ){
                bbn.fn.log("is not context", e);
                e.preventDefault();
                e.stopImmediatePropagation();
                this.clickItem(e);
              }
            },
            contextmenu: (e) => {
              if ( this.context ){
                bbn.fn.log("is context");
                e.preventDefault();
                e.stopImmediatePropagation();
                this.clickItem(e);
              }
            }
          }
        }, true),
        this.$slots.default
      );
    }
  });

})(jQuery, bbn, kendo);
