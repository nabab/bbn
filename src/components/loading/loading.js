/**
 * Created by BBN on 07/01/2017.
 */
;(function($, bbn, kendo){
  "use strict";

  /**
   * Classic input with normalized appearance
   */
  Vue.component('bbn-loading', {
    mixins: [bbn.vue.optionComponent],
    template: '#bbn-tpl-component-loading',
    props: {
      position: {},
      cfg: {
        type: Object,
        default: function(){
          return {
            position: {
              bottom: 5,
              right: 5
            }
          };
        }
      }
    },
    data: function(){
      return $.extend({
        currentID: false
      }, bbn.vue.treatData(this));
    },

    methods: {
      start: function(url, id){
        var vm = this;
        this.widget.show({url: url}, "loading");
        vm.setID(id);
      },

      end: function(url, id){
        this.deleteFromID(id);
      },

      setID: function(id){
        if ( !id ){
          id = (new Date()).getTime();
        }
        this.widget.getNotifications().last().data("bbn-id", id);
        return id;
      },

      getFromID: function(id){
        if ( this.widget ){
          return this.widget.getNotifications().filter(function(){
            //bbn.fn.log("COMPARE", $(this).data("bbn-id"), id);
            return $(this).data("bbn-id") === id;
          }).first();
        }
        return [];
      },

      deleteFromID: function(id){
        var $ele = this.getFromID(id),
            $close = $ele.find(".bbn-notification-close:visible");
        //bbn.fn.log(id, $ele, close);
        if ( $close.length ){
          $close.click();
        }
        else{
          $ele.parent().fadeOut("fast", function(){
            $(this).remove();
          });
        }
      },

      deleteAll: function(){
        this.widget.hide();
      },
    },

    mounted: function(){
      var vm = this,
          cfg = vm.getOptions();
      vm.widget = $(vm.$el).kendoNotification({
        autoHideAfter: 0,
        hide: function(e){
          e.preventDefault();
          var $p = e.element.parent(),
              h = $p.outerHeight(true) + 4;
          $p.nextAll(".k-animation-container").each(function(){
            vm.ele.animate({top: (parseFloat(ele.css('top')) + h) + 'px'});
          });
          setTimeout(function(){
            $p.remove();
          }, 500);
        },
        position: cfg.position,
        stacking: "up",
        hideOnClick: true,
        button: true,
        templates: [{
          // define a custom template for the built-in "loading" notification type
          type: "loading",
          template: function(d){
            return '<div class="bbn-loading k-notification-wrap">' +
              '<div><span class="bbn-notification-icon loader"><span class="loader-inner"></span></span> ' +
              bbn._("Loading...") +
              ( d.url ? '</div><div class="bbn-notification-info">' + d.url : '' ) +
              '</div></div>';
          }
        }]
      }).data("kendoNotification");
      vm.currentID = vm.setID();
    }
  });

})(jQuery, bbn, kendo);
