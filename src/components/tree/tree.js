/**
 * Created by BBN on 10/02/2017.
 */
(function($, bbn){
  "use strict";

  const NODE_PROPERTIES = ["selected", "selectedClass", "activeClass", "expanded", "tooltip", "icon", "selectable", "text", "data", "cls", "component", "num", "source", "level", "items"];

  Vue.component('bbn-tree', {
    mixins: [bbn.vue.basicComponent, bbn.vue.localStorageComponent],
    // The events that will be emitted by this component
    _emitter: ['dragstart', 'drag', 'dragend', 'select', 'open'],
    props: {
      filterString: {
        type: String
      },
      // The level until which the tree must be opened
      minExpandLevel: {
        type: Number,
        default: 0
      },
      // True if the whole tree must be opened
      opened: {
        type: Boolean,
        default: false
      },
      // A function for mapping the tree data
      map: {
        type: Function,
      },
      // The data to send to the server
      data: {
        type: [Object, Function],
        default(){
          return {};
        }
      },
      // An array of objects representing the nodes
      source: {
        Type: [Array, String]
      },
      // Set to false if the source shouldn't be loaded at mount time
      autoload: {
        type: Boolean,
        default: true
      },
      // The class given to the node (or a function returning the class name)
      cls: {
        type: [Function, String]
      },
      // A component for the node
      component: {
        type: [Function, String, Object]
      },
      // The data field used as UID
      uid: {
        Type: String
      },
      // Set to true for having the nodes draggable
      draggable: {
        type: Boolean,
        default: false
      },
      // An array (or a function returning one) of elements for the node context menu
      menu: {
        type: [Array, Function]
      },
      // An string (or a function returning one) for the icon's color
      iconColor: {
        type: [String, Function]
      },
      // The value of the UID to send for the root tree
      root: {
        type: [String, Number]
      },
      // The hierarchy level, root is 0, and for each generation 1 is added to the level
      level: {
        type: Number,
        default: 0
      },
      // Other trees where nodes can be dropped on
      droppables: {
        type: Array,
        default(){
          return [];
        }
      },
      // If set to false a draggable tree will not be able to drop on itself
      selfDrop: {
        type: Boolean,
        default: true
      },
      // Helper to transform data when passing from one tree to another
      transferData: {
        type: Function
      },
      path: {
        type: Array,
        default(){
          return [];
        }
      },
      value: {}
    },

    data(){
      return {
        // Only for the origin tree
        isRoot: false,
        // The parent node if not root
        node: false,
        // The parent tree if not root
        tree: false,
        // The URL where to pick the data from if isAjax
        url: typeof(this.source) === 'string' ? this.source : false,
        // Is the data provided from the server side
        isAjax: typeof(this.source) === 'string',
        // True when the data is currently loading in the tree (unique to the root)
        isLoading: false,
        // True when the data is currently loading in the current tree
        loading: false,
        // True once the data of the tree has been loaded
        isLoaded: false,
        // True once the component is mounted
        isMounted: false,
        // The actual list of items (nodes)
        items: this.getItems(),
        // The currently active node component object
        activeNode: false,
        // The currently selected node component object
        selectedNode: false,
        // The component node object over which the mouse is now
        overNode: false,
        // dragging state, true if an element is being dragged
        dragging: false,
        // Real dragging will start after the mouse's first move, useful to kow if we are in a select or drag context
        realDragging: false
      };
    },

    computed: {
      droppableTrees(){
        let r = this.selfDrop ? [this] : [];
        if ( this.droppables.length ){
          for ( let a of this.droppables ){
            r.push(a);
          }
        }
        return r;
      }
    },

    methods: {

      getItems(){
        let items = [];
        if ( typeof(this.source) !== 'string' ){
          if ( this.map ){
            $.each(this.source, (i, a) =>{
              items.push(this.map(a));
            })
          }
          else if ( this.source.length ){
            items = this.source.slice();
          }
        }
        return items;
      },
      reset(){
        if ( this.isAjax ){
          this.isLoaded = false;
        }
        this.items = [];
        this.$forceUpdate();
        this.$nextTick(() => {
          if ( this.isAjax ){
            this.load();
          }
          else{
            this.items = this.getItems();
            this.$forceUpdate();
          }
        })
      },

      // Resize the root scroller
      resize(){
        if ( this.tree.$refs.scroll ){
          this.tree.$refs.scroll.onResize();
        }
      },

      // Make the root tree resize and emit an open event
      onOpen(){
        this.resize();
        this.$emit('open');
        this.tree.$emit('open', this);
      },

      // Make the root tree resize and emit a close event
      onClose(){
        this.resize();
        this.$emit('close');
        this.tree.$emit('close', this);
      },

      // Find a node based on its props
      _findNode(props, node){
        let ret = false;
        if ( node.numChildren && !node.isExpanded ){
          node.isExpanded = true;
        }
        if ( node.$children && node.numChildren && node.isExpanded && Object.keys(props) ){
          $.each(node.$children, (i, n) => {
            if ( n.data ){
              let tmp = {};
              $.each(Object.keys(props), (j, k) => {
                if ( n.data[k] === undefined ){
                  return true;
                }
                tmp[k] = n.data[k];
              });
              if ( JSON.stringify(tmp) === JSON.stringify(props) ){
                ret = n;
              }
            }
          });
        }
        return ret;
      },

      // Find a node based on path
      getNode(arr, node){
        node = node || this.$refs.root;
        if ( arr ){
          if ( !$.isArray(arr) ){
            arr = [arr];
          }
          arr = arr.map((v) => {
            if ( (typeof v === 'number') || (typeof v === 'string') ){
              return {idx: v}
            }
            return v;
          });
          $.each(arr, (i, v) => {
            node = this._findNode(v, node);
          });
          return node;
        }
      },

      // Returns the menu of a given node
      getMenu(node){
        let idx = $(node.$el).index();
        let menu = [];
        if ( node.numChildren ){
          menu.push({
            text: node.isExpanded ? bbn._("Close") : bbn._("Open"),
            icon: node.isExpanded ? 'fa fa-arrow-circle-up' : 'fa fa-arrow-circle-down',
            command: () => {
              node.isExpanded = !node.isExpanded;
            }
          });
        }
        if ( this.isAjax && node.numChildren && node.$refs.tree && node.$refs.tree[0].isLoaded ){
          menu.push({
            text: bbn._("Refresh"),
            icon: 'fa fa-refresh',
            command: () => {
              this.reload(node);
            }
          })
        }
        if ( this.menu ){
          let m2 = $.isFunction(this.menu) ? this.menu(node, idx) : this.menu;
          if ( m2.length ){
            $.each(m2, function(i, a){
              menu.push({
                text: a.text,
                icon: a.icon ? a.icon : '',
                command: a.command ? () => {
                  a.command(node)
                } : false
              });
            })
          }
        }
        return menu;
      },

      // Returns an object with the data to send for a given node
      // If UID has been given obj will only have this prop other the whole data object
      dataToSend(){
        // The final object to send
        let r = {},
            uid = this.uid || this.tree.uid;
        // If the uid field is defined
        if ( uid ){
          // If an item has been given we send the corresponding data, or otherwise an empty string
          if ( this.node ){
            r[uid] = this.node.data && this.node.data[uid] ? this.node.data[uid] : '';
          }
          else if ( this.isRoot ){
            r[uid] = this.root ? this.root : '';
          }
        }
        else if ( this.node ){
          r = this.node.data;
        }
        else if ( $.isFunction(this.data) ){
          r = this.data();
        }
        else{
          r = this.data;
        }
        return r;
      },

      // Makes an object out of the given properties, adding to data all non existing props
      normalize(obj){
        let r = {
          data: {}
        };
        if ( obj.text || obj.icon ){
          for ( let n in obj ){
            if ( obj.hasOwnProperty(n) && (typeof n === 'string') ){
              if ( $.inArray(n, NODE_PROPERTIES) > -1 ){
                r[n] = obj[n];
              }
              else{
                r.data[n] = obj[n];
              }
            }
          }
          return r;
        }
        return false;
      },

      // Manages the key navigation inside the tree
      keyNav(e){
        e.preventDefault();
        e.stopImmediatePropagation();
        if ( this.tree.activeNode ){
          let idx = false,
              min = 1,
              max = this.tree.activeNode.$parent.$children.length - 1,
              parent = this.tree.activeNode.$parent;
          $.each(this.tree.activeNode.$parent.$children, (i, a) => {
            if ( a === this.tree.activeNode ){
              idx = i;
              return false;
            }
          });
          bbn.fn.log("keyNav", idx, max, e.key);
          switch ( e.key ){
            case 'Enter':
            case ' ':
              this.tree.activeNode.isSelected = !this.tree.activeNode.isSelected;
              break;
            case 'PageDown':
            case 'End':
              if ( this.tree.activeNode ){
                this.tree.activeNode.isActive = false;
              }
              let node = this.$refs.root;
              while ( node.$children.length && node.isExpanded ){
                node = node.$children[node.$children.length-1];
              }
              node.isActive = true;
              break;

            case 'PageUp':
            case 'Home':
              if ( this.tree.activeNode ){
                this.tree.activeNode.isActive = false;
              }
              if ( this.$refs.root.$children[1] ){
                this.$refs.root.$children[1].isActive = true;
              }
              break;

            case 'ArrowLeft':
              if ( this.tree.activeNode.isExpanded ){
                this.tree.activeNode.isExpanded = false;
              }
              else if ( this.tree.activeNode.$parent !== this.$refs.root ){
                this.tree.activeNode.$parent.isActive = true;
              }
              break;
            case 'ArrowRight':
              if ( !this.tree.activeNode.isExpanded ){
                this.tree.activeNode.isExpanded = true;
              }
              break;
            case 'ArrowDown':
              if ( this.tree.activeNode.isExpanded && (this.tree.activeNode.items.length > 1) ){
                this.tree.activeNode.$children[1].isActive = true;
              }
              else if ( idx < max ){
                bbn.fn.log("ORKING");
                parent.$children[idx+1].isActive = true;
              }
              else {
                let c = this.tree.activeNode,
                    p = this.tree.activeNode.$parent;
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
                let c = this.tree.activeNode.$parent,
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
        }
        else if ( this.tree.selectedNode ){
          this.tree.activeNode = this.tree.selectedNode;
        }
      },

      // Reloads a node already loaded
      reload(node){
        if ( this.isAjax ){
          if ( !node ){
            if ( this.isRoot ){
              this.items = [];
              this.isLoaded = false;
              this.$nextTick(() => {
                this.load();
              })
            }
            else{
              this.node.isExpanded = false;
              this.node.$refs.tree[0].isLoaded = false;
              this.node.$forceUpdate();
              this.$nextTick(() => {
                this.node.isExpanded = true;
              })
            }
          }
          else if ( node.$refs.tree ){
            node.isExpanded = false;
            node.$refs.tree[0].isLoaded = false;
            node.$forceUpdate();
            this.$nextTick(() => {
              node.isExpanded = true;
            })
          }
        }
      },

      mapper(fn, data){
        let res = [];
        $.each(data, (i, a) => {
          let tmp = fn(a);
          if ( tmp.items ){
            tmp.items = this.mapper(fn, tmp.items);
          }
          res.push(tmp);
        });
        return res;
      },

      // Loads a node
      load(){
        // It must be Ajax and not being already in loading state
        if ( this.isAjax && !this.tree.isLoading && !this.isLoaded ){
          this.tree.isLoading = true;
          this.loading = true;
          bbn.fn.post(this.tree.url, this.dataToSend(), (res) => {
            this.tree.isLoading = false;
            this.loading = false;
            if ( res.data ){
              if ( this.tree.map ){
                this.items = this.mapper(this.tree.map, res.data);
              }
              else{
                this.items = res.data;
              }
            }
            this.isLoaded = true;
          })
        }
      },

      openPath(){
        if ( this.path.length ){
          let path = this.path.slice(),
              criteria = path.shift(),
              idx = -1;
          if ( typeof(criteria) === 'object' ){
            idx = bbn.fn.search(this.items, criteria);
          }
          else if ( this.tree.uid ){
            let cr = {};
            cr[this.tree.uid] = criteria;
            idx = bbn.fn.search(this.items, cr);
          }
          else if ( typeof(criteria) === 'number' ){
            idx = criteria;
          }
          bbn.fn.log("OopenPath", path, idx, criteria, this.items);
          if ( idx > -1 ){
            $.each(this.items, (i, a) => {
              if ( i !== idx ){
                this.$set(this.items[idx], "path", []);
                this.$set(this.items[idx], "path", []);
              }
            })
            if ( path.length ){
              this.$children[idx].isExpanded = true;
              this.$children[idx].path = path;
            }
            else{
              this.$set(this.items[idx], "selected", true);
            }
          }
        }
      },

      // Unselects the currently selected node
      unselect(){
        if ( this.tree.selectedNode ){
          this.tree.selectedNode.isSelected = false;
        }
      },

      // Deactivate the active node
      deactivateAll(){
        if ( this.tree.activeNode ){
          this.tree.activeNode.isActive = true;
        }
      },

      // Returns true if the first argument node descends from the second
      isNodeOf(childNode, parentNode){
        childNode = bbn.vue.closest(childNode, 'bbn-tree-node');
        while ( childNode ){
          if ( childNode === parentNode ){
            return true;
          }
          childNode = bbn.vue.closest(childNode, 'bbn-tree-node');
        }
        return false;
      },

      // Moves a node to or inside a tree
      move(node, target, index){
        let idx = $(node.$el).index(),
            parent = node.parent;
        if ( idx > -1 ){
          if ( !target.numChildren ){
            target.numChildren = 1;
            target.$forceUpdate();
          }
          else{
            target.numChildren++;
          }
          this.$nextTick(() => {
            let targetTree = target.$refs.tree[0];
            parent.numChildren--;
            let params = parent.items.splice(idx, 1)[0];
            targetTree.items.push(params);
            if ( !targetTree.isExpanded ){
              targetTree.isExpanded = true;
            }
            parent.$forceUpdate();
            target.$forceUpdate();
          });
        }
      },

      /*
      // dragging action
      drag(e){
        if ( this.tree.dragging ){
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
          this.tree.$refs.helper.style.left = left + 'px';
          this.tree.$refs.helper.style.top = top + 'px';
          let ok = false;
          if (
            this.overNode &&
            (this.tree.dragging !== this.overNode) &&
            !this.isNodeOf(this.overNode, this.tree.dragging)
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
      },
      */

      // Returns an object with all the unknown properties of the node component
      toData(data){
        let r = {};
        for ( let n in data ){
          if ( $.inArray(n, NODE_PROPERTIES) === -1 ){
            r[n] = data[n];
          }
        }
        return r;
      }
    },

    // Definition of the root tree and parent node
    created(){
      let cp = bbn.vue.closest(this, 'bbn-tree');
      if ( !cp ){
        this.isRoot = true;
        this.node = false;
        this.tree = this;
      }
      else{
        while ( cp && cp.level ){
          cp = bbn.vue.closest(cp, 'bbn-tree');
        }
        if ( cp && !cp.level ){
          this.tree = cp;
          this.isAjax = this.tree.isAjax;
        }
        this.node = bbn.vue.closest(this, 'bbn-tree-node');
      }
      if ( !this.isAjax || this.items.length ){
        this.isLoaded = true;
      }
    },

    mounted(){
      if ( this.isRoot && this.autoload ){
        this.load();
      }
      else if ( this.isExpanded ){
        this.load();
      }
      this.isMounted = true;
    },

    watch: {
      activeNode(newVal){
        if ( newVal ){
          this.$refs.scroll.scrollTo(0, newVal.$el);
        }
      },
      path(newVal){
        bbn.fn.log("Change path", newVal);
        this.$emit('pathChange');
      },
      items: {
        deep: true,
        handler(){
          this.resize();
        }
      },
      source(){
        this.reset();
        this.load();
      }
    },

    components: {
      'bbn-tree-node': {
        name: 'bbn-tree-node',

        props: {
          filterString: {
            type: String
          },
          // True if the node is the one selected
          selected:{
            type: Boolean,
            default: false
          },
          // True if the node is expanded (opened)
          expanded:{
            type: Boolean,
            default: false
          },
          // A message to show as tooltip
          tooltip: {
            type: String
          },
          // The icon - or not
          icon:{
            type: [Boolean, String]
          },
          // True if the node is selectable
          selectable: {
            type: Boolean,
            default: true
          },
          // The text inside the node, its title
          text: {
            type: String
          },
          // The data attached to the node
          data: {
            type: Object,
            default(){
              return {};
            }
          },
          // The opened path if there is one
          path: {
            type: Array,
            default(){
              return [];
            }
          },
          // A class to give to the node
          cls: {
            type: [String]
          },
          // A component for the node
          component: {
            type: [String, Function, Vue]
          },
          // The number of children of the node
          num: {
            type: Number
          },
          // The list of children from the node
          source: {
            type: Array,
            default(){
              return [];
            }
          },
          // Node's level (see tree)
          level: {
            type: Number,
            default: 1
          },
          idx: {
            type: Number
          }
        },

        data: function(){
          return {
            // The parent tree
            parent: false,
            // The root tree
            tree: false,
            // Sanitized list of items
            items: this.source.slice(),
            isActive: false,
            isSelected: !!this.selected,
            isExpanded: this.expanded,
            numChildren: this.num !== undefined ? this.num : this.source.length,
            animation: this.level > 0,
            isMounted: false,
            isMatch: true,
            numMatches: 0
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
          remove(){

          },
          update(attr){
            for ( let n in attr ){
              this[n] = attr[n];
            }
          },
          add(obj){

          },
          resize(){
            this.tree.resize();
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
            if ( this.tree.droppableTrees.length ){
              $.each(this.tree.droppableTrees, (i, a) => {
                if ( a !== this.tree ){
                  a.dragging = this;
                }
              });
            }
            $(document.body).one('mouseup', this.endDrag);
            $(document.body).on("mousemove", this.drag);
          },
          drag(e){
            bbn.fn.log("DS");
            e.stopImmediatePropagation();
            e.preventDefault();
            this.tree.$refs.helper.style.left = (e.pageX + 20) + 'px';
            this.tree.$refs.helper.style.top = e.pageY + 'px';
            if ( !this.tree.realDragging ){
              if ( this.tree.selectedNode ){
                this.tree.selectedNode.isSelected = false;
              }
              let ev = $.Event("dragStart");
              this.tree.$emit("dragStart", this, ev);
              if ( !ev.isDefaultPrevented() ){
                this.tree.realDragging = true;
                let helper = this.tree.$refs.helper;
                helper.innerHTML = this.$el.outerHTML;
                $(helper).appendTo(document.body);
              }
            }
            else{
              for ( let a of this.tree.droppableTrees ){
                $(a.$el).find(".dropping").removeClass("dropping");
              }
              let ok = false;
              for ( let a of this.tree.droppableTrees ){
                if (
                  a.overNode &&
                  (a.dragging !== a.overNode) &&
                  !a.isNodeOf(a.overNode, this.tree.dragging) &&
                  (!a.overNode.$refs.tree || (a.overNode.$refs.tree[0] !== this.parent))
                ){
                  let $t = $(e.target);
                  $t.parents().each((i, b) => {
                    if ( b === a.overNode.$el ){
                      ok = 1;
                      return false;
                    }
                    else if ( b === this.$el ){
                      return false;
                    }
                  });
                }
                if ( ok ){
                  let ev = $.Event("dragOver");
                  a.$emit("dragOver", this, ev, a.overNode);
                  if ( !ev.isDefaultPrevented() ){
                    $(a.overNode.$el)
                      .children("span.node")
                      .addClass("dropping");
                  }
                }
                else{
                  a.overNode = false;
                }
              }
            }
          },
          endDrag(e){
            e.preventDefault();
            e.stopImmediatePropagation();
            if ( this.tree.realDragging ){
              $(this.tree.$refs.helper).appendTo(this.tree.$refs.helperContainer).empty();
              this.tree.realDragging = false;
              if ( this.tree.droppableTrees.length ){
                for ( let a of this.tree.droppableTrees ){
                  if (
                    a.overNode &&
                    (this.tree.dragging !== a.overNode) &&
                    !a.isNodeOf(a.overNode, this.tree.dragging)
                  ){
                    $(a.overNode.$el).children("span.node").removeClass("dropping");
                    let ev = $.Event("dragEnd");
                    a.tree.$emit("dragEnd", ev, this, a.overNode);
                    if ( !ev.isDefaultPrevented() ){
                      if ( a === this.tree ){
                        this.tree.move(this, a.overNode);
                      }
                    }
                  }
                }
              }
              else{
                let ev = $.Event("dragEnd");
                this.tree.$emit("dragEnd", this, ev);
              }
            }
            $(document.body).off("mousemove", this.drag);
            for ( let a of this.tree.droppableTrees ){
              a.dragging = false;
            }
            /*
            if (
              this.tree.overNode &&
              (this.tree.dragging !== this.tree.overNode) &&
              !this.tree.isNodeOf(this.tree.overNode, this.tree.dragging)
            ){
              this.tree.move(this, this.tree.overNode);
            }
            */
          },
          mouseOver(){
            this.tree.overNode = this;
          },
          checkPath(){
            if ( this.tree.path.length > this.level ){
              let item = this.tree.path.slice(this.level, this.level + 1)[0],
                  type = typeof item,
                  match = false;
              if ( (type === 'object') && (bbn.fn.search([this.data], item) === 0) ){
                match = true;
              }
              else if ( this.tree.uid && this.data[this.tree.uid] && (this.data[this.tree.uid] === item) ){
                match = true;
              }
              else if ( (type === 'number') && (this.idx === item) ){
                match = true;
              }
              if ( match ){
                if ( this.tree.path.length > (this.level + 1) ){
                  this.isExpanded = true;
                }
                else{
                  this.isSelected = true;
                  this.tree.$refs.scroll.scrollTo(0, this.$el);
                }
              }
            }
          },
          getPath(numeric){
            let r = [],
                parent = this;
            while ( parent ){
              if ( this.tree.uid ){
                r.unshift(parent.data[this.tree.uid]);
              }
              else if ( numeric ){
                r.unshift(parent.index);
              }
              else{
                r.unshift(parent.data);
              }
              parent = bbn.vue.closest(parent, 'bbn-tree-node');
            }
            return r;
          }
        },
        created(){
          this.parent = bbn.vue.closest(this, 'bbn-tree');
          this.tree = this.parent.tree || this.parent;
        },
        mounted(){
          if ( this.tree.opened ){
            this.isExpanded = true;
          }
          else if ( this.level < this.tree.minExpandLevel ){
            this.isExpanded = true;
          }
          this.$nextTick(() => {
            if ( !this.animation ){
              setTimeout(() => {
                this.animation = true;
              }, 500)
            }
            this.isMounted = true;
            this.tree.$on('pathChange', () => {
              this.checkPath();
            });
            this.$nextTick(() => {
              this.checkPath();
            });
            this.resize();
            /*
            $(this.$el)
              .draggable({
                //containment: this.tree.$refs.scroll.$refs.scrollContent,
                //appendTo: this.tree.$refs.scroll.$refs.scrollContent,
                //appendTo: document.body,
                helper: "clone",
                opacity: 0.6,
                drag: (e, ui) => {
                  //let posY = ui.top;
                  //bbn.fn.log(e.pageY, e, ui);
                }
              })
              .children(".node")
              .droppable({
                accept: '.bbn-tree-node',
                hoverClass: 'dropping'
              });
              */
          })
        },
        watch: {
          isExpanded(newVal){
            if ( newVal ){
              if ( this.numChildren && !this.$refs.tree[0].isLoaded ){
                this.$refs.tree[0].load();
              }
              else{
                this.resize();
              }
            }
            else{
              if ( this.tree.selectedNode && this.tree.isNodeOf(this.tree.selectedNode, this) ){
                this.isSelected = true;
              }
              this.resize();
            }
          },
          isSelected(newVal){
            if ( newVal && this.tree.selectedNode ){
              this.tree.selectedNode.isSelected = false;
            }
            if ( newVal ){
              let ev = $.Event('select');
              this.tree.$emit('select', this, ev);
              this.tree.selectedNode = this;
            }
            else{
              this.tree.$emit('unselect', this);
            }
          },
          isActive(newVal){
            if ( this.tree.activeNode ){
              this.tree.selectedNode.isActive = false;
            }
            this.tree.activeNode = this;
            this.tree.$emit(newVal ? 'activate' : 'deactivate', this);
          },
          filterString(newVal){
            this.numMatches = 0;
            if ( !newVal ){
              this.isMatch = true;
            }
            else{
              this.isMatch = bbn.fn.compare(this.text, newVal, 'icontains');
              if ( this.isMatch ){
                let vm = this;
                while ( vm.parent && vm.parent.node ){
                  vm.parent.node.numMatches++;
                  vm = vm.parent;
                }
              }
            }
          }
        }
      }
    }
  });

})(jQuery, bbn);
