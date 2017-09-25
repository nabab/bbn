/**
 * Created by BBN on 15/02/2017.
 */
(function($, bbn){
  "use strict";

  /**
   * Classic input with normalized appearance
   */
  Vue.component('bbn-dashboard', {
    mixins: [bbn.vue.resizerComponent],
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
    data: function(){
      let order = [],
          widgets = $.map(this.source, (a) => {
            a.hidden = !!a.hidden;
            if ( !a.key ){
              a.key = a.uid ? a.uid : bbn.vue.makeUID();
            }
            order.push(a.key);
            return a;
          });
      return {
        menu: [],
        isRefreshing: false,
        widgets: widgets,
        order: order
      };
    },
    methods: {
      getWidget(key){
        let idx = bbn.fn.search(this.widgets, {key: key});
        if ( idx > -1 ){
          return bbn.vue.closest(this, ".bbn-tab");
        }
      },
      hideWidget: function(key){
        return this.toggleWidget(key, true);
      },
      showWidget: function(key){
        return this.toggleWidget(key, false);
      },
      toggleWidget: function(key, hidden){
        let idx = bbn.fn.search(this.widgets, {key: key});
        if ( idx > -1 ){
          this.updateWidget(key, {
            hidden: hidden === undefined ? !this.widgets[idx].hidden : hidden
          }).then(() => {
            this.updateMenu();
          });
        }
      },

      paint: function(){
        let $ele = $(this.$refs.container),
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
        if ( this.sortable && !$ele.hasClass("ui-sortable") ){
          let oldIdx = false;
          $ele.sortable({
            placeholder: "bbn-widget bbn-bg-grey bbn-widget-placeholder",
            opacity: 0.5,
            forcePlaceholderSize: true,
            handle: "div.k-header h4",
            start: (e, ui) => {
              oldIdx = ui.item.index(".bbn-widget:not(.bbn-widget-placeholder)");
            },
            stop: (e, ui) => {
              if ( oldIdx !== false ){
                let newIdx = ui.item.index(".bbn-widget:not(.bbn-widget-placeholder)");
                bbn.fn.log(newIdx, oldIdx);
                if ( this.widgets[oldIdx].key && (newIdx !== oldIdx) ){
                  if ( this.url ){
                    try {
                      bbn.fn.post(this.url + 'move', {
                        id: this.widgets[oldIdx].key,
                        index: newIdx
                      }, (d) => {
                        if ( d.success ){
                          bbn.fn.move(this.widgets, oldIdx, newIdx);
                        }
                        else{
                          $ele.sortable("cancel");
                        }
                      });
                    }
                    catch (e){
                      throw new Error(bbn._("Impossible to find the idea"));
                    }
                  }
                  else{
                    bbn.fn.move(this.widgets, oldIdx, newIdx);
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
      
      updateMenu(){
        let tab = bbn.vue.closest(this, ".bbn-tab");
        if ( tab ){
          if ( this.menu.length ){
            $.each(this.menu, function(i, a){
              bbn.fn.info("REMOVIUNG MENU", i, a);
              tab.deleteMenu(a);
            });
          }
          this.menu = [];
          let items = [];
          $.each(this.widgets, (i, a) => {
            items.push({
              disabled: !a.closable,
              selected: !a.hidden,
              text: a.text,
              click: (e, idx) => {
                if ( a.closable !== false ){
                  this.toggleWidget(a.key);
                }
              }
            })
          });
          this.menu.push(tab.addMenu({
            text: bbn._("Widgets"),
            mode: 'options',
            // We keep the original source order
            items: items
          }));
        }
      },
      
      updateWidget(key, cfg){
        bbn.fn.info(JSON.stringify(cfg));
        let vm = this,
            idx = bbn.fn.search(vm.widgets, "key", key),
            params = {id: key, cfg: cfg},
            no_save = ['items', 'num', 'start'];
        bbn.fn.log("updateWidget");
        if ( idx > -1 ){
          bbn.fn.log("IDX OK", JSON.stringify(params));
          $.each(no_save, function(i, a){
            if ( cfg[a] !== undefined ){
              bbn.fn.log("DELETING " + a);
              delete params.cfg[a];
            }
          });

          if ( bbn.fn.countProperties(params.cfg) ){
            bbn.fn.log("PROP OK");
            if ( vm.url ){
              bbn.fn.log("URL OK");
              return bbn.fn.post(vm.url + 'save', params).then((d) => {
                if ( d.success ){
                  for ( var n in params.cfg ){
                    vm.$set(vm.widgets[idx], n, params.cfg[n]);
                  }
                  vm.$forceUpdate();
                }
              })
            }
            else{
              bbn.fn.log("PROMISE");
              let resolvedProm = Promise.resolve('ok');
              return resolvedProm.then(()=>{
                vm.$set(vm.widgets[idx], n, params.cfg[n]);
                vm.$forceUpdate();
              })
            }
          }
        }
        bbn.fn.log(cfg);
        new Error("No corresponding widget found for key " + key);
      },

      resizeScroll(){
        if ( this.$refs.scroll ){
          this.$refs.scroll.onResize()
        }
      }
    },

    mounted(){
      this.paint();
      this.updateMenu();
    },

    updated(){
      this.paint();
      bbn.fn.log("from dashboard");
      this.resizeScroll()
    },

    watch: {
      widgets: {
        deep: true,
        handler(){
          bbn.fn.info("CHANGE WIDGET");
        }
      }
    }
  });

})(jQuery, bbn);
