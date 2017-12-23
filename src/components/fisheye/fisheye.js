/**
 * Created by BBN on 15/02/2017.
 */
(function($, bbn){
  "use strict";

  /**
   * Classic input with normalized appearance
   */
  Vue.component('bbn-fisheye', {
    mixins: [bbn.vue.basicComponent, bbn.vue.optionComponent],
    props: {
      source: {
        type: Array,
        default(){
          return [];
        }
      },
      fixedLeft: {
        type: Array,
        default(){
          return [];
        }
      },
      fixedRight: {
        type: Array,
        default(){
          return [];
        }
      },
      zIndex: {
        type: Number,
        default: 1
      },
      delUrl: {},
      insUrl: {},
      top: {
        type: [Number, String],
        default: '0px'
      },
      bottom: {
        type: [Number, String],
        default: '0px'
      },
      position: {
        type: String,
        default: 'left'
      }
    },

    data(){
      return {
        currentData: this.source.slice(),
        menu: false,
        widget: false,
        binEle: false,
        droppableBin: false
      };
    },

    computed: {
      items(){
        let items = this.fixedLeft.slice();
        $.each(this.currentData, (i, a) => {
          items.push(a);
        });
        $.each(this.fixedRight, (i, a) => {
          items.push(a);
        });
        return items;
      }
    },

    methods: {
      onClick(it){
        if ( it.command && $.isFunction(it.command) ){
          it.command();
        }
      },

      add(obj){
        if (
          this.insUrl &&
          (typeof(obj) === 'object') &&
          obj.url &&
          obj.icon &&
          obj.text &&
          obj.id
        ){
          bbn.fn.post(this.insUrl, {id: obj.id}, (d) => {
            if ( d.success ){
              obj.id_option = obj.id;
              obj.id = d.id;
              this.currentData.push(obj);
            }
            else{
              new Error(bbn._("The shortcut has failed to be inserted"));
            }
          });
        }
      },

      remove(id){
        if ( id && this.delUrl ){
          bbn.fn.post(this.delUrl, {id: id}, (d) => {
            if ( d.success ){
              let idx = bbn.fn.search(this.currentData, "id", id);
              if ( idx > -1 ){
                this.currentData.splice(idx, 1)
              }
            }
          });
        }
      },

      setup(){
        var vm = this,
            $ele = $(vm.$el);

        // Bin management
        if ( vm.delUrl ){
          vm.binEle = $("#bbn_dock_menu_bin");
          if ( !vm.binEle.length ){
            vm.binEle = $('<div id="bbn_dock_menu_bin" style="z-index: ' + vm.zIndex + '"><i class="fa fa-trash"></i> </div>').appendTo(document.body);
          }
          if ( vm.droppableBin ){
            vm.droppableBin.droppable("destroy");
          }
          vm.droppableBin = vm.binEle.droppable({
            accept: "li",
            hoverClass: "k-state-hover",
            activeClass: "k-state-active",
            drop: (e, ui) => {
              this.remove(ui.draggable.attr("data-id"))
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
            zIndex: vm.zIndex,
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
        });
      },
    },
    mounted: function(){
      this.setup();
      setTimeout(() => {
        $(this.$el).trigger('mousemove');
      }, 1000);
    },

    updated: function(){
      this.setup();
    }
  });

})(jQuery, bbn);
