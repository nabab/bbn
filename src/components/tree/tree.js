/**
 * Created by BBN on 10/02/2017.
 */
(function($, bbn){
  "use strict";

  const nodeProperties = ["selected", "selectedClass", "activeClass", "expanded", "tooltip", "icon", "selectable", "text", "data", "cls", "component", "num", "source", "level", "initial"];

  Vue.component('bbn-tree', {
    template: '#bbn-tpl-component-tree',
    props: {
      autoload: {
        type: Boolean,
        default: true
      },
      minExpandLevel: {
        type: Number,
        default: 0
      },
      opened: {
        type: Boolean,
        default: false
      },
      map: {
        type: Function,
      },
      source: {
        Type: [Array, String]
      },
      cls: {
        type: [Function, String]
      },
      component: {
        type: [Function, String]
      },
      uid: {
        Type: String
      },
      draggable: {
        type: Boolean,
        default: false
      },
      target: {
        type: Boolean,
        default: false
      },
      root: {
        type: [String, Number]
      },
      menu: {
        type: [Array, Function]
      },
      iconColor: {
        type: [String, Function]
      },
      connectTo: {
        type: [Function, Array, Vue]
      },
      startDrag: {
        type: [Function],
        default(){
          return true
        }
      },
      endDrag: {
        type: [Function],
        default(){
          return true
        }
      },
    },
    data: function(){
      let items = [];
      if ( typeof(this.source) !== 'string' ){
        if ( this.map ){
          $.each(this.source, (i, a) => {
            items.push(this.map(a));
          })
        }
        else if ( this.source.length ){
          items = this.source.slice();
        }
      }
      return {
        url: false,
        isAjax: typeof(this.source) === 'string',
        isLoading: false,
        items: items,
        isInit: false,
        activeNode: false,
        selectedNode: false,
        overNode: false,
        dragging: false
      };
    },
    beforeMount(){
      if ( this.isAjax === 'string' ){
        this.url = this.source;
        if ( this.autoload ){
          this.load();
        }
      }
    },
    methods: {
      getMenu(node){
        let menu = [];
        if ( this.isAjax ){
          menu.push({
            text: bbn._("Refresh"),
            icon: 'fa fa-refresh',
            click: () => {
              this.reload(node);
            }
          })
        }
        if ( this.menu ){
          let m2 = $.isFunction(this.menu) ? this.menu(this) : this.menu;
          if ( m2.length ){
            $.each(m2, function(i, a){
              menu.push(a);
            })
          }
        }
        return menu;
      },
      dataToSend(node){
        // The final object to send
        let r = {};
        // If the uid field is defined
        if ( this.uid ){
          // If an item has been given we send the corresponding data, or otherwise an empty string
          r[this.uid] = node && node.data && node.data[this.uid] ? node.data[this.uid] : (this.root ? this.root : '');
        }
        else if ( node && node.data ){
          r = node.data;
        }
        return r;
      },
      normalize(obj){
        let r = {
          data: {}
        };
        if ( obj.text || obj.icon ){
          for ( var n in obj ){
            if ( $.inArray(n, nodeProperties) > -1 ){
              r[n] = obj[n];
            }
            else{
              r.data[n] = obj[n];
            }
          }
          return r;
        }
        return false;
      },
      keyNav(e){
        e.preventDefault();
        e.stopImmediatePropagation();
        if ( this.activeNode ){
          let idx = false,
              min = 1,
              max = this.activeNode.$parent.$children.length - 1,
              parent = this.activeNode.$parent;
          $.each(this.activeNode.$parent.$children, (i, a) => {
            if ( a === this.activeNode ){
              idx = i;
              return false;
            }
          });
          bbn.fn.log("keyNav", idx, max, e.key);
          switch ( e.key ){
            case 'Enter':
            case ' ':
              this.activeNode.isSelected = !this.activeNode.isSelected;
              break;
            case 'PageDown':
            case 'End':
              if ( this.activeNode ){
                this.activeNode.isActive = false;
              }
              let node = this.$refs.root;
              while ( node.$children.length && node.isExpanded ){
                node = node.$children[node.$children.length-1];
              }
              node.isActive = true;
              break;

            case 'PageUp':
            case 'Home':
              if ( this.activeNode ){
                this.activeNode.isActive = false;
              }
              if ( this.$refs.root.$children[1] ){
                this.$refs.root.$children[1].isActive = true;
              }
              break;

            case 'ArrowLeft':
              if ( this.activeNode.isExpanded ){
                this.activeNode.isExpanded = false;
              }
              else if ( this.activeNode.$parent !== this.$refs.root ){
                this.activeNode.$parent.isActive = true;
              }
              break;
            case 'ArrowRight':
              if ( !this.activeNode.isExpanded ){
                this.activeNode.isExpanded = true;
              }
              break;
            case 'ArrowDown':
              if ( this.activeNode.isExpanded && (this.activeNode.items.length > 1) ){
                this.activeNode.$children[1].isActive = true;
              }
              else if ( idx < max ){
                bbn.fn.log("ORKING");
                parent.$children[idx+1].isActive = true;
              }
              else {
                let c = this.activeNode,
                    p = this.activeNode.$parent;
                while ( (p.level > 0) && !p.$children[idx+1] ){
                  c = p;
                  p = p.$parent;
                  $.each(p.$children, (i, a) => {
                    if ( a === c ){
                      idx = i;
                      return false;
                    }
                  });
                }
                if ( p.$children[idx+1] ){
                  p.$children[idx+1].isActive = true;
                }
              }
              break;
            case 'ArrowUp':
              if ( idx > min ){
                if ( parent.$children[idx - 1].isExpanded && parent.$children[idx - 1].items.length ){
                  let p = parent.$children[idx - 1],
                      c = p.$children[p.$children.length - 1];
                  while ( c.isExpanded && c.items.length ){
                    p = c;
                    c = p.$children[p.$children.length - 1];
                  }
                  c.isActive = true;
                }
                else{
                  parent.$children[idx - 1].isActive = true;
                }
              }
              else{
                if ( parent !== this.$refs.root ){
                  parent.isActive = true;
                }
                /*
                let c = this.activeNode.$parent,
                    p = c.$parent,
                    idx = false;


                while ( p.$children[idx-1] && p.$children[idx-1].isExpanded && p.$children[idx-1].items.length ){
                  p = p.$children[idx-1];
                  idx = p.$children.length - 1;
                }
                if ( p.$children[idx-1] ){
                  p.$children[idx-1].isActive = true;
                }
                */
              }
              break;
          }
          bbn.fn.log("TEST TREE", e, this.activeNode)
        }
        else if ( this.selectedNode ){
          this.activeNode = this.selectedNode;
        }
      },
      reload(treeNode){
        treeNode.items = [];
        this.$nextTick(() => {
          this.load(treeNode);
        })
      },
      load(treeNode){
        bbn.fn.log("treeNode", treeNode);
        if ( this.isAjax ){
          this.isLoading = true;
          bbn.fn.post(this.source, this.dataToSend(treeNode), (res) => {
            this.isLoading = false;
            if ( res.data ){
              if ( treeNode ){
                if ( this.map ){
                  treeNode.items = $.map(res.data || [], this.map);
                }
                else{
                  treeNode.items = res.data;
                }
                treeNode.numChildren = treeNode.items.length;
              }
              else{
                if ( this.map ){
                  this.items = $.map(res.data || [], this.map);
                }
                else{
                  this.items = res.data;
                }
                this.$nextTick(() => {
                  this.isInit = true;
                });
              }
            }
          })
        }
      },
      select(node){
        if ( this.selectedNode ){
          this.selectedNode.isSelected = false;
        }
        this.selectedNode = node;
        bbn.fn.log(node, this);
        this.$emit('select', node.data, node, this);
      },
      unselect(node){
        if ( this.selectedNode === node ){
          this.selectedNode = false;
        }
        bbn.fn.log("unselecting", node);
      },
      activate(node){
        if ( this.activeNode ){
          this.activeNode.isActive = false;
        }
        this.activeNode = node;
        this.$emit('activate', this);
      },
      deactivate(node){
        if ( node.isActive ){
          node.isActive = false;
        }
        this.$emit('deactivate', this);
      },
      isNodeOf(childNode, parentNode){
        childNode = childNode.$parent;
        while ( childNode && (childNode !== this.root) ){
          if ( childNode === parentNode ){
            return true;
          }
          childNode = childNode.$parent;
        }
        return false;
      },
      move(node, target, index){
        if ( this.endDrag(this, node, target, index) ){
          alert('move');
          let idx = $(node.$el).index();
          if ( idx > -1 ){
            let params = $.extend({}, node.$options.propsData);
            node.$parent.items.splice(idx, 1);
            target.items.push(params);
            target.numChildren++;
            if ( !target.isExpanded ){
              target.isExpanded = true;
            }
            target.$forceUpdate();
          }
          bbn.fn.log(idx, node.$options.propsData, target.$options.propsData, node, target);
        }
      },
      drag(e){
        if ( this.dragging ){
          e.preventDefault();
          e.stopImmediatePropagation();
          $(this.$el).find(".dropping").removeClass("dropping");
          bbn.fn.log(e);
          let $container = $(e.target).offsetParent(),
              top = e.layerY,
              left = e.layerX,
              p;
          while ( $container[0] !== this.$refs.scroll.$refs.scrollContent ){
            p = $container.position();
            top += p.top;
            left += p.left;
            $container = $container.offsetParent();
          }
          this.$refs.helper.style.left = left + 'px';
          this.$refs.helper.style.top = top + 'px';
          let ok = false;
          if (
            this.overNode &&
            (this.dragging !== this.overNode) &&
            !this.isNodeOf(this.overNode, this.dragging)
          ){
            let $t = $(e.target);
            $t.parents().each((i, a) => {
              if ( a === this.overNode.$el ){
                ok = 1;
                return false;
              }
              else if ( a === this.$el ){
                return false;
              }
            });
          }
          if ( ok ){
            $(this.overNode.$el).children("span.node").addClass("dropping");
          }
          else{
            this.overNode = false;
          }
        }
      }
    },
    mounted: function(){
      this.load();
    },
    watch: {
      activeNode(newVal){
        if ( newVal ){
          this.$refs.scroll.scrollTo(0, newVal.$el);
        }
      }
    }
  });

})(jQuery, bbn);
