/**
 * Created by BBN on 10/02/2017.
 */
(function($, bbn){
  "use strict";

  Vue.component('bbn-tree-node', {
    template: '#bbn-tpl-component-tree-node',
    props: {
      selected:{
        type: Boolean,
        default: false
      },
      selectedClass: {
        type: String,
        default: 'selected'
      },
      activeClass: {
        type: String,
        default: 'active'
      },
      expanded:{
        type: Boolean,
        default: false
      },
      tooltip: {
        type: String
      },
      icon:{
        type: [Boolean, String]
      },
      selectable: {
        type: Boolean,
        default: true
      },
      text: {
        type: String
      },
      data: {
        type: Object,
        default(){
          return {};
        }
      },
      cls: {
        type: [String]
      },
      component: {
        type: String
      },
      num: {
        type: Number
      },
      source: {
        type: Array,
        default(){
          return [];
        }
      },
      level: {
        type: Number,
        default: 1
      },
      initial: {
        type: Boolean,
        default: false
      }
    },
    data: function(){
      return {
        items: this.source,
        isSelected: this.selected,
        isActive: false,
        tree: false,
        isExpanded: this.expanded,
        numChildren: this.num !== undefined ? this.num : this.source.length,
        animation: this.initial ? false : true
      }
    },
    computed: {
      iconStyle(){
        let style = {};
        if ( this.tree.iconColor ){
          style.color = $.isFunction(this.tree.iconColor) ? this.tree.iconColor(this) : this.tree.iconColor;
        }
        return style;
      },
      menu(){
        return this.getMenu()
      }
    },
    methods: {
      onOpen(){
        this.resize();
        this.tree.$emit('open', this);
      },
      onClose(){
        this.resize();
        this.tree.$emit('close', this);
      },
      resize(){
        if ( this.tree ){
          this.tree.$refs.scroll.onResize();
        }
      },
      getMenu(){
        return this.tree.getMenu(this);
      },
      beforeEnter(){
        if ( this.animation ){
          alert("beforeEnter " + $(this.$refs.container).height());
        }
      },
      enter(){
        if ( this.animation ){
          alert("enter " + $(this.$refs.container).height());
        }
      },
      afterEnter(){
        if ( this.animation ){
          alert("afterEnter " + $(this.$refs.container).height());
        }
      },
      startDrag(e){
        e.preventDefault();
        e.stopImmediatePropagation();
        this.tree.dragging = this;
        $(document.body).one('mouseup', this.endDrag);
        this.tree.$refs.helper.innerHTML = this.$el.outerHTML;
      },
      endDrag(e){
        e.preventDefault();
        e.stopImmediatePropagation();
        if (
          this.tree.overNode &&
          (this.tree.dragging !== this.tree.overNode) &&
          !this.tree.isNodeOf(this.tree.overNode, this.tree.dragging)
        ){
          this.tree.move(this, this.tree.overNode);
        }
        this.tree.dragging = false;
        this.isSelected = !this.isSelected;
      },
      mouseOver(){
        this.tree.overNode = this;
      },
      toData(item){
        let r = {};
        for ( let n in item ){
          if ( (n !== 'items') && ($.inArray(n, this.$options._propKeys) == -1) ){
            r[n] = item[n];
          }
        }
        return r;
      }
    },
    created(){
      this.tree = bbn.vue.closest(this, "bbn-tree");
      if ( this.tree.opened ){
        this.isExpanded = true;
      }
      else if ( this.level <= this.tree.minExpandLevel ){
        this.isExpanded = true;
      }
    },
    mounted(){
      if ( !this.animation ){
        setTimeout(() => {
          this.animation = true;
        }, 500)
      }
      /*
      $(this.$el)
        .draggable({
          containment: this.tree.$refs.scroll.$refs.scrollContent,
          appendTo: this.tree.$refs.scroll.$refs.scrollContent,
          helper: "clone",
          opacity: 0.6,
          drag: (e, ui) => {
            let posY = ui.top;
            bbn.fn.log(e.pageY, e, ui);
          }
        })
        .children(".node")
        .droppable({
          accept: '.bbn-tree-node',
          hoverClass: 'dropping'
        });
        */
    },
    watch: {
      isExpanded(newVal){
        if ( newVal ){
          if ( !this.items || (this.items.length !== this.numChildren) ){
            this.tree.load(this);
          }
          else{
            this.resize();
          }
        }
        else{
          bbn.fn.log("isExpanded false", this.tree.isNodeOf(this.tree.selectedNode, this));
          if ( this.tree.selectedNode && this.tree.isNodeOf(this.tree.selectedNode, this) ){
            this.isSelected = true;
          }
          this.resize();
        }
      },
      isSelected(newVal){
        if ( newVal ){
          this.tree.select(this);
        }
        else{
          this.tree.unselect(this);
        }
      },
      isActive(newVal){
        if ( newVal ){
          this.tree.activate(this);
        }
      }
    }
  });

})(jQuery, bbn);
