/**
 * Created by BBN on 07/01/2017.
 */
(function($, bbn, kendo){
  "use strict";

  /**
   * Classic input with normalized appearance
   */
  Vue.component('bbn-message', {
    mixins: [bbn.vue.basicComponent, bbn.vue.optionComponent],
    props:{
      position: {},
      cfg: {
        type: Object,
        default(){
          return {
            position: "tr"
          };
        }
      }
    },
    data(){
      return {
        todo: {
        },
        isShown: false,
        num: 0
      };
    },
    methods: {
      _getCfg(obj, type, timeout) {
        var group = type ? type : 'info',
            cfg = {
              time: new Date(),
              type: group,
              widget: {
                cssClass: group
              }
            };

        if ( typeof(obj) !== 'object' ) {
          obj = {text: obj.toString()};
        }
        else if ( obj.widget ){
          return obj;
        }
        if ( timeout === false ){
          cfg.close = true;
        }
        else if ( timeout ){
          cfg.timeout = timeout;
        }
        if ( obj.html ){
          cfg.html = obj.html;
        }
        else if ( obj.text ){
          cfg.html = '<div>' + obj.text + '</div>';
        }
        cfg.title = obj.title ? obj.title : 'misc';
        cfg.cat = bbn.fn.md5(cfg.title);
        cfg.data = obj.data ? obj.data : false;
        cfg.url = obj.url ? obj.url : false;
        return cfg;
      },

      _getClass(cfg){
        return 'bbn-notification-section-' + cfg.cat;
      },

      _getTitleHTML(cfg){
        return '<h5 class="ui dividing header">' + cfg.title + '</h5>';
      },

      _getItemHTML(cfg){
        if ( cfg.time && cfg.html ){
          var m = moment(cfg.time);
          return '<div class="bbn-form-label" style="width: 130px">' +
            '<div class="metadata"><span class="date">' + m.calendar() + '</span></div>' +
            '</div><div class="ui reset bbn-form-field">' +
            cfg.html +
            '</div>';

        }
        return '';
      },

      _getHTML(cfg){
        return '<div class="bbn-form-full ' + this._getClass(cfg) + '">' + this._getTitleHTML(cfg) + this._getItemHTML(cfg) + '</div>';
      },

      _addHTML(cfg){
        var $cont = $(".bbn-notification:visible"),
            $ele = $("." + this._getClass(cfg), $cont[0]);
        if ( !$cont.length ){
          return;
        }
        if ( !$ele.length ){
          $cont.prepend(this._getHTML(cfg));
        }
        else{
          $ele.prependTo($cont).find(".ui.header:first").after(this._getItemHTML(cfg));
        }
        if ( cfg.data ){
          var d = $cont.data("bbn-data");
          if ( d ){
            d.push(cfg.data);
            $cont.data("bbn-data", d);
          }
          else{
            $cont.data("bbn-data", [cfg.data]);
          }
        }
        $cont.bbn("analyzeContent", true);
      },

      _callWidget(cfg){
        const vm = this;
        var uncertain = {};
        vm.isShown = cfg.type;
        if ( cfg.close ){
          uncertain.close = cfg.close;
        }
        if ( cfg.timeout ){
          uncertain.delay = cfg.timeout;
        }
        $.notifyBar($.extend({
          html: '<div class="bbn-notification">' + vm._getHTML(cfg) + '</div>',
          cssClass: cfg.type,
          closeOnClick: false,
          onBeforeHide(){
            vm.isShown = false;
            //bbn.fn.log($(".bbn-notification:visible").length, $(".bbn-notification:visible").data("bbn-data"));
            if ( cfg.onClose ){
              cfg.onClose(cfg.data ? cfg.data : []);
            }
          },
          onShow(){
            var $n = $(".bbn-notification:visible").redraw();
            if ( cfg.data ){
              $n.data("bbn-data", [cfg.data]);
            }
          }
        }, uncertain));
      },
      success(obj, timeout) {
        return this.show(obj, "success", timeout);
      },

      info(obj, timeout) {
        return this.show(obj, "info", timeout ? timeout : false);
      },

      warning(obj, timeout) {
        return this.show(obj, "warning", timeout ? timeout : false);
      },

      error(obj, timeout) {
        return this.show(obj, "error", timeout === undefined ? 2000 : timeout);
      },

      show(obj, type, timeout) {
        const vm = this;
        if ( !$.notifyBar ) {
          alert("The library notifyBar is needed for bbn.app.messages");
          return false;
        }
        var cfg = vm._getCfg(obj, type, timeout);
        if ( vm.isShown ){
          if ( vm.isShown === cfg.type ){
            if ( cfg.close ) {
              addHTML(cfg);
            }
          }
          else{
            if ( !todo[cfg.type] ) {
              todo[cfg.type] = {
                items: []
              };
              todo._num++;
            }
            todo[cfg.type].last = cfg.time.getTime();
            todo[cfg.type].items.push(cfg);
          }
        }
        else{
          callWidget(cfg);
        }
      },

      setID(id) {
        if (!id) {
          id = (new Date()).getMilliseconds();
        }
        widget.getNotifications().last().data("bbn-id", id);
        return id;
      },

      getFromID(id) {
        return widget.getNotifications().filter(function () {
          return $(this).data("bbn-id") === id;
        }).first();
      },

      deleteFromID(id) {
        var ele = this.getFromID(id),
            close = ele.find(".bbn-notification-close");
        if (close.length) {
          close.click();
        }
        else {
          ele.parent().fadeOut("fast", function () {
            $(this).remove();
          });
        }
      },

      deleteAll() {
        widget.hide();
      },
    },
    mounted(){
      const vm = this;
      /*
      setInterval(function(){
        if ( vm.num && !vm.isShown ){
          for ( var n in vm.todo ){
            if ( vm.todo[n].items.length ){
              $.each(vm.todo[n].items, function(i, v){
                vm.show(v, v.type);
              });
              delete vm.todo[n];
              vm.num--;
              break;
            }
          }
        }
      }, 1000);
      */
      return this;
    },

  });
})(window.jQuery, window.bbn, window.kendo);
