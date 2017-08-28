/**
 * Created by BBN on 15/02/2017.
 */
(function($, bbn, kendo){
  "use strict";

  /**
   * Classic input with normalized appearance
   */
  Vue.component('bbn-splitter', {
    mixins: [bbn.vue.optionComponent, bbn.vue.resizerComponent],
    template: '#bbn-tpl-component-splitter',
    props: {
      orientation: {
        type: String
      },
      cfg: {
        type: Object,
        default: function(){
          return {
            orientation: "horizontal",
          }
        }
      }
    },
    data(){
      return {
        resizeTimeout: false
      };
    },
    methods: {
      build(){
        let cfg = this.getOptions();
        /*
        cfg.resize = () => {
          clearTimeout(this.resizeTimeout);
          this.resizeTimeout = setTimeout(() => {
            this.$emit("resize");
            bbn.fn.log("Emitting from splitter", this.$el);
          }, 250);
        };
        */
        cfg.panes = [];
        $.each(this.$el.children, (i, a) => {
          if ( bbn.fn.tagName(a) === 'div' ){
            var $pane = $(a),
                o = {
                  collapsible: $pane.attr("collapsible") && ($pane.attr("collapsible") !== 'false') ? true : false,
                  collapsed: $pane.attr("collapsed") && ($pane.attr("collapsed") !== 'false') ? true : false,
                  scrollable: $pane.attr("scrollable") && ($pane.attr("scrollable") !== 'false') ? true : false,
                  resizable: $pane.attr("resizable") && ($pane.attr("resizable") !== 'false') ? true : false
                },
                size = a.style[cfg.orientation === 'vertical' ? "height" : "width"] || 0;
            if ( size ){
              o.size = size;
            }
            cfg.panes.push(o);
          }
        });
        this.widget = $(this.$el).kendoSplitter(cfg).data("kendoSplitter");
      },
      resize(){
        if ( this.widget ){
          this.widget.resize();
        }
      },
      onResize(){
        this.resize();
      }
    },
    data(){
      return $.extend({
        widgetName: "kendoSplitter"
      }, bbn.vue.treatData(this));
    },
    mounted(){
      this.build();
      this.$nextTick(() => {
        this.resize();
      })
    },
    updated(){
      this.resize();
    },
    watch: {
      orientation(newVal, oldVal){
        const accepted = ['horizontal', 'vertical'];
        bbn.fn.log("Changing orientation", newVal, oldVal);
        if ( (newVal!== oldVal) && ($.inArray(newVal, accepted) > -1) ){
          this.widget.element
            .children(".k-splitbar").remove()
            .end()
            .children(".k-pane").css({width: "", height: "", top: "", left: ""});
          this.widget.destroy();
          this.build();
          this.resize();
        }
      }
    }
  });

})(jQuery, bbn, kendo);
