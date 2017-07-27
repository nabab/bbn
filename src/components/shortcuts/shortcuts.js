/**
 * Created by BBN on 07/01/2017.
 */
;(function($){
  return {
    defaults: {
      data: [],
      top: "52px"
    },

    setup_bin: function(){
      var md = this;
      md.bin = $("#appui_dock_menu_bin");
      if ( !md.bin.length ){
        md.bin = $('<div id="appui_dock_menu_bin"> </div>').appendTo(document.body);
      }
      md.bin
        .droppable({
          accept: "div",
          hoverClass: "k-state-hover",
          activeClass: "k-state-active",
          drop: function (e, ui) {
            var id = parseInt(ui.draggable.attr("data-id"));
            bbn.fn.post("menu/shortcuts/delete", {id: id}, function (d) {
              if (d.success) {
                bbn.app.dock.ele.fisheye("remove", id);
              }
            });
          }
        });
    },

    connect_menu: function(){
      var md = this;
      $("li", appui.menu.panel).filter(function(){
          return $(this).find("li").length ? false : true;
        })
        .draggable({
          cursorAt: {top: 1, left: 0},
          zIndex: 13,
          helper: function (e) {
            var t = $(e.currentTarget).closest("li"),
                i = t.find("i"),
                r = $('<div id="appui_menu2dock_helper" class="bbn-xl"/>');
            r.append(i[0].outerHTML);
            return r;
          },
          scroll: false,
          revert: true,
          revertDuration: 0,
          containment: "window",
          appendTo: '#appui_dock_menu .bbn-dock-menu',
          start: function (e, ui) {
            bbn.app.dock.ele.fisheye("disable");
            var dataItem = $.ui.fancytree.getNode(e.currentTarget).data;
            if (dataItem.is_parent) {
              return false;
            }
          },
          stop: function (e, ui) {
            bbn.app.dock.ele.fisheye("enable");
          }
        });


    },

    fill: function(){
      var md = this;
      md.dock.empty();
      if ( $.isArray(md.settings.data) && md.settings.data.length ){
        if ( !$.isArray(md.settings.data[0]) ){
          md.settings.data = [md.settings.data];
        }
        $.each(md.settings.data, function(i, a){
          $.each(a, function(j, b){
            if ( b && b.text && b.icon ){
              var div = $('<div/>'),
                  lnk = $('<a/>').append('<i class="' + b.icon + '"> </i>'),
                  span = $('<span/>').text(b.text);
              lnk.attr("href", b.url ? b.url : 'javascript:;');
              if ( b.click ){
                lnk.click(b.click);
              }
              div.append(lnk, span).appendTo(md.dock);
            }
          })
        });
        bbn.fn.log("FILLED!", md, this);
      }

      md.dock
        .find("div[data-id]")
        .draggable({
          helper: function (e, ui) {
            var t = $(e.currentTarget),
                i = t.find("i"),
                r = $('<div id="appui_menu2dock_helper"/>');
            r.append(i[0].outerHTML);
            return r;
          },
          cursorAt: {top: 1, left: 0},
          zIndex: 13,
          scroll: false,
          containment: "window",
          appendTo: 'body',
          start: function (e, ui) {
            md.bin.show();
          },
          stop: function (e, ui) {
            md.bin.hide();
          }
        });
    },

    builder: function(){
      var md = this;
      // Fisheye menu
      if ( $.ui.fisheye ) {

        md.dock = md.ele.children(".bbn-dock-menu");

        md.setup_bin(md);

        md.fill(md);

        md.ele
          .fisheye({
            items: 'div',
            itemsText: 'span',
            container: md.dock[0],
            valign: "top"
          });

        md.connect_menu(md);

        md.dock
        .droppable({
          accept: "#lateral_menu li",
          hoverClass: "bbn-dropable-hover",
          activeClass: "bbn-dropable-active",
          drop: function (e, ui) {
            var dataItem = $.ui.fancytree.getNode(ui.draggable[0]).data;
            bbn.fn.log(dataItem);
            bbn.fn.post("menu/shortcuts/insert", {id: dataItem.id}, function (d) {
              if (d.success) {
                bbn.app.dock.ele.fisheye("add", {
                  id: dataItem.id,
                  url: dataItem.link ? dataItem.link : 'javascript: ' + dataItem.click,
                  text: dataItem.text,
                  icon: dataItem.icon
                });
                bbn.app.dock.exit.prev().draggable(bbn.app.dock.dragCfg);
              }
            });
            return ui;
          }
        });
      }

    }
  };
})(jQuery);
