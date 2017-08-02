/**
 * Created by BBN on 11/01/2017.
 */
(function($, bbn, kendo){
  "use strict";

  /**
   * Classic input with normalized appearance
   */
  Vue.component('bbn-notification', {
    mixins: [bbn.vue.optionComponent],
    template: '#bbn-tpl-component-notification',
    props: {
      pinned: {},
      top: {},
      left: {},
      bottom: {},
      right: {},
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
      return {
        tplCfg: {
          info: {
            cls: 'groupe',
            icon: 'info'
          },
          success: {
            cls: 'adherent',
            icon: 'flag-checkered'
          },
          warning: {
            cls: 'prospect',
            icon: 'warning'
          },
          error: {
            cls: 'radie',
            icon: 'bomb'
          }
        },
      };
    },
    methods: {
      template: function (obj, type){
        var vm = this;
        if ( typeof(obj) === 'object' ){
          if ( obj.type ){
            type = obj.type;
          }
          var cfg = vm.tplCfg;
          return '<div class="bbn-notification k-notification-wrap ' +
            '">' +
            ( type && cfg[type] ? '<div class="bbn-notification-close k-i-close k-button" title="' + bbn.lng.close + '"><i class="fa fa-times"> </i></div>' : '' ) +
            ( type && cfg[type] ? '<i class="bbn-notification-icon fa fa-' + cfg[type].icon + '"> </i>' : '<span class="bbn-notification-icon loader"><span class="loader-inner"></span></span> ' ) +
            ( obj.title ? '<span class="bbn-b">' + obj.title + '</span><hr>' : '' ) +
            ( obj.content ? obj.content : ( obj.text ? obj.text : bbn.lng.loading ) ) +
            '</div>';
        }
        bbn.fn.log("Bad argument for notification template");
      },
      success: function (obj, timeout){
        return this.show(obj, "success", timeout ? timeout : 2000);
      },
      error: function (obj, timeout){
        return this.show(obj, "error", timeout ? timeout : 5000);
      },
      warning: function (obj, timeout){
        return this.show(obj, "warning");
      },
      show: function (obj, type, timeout){
        if ( typeof(obj) === 'string' ){
          obj = {content: obj};
        }
        if ( typeof(obj) === 'object' ){
          this.widget.show(obj, type);
          if ( timeout ){
            var id = this.setID(),
                t  = this;
            setTimeout(function (){
              t.deleteFromID(id);
            }, timeout < 50 ? timeout * 1000 : timeout);
          }
        }
        else{
          this.widget.show({content: bbn.lng.loading}, "loading");
        }
      },
      info: function (obj, timeout){
        return this.show(obj, "info", timeout);
      },
      setID: function (id){
        if ( !id ){
          id = (new Date()).getMilliseconds();
        }
        this.widget.getNotifications().last().data("bbn-id", id);
        return id;
      },
      getFromID: function (id){
        return this.widget.getNotifications().filter(function (){
          return $(this).data("bbn-id") === id;
        }).first();
      },
      deleteFromID: function (id){
        var ele   = this.getFromID(id),
            close = ele.find(".bbn-notification-close");
        if ( close.length ){
          close.click();
        }
        else{
          ele.parent().fadeOut("fast", function (){
            $(this).remove();
          });
        }
      },
      deleteAll: function (){
        this.widget.hide();
      }
    },
    mounted: function(){
      var vm = this,
          cfg = vm.getOptions();
      vm.widget = $(vm.$el).kendoNotification({
        autoHideAfter: 0,
        hide: function(e) {
          e.preventDefault();
          var $p = e.element.parent(),
              h = $p.outerHeight(true) + 4;
          $p.nextAll(".k-animation-container").each(function () {
            var n = $(vm.$el);
            n.animate({top: (parseFloat(n.css('top')) + h) + 'px'});
          });
          setTimeout(function () {
            $p.remove();
          }, 500);
        },
        position: {
          pinned: cfg.pinned,
          top: cfg.top,
          left: cfg.left,
          bottom: cfg.bottom,
          right: cfg.right
        },
        hideOnClick: false,
        button: true,
        templates: [{
          // define a custom template for the built-in "info" notification type
          type: "info",
          template: function (d) {
            return vm.template(d, "info");
          }
        }, {
          // define a custom template for the built-in "success" notification type
          type: "success",
          template: function (d) {
            return vm.template(d, "success");
          }
        }, {
          // define a custom template for the built-in "warning" notification type
          type: "warning",
          template: function (d) {
            return vm.template(d, "warning");
          }
        }, {
          // define a custom template for the built-in "error" notification type
          type: "error",
          template: function (d) {
            return vm.template(d, "error");
          }
        }, {
          // define a custom template for the built-in "loading" notification type
          type: "loading",
          template: function (d) {
            return vm.template(d, "loading");
          }
        }]
      }).data("kendoNotification");
    },
  });
})(window.jQuery, window.bbn, window.kendo);
