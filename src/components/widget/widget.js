/**
 * Created by BBN on 15/02/2017.
 */
(function($, bbn, kendo){
  "use strict";

  var limits = [5, 10, 15, 20, 25, 30, 40, 50];

  /**
   * Classic input with normalized appearance
   */
  Vue.component('bbn-widget', {
    template: "#bbn-tpl-component-widget",
    props: {
      uid: {},
      content: {
        type: String
      },
      url: {
        type: [String, Boolean],
        default: false
      },
      limit: {
        type: Number
      },
      start: {
        type: Number,
        default: 0
      },
      total: {
        type: Number,
        default: 0
      },
      template: {

      },
      component: {
        type: [String, Object]
      },
      itemComponent: {
        type: [String, Object]
      },
      itemStyle: {
        type: [String, Object],
        default: ''
      },
      itemClass: {
        type: [String, Object],
        default: ''
      },
      title: {
        type: String
      },
      buttonsLeft: {
        type: Array,
        default(){
          return [];
        }
      },
      buttonsRight: {
        type: Array,
        default(){
          return [];
        }
      },
      zoomable: {
        type: Boolean,
        default: true
      },
      closable: {
        type: Boolean,
        default: true
      },
      sortable: {
        type: Boolean,
        default: true
      },
      source: {
        type: Object,
        default: function(){
          return {};
        }
      },
      items: {
        type: Array,
        default: function(){
          return [];
        }
      },
      menu: {
        type: Array,
        default: function(){
          return [];
        }
      },
      position: {
        type: String
      },
      top: {},
      bottom: {},
      opened: {}
    },
    data: function(){
      return {
        dashBoard: bbn.vue.closest(this, "bbn-dashboard"),
        currentLimit: this.limit,
        currentItems: this.items,
        currentStart: this.start,
        currentContent: this.content || false,
        lang: {
          close: bbn._("Close")
        }
      };
    },
    computed: {
      hasMenu: function(){
        return this.finalMenu.length ? true : false;
      },
      finalMenu: function(){
        let tmp = this.menu.slice();
        if ( this.url ){
          tmp.unshift({
            text: bbn._("Reload"),
            icon: "fa fa-refresh",
            click: () => {
              this.reload();
            }
          });
        }
        if ( this.currentLimit ){
          let items = [];
          $.each(limits, (i, a) => {
            items.push({
              text: a.toString() + " " + bbn._("Items"),
              selected: a === this.currentLimit,
              click: () => {
                this.currentLimit = a;
              }
            })
          });
          tmp.push({
            text: bbn._("Limit"),
            items: items,
            mode: "selection"
          });
        }
        return tmp;
      }
    },
    methods: {
      _: bbn._,
      close: function(){
        this.$emit("close", this.uid, this);
      },
      zoom: function(){
      },
      reload: function(){
        this.currentItems = [];
        this.$nextTick(() => {
          this.load();
        })
      },
      updateDashboard(){
        if ( !this.dashboard ) {
          this.dashboard = bbn.vue.closest(this, "bbn-dashboard");
        }
      },
      load: function(){
        this.updateDashboard();
        if ( this.url ){
          let params = {
            key: this.uid
          };
          if ( this.currentLimit ){
            params.limit = this.currentLimit;
            if ( this.start !== undefined ){
              params.start = this.currentStart;
            }
          }
          bbn.fn.post(this.url, params, (d) => {
            if ( d.success && d.data ){
              if ( d.data.items !== undefined ){
                this.currentItems = d.data.items;
              }
              if ( d.data.limit ){
                this.currentLimit = d.data.limit;
              }
              if ( d.data.start !== undefined ){
                this.currentStart = d.data.start;
              }
              this.$emit("loaded");
            }
          })
        }
      },
      actionButton: function(name){
        var tmp = this;
        if ( $.isFunction(name) ){
          bbn.fn.log("action", name);
          return name(tmp, tmp.items);
        }
        while ( tmp ){
          if ( $.isFunction(tmp[name]) ){
            return tmp[name]();
          }
          tmp = tmp.$parent;
        }
      },
    },
    mounted: function(){
      this.load();
    },
    watch: {
      currentLimit(newVal){
        this.updateDashboard();
        if ( this.dashboard ){
          this.dashboard.updateWidget(this.uid, {limit: newVal})
        }
        this.reload();
      }
    }
  });

})(jQuery, bbn, kendo);
