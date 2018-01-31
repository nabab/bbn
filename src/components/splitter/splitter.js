/**
 * Created by BBN on 15/02/2017.
 */
(function($, bbn, kendo){
  "use strict";

  /**
   * Classic input with normalized appearance
   */
  Vue.component('bbn-splitter', {
    mixins: [bbn.vue.basicComponent, bbn.vue.optionComponent, bbn.vue.resizerComponent],
    props: {
      orientation: {
        type: String,
        default: 'auto'
      },
      resizable: {
        type: Boolean,
        default: false
      },
      collapsible: {
        type: Boolean,
        default: false
      },
      scrollable: {
        type: Boolean,
        default: false
      },
      resizerSize: {
        type: Number,
        default: 10
      },
      resizerClass: {
        type: [String, Function],
        default: 'resizer'
      }
    },
    data(){
      return {
        _mountingTimeout: false,
        resizeTimeout: false,
        isResized: false,
        currentOrientation: this.orientation,
        panes: [],
        originalDimensions: [],
        currentDimensions: [],
        diffDimensions: [],
      };
    },
    computed: {
      isResizable(){
        return (this.resizable || this.collapsible) && (bbn.fn.count(this.panes, 'resizable', false, '!==') >= 2)
      },
      columnsCfg(){
        return this.currentDimensions.length && (this.currentOrientation === 'horizontal') ?
          this.getFormatted() : 'auto';
      },
      rowsCfg(){
        return this.currentDimensions.length && (this.currentOrientation === 'vertical') ?
          this.getFormatted() : 'auto';
      },
      resizers(){
        if ( !this.isResizable ){
          return [];
        }
        let r = [],
            pos = 1;
        $.each(this.panes, (i, pane) => {
          if ( pane.position === (pos + 1) ){
            let j = 1,
                assoc = this.panes[i - j];
            while ( assoc && !assoc.isResizable ){
              j++;
              assoc = this.panes[i - j];
            }
            r.push({
              position: pos,
              difference: 0,
              pane1: {
                obj: this.panes[assoc.index],
                cp: bbn.vue.find(this, 'bbn-pane', assoc.index)
              },
              pane2: {
                obj: pane,
                cp: bbn.vue.find(this, 'bbn-pane', i)
              },
            });
            pos++;
            /*
            if (
              (this.panes[i+1].resizable !== false) && (
                (this.resizable && (pane.resizable !== false)) ||
                pane.resizable ||
                (
                  // In the middle we take the collapsible
                  ((i > 1) && (i < this.panes.length - 2)) &&
                  (
                    (this.collapsible && (pane.collapsible !== false)) ||
                    pane.collapsible
                  )
                )
              )
            ){
              r.push({
                left: this.currentOrientation === 'horizontal' ? totalSize : 0,
                top: this.currentOrientation === 'horizontal' ? 0 : totalSize,
                panes: [i, i + 1]
              });
            }
            */
          }
          pos++;
        });
        return r;
      },

      currentAxis(){
        return this.currentOrientation === 'horizontal' ? 'x' : 'y'
      },
      currentOffType(){
        return this.currentOrientation === 'horizontal' ? 'left' : 'top';
      },
      currentSizeType(){
        return this.currentOrientation === 'horizontal' ? 'Width' : 'Height';
      },
      currentSize(){
        return this['lastKnown' + this.currentSizeType];
      },
      availableSize(){
        let availableSize = this.currentSize;
        $.each(this.resizers, (i, a) => {
          availableSize -= this.resizerSize;
        });
        return availableSize;
      },

    },
    methods: {
      getFormatted(){
        let pos = 1,
            toAdd = false,
            tmp = $.map(this.panes, (a) => {
              let sz = '';
              if ( a.position !== pos ){
                sz += '10px ';
                pos++;
              }
              if ( a.difference || toAdd ){
                sz += 'calc( '
              }
              if ( typeof a.size === 'number' ){
                sz += a.size + 'px'
              }
              else if ( a.size && (a.size !== 'auto') ){
                sz += a.size;
              }
              else if ( a.difference || toAdd ){
                sz = '0';
              }
              if ( a.difference ){
                sz += ' + ' + a.difference + 'px'
              }
              if ( toAdd ){
                sz += ' + ' + toAdd + ' '
              }
              if ( a.difference || toAdd ){
                sz += ')'
              }
              else if ( !a.size && !a.collapsed ){
                sz += 'auto'
              }
              if ( a.collapsed ){
                if ( a.difference || toAdd ){
                  sz = sz.substr(5, sz.length - 6);
                }
                toAdd = sz;
                sz = '0';
              }
              pos++;
              return sz;
            });
        return tmp.join(' ');
      },
      realResizerClass(resizer){
        if ( $.isFunction(this.resizerClass) ){
          return this.resizerClass(resizer);
        }
        return this.resizerClass;
      },
      getOrientation(){
        return this.lastKnownWidth > this.lastKnownHeight ? 'horizontal' : 'vertical';
      },
      onResize(){
        if ( this.orientation === 'auto' ){
          let o = this.getOrientation();
          if ( o !== this.currentOrientation ){
            this.currentOrientation = o;
          }
        }
      },
      updatePositions(){
        $.each(this.panes, (i, pane) => {
          this.$children[pane.index].$el.style.gridColumn = this.currentOrientation === 'horizontal' ? pane.position : 1;
          this.$children[pane.index].$el.style.gridRow = this.currentOrientation === 'vertical' ? pane.position : 1;
        })
      },
      init(){
        clearTimeout(this._mountingTimeout);
        this._mountingTimeout = setTimeout(() => {
          this.panes.splice(0, this.panes.length);
          this.originalDimensions = [];
          let currentPosition = 1,
              tmp = [];
          $.each(this.$children, (i, pane) => {
            bbn.fn.log("CHILDREN", pane);
            if ( pane.$vnode.componentOptions.tag === 'bbn-pane' ){
              bbn.fn.warning("IS PANE");
              let isPercent = false,
                  isFixed = false,
                  props = pane.$vnode.componentOptions.propsData,
                  resizable = this.resizable && (props.resizable !== false),
                  collapsible = this.collapsible && (props.collapsible !== false);
              if ( props.size ){
                isFixed = true;
                if ( (typeof props.size === 'string') && (props.size.substr(-1) === '%') ){
                  isPercent = true;
                }
              }
              this.originalDimensions.push(props.size || false);
              this.currentDimensions.push(props.size || false);
              let obj = $.extend({
                index: i,
                difference: 0,
                oDifference: 0,
                isPercent: isPercent,
                isFixed: isFixed,
                resizable: resizable,
                collapsible: collapsible,
                isResizable: collapsible || resizable,
              }, props);
              tmp.push(obj);
            }
          });
          $.each(tmp, (idx, pane) => {
            pane.position = currentPosition;
            this.panes.push(pane);
            currentPosition++;
            if ( pane.isResizable ){
              for ( let i = idx + 1; i < tmp.length; i++ ){
                if ( tmp[i].isResizable ){
                  currentPosition++;
                  break;
                }
              }
            }
          });
          setTimeout(() => {
            $.each(this.resizers, (i, a) => {
              bbn.fn.log("DRAGGABLE?", this.$children[i].$el);
              let prop = this.currentOrientation === 'horizontal' ? 'left' : 'top',
                  max,
                  min;
              $(this.$el).children(".resizer").eq(i).draggable({
                helper: 'clone',
                containment: "parent",
                opacity: 0.1,
                axis: this.currentAxis,
                start: (e, ui) => {
                  let pos1 = a.pane1.cp.$el.getBoundingClientRect(),
                      pos2 = a.pane2.cp.$el.getBoundingClientRect();
                  min = - pos1.width + 20;
                  max = pos2.width - 20;
                  bbn.fn.log("START", min, max, ui.position[prop] + '/' + ui.originalPosition[prop], "------------");
                },
                drag: (e, ui) => {
                  let size = a.pane1.obj.oDifference + ui.position[prop] - ui.originalPosition[prop];
                  if ( (size <= max) && (size >= min) ){
                    this.$set(a.pane2.obj, 'difference', - size);
                    this.$set(a.pane1.obj, 'difference', size);
                    bbn.fn.log(size, ui.position[prop] + '/' + ui.originalPosition[prop], "------------");
                  }
                },
                stop: (e, ui) => {
                  let size = a.pane1.obj.oDifference + ui.position[prop] - ui.originalPosition[prop];
                  if ( (size <= max) && (size >= min) ){
                    this.$set(a.pane2.obj, 'difference', -size);
                    this.$set(a.pane1.obj, 'difference', size);
                    this.$set(a.pane2.obj, 'oDifference', -size);
                    this.$set(a.pane1.obj, 'oDifference', size);
                  }
                  else{
                    bbn.fn.log("STOOOPO", e, ui);
                  }
                }
              })
            })
          }, 1000);
          this.onResize();
        }, 500)
      },
      collapse(resizerIndex, paneObj){
        if ( this.collapsible ){
          let collapsing = !paneObj.obj.collapsed;
          paneObj.cp.currentHidden = collapsing;
          this.$set(paneObj.obj, 'collapsed', collapsing);
          for ( let i = paneObj.obj.index + 1; i < this.panes.length; i++ ){
            this.$set(this.panes[i], 'position', collapsing ? this.panes[i].position - 1 : this.panes[i].position + 1);
          }
          this.updatePositions();
        }
      },
      hasExpander(paneIdx, resizerIdx){
        return false;
        let pane = this.panes[paneIdx],
            paneBefore = this.panes[paneIdx+1];
        if ( this.collapsible && (pane.collapsible !== false) && paneBefore && (paneBefore.collapsible !== false) && (paneBefore.resizable !== false) ){
          return true;
        }
        return false;
      },
      expanderClass(paneIdx, resizerIdx){
        return '';
        let direction = this.panes[paneIdx].collapsed || (resizerIdx === 1) ?
              (this.currentOrientation === 'horizontal' ? 'right' : 'down') :
              (this.currentOrientation === 'horizontal' ? 'left' : 'up'),
            icon = (resizerIdx === 1) && this.panes[paneIdx].collapsed ? 'angle-double-' : 'angle-',
            cls = 'bbn-p fa fa-' + icon + direction;
        return cls;
      }
    },
    beforeDestroy(){},
    updated(){
      this.onResize();
    },
    watch: {
      orientation(newVal){
        if ( newVal !== this.currentOrientation ){
          this.currentOrientation = newVal === 'auto' ? this.getOrientation() : newVal;
          this.init();
        }
      },
      currentOrientation(newVal, oldVal){
        bbn.fn.warning("Changing orientation", newVal, oldVal);
        if ( this.widget && this.widget.element && (oldVal !== 'auto') ){
          const accepted = ['horizontal', 'vertical', 'auto'];
          if ( (newVal!== oldVal) && ($.inArray(newVal, accepted) > -1) ){
            bbn.fn.info("reeally changin orientation");
            this.widget.element
              .children(".k-splitbar").remove()
              .end()
              .children(".k-pane").css({width: "", height: "", top: "", left: ""});
            this.widget.destroy();
            this.build();
          }
        }
      },
      isResized(){

      }
    },
  });

})(jQuery, bbn, kendo);
