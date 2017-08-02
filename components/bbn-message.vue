<template>
  <span class="bbn-message"></span>
</template>
<script>
 export default {
  name:'bbn-message',
  mixins:[bbn.vue.optionComponent],
  props:{
      position: {},
      cfg: {
        type: Object,
        default: function(){
          return {
            position: "tr"
          };
        }
      }
    },
    data: function(){
      return {
        _todo: {
          _num: 0
        },
        _isShown: false,
      };
    },
    methods: {
      _getCfg: function (obj, type, timeout) {
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

      _getClass: function(cfg){
        return 'appui-notification-section-' + cfg.cat;
      },

      _getTitleHTML: function(cfg){
        return '<h5 class="ui dividing header">' + cfg.title + '</h5>';
      },

      _getItemHTML: function(cfg){
        if ( cfg.time && cfg.html ){
          var m = moment(cfg.time);
          return '<div class="appui-form-label" style="width: 130px">' +
            '<div class="metadata"><span class="date">' + m.calendar() + '</span></div>' +
            '</div><div class="ui reset appui-form-field">' +
            cfg.html +
            '</div>';

        }
        return '';
      },

      _getHTML: function(cfg){
        return '<div class="appui-form-full ' + this._getClass(cfg) + '">' + this._getTitleHTML(cfg) + this._getItemHTML(cfg) + '</div>';
      },

      _addHTML: function(cfg){
        var $cont = $(".appui-notification:visible"),
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
          var d = $cont.data("appui-data");
          if ( d ){
            d.push(cfg.data);
            $cont.data("appui-data", d);
          }
          else{
            $cont.data("appui-data", [cfg.data]);
          }
        }
        $cont.bbn("analyzeContent", true);
      },

      _callWidget: function(cfg){
        var md = this,
            uncertain = {};
        this._isShown = cfg.type;
        if ( cfg.close ){
          uncertain.close = cfg.close;
        }
        if ( cfg.timeout ){
          uncertain.delay = cfg.timeout;
        }
        $.notifyBar($.extend({
          html: '<div class="appui-notification">' + md._getHTML(cfg) + '</div>',
          cssClass: cfg.type,
          closeOnClick: false,
          onBeforeHide: function(){
            md._isShown = false;
            //bbn.fn.log($(".appui-notification:visible").length, $(".appui-notification:visible").data("appui-data"));
            if ( cfg.onClose ){
              cfg.onClose(cfg.data ? cfg.data : []);
            }
          },
          onShow: function(){
            var $n = $(".appui-notification:visible").redraw();
            if ( cfg.data ){
              $n.data("appui-data", [cfg.data]);
            }
          }
        }, uncertain));
      },
      success: function (obj, timeout) {
        return this.show(obj, "success", timeout);
      },

      info: function (obj, timeout) {
        return this.show(obj, "info", timeout ? timeout : false);
      },

      warning: function (obj, timeout) {
        return this.show(obj, "warning", timeout ? timeout : false);
      },

      error: function (obj, timeout) {
        return this.show(obj, "error", timeout === undefined ? 2000 : timeout);
      },

      show: function (obj, type, timeout) {
        if ( !$.notifyBar ) {
          alert("The library notifyBar is needed for bbn.app.messages");
          return false;
        }
        var cfg = md._getCfg(obj, type, timeout);
        if ( md._isShown ){
          if ( md._isShown === cfg.type ){
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

      setID: function (id) {
        if (!id) {
          id = (new Date()).getMilliseconds();
        }
        widget.getNotifications().last().data("appui-id", id);
        return id;
      },

      getFromID: function (id) {
        return widget.getNotifications().filter(function () {
          return $(this).data("appui-id") === id;
        }).first();
      },

      deleteFromID: function (id) {
        var ele = this.getFromID(id),
            close = ele.find(".appui-notification-close");
        if (close.length) {
          close.click();
        }
        else {
          ele.parent().fadeOut("fast", function () {
            $(this).remove();
          });
        }
      },

      deleteAll: function () {
        widget.hide();
      },
    },
    mounted: function(){
      var vm = this;
      setInterval(function(){
        if ( vm._todo._num && !vm._isShown ){
          for ( var n in vm._todo ){
            if ( (n.indexOf('_') !== 0) && vm._todo[n].items.length ){
              $.each(vm._todo[n].items, function(i, v){
                vm.show(v, v.type);
              });
              delete vm._todo[n];
              vm._todo._num--;
              break;
            }
          }
        }
      }, 1000);
      return this;
    },

  }
</script>
<style>
</style>
