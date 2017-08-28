/**
 * Created by BBN on 10/02/2017.
 */
(function($, bbn){
  "use strict";

  Vue.component('bbn-tree2', {
    template: '#bbn-tpl-component-tree2',
    props: {
      extensions:{
        type: Array,
        // default: ["dnd"]
      },
      minExpandLevel: {},
      autoExpandMS:{
        type: Number
      },
      map: {
        type: Function,
      },
      source: {
        Type: [Array, String]
      },
      sourceValue: {
        Type: String
      },
      draggable: {
        type: Boolean,
        default: false
      },
      target: {
        type: Boolean,
        default: false
      },
      dragStart: {
        type: Function,
        default: function(node, data){
          return true
        }
      },
      dragEnter: {
        type: Function,
        default: function(node, data){
          return true
        }
      },
      dragDrop: {
        type: Function,
        default: function(node, data){
          data.otherNode.moveTo(node, data.hitMode);
        }
      },
      ajaxProp: {
        type: String
      },
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
      return {
        url: false,
        items: []
      }
    },
    created(){
      if ( typeof(this.source) === 'string' ){
        this.url = this.source;
        this.load();
      }
      else if ( $.isArray(this.source) ){
        this.items = this.source;
      }
    },
    methods: {
      load(){
        if ( this.url ){
          bbn.fn.post(this.url, {}, (d) => {
            if ( this.ajaxProp ){
              if ( d[this.ajaxProp] !== undefined ){
                d = d[this.ajaxProp];
              }
            }
            if ( $.isArray(d) ){
              if ( this.map ){
                $.map(d, (i, a) => {
                  return this.map(a);
                })
              }
              this.items = d;
            }
          })
        }
      }
    },
    mounted: function(){
    }
  });

})(jQuery, bbn);
