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
    mixins: [bbn.vue.basicComponent, bbn.vue.inputComponent],
    props: {
      pinned: {},
      top: {},
      left: {},
      bottom: {},
      right: {},
      height:{
        type: [String, Number]
      },
      buttons: {
        type: Array,
        default(){
          return [
            ['viewHTML'],
            ['undo', 'redo'], // Only supported in Blink browsers
            ['formatting'],
            ['strong', 'em', 'underline', 'del'],
            ['superscript', 'subscript'],
            ['link'],
            ['insertImage'],
            ['justifyLeft', 'justifyCenter', 'justifyRight', 'justifyFull'],
            ['unorderedList', 'orderedList'],
            ['horizontalRule'],
            ['removeformat'],
            ['fullscreen']
          ];
        }
      },
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
    methods: {
      changeHidden(e){
        bbn.fn.log("changeHidden", e);
        bbn.fn.log(e.target.value, this.value);
      }
    },

    mounted: function(){
      let cfg = this.getOptions(),
          $ele = $(this.$refs.element);
      if ( this.height ){
        $(this.$el).css('height', this.height);
      }
      this.widget = $ele.trumbowyg({
        lang: 'fr',
        resetCss: true,
        btns: this.buttons
      });
      $ele.on("tbwchange tbwpaste", (e) => {
        this.emitInput(e.target.value)
      });
      this.$emit("ready", this.value);
    },
    watch: {
      value(newVal){
        if ( this.widget.trumbowyg('html') !== newVal ){
          this.widget.trumbowyg('html', newVal);
        }
      }
    }
  });
})(jQuery);
