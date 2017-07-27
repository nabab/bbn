/**
 * Created by BBN on 15/02/2017.
 */
(function($, bbn, kendo){
  "use strict";

  /**
   * Classic input with normalized appearance
   */
  Vue.component('bbn-splitter', {
    mixins: [bbn.vue.optionComponent],
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
    methods: {
      i18n(text){
        return bbn._(text);
      },
      build(){
        const vm = this;
        let cfg = this.getOptions();
        cfg.resize = function(){
          bbn.fn.log("RESIZING FROM CFG");
          bbn.fn.analyzeContent(vm.$el, true);
          bbn.fn.propagateResize(vm.$el);
        };
        cfg.panes = [];
        $.each(vm.$el.children, function(i, a){
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
        vm.widget = $(vm.$el).kendoSplitter(cfg).data("kendoSplitter");
      }
    },
    data(){
      return $.extend({
        widgetName: "kendoSplitter"
      }, bbn.vue.treatData(this));
    },
    mounted(){
      const vm = this;
      vm.build();
      vm.widget.resize();
    },
    updated(){
      const vm = this;
      vm.widget.resize();
    },
    watch: {
      orientation(newVal, oldVal){
        const vm = this,
              accepted = ['horizontal', 'vertical'];
        bbn.fn.log("Changing orientation", newVal, oldVal);
        if ( (newVal!== oldVal) && ($.inArray(newVal, accepted) > -1) ){
          vm.widget.element
            .children(".k-splitbar").remove()
            .end()
            .children(".k-pane").css({width: "", height: "", top: "", left: ""});
          vm.widget.destroy();
          vm.build();
          vm.$nextTick(() => {
            vm.widget.trigger("resize");
          })
        }
      }
    }
  });

})(jQuery, bbn, kendo);
