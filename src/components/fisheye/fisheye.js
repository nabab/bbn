/**
 * Created by BBN on 15/02/2017.
 */
(function($, bbn){
  "use strict";

  /**
   * Classic input with normalized appearance
   */
  Vue.component('bbn-fisheye', {
    mixins: [bbn.vue.optionComponent],
    template: "#bbn-tpl-component-fisheye",
    props: {
      value: {
        type: Array,
        default: function(){
          return [];
        }
      },
      minIndex: {
        type: Number,
        default: 0
      },
      delUrl: {},
      insUrl: {},
      top: {},
      menu: {},
      bottom: {},
      position: {
        type: String
      },
      cfg: {
        type: Object,
        default: function(){
          return {
            minIndex: 0,
            bin_url: false,
            value: [],
            top: "0px",
            bottom: "0px",
            position: "left",
            opened: false
          };
        }
      }
    },
    methods: {
      onClick: function(it){
        if ( it.click && $.isFunction(it.click) ){
          it.click();
        }
      },

      insert: function(obj){
        var vm = this;
        if (
          vm.insUrl &&
          (typeof(obj) === 'object') &&
          obj.url &&
          obj.icon &&
          obj.text &&
          obj.id
        ){
          bbn.fn.post(vm.insUrl, {id: obj.id}, function(d){
            if (d.success) {
              var index = -1;
              $.each(vm.value, function(i, a){
                if ( a.id ){
                  index = i;
                }
              });
              var newSelected = index + 1;
              if ( newSelected < vm.minIndex ){
                newSelected = vm.minIndex;
              }
              vm.value.splice(newSelected, 0, obj);
            }
            else{
              new Error(bbn._("The shortcut has failed to be inserted"));
            }
          });
        }
      },

      setup: function(){
        var vm = this,
            $ele = $(vm.$el);

        // Bin management
        if ( vm.delUrl ){
          vm.binEle = $("#bbn_dock_menu_bin");
          if ( !vm.binEle.length ){
            vm.binEle = $('<div id="bbn_dock_menu_bin"><i class="fa fa-trash"></i> </div>').appendTo(document.body);
          }
          if ( vm.droppableBin ){
            vm.droppableBin.droppable("destroy");
          }
          vm.droppableBin = vm.binEle.droppable({
            accept: "li",
            hoverClass: "k-state-hover",
            activeClass: "k-state-active",
            drop: function (e, ui) {
              var id = parseInt(ui.draggable.attr("data-id"));
              if ( id ){
                bbn.fn.post(vm.delUrl, {id: id}, function (d) {
                  if (d.success) {
                    $ele.fisheye("remove", id);
                    var idx = bbn.fn.search(vm.items, "id", id);
                    if ( idx > -1 ){
                      vm.items.splice(idx, 1)
                    }
                  }
                });
              }
            }
          });

          if ( vm.draggable ){
            vm.draggable.destroy();
          }
          vm.draggable = $ele.find("li[data-id!='']").draggable({
            helper: function (e, ui) {
              var t = $(e.currentTarget),
                  i = t.find("i"),
                  r = $('<div id="bbn_menu2dock_helper"/>');
              r.append(i[0].outerHTML);
              return r;
            },
            cursorAt: {top: 1, left: 0},
            zIndex: 13,
            scroll: false,
            containment: "window",
            appendTo: 'body',
            start: function (e, ui) {
              vm.binEle.show();
            },
            stop: function (e, ui) {
              vm.binEle.hide();
            }
          }).data("draggable");

          if ( vm.widget ){
            vm.widget.fisheye("destroy");
          }
        }

        if ( $ele.hasClass("ui-fisheye") ){
          $ele.fisheye("destroy");
        }

        vm.widget = $ele.fisheye({
          items: 'li',
          itemsText: 'span',
          container: 'ul',
          valign: 'top'
        })
      },
    },
    data: function(){
      return $.extend({
        widget: false,
        binEle: false,
        droppableBin: false,
        droppable: false
      }, bbn.vue.treatData(this));
    },

    mounted: function(){
      this.setup();
    },

    updated: function(){
      this.setup();
    }
  });

})(jQuery, bbn);
