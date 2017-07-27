/**
 * Created by BBN on 15/02/2017.
 */
(function($, bbn, kendo){
  "use strict";

  /**
   * Classic input with normalized appearance
   */
  Vue.component('bbn-treemenu', {
    mixins: [bbn.vue.optionComponent],
    template: "#bbn-tpl-component-treemenu",
    props: {
      placeholder: {
        type: String,
        default: "Search"
      },
      source: {},
      fisheye: {},
      top: {},
      bottom: {},
      position: {
        type: String
      },
      opened: {},
      cfg: {
        type: Object,
        default: function(){
          return {
            fisheye: false,
            data: [],
            top: "0px",
            bottom: "0px",
            position: "left",
            opened: false
          };
        }
      }
    },
    methods: {
      _position: function(){
        var vm = this,
            cfg = vm.getOptions();
        $(vm.$el)
          .animate($.extend({
            top: cfg.top,
            bottom: cfg.bottom
          }, vm.posObject()), 200);
        bbn.fn.analyzeContent(vm.$el, true);
      },
      _disconnect_menu: function(){
        var vm = this;
        if ( vm.draggable ){
          vm.draggable.draggable("destroy");
        }
      },
      _connect_menu: function(){
        var vm = this;
        if ( vm.fisheye && $.fn.fisheye ){
          var fisheye = bbn.vue.retrieveRef(vm, vm.fisheye);
          if ( fisheye ){
            if ( vm.draggable ){
              try{
                vm.draggable.draggable("destroy");
              }
              catch(e){
                new Error("no draggable")
              }
            }
            vm.draggable = $(vm.$el).find("li")
              .filter(function (){
                return $(this).find("li").length ? false : true;
              })
              .draggable({
                cursorAt: {top: 1, left: 0},
                zIndex: 15000,
                helper: function (e){
                  bbn.fn.log(e);
                  var ele = $(e.currentTarget),
                      t   = ele.is("li") ? ele : ele.closest("li"),
                      i   = t.find("i,span.fancytree-custom-icon").first(),
                      r   = $('<div id="bbn_menu2dock_helper" class="appui-xxxl"/>');
                  r.append(i.clone(false));
                  return r;
                },
                scroll: false,
                revert: true,
                revertDuration: 0,
                containment: "window",
                appendTo: document.body,
                start: function (e, ui){
                  //e.stopImmediatePropagation();
                  $(fisheye.$el).fisheye("disable");
                },
                stop: function (e, ui){
                  $(fisheye.$el).fisheye("enable");
                }
              });

            if ( vm.droppable ){
              vm.droppable.droppable("destroy");
            }
            vm.droppable = $(fisheye.$el).droppable({
              accept: 'li',
              activeClass: 'active',
              hoverClass: 'ready',
              drop: function (e, ui) {
                var dataItem = $.ui.fancytree.getNode(ui.draggable[0]).data,
                    obj = {
                      icon: dataItem.icon,
                      text: dataItem.text,
                      url: dataItem.link,
                      id: dataItem.id
                    };
                fisheye.insert(obj);
              }
            });
            bbn.fn.log("Connecting menu", $(vm.$el).find("li").length, vm.draggable);
          }

        }
      },

      posObject: function(){
        if ( this.cfg.position === 'right' ){
          return {right: this.isOpened ? 0 : -($(this.$el).width() + 40)};
        }
        else{
          return {left: this.isOpened ? 0 : -($(this.$el).width() + 40)};
        }
      },
      show: function(){
        this.isOpened = true;
        this._position();
      },
      hide: function(){
        this.isOpened = false;
        this._position();
      },
      toggle: function(){
        if ( this.isOpened ){
          this.hide();
        }
        else{
          this.show();
        }
      },
      search: function(v){
        var vm = this;
        if (!v.length) {
          vm.$refs.tree.widget.clearFilter()
        }
        else {
          v = bbn.fn.removeAccents(v).toLowerCase();
          vm.$refs.tree.widget.filterNodes(function(a) {
            var txt = bbn.fn.removeAccents($('<div/>').html(a.title).text()).toLowerCase();
            bbn.fn.log(txt, txt.indexOf(v));
            return txt.indexOf(v) > -1;
          })
        }
      },
      go: function(id, data, node){
        if ( data.link ){
          appui.$refs.tabnav.load(data.link);
        }
        this.hide();
      }
    },
    computed: {
      treeSource: function(){
        return [];
      },
    },
    data: function(){
      return $.extend({
        widgetName: "fancytree",
        isOpened: false
      }, bbn.vue.treatData(this));
    },
    mounted: function(){
      var vm = this,
          cfg = vm.getOptions();
      if ( cfg.opened ){
        vm.isOpened = true;
      }
      vm._position();
      /*
      $(vm.$refs.search).keyup(function (e) {
        var v = $(this).val();
        if (!v.length) {
          md.wid.clearFilter()
        }
        else {
          v = bbn.fn.removeAccents(v).toLowerCase();
          md.wid.filterNodes(function (a) {
            var txt = bbn.fn.removeAccents($('<div/>').html(a.title).text()).toLowerCase();
            bbn.fn.log(txt, txt.indexOf(v));
            return txt.indexOf(v) > -1;
          })
        }
      });
      */

      $(document.body).on("mousedown touch", "*", function(e){
        var $t = $(e.target);
        if ( vm.isOpened &&
          !$t.closest(".bbn-treemenu").length &&
          !$t.closest(".bbn-menu-button").length
        ){
          e.preventDefault();
          e.stopImmediatePropagation();
          vm.toggle();
        }
      })
    },
    watch: {
      isOpened: function(newVal, oldVal){
        var vm = this;
        vm.$nextTick(function(){
          if ( newVal && !oldVal ){
            vm._connect_menu();
          }
          else if ( !newVal && oldVal ){
            vm._disconnect_menu();
          }
        });
      }
    }
  });

})(jQuery, bbn, kendo);
