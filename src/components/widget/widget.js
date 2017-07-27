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
      content: {},
      url: {},
      limit: {},
      start: {},
      total: {},
      template: {},
      component: {},
      itemComponent: {},
      title: {},
      buttonsLeft: {},
      buttonsRight: {},
      zoomable: {},
      closable: {},
      sortable: {},
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
      top: {},
      menu: {
        type: Array,
        default: function(){
          return [];
        }
      },
      bottom: {},
      position: {
        type: String
      },
      opened: {}
    },
    computed: {
      hasMenu: function(){
        return this.finalMenu.length ? true : false;
      },
      finalMenu: function(){
        var vm = this,
            tmp = vm.menu.slice();
        if ( vm.url ){
          tmp.unshift({
            text: bbn._("Reload"),
            icon: "fa fa-refresh",
            click: function(){
              vm.reload();
            }
          });
        }
        if ( vm.limit ){
          var items = [];
          $.each(limits, function(i, a){
            items.push({
              text: a.toString() + " " + bbn._("Items"),
              selected: a === vm.currentLimit,
              click: function(){
                vm.setLimit(a);
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
        var vm = this,
            $ele = $(vm.$el);
        vm.$emit("close", vm.uid, vm);
        /*
        $ele.bbn("animateCss", "zoomOut", function () {
          $(this).hide();
          vm.$emit("close", $ele.attr("data-bbn-type") || null, vm);
        })
        */
      },
      zoom: function(){
        var vm = this,
            o = vm.getOptions(),
            $ele = $(vm.$el);

      },
      getOptions: function(){
        var vm = this,
            o = bbn.vue.getOptions(this);
        return o;
      },
      reload: function(){
        var vm = this;
        vm.items.splice(0, vm.items.length);
        vm.$nextTick(function(){
          vm.load();
        })
      },
      load: function(){
        var vm = this,
            o = vm.getOptions();
        if ( o.url ){
          var params = {
            key: vm.uid
          };
          if ( o.limit ){
            params.limit = o.limit;
            if ( o.start ){
              params.start = o.start;
            }
          }
          bbn.fn.post(o.url, params, function(d){
            if ( d.success && d.data ){
              if ( vm.dashBoard ){
                var idx = bbn.fn.search(vm.dashBoard.source, "key", vm.uid);
                if ( idx > -1 ){
                  if ( d.data.limit && vm.currentLimit ){
                    delete d.data.limit;
                  }
                  vm.dashBoard.updateWidget(vm.uid, d.data);
                }
              }
              /*
               var topSrc = vm;
               while ( topSrc.$parent && (topSrc.$parent.source !== undefined) ){
               topSrc = topSrc.$parent;
               }
               topSrc.$set(vm.$parent.source, "items", d.items);
               topSrc.$set(vm.$parent.source, "num", d.num);
               //vm.$set(vm, "num", d.num);
               //vm.$forceUpdate();
               */
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
      setLimit: function(limit){
        var vm = this;
        vm.currentLimit = limit;
        if ( vm.dashBoard ){
          vm.dashBoard.updateWidget(vm.uid, {limit: limit}).then(() => {
            vm.reload();
          });
        }
        else{
          vm.reload();
        }
      },
    },
    data: function(){
      return $.extend(bbn.vue.treatData(this), {
        dashBoard: bbn.vue.closest(this, ".bbn-dashboard"),
        currentLimit: this.limit,
        lang: {
          close: bbn._("Close")
        }
      });
    },
    mounted: function(){
      this.load();
    },
  });

})(jQuery, bbn, kendo);
