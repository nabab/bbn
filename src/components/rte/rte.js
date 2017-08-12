/**
 * Created by BBN on 11/01/2017.
 */
(function($){
  "use strict";

  /**
   * Classic input with normalized appearance
   */
  $.trumbowyg.svgPath = bbn_root_url + 'lib/Trumbowyg/v2.5.1/dist/ui/icons.svg';

  Vue.component('bbn-rte', {
    mixins: [bbn.vue.fullComponent],
    template: '#bbn-tpl-component-rte',
    props: {
      pinned: {},
      top: {},
      left: {},
      bottom: {},
      right: {},
      height:{},
      cfg: {
        type: Object,
        default: function(){
          return {
            pinned: true,
            top: null,
            left: null,
            bottom: 5,
            right: 5,
          }
        }
      }
    },
    data: function(){
      return bbn.vue.treatData(this);
    },
    methods: {
    },
    mounted: function(){
      let cfg = this.getOptions(),
          $ele = $(this.$refs.element);
      this.widget = $ele.trumbowyg({
        lang: 'fr',
        resetCss: true
      });
      $ele.on("tbwchange", (e) => {
        this.emitInput(e.target.value)
      });
      this.$emit("ready", this.value);
    },
  });
})(jQuery);
