/**
 * Created by BBN on 15/02/2017.
 */
(function($, bbn, kendo){
  "use strict";

  /**
   * Classic input with normalized appearance
   */
  Vue.component('bbn-dashboard', {
    mixins: [bbn.vue.optionComponent],
    template: "#bbn-tpl-component-dashboard",
    props: {
      components: {
        type: Object,
        default: function(){
          return {};
        }
      },
      sortable: {},
      source: {},
      url: {},
      cfg: {
        type: Object,
        default: function(){
          return {
            sortable: true,
            url: false,
            source: [],
            components: {}
          };
        }
      }
    },
    computed: {
      widgets: function(){
        var vm = this;
        return $.map(vm.source, function(a){
          a.hidden = a.hidden ? true : false;
          if ( !a.key ){
            a.key = a.uid ? a.uid : vm.makeId();
          }
          return a;
        })
      }
    },
    data: function(){
      return {
        menu: [],
        isRefreshing: false
      };
    },
    methods: {
      hideWidget: function(e, key){
        return this.toggleWidget(name, key, true);
      },
      showWidget: function(e, key){
        return this.toggleWidget(name, key, false);
      },
      toggleWidget: function(e, key, hidden){
        var vm = this,
            //vueObj = bbn.vue.findByKey(vm, key, '.bbn-widget'),
            idx = bbn.fn.search(vm.source, "key", key);
        if ( idx > -1 ){
          this.updateWidget(key, {
            hidden: typeof(hidden) !== "boolean" ? hidden : (vm.source[idx].hidden ? false : true)
          });
        }
      },
      
      makeId: function(){
        return bbn.fn.randomString(15, 20);
      },
      
      paint: function(){
        var vm = this,
            cfg = vm.getOptions(),
            $ele = $(vm.$el),
            actualWidth = $ele.innerWidth(),
            num = 1,
            steps = [800, 1150, 1550, 2200, 3000];
        $.each(steps, function(i, step){
          if ( actualWidth >= step ){
            num++;
          }
          else{
            return false;
          }
        });
        if ( cfg.sortable && !$ele.hasClass("ui-sortable") ){
          var oldIdx = false;
          $ele.sortable({
            placeholder: "bbn-widget bbn-bg-grey bbn-widget-placeholder",
            opacity: 0.5,
            forcePlaceholderSize: true,
            handle: "div.k-header h4",
            start: function(e, ui){
              oldIdx = ui.item.index(".bbn-widget:not(.bbn-widget-placeholder)");
            },
            stop: function(e, ui){
              if ( oldIdx !== false ){
                var newIdx = ui.item.index(".bbn-widget:not(.bbn-widget-placeholder)");
                if ( newIdx !== oldIdx ){
                  if ( vm.url ){
                    try {
                      var tmp = vm.$children[oldIdx].$vnode.data.key;
                    }
                    catch (e){
                      new Error(bbn._("Impossible to find the idea"));
                    }
                    bbn.fn.post(vm.url + 'move', {
                      id: tmp,
                      index: newIdx
                    }, function(d){
                      if ( d.success ){
                        vm.isRefreshing = true;
                        //bbn.fn.move(vm.source, newIdx, oldIdx);
                        vm.$nextTick(function(){
                          vm.isRefreshing = false;
                        })
                      }
                      else{
                        $ele.sortable("cancel");
                      }
                    });
                  }
                  else{
                    bbn.fn.move(vm.source, oldIdx, newIdx);
                    bbn.fn.log("NO SAVING BECAUSE NO URL");
                  }
                }
              }
            }
          })
        }
        $ele.css({
          "-moz-column-count": num,
          "-webkit-column-count": num,
          "column-count": num
        });
      },
      
      updateMenu: function(){
        var vm = this,
            tab = bbn.vue.closest(vm, ".bbn-tab");
        if ( tab ){
          if ( vm.menu.length ){
            $.each(vm.menu, function(i, a){
              tab.deleteMenu(a);
            });
          }
          vm.menu = [];
          vm.menu.push(tab.addMenu({
            text: bbn._("Widgets"),
            mode: 'options',
            items: $.map(vm.source, function(a){
              return {
                disabled: !a.closable,
                selected: !a.hidden,
                text: a.text,
                click: function(e, idx, obj){
                  if ( vm.source[idx] && (vm.source[idx].closable !== false) ){
                    var key = a.key,
                        obj = bbn.vue.getChildByKey(vm, key, '.bbn-widget');
                    if ( obj ){
                      //obj.close();
                    }
                    vm.toggleWidget(e, key, vm.source[idx].hidden ? false : true);
                  }
                }
              }
            })
          }));
        }
      },
      
      updateWidget(key, cfg){
        var vm = this,
            idx = bbn.fn.search(vm.widgets, "key", key),
            params = {id: key, cfg: cfg},
            no_save = ['items', 'num', 'start'];
        if ( idx > -1 ){
          $.each(no_save, function(i, a){
            if ( cfg[a] !== undefined ){
              vm.$set(vm.source[idx], a, cfg[a]);
              delete params.cfg[a];
            }
          });
          for ( var n in params.cfg ){
            if ( params.cfg[n] === vm.source[idx][n] ){
              delete params.cfg[n];
            }
          }
          if ( bbn.fn.countProperties(params.cfg) ){
            if ( vm.url ){
              return bbn.fn.post(vm.url + 'save', params).then((d) => {
                if ( d.success ){
                  for ( var n in params.cfg ){
                    vm.$set(vm.source[idx], n, params.cfg[n]);
                  }
                }
              })
            }
            else{

              let resolvedProm = Promise.resolve('ok');
              return resolvedProm.then(()=>{
                vm.$set(vm.source[idx], n, params.cfg[n]);
              })
            }
          }
        }
        bbn.fn.log(cfg);
        new Error("No corresponding widget found for key " + key);
      }
    },

    mounted: function(){
      var vm = this;
      vm.paint();
      vm.updateMenu();
    },

    updated: function(){
      var vm = this;
      vm.paint();
    }
  });

})(jQuery, bbn, kendo);
