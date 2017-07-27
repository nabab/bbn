/**
 * Created by BBN on 10/02/2017.
 */
(function($, bbn){
  "use strict";

  var transform = function(data){
        return $.map(data, function(v){
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
            r.children = transform(v.items);
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
      src = function(url, id){
        return bbn.fn.post(url + '/' + id).promise().then(function(d){
          return transform(d.data);
        });
      };

  Vue.component('bbn-tree', {
    mixins: [bbn.vue.vueComponent],
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
      build: function(){
        var vm = this,
            cfg = $.extend({
              extensions: this.extensions,
              keydown: function (e, d) {
                if ((e.key === 'Enter') || (e.key === ' ')) {
                  $(d.node.li).find("span.fancytree-title").click();
                }
              },
            }, vm.getOptions());

        if ( vm.select && $.isFunction(vm.select) ){
          cfg.click = function (e, d){
            if ( d.targetType === 'title' ){
              return vm.select(d.node.data.id, d.node.data, d.node);
            }
          };
        }
        else if ( vm.ivalue !== undefined ){
          // Load code from the list
          cfg.click = function (e, d){
            if ( d.targetType === 'title' ){
              bbn.fn.log("click", e, d);
              vm.ivalue = d.node.data.text;
              vm.currentSelection = d.node.data.text;
              vm.update(d.node.data.id);
              bbn.fn.closePopup();
            }
          };
        }
        if ( typeof(cfg.source) === 'string' ){
          var url = cfg.source;
          cfg.source = function(){
            return src(url, cfg.root);
          };
          cfg.lazyLoad = function(e, d){
            d.result = src(url, d.node.data.id);
          };
        }
        else if ( $.isArray(cfg.source) ){
          cfg.source = transform(vm.source);
        }
        cfg.disabled = false;
        $(this.$el).fancytree(cfg)
        vm.widget = $(this.$el).fancytree("getTree");
      }
    },
    mounted: function(){
      this.build();
    }
  });

})(jQuery, bbn);
