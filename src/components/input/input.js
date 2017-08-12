/**
 * Created by BBN on 10/02/2017.
 */
(function($, bbn, kendo){
  "use strict";

  /**
   * Classic input with normalized appearance
   */
  Vue.component('bbn-input', {
    mixins: [bbn.vue.fullComponent],
    template: '#bbn-tpl-component-input',
    props: {
      autocomplete: {},
      type: {
        type: String,
      },
      buttonLeft: {
        type: String
      },
      buttonRight: {
        type: String
      },
      actionLeft: {},
      actionRight: {},
      autoHideLeft: {},
      autoHideRight: {},
      pattern: {
        type: String
      },
      cfg:{
        type: Object,
        default: function(){
          return {
            autocomplete: true,
            type: "text"
          }
        }
      },
    },
    methods: {
      clear: function(){
        this.emitInput('');
      }
    },
    data: function(){
      return $.extend({
        widgetName: "input",
      }, bbn.vue.treatData(this));
    },
    mounted: function(){
      let $ele = $(this.$el),
          cfg = this.getOptions();

      // button left
      if ( cfg.buttonLeft ){
        let $al = $('<a class="k-icon ' + cfg.buttonLeft + ( cfg.autoHideLeft ? ' bbn-invisible' : '' ) + '"></a>');
        $ele.addClass("k-space-left").append($al);
        if ( cfg.actionLeft ){
          $al.click((e) => {
            if ( $.isFunction(cfg.actionLeft) ){
              cfg.actionLeft(e, this);
            }
            else if ( $.isFunction(this[cfg.actionLeft]) ){
              this[cfg.actionLeft](e, this);
            }
          });
        }
        if ( cfg.autoHideLeft ){
          $ele.hover(function(){
            $al.css({opacity: 0.5});
          }, function(){
            $al.css({opacity: null});
          })
        }
      }

      // button right
      if ( cfg.buttonRight ){
        var $ar = $('<a class="k-icon ' + cfg.buttonRight + ( cfg.autoHideRight ? ' bbn-invisible' : '' ) + '"></a>');
        $ele.addClass("k-space-right").append($ar);
        if ( cfg.actionRight ){
          $ar.click((e) => {
            if ( $.isFunction(cfg.actionRight) ){
              cfg.actionRight(e, this);
            }
            else if ( $.isFunction(this[cfg.actionRight]) ){
              this[cfg.actionRight](e, this);
            }
          });
        }
        if ( cfg.autoHideRight ){
          $ele.hover(() => {
            $ar.css({opacity: 0.5});
          }, () => {
            $ar.css({opacity: null});
          })
        }
      }

      if ( this.disabled ){
        $ele.addClass("k-state-disabled");
      }
			
			if ( this.type === 'hidden' ){
				$ele.hide();
			}
      this.$emit("ready", this.value);
    }
  });

})(jQuery, bbn, kendo);
