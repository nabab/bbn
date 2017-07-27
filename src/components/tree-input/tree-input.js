/**
 * Created by BBN on 10/02/2017.
 */
(function($, bbn){
  "use strict";

  var src = function(url, id){
    return bbn.fn.post(url + '/' + id).promise().then(function(d){
      return $.map(d.data, function(v){
        var r = {
          title: v.text,
          /** @todo apply a function on the icons for removing all which only have 'cogs' (without fa fa-) */
          icon: v.icon && v.icon.length ? v.icon : 'fa fa-cog',
          data: {}
        };
        if ( v.code ){
          r.title += ' <span class="bbn-grey">' + v.code + '</span>';
        }
        if ( v.num_children ){
          r.lazy = true;
        }
        for ( var n in v ){
          r.data[n] = v[n];
        }
        return r;
      });
    });
  };

  Vue.component('bbn-tree-input', {
    mixins: [bbn.vue.vueComponent],
    template: '#bbn-tpl-component-tree-input',
    props: {
      extensions:{
        type: Array,
        // default: ["dnd"]
      },
      autoExpandMS:{
        type: Number
      },
      source: {
        type: [String, Array, Object]
      },
      cfg: {
        type: Object,
        default: function(){
          return {
            extensions: ["dnd"],
            auoExpandedMS: 400,
            source: [],
            disabled: false
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
                  //$(d.node.li).find("span.fancytree-title").click();
                }
              },
              // Load code from the list
              click: function (e, d) {
                bbn.fn.log("click", e, d);
                vm.ivalue = d.node.data.text;
                vm.currentSelection = d.node.data.text;
                vm.update(d.node.data.id);
                bbn.fn.closePopup();
              },
            }, vm.getOptions());
        if ( typeof(cfg.source) === 'string' ){
          var url = cfg.source;
          cfg.source = function(){
            return src(url, 0);
          };
          cfg.lazyLoad = function(e, d){
            bbn.fn.log("LAZY LOAD", d);
            d.result = src(url, d.node.data.id);
          };
        }
        cfg.disabled = false;
        bbn.fn.popup('<div></div>', bbn._("Selector"), function(ele){
          vm.widget = ele.children().fancytree(cfg);
        }, function(){
          vm.widget = false;
        })
      }
    },
    mounted: function(){

    }
  });

})(jQuery, bbn);
