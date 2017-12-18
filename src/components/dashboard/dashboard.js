/**
 * Created by BBN on 15/02/2017.
 */
(function($, bbn){
  "use strict";

  var limits = [5, 10, 15, 20, 25, 30, 40, 50];
  /**
   * Classic input with normalized appearance
   */
  Vue.component('bbn-dashboard', {
    mixins: [bbn.vue.basicComponent, bbn.vue.resizerComponent, bbn.vue.localStorageComponent],
    props: {
      components: {
        type: Object,
        default(){
          return {};
        }
      },
      selectable: {
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
      source: {},
      url: {},
      loadedConfig: {
        type: Object
      },

      cfg: {
        type: Object,
        default(){
          return {
            sortable: true,
            url: false,
            source: [],
            components: {}
          };
        }
      }
    },
    data(){
      return {
        originalSource: [],
        menu: [],
        isRefreshing: false,
        widgets: [],
        order: [],
        hidden: []
      };
    },
    methods: {
      setConfig(uid, config){
        this.setStorage({
          order: this.order,
          hidden: this.hidden
        });
      },
      getWidget(key){
        let idx = bbn.fn.search(this.widgets, {key: key});
        if ( idx > -1 ){
          return bbn.vue.closest(this, ".bbn-tab");
        }
      },
      hideWidget(key){
        return this.toggleWidget(key, true);
      },
      showWidget(key){
        return this.toggleWidget(key, false);
      },
      toggleWidget(key, hidden){
        let idx = bbn.fn.search(this.widgets, {key: key});
        this.updateWidget(key, {
          hidden: hidden === undefined ? !this.widgets[idx].hidden : hidden
        }).then(() => {
          this.updateMenu();
        });
      },

      onResize(){
        let $ele = $(".bbn-masonry:first", this.$el),
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
              oldIdx = ui.item.index();
            },
            stop: (e, ui) => {
              if ( oldIdx > -1 ){
                let newIdx = ui.item.index();
                if ( this.widgets[oldIdx] && this.widgets[oldIdx].key && (newIdx !== oldIdx) ){
                  if ( this.url ){
                    try {
                      bbn.fn.post(this.url + 'move', {
                        id: this.widgets[oldIdx].key,
                        index: newIdx
                      }, (d) => {
                        if ( d.success ){
                          this.move(oldIdx, newIdx);
                        }
                        else{
                          $ele.sortable("cancel");
                        }
                      });
                    }
                    catch (e){
                      throw new Error(bbn._("Impossible to find the index"));
                    }
                  }
                  else{
                    this.move(oldIdx, newIdx);
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

      moveWidgets(oldIdx, newIdx){
        bbn.fn.move(this.widgets, oldIdx, newIdx);
        $.each(this.widgets, (i, a) => {
          if ( i !== a.index ){
            this.$set(this.widgets[i], "index", i);
          }
        });
      },

      move(oldIdx, newIdx){
        $.each(this.widgets, (i, a) => {
          if ( a.hidden ){
            if ( i <= oldIdx ){
              oldIdx++;
            }
            if ( i <= newIdx ){
              newIdx++;
            }
            if ( (i > oldIdx) && (i > newIdx) ){
              return false;
            }
          }
        });
        bbn.fn.move(this.widgets, oldIdx, newIdx);
        $.each(this.widgets, (i, a) => {
          if ( i !== a.index ){
            this.updateWidget(this.widgets[i].uid, {index: i});
          }
        });
        if ( this.storageFullName ){
          let cps = bbn.vue.findAll(this.$root, 'bbn-dashboard');
          $.each(cps, (i, cp) => {
            if ( (cp !== this) && (cp.storageFullName === this.storageFullName) ){
              cp.moveWidgets(oldIdx, newIdx);
            }
          })
        }
      },

      updateMenu(){
        let tab = bbn.vue.closest(this, ".bbn-tab");
        if ( tab ){
          if ( this.selectable && this.menu && this.menu.length ){
            $.each(this.menu, function(i, a){
              tab.deleteMenu(a);
            });
          }
          this.menu = [];
          let items = [];
          $.each(this.originalSource, (i, a) => {
            let idx = bbn.fn.search(this.widgets, {uid: a.uid});
            if ( idx > -1 ){
              items.push({
                disabled: !this.closable || (this.widgets[idx].closable === false),
                selected: !this.widgets[idx].hidden,
                text: this.widgets[idx].text ? this.widgets[idx].text : (this.widgets[idx].title ? this.widgets[idx].title : bbn._('Untitled')),
                command: () => {
                  if ( this.widgets[idx].closable !== false ){
                    this.toggleWidget(a.uid);
                  }
                }
              })
            }
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
        let vm = this,
            idx = bbn.fn.search(vm.widgets, "key", key),
            params = {id: key, cfg: cfg},
            no_save = ['items', 'num', 'start'];
        if ( idx > -1 ){
          $.each(no_save, function(i, a){
            if ( cfg[a] !== undefined ){
              delete params.cfg[a];
            }
          });

          if ( bbn.fn.countProperties(params.cfg) ){
            let prom = this.url ? bbn.fn.post(vm.url + 'save', params) : Promise.resolve({success: true});
            return prom.then((d) => {
              if ( d.success ){
                for ( var n in params.cfg ){
                  vm.$set(vm.widgets[idx], n, params.cfg[n]);
                }
                this.setWidgetStorage(idx);
                if ( params.cfg.hidden !== undefined ){
                  this.updateMenu();
                }
                vm.$forceUpdate();
              }
            });
          }
        }
        bbn.fn.log(cfg);
        new Error("No corresponding widget found for key " + key);
      },

      setWidgetStorage(idx){
        this.storage.set(this.widgets[idx].storageFullName, {
          uid: this.widgets[idx].uid,
          hidden: this.widgets[idx].hidden,
          limit: this.widgets[idx].limit,
          index: this.widgets[idx].index
        });
      },

      normalize(obj_orig){
        let obj = $.extend({}, obj_orig);
        obj.hidden = !!obj.hidden;
        if ( !obj.key ){
          obj.key = obj.uid ? obj.uid : bbn.vue.makeUID();
        }
        if ( !obj.uid ){
          obj.uid = obj.key;
        }
        obj.storageFullName = (this.storageFullName ? this.storageFullName : this._getStorageRealName()) + '-' + obj.key;
        return obj;
      },

      add(obj, idx){
        if ( (idx === undefined) || (idx < 0) || (key >= this.widgets.length) ){
          this.order.push(a.key);
          this.widgets.push(obj);
        }
        else{
          this.order.splice(idx, 0, a.key);
          this.widgets.splice(idx, 0, obj);
        }
        return obj;
      },

      resizeScroll(){
        if ( this.$refs.scroll ){
          this.$refs.scroll.onResize()
        }
      }
    },

    created(){

      // Adding bbn-tab from the slot
      if ( this.$slots.default ){
        for ( let node of this.$slots.default ){
          if (
            node &&
            (node.tag === 'bbn-widget')
          ){
            this.originalSource.push(this.normalize(node.data.attrs));
          }
        }
      }
      $.each(this.source, (i, obj) => {
        this.originalSource.push(this.normalize(obj));
      });
      let cfg = [];

      $.each(this.originalSource, (i, obj) => {
        let tmp = this.storage.get(obj.storageFullName);
        if ( tmp ){
          $.extend(this.originalSource[i], tmp);
        }
        else{
          this.originalSource[i].index = 10000+i;
        }
        cfg.push(tmp);
      });
      let widgets = bbn.fn.order(this.originalSource.slice(), "index");
      $.each(widgets, (i, obj) => {
        this.add(obj);
      });
    },

    mounted(){
      this.updateMenu();
    },

    updated(){
      this.selfEmit(true);
    },

    components: {
      'bbn-widget': {
        name: 'bbn-widget',
        mixins: [bbn.vue.localStorageComponent],
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
            type: Number,
            default: 0
          },
          index: {
            type: Number
          },
          hidden: {
            type: Boolean,
            default: false
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
            default: false
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
        data(){
          return {
            isLoading: false,
            dashBoard: bbn.vue.closest(this, "bbn-dashboard"),
            currentItems: this.items,
            currentStart: this.start,
            currentTotal: this.total,
            currentContent: this.content || false,
            lang: {
              close: bbn._("Close")
            }
          };
        },
        computed: {
          currentPage(){
            if ( this.currentTotal > this.limit ){
              return (this.currentStart + this.limit) / this.limit;
            }
            return 0;
          },
          totalPages(){
            if ( this.currentTotal > this.limit ){
              return Math.ceil(this.currentTotal / this.limit);
            }
            return 1;
          },
          hasMenu: function(){
            return !!this.finalMenu.length;
          },
          finalMenu: function(){
            let tmp = this.menu.slice();
            if ( this.url ){
              tmp.unshift({
                text: bbn._("Reload"),
                icon: "fa fa-refresh",
                command: () => {
                  this.reload();
                }
              });
            }
            if ( this.limit ){
              let items = [];
              $.each(limits, (i, a) => {
                items.push({
                  text: a.toString() + " " + bbn._("Items"),
                  selected: a === this.limit,
                  command: () => {
                    this.dashboard.updateWidget(this.uid, {limit: a});
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
            this.dashboard.updateWidget(this.uid, {hidden: !this.hidden});
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
              this.isLoading = true;
              this.$forceUpdate();
              if ( this.limit ){
                params.limit = this.limit;
                params.start = this.currentStart;
              }
              bbn.fn.post(this.url, params, (d) => {
                if ( d.data !== undefined ){
                  this.currentItems = d.data;
                  if ( d.limit && (this.limit !== d.limit) ){
                    this.dashboard.updateWidget(this.uid, {limit: d.limit});
                  }
                  if ( d.start !== undefined ){
                    this.currentStart = d.start;
                  }
                  if ( d.total !== undefined && (this.currentTotal !== d.total) ){
                    this.currentTotal = d.total;
                  }
                }
                this.$nextTick(() => {
                  this.isLoading = false;
                  this.$emit("loaded");
                })
              })
            }
          },
          nav(arg){
            let newStart = false;
            switch ( arg ){
              case 'first':
                newStart = 0;
                break;
              case 'prev':
                newStart = this.currentStart >= this.limit ? this.currentStart - this.limit : 0;
                break;
              case 'next':
                newStart = this.currentStart + this.limit;
                break;
              case 'last':
                newStart = (this.totalPages - 1) * this.limit;
                break;
            }
            if ( (newStart !== false) && (newStart !== this.currentStart) ){
              this.currentStart = newStart;
              this.load();
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
          setConfig(){
            this.dashboard.setConfig(this.uid, {
              uid: this.uid,
              limit: this.limit,
              hidden: this.hidden,
              index: this.index
            });
          }
        },
        mounted(){
          this.load();
        },
        updated(){
          this.dashboard.selfEmit(true);
        },
        watch: {
          limit(newVal){
            this.load();
          },
          hidden(newVal){
            if ( !newVal ){
              this.load();
            }
          }
        }
      }
    }
  });

})(jQuery, bbn);
