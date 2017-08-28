/**
 * Created by BBN on 10/02/2017.
 */
(function($, bbn){
  "use strict";

  Vue.component('bbn-tree', {
    mixins: [bbn.vue.fullComponent],
    template: '#bbn-tpl-component-tree',
    props: {
      extensions:{
        type: Array,
        // default: ["dnd"]
      },
      minExpandLevel: {},
      autoExpandMS:{
        type: Number
      },
      source: {},
      root: {},
      select: {},
      contextMenu: {
        type: Array
      },
      cfg: {
        type: Object,
        default: function(){
          return {
            root: 0,
            extensions: ["dnd", "filter"],
            autoExpandedMS: 400,
            source: [],
            disabled: false,
            filter: {
              highlight: true,
              // Re-apply last filter if lazy data is loaded
              autoApply: true,
              // Expand all branches that contain matches while filtered
              autoExpand: true,
              // Hide expanders if all child nodes are hidden by filter
              hideExpanders: false,
              // Match end nodes only
              leavesOnly: false,
              nodata: bbn._("No data match your search"),
              mode: "hide"
            }
          };
        }
      }
    },
    data: function(){
      return $.extend({
        widgetName: "fancytree",
        ivalue: this.currentSelection ? this.currentSelection : ''
      }, bbn.vue.treatData(this));
    },
    methods: {
      _transform(data){
        return $.map(data, (v) => {
          var r = {
            title: v.text,
            /** @todo apply a function on the icons for removing all which only have 'cogs' (without fa fa-) */
            data: {}
          };
          if ( v.icon && v.icon.length ){
            r.icon = v.icon;
          }
          else {
            r.icon = v.icon ? 'fa fa-cog' : false
          }
          if ( v.code ){
            r.title += ' <span class="bbn-grey">' + v.code + '</span>';
          }
          if ( v.items ){
            r.children = this._transform(v.items);
          }
          else if ( v.num_children ){
            r.lazy = true;
          }
          for ( var n in v ){
            r.data[n] = v[n];
          }
          return r;
        });
      },
      _src(url, id){
        return bbn.fn.post(url + '/' + id).promise().then((d) => {
          setTimeout(() => {
            this.$emit("load");
          }, 500);
          return this._transform(d.data);
        });
      },
      build(){
        let cfg = $.extend({
              extensions: this.extensions,
              keydown: function (e, d) {
                if ((e.key === 'Enter') || (e.key === ' ')) {
                  $(d.node.li).find("span.fancytree-title").click();
                }
              },
            }, this.getOptions());

        if ( this.select && $.isFunction(this.select) ){
          cfg.click = (e, d) => {
            if ( d.targetType === 'title' ){
              return this.select(d.node.data.id, d.node.data, d.node);
            }
          };
        }
        else if ( this.ivalue !== undefined ){
          // Load code from the list
          cfg.click = function (e, d){
            if ( d.targetType === 'title' ){
              this.ivalue = d.node.data.text;
              this.currentSelection = d.node.data.text;
              this.emitInput(d.node.data.id);
              bbn.fn.closePopup();
            }
          };
        }
        if ( typeof(cfg.source) === 'string' ){
          let url = cfg.source;
          cfg.source = () => {
            return this._src(url, cfg.root);
          };
          cfg.lazyLoad = (e, d) => {
            d.result = this._src(url, d.node.data.id);
          };
        }
        else if ( Array.isArray(cfg.source) ){
          cfg.source = this._transform(this.source);
        }
        cfg.renderNode = (e, n) => {
          if ( n.node.data.bcolor ){
            $("span.fancytree-custom-icon", n.node.span).css("color", n.node.data.bcolor);
          }
          // ContextMenu
          if ( cfg.contextMenu ){
            let ds = [],
                data = $.extend({is_folder: n.node.folder, key: n.node.key}, n.node.data);
            $.each(cfg.contextMenu, (i, v) => {
              let r = $.extend({}, v);
              if ( $.isFunction(v.click) ){
                r.click = () => {
                  v.click(data, n.node.li);
                };
                ds.push(r);
              }
            });

            /*
            $("<ul/>").kendoContextMenu({
              target: n.node.li,
              animation: {
                open: { effects: "fadeIn" },
                duration: 300
              },
              dataSource: ds
            });
            */
            //bbn.fn.log("TEST00000000000000000000000000000000000000000000000000000", test);
            let ele = $("span.fancytree-title:first", n.node.span).wrap('<bbn-context :context="true" :source="source"></bbn-context>').parent()[0];
            let test = new Vue({
              el: ele,
              data: {
                source: ds
              }
            });

          }
        };
        cfg.disabled = false;
        $(this.$el).fancytree(cfg);
        this.widget = $(this.$el).fancytree("getTree");
      }
    },
    mounted: function(){
      this.build();
    }
  });

})(jQuery, bbn);