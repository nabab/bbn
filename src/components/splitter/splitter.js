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
      cfg: {
        type: Object
      }
    },
    data(){
      return {
        resizeTimeout: false,
        currentOrientation: this.orientation,
        panes: []
      };
    },
    computed: {
      resizers(){
        if ( !this.panes.length ){
          return [];
        }
        let r = [],
            totalSize = 0;

        $.each(this.panes, (i, pane) => {
          if (
            (this.resizable && (pane.resizable !== false)) ||
            (this.collapsible && (pane.collapsible !== false))
          ){
            if ( !pane.collapsed ){
              totalSize += this.$children[i].$el['client' + (this.currentOrientation === 'horizontal' ? 'Width' : 'Height')];
            }
            let resizer = {
              left: 0,
              top: 0
            };
            if ( this.currentOrientation === 'horizontal' ){
              resizer[this.currentOrientation === 'horizontal' ? 'left' : 'top'] = totalSize;
            }
            r.push(resizer);
          }
        });
        return r;
      }

    },
    methods: {
      getOrientation(){
        return this.lastKnownWidth > this.lastKnownHeight ? 'horizontal' : 'vertical';
      },
      onResize(){
        if ( this.orientation === 'auto' ){
          let o = this.getOrientation();
          if ( o !== this.currentOrientation ){
            this.currentOrientation = this.getOrientation();
          }
        }
      }
    },
    mounted(){
      $.each(this.$children, (i, a) => {
        if ( a.$vnode.componentOptions.tag === 'bbn-pane' ){
          this.panes.push(a.$vnode.componentOptions.propsData);
        }
        else{
          bbn.fn.log("BAD PANE", a);
        }
      });
      setTimeout(() => {
        $.each(this.resizers, (i, a) => {
          bbn.fn.log("DRAGGABLE?", this.$children[i].$el);
          $(this.$refs.container).children(".resizer").eq(i).draggable({
            containment: "parent",
            opacity: 0.5,
            axis: this.currentOrientation === 'horizontal' ? 'x' : 'y'
          })
        })
      }, 1000);
      this.onResize();
    },
    updated(){
      this.onResize();
    },
    watch: {
      orientation(newVal){
        if ( newVal !== this.currentOrientation ){
          this.currentOrientation = newVal === 'auto' ? this.getOrientation() : newVal;
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
      }
    },
    components: {
      'bbn-pane': {
        name: 'bbn-pane',
        props: {
          size: {
            type: [String, Number, Function]
          },
          resizable: {
            type: Boolean
          },
          collapsible: {
            type: Boolean
          },
          collapsed: {
            type: Boolean
          },
        },
        data(){
          return {
            currentSize: this.size
          }
        }
      }
    }
  });

})(jQuery, bbn, kendo);
