/**
 * Created by BBN on 15/02/2017.
 */
(($, bbn) => {
  "use strict";

  /**
   * Classic input with normalized appearance
   */
  Vue.component('bbn-treemenu', {
    mixins: [bbn.vue.basicComponent, bbn.vue.resizerComponent],
    props: {
      placeholder: {
        type: String,
        default: "Search"
      },
      source: {
        type: [String, Array, Function],
        default(){
          return [];
        }
      },
      shortcuts: {
        type: [Function, Vue]
      },
      top: {
        type: Number,
        default: 0
      },
      bottom: {
        type: Number,
        default: 0
      },
      position: {
        type: String,
        default: 'left'
      },
      opened: {
        type: Boolean,
        default: false
      },
      search: {
        type: Boolean,
        default: true
      },
      menus: {
        type: Array,
        default(){
          return [];
        }
      },
      current: {}
    },
    data(){
      let isAjax = !Array.isArray(this.source)
      return {
        searchExp: '',
        isOpened: this.opened,
        hasBeenOpened: false,
        posTop: this.top,
        posBottom: this.bottom,
        isAjax: isAjax,
        items: isAjax ? [] : this.source,
        currentMenu: this.current
      };
    },
    methods: {
      getMenu(node){
        if ( !this.shortcuts || node.numChildren ){
          return [];
        }
        let obj = {
          url: node.data.link,
          icon: node.icon,
          text: node.text,
          id: node.data.id
        },
            menu = this;
        return [{
          text: bbn._('Create a shortcut'),
          icon: 'fa fa-external-link-square',
          command(){
            if ( menu.shortcuts ){
              let sc = $.isFunction(menu.shortcuts) ? menu.shortcuts() : menu.shortcuts;
              if ( sc ){
                sc.add(obj);
              }
            }

          }
        }]
      },
      _position(){
        $(this.$el)
          .animate($.extend({
            top: this.posTop + 'px',
            bottom: this.posBottom + 'px'
          }, this.posObject()), 200);
      },
      posObject(){
        let o = {};
        o[this.position === 'right' ? 'right' : 'left'] = this.isOpened ? 0 : -($(this.$el).width() + 40);
        return o;
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
      mapSrc(data){
        if ( data.items && data.items.length ){
          data.cls = 'bbn-lg';
          data.selectable = false;
        }
        return data;
      },
      go(node, event){
        event.preventDefault();
        if ( node && node.data && node.data.link ){
          bbn.fn.link(node.data.link);
          this.hide();
        }
      },
      resizeScroll(){
        if ( this.$refs.scroll ){
          this.$refs.scroll.onResize()
        }
      },
      reset(){
        this.$refs.tree.reset();
        this.$refs.tree.load();
      },
      getData(){
        return {menu: this.currentMenu};
      },
      checkMouseDown(e){
        let $t = $(e.target);
        if ( this.isOpened &&
          !$t.closest(".bbn-treemenu").length &&
          !$t.closest(".k-list-container").length &&
          !$t.closest(".bbn-menu-button").length
        ){
          e.preventDefault();
          e.stopImmediatePropagation();
          this.toggle();
        }
      }
    },
    mounted(){
      this.onResize();
      this._position();
    },
    watch: {
      isOpened(newVal){
        if ( newVal ){
          if ( !this.hasBeenOpened ){
            this.hasBeenOpened = true;
            this.$refs.tree.load();
          }
          $(document.body).on("mousedown touch", "*", this.checkMouseDown)
        }
        else{
          $(document.body).off("mousedown touch", "*", this.checkMouseDown)
        }
      },
      currentMenu(){
        this.reset()
      }
    }
  });

})(jQuery, bbn);
