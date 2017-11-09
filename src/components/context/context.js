/**
 * Created by BBN on 15/02/2017.
 */
(function($, bbn, kendo){
  "use strict";

  /**
   * Classic input with normalized appearance
   */
  Vue.component('bbn-context', {
    template: '#bbn-tpl-component-context',
    props: {
      source: {
        type: [Function, Array],
        default(){
          return []
        }
      },
      sourceOption: {
        default: null
      },
      tag: {
        type: String,
        default: 'span'
      },
      context: {
        type: Boolean,
        default: false
      },
      mode: {
        type: String,
        default: 'free'
      }
    },
    data(){
      return {
        items: this.getItems()
      };
    },
    methods: {
      getItems(){
        return ($.isFunction(this.source) ? this.source(this.sourceOption) : this.source) || [];
      },
      clickItem(e){
        if (
          ((e.type === 'contextmenu') && this.context) ||
          ((e.type === 'click') && !this.context)
        ){
          let vlist = this.$root.vlist || (window.appui ? window.appui.vlist : undefined);
          this.items = this.getItems();
          if ( this.items.length && (vlist !== undefined) ){
            let x, y;
            x = (x = e.clientX ? e.clientX : this.$el.offsetLeft) < 5 ? 0 : x - 5;
            y = (y = e.clientY ? e.clientY : this.$el.offsetTop) < 5 ? 0 : y - 5;

            vlist.push({
              mode: this.mode,
              items: this.getItems(),
              left: x,
              top: y
            });
          }
        }
      },
    },
    watch: {
      source: {
        deep: true,
        handler(){
          this.items = this.getItems()
        }
      },
      sourceOption(){
        this.items = this.getItems()
      }
    }
  });

})(jQuery, bbn, kendo);
