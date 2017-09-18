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
    computed: {
      widgets: function(){
        return $.map(this.source, (a) => {
          a.hidden = !!a.hidden;
          if ( !a.key ){
            a.key = a.uid ? a.uid : this.makeId();
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
        let idx = bbn.fn.search(this.source, "key", key);
        bbn.fn.log("FOUND?");
        if ( idx > -1 ){
          bbn.fn.log("FOUND", key, idx, this.source[idx]);
          this.updateWidget(key, {
            hidden: typeof(hidden) !== "boolean" ? hidden : !!this.source[idx].hidden
          });
        }
      },
      
      makeId: function(){
        return bbn.fn.randomString(15, 20);
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
                if ( newIdx !== oldIdx ){
                  if ( this.url ){
                    try {
                      let tmp = this.$children[oldIdx].$vnode.data.key;
                      bbn.fn.post(this.url + 'move', {
                        id: tmp,
                        index: newIdx
                      }, (d) => {
                        if ( d.success ){
                          this.isRefreshing = true;
                          this.$nextTick(() => {
                            this.isRefreshing = false;
                          })
                        }
                        else{
                          $ele.sortable("cancel");
                        }
                      });
                    }
                    catch (e){
                      new Error(bbn._("Impossible to find the idea"));
                    }
                  }
                  else{
                    bbn.fn.move(this.source, oldIdx, newIdx);
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
              tab.deleteMenu(a);
            });
          }
          this.menu = [];
          this.menu.push(tab.addMenu({
            text: bbn._("Widgets"),
            mode: 'options',
            items: $.map(this.source, (a) => {
              return {
                disabled: !a.closable,
                selected: !a.hidden,
                text: a.text,
                click: (e, idx, obj) => {
                  bbn.fn.log("OK1");
                  if ( this.source[idx] && (this.source[idx].closable !== false) ){
                    bbn.fn.log("OK2");
                    let key = a.key,
                        obj = bbn.vue.getChildByKey(this, key, '.bbn-widget');
                    if ( obj ){
                      //obj.close();
                    }
                    this.toggleWidget(e, key, this.source[idx].hidden ? false : true);
                  }
                }
              }
            })
          }));
        }
      },
      
      updateWidget(key, cfg){
        /*
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
                  vm.$forceUpdate();
                }
              })
            }
            else{
              let resolvedProm = Promise.resolve('ok');
              return resolvedProm.then(()=>{
                vm.$set(vm.source[idx], n, params.cfg[n]);
                vm.$forceUpdate();
              })
            }
          }
        }
        bbn.fn.log(cfg);
        new Error("No corresponding widget found for key " + key);
        */
      },

      resizeScroll(){
        if ( this.$refs.scroll ){
          this.$refs.scroll.$emit('resize')
        }
      }
    },

    mounted(){
      this.paint();
      this.updateMenu();
      setTimeout(() => {
        this.resizeScroll()
      }, 500);
      //vm.emitInputMenu();
    },

    updated(){
      this.paint();
    }
  });

})(jQuery, bbn);
