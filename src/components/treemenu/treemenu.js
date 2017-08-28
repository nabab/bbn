/**
 * Created by BBN on 15/02/2017.
 */
(function($, bbn, kendo){
  "use strict";

  /**
   * Classic input with normalized appearance
   */
  Vue.component('bbn-treemenu', {
    mixins: [bbn.vue.optionComponent, bbn.vue.resizerComponent],
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
    data(){
      return {
        widgetName: "fancytree",
        isOpened: false,
        hasBeenOpened: false
      };
    },
    computed: {
      treeSource(){
        return [];
      },
    },
    methods: {
      _position(){
        let cfg = this.getOptions();
        $(this.$el)
          .animate($.extend({
            top: cfg.top,
            bottom: cfg.bottom
          }, this.posObject()), 200);
      },
      _disconnect_menu(){
        if ( this.draggable ){
          this.draggable.draggable("destroy");
        }
      },
      _connect_menu(){
        if ( this.fisheye && $.fn.fisheye ){
          let fisheye = bbn.vue.retrieveRef(this, this.fisheye);
          if ( fisheye ){
            if ( this.draggable ){
              try{
                this.draggable.draggable("destroy");
              }
              catch(e){
                new Error("no draggable")
              }
            }
            this.draggable = $(this.$el).find("li")
              .filter(function (){
                return $(this).find("li").length ? false : true;
              })
              .draggable({
                cursorAt: {top: 1, left: 0},
                zIndex: 15000,
                helper: (e) => {
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
                start(e, ui){
                  //e.stopImmediatePropagation();
                  $(fisheye.$el).fisheye("disable");
                },
                stop: function (e, ui){
                  $(fisheye.$el).fisheye("enable");
                }
              });

            if ( this.droppable ){
              this.droppable.droppable("destroy");
            }
            this.droppable = $(fisheye.$el).droppable({
              accept: 'li',
              activeClass: 'active',
              hoverClass: 'ready',
              drop: (e, ui) => {
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
            bbn.fn.log("Connecting menu", $(this.$el).find("li").length, this.draggable);
          }

        }
      },

      posObject(){
        if ( this.cfg.position === 'right' ){
          return {right: this.isOpened ? 0 : -($(this.$el).width() + 40)};
        }
        else{
          return {left: this.isOpened ? 0 : -($(this.$el).width() + 40)};
        }
      },
      show(){
        this.isOpened = true;
        this._position();
      },
      hide(){
        this.isOpened = false;
        this._position();
      },
      toggle(){
        if ( this.isOpened ){
          this.hide();
        }
        else{
          this.show();
        }
      },
      search(v){
        if (!v.length) {
          this.$refs.tree.widget.clearFilter()
        }
        else {
          v = bbn.fn.removeAccents(v).toLowerCase();
          this.$refs.tree.widget.filterNodes((a) => {
            var txt = bbn.fn.removeAccents($('<div/>').html(a.title).text()).toLowerCase();
            bbn.fn.log(txt, txt.indexOf(v));
            return txt.indexOf(v) > -1;
          })
        }
      },
      go(id, data, node){
        if ( data.link ){
          appui.$refs.tabnav.load(data.link);
        }
        this.hide();
      },
      resizeScroll(){
        this.$refs.scroll.$emit('resize')
      }
    },
    mounted(){
      let cfg = this.getOptions();
      if ( cfg.opened ){
        this.isOpened = true;
      }
      this._position();
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

      $(document.body).on("mousedown touch", "*", (e) => {
        var $t = $(e.target);
        if ( this.isOpened &&
          !$t.closest(".bbn-treemenu").length &&
          !$t.closest(".bbn-menu-button").length
        ){
          bbn.fn.log("DEFAULT PREVENTED ON MOUSEDOWN AND TOUCH");
          e.preventDefault();
          e.stopImmediatePropagation();
          this.toggle();
        }
      })
    },
    watch: {
      isOpened(newVal, oldVal){
        if ( newVal ){
          if ( !this.hasBeenOpened ){
            this.hasBeenOpened = true;
          }
        }
        this.$nextTick(() => {
          if ( newVal && !oldVal ){
            this._connect_menu();
          }
          else if ( !newVal && oldVal ){
            this._disconnect_menu();
          }
        });
      }
    }
  });

})(jQuery, bbn, kendo);
