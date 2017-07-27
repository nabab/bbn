/**
 * Created by BBN on 10/02/2017.
 */
(function($, bbn, kendo){
  "use strict";

  /**
   * Classic input with normalized appearance
   */
  Vue.component('bbn-input', {
    mixins: [bbn.vue.vueComponent],
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
			pattern: {},
			size: {},
			maxlength: {},
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
        this.update('');
      }
    },
    data: function(){
      return $.extend({
        widgetName: "input",
      }, bbn.vue.treatData(this));
    },
    mounted: function(){
      var vm = this,
          $ele = $(vm.$el),
          cfg = vm.getOptions();

      // button left
      if ( cfg.buttonLeft ){
        var $al = $('<a class="k-icon ' + cfg.buttonLeft + ( cfg.autoHideLeft ? ' bbn-invisible' : '' ) + '"></a>');
        $ele.addClass("k-space-left").append($al);
        if ( cfg.actionLeft ){
          $al.click(function(e){
            if ( $.isFunction(cfg.actionLeft) ){
              cfg.actionLeft(e, vm);
            }
            else if ( $.isFunction(vm[cfg.actionLeft]) ){
              vm[cfg.actionLeft](e, vm);
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
          $ar.click(function(e){
            if ( $.isFunction(cfg.actionRight) ){
              cfg.actionRight(e, vm);
            }
            else if ( $.isFunction(vm[cfg.actionRight]) ){
              vm[cfg.actionRight](e, vm);
            }
          });
        }
        if ( cfg.autoHideRight ){
          $ele.hover(function(){
            $ar.css({opacity: 0.5});
          }, function(){
            $ar.css({opacity: null});
          })
        }
      }

      if ( this.disabled ){
        $ele.addClass("k-state-disabled");
      }
			
			if ( vm.type === 'hidden' ){
				$ele.hide();
			}
    }
  });

})(jQuery, bbn, kendo);
