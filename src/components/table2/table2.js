/**
 * Created by BBN on 14/02/2017.
 */
(function($, bbn){
  "use strict";

  /**
   * Classic input with normalized appearance
   */
  Vue.component('bbn-table2', {
    template: '#bbn-tpl-component-table2',
    mixins: [bbn.vue.resizerComponent],
    props: {
      limit: {
        type: Number,
        default: 25
      },
      pagination: {
        type: Boolean,
        default: false
      },
      paginationType: {
        type: String,
        default: 'input'
      },
      info: {
        type: Boolean,
        default: false
      },
      search: {
        type: Boolean,
        default: false
      },
      currency: {
        type: String
      },
      url: {
        type: String
      },
      trClass: {
        type: [String,Function]
      },
      component: {
        type: [String, Boolean],
        default: false
      },
      fixedLeft: 0,
      fixedRight: 0,
      toolbar: {},
      source: {
        type: [Array, String],
        default(){
          return [];
        }
      },
      columns: {
        type: Array,
        default: function(){
          return [];
        }
      },
      cfg: {
        type: Object,
        default: function(){
          return {
            columns: [],
            take: 50,
            skip: 0,
            currency: ''
          };
        }
      },
      edit: {
        type: Function
      }
    },
    data: function(){
      return $.extend({
        currentData: [],
        limits: [10, 25, 50, 100, 250, 500],
        start: 0,
        total: 0,
        buttonCls: 'bbn-table-command-',
        buttonDone: 'bbn-table-button',
        selectDone: 'bbn-table-select',
        widgetName: "DataTable",
        toolbarDone: [],
        tmpRow: false,
        originalRow: false,
        editedRow: false,
        editedTr: false,
        cols: [],
        table: false,
        isLoading: false,
        isAjax: typeof this.source === 'string',
        xScrollNumber: 1,
        yScrollNumber: 1,
        currentLimit: this.limit

      }, bbn.vue.treatData(this));
    },
    computed: {
      numPages(){
        return Math.ceil(this.total/this.currentLimit);
      },
      currentPage: {
        get(){
          return Math.ceil((this.start+1)/this.currentLimit);
        },
        set(val){
          this.start = val > 1 ? (val-1) * this.currentLimit : 0;
          this.updateData();
        }
      }
    },
    methods: {
      /** i18n */
      _: bbn._,

      /** Refresh the current data set */
      updateData(){
        if ( this.isAjax ){
          this.isLoading = true;
          this.$forceUpdate();
          this.$nextTick(() => {
            bbn.fn.post(this.source, {
              length: this.currentLimit,
              limit: this.currentLimit,
              start: this.start
            }, (result) => {
              this.isLoading = false;
              if ( !result || result.error ){
                alert(result || "Error in updateData")
              }
              else{
                this.currentData = result.data || [];
                this.total = result.total || 0;
                this.$forceUpdate();
              }
            })
          })
        }
        else if ( $.isArray(this.source) ){
          this.currentData = this.source;
          this.total = this.source.length;
          this.$forceUpdate();
        }
      },

      /** Renders a column according to config */
      render(data, column, index){
        let field = column && column.field ? column.field : '',
            value = data && column.field ? data[column.field] || '' : '';

        if ( column.source ){
          if ( value ){
            if ( $.isArray(column.source) ){
              return bbn.fn.get_field(column.source, 'value', value, 'text');
            }
            else if ( column.source[value] !== undefined ){
              return column.source[value];
            }
            return bbn._("<em>?</em>");
          }
          return "<em>-</em>";
        }
        else if ( column.type ){
          switch ( column.type ){
            case "date":
              if ( column.format ){
                return value ? (new moment(value)).format(a.format) : '-';
              }
              else{
                return value ? bbn.fn.fdate(value) : '-';
              }
            case "email":
              return value ? '<a href="mailto:' + value + '">' + value + '</a>' : '-';
            case "url":
              return value ? '<a href="' + value + '">' + value + '</a>' : '-';
            case "number":
              return value ? kendo.toString(parseInt(value), "n0") + ( a.unit || this.unit ? " " + ( column.unit || this.unit ) : "") : '-';
            case "money":
              return data ?
                bbn.fn.money(data) + (
                  column.unit || this.currency ?
                    " " + ( column.unit || this.currency )
                    : ""
                )
                : '-';
            case "bool":
            case "boolean":
              return value && (value !== 'false') && (value !== '0') ? bbn._("Yes") : bbn._("No");
          }
        }
        else if ( column.render ){
          return column.render(value, index, data)
        }
        return value;
      },

      /** Returns header's CSS object */
      headStyles(col){
        let css = {
          width: this.getWidth(col.width)
        };
        if ( col.hidden ){
          css.display = 'none';
        }
        return css;
      },

      /** Returns body's CSS object */
      bodyStyles(col){
        return {};
      },

      /** @todo */
      getRow(where){
        var vm = this,
            retrieved = false,
            res = this.widget.row(function(idx, data, tr){
              if ( !retrieved ){
                var cfg = {
                  idx: idx,
                  data: data,
                  tr: tr
                };
                switch ( typeof(where) ){
                  case 'object':
                    // DOM Element
                    if ( bbn.fn.isDom(where) ){
                      if ( tr === where ){
                        retrieved = cfg;
                        return true;
                      }
                      return false;
                    }
                    else{
                      var ok = true;
                      for ( var n in where ){
                        if ( where[n] !== data[n] ){
                          ok = false;
                        }
                      }
                      if ( ok ){
                        retrieved = cfg;
                      }
                      return ok;
                    }
                    break;

                  case 'number':
                    if ( where === idx ){
                      retrieved = cfg;
                      return true;
                    }
                    return false;
                    break;
                }
              }
            });
        if ( retrieved ){
          retrieved.obj = res;
        }
        return retrieved;
      },

      /** @todo */
      defaultDataSet(data){
        let res = [];
        $.each(this.cols, function(i, a){
          if ( a.field ){
            res.push(data[a.field] !== undefined ? data[a.field] : (a.default !== undefined ? a.default : ''));
          }
          else{
            res.push('');
          }
        });
        return res;
      },

      /** @todo */
      defaultRow(data){
        let data2 = this.defaultDataSet(data);
        let res = '<tr role="row">';
        if ( data2 ){
          $.each(data2, (i, v) => {
            res += '<td>' + v + '</td>';
          })
        }
        res += '</tr>';
        bbn.fn.log(data2, res);
        return res;
      },

      /** @todo */
      add(data){
        this.widget.rows().add([data]);
        this.widget.draw();
      },

      /** @todo */
      update(where, data, update){
        bbn.fn.log(where);
        var res = this.getRow(where);
        bbn.fn.log("UPDATE", res, where);
        if ( res ){
          if ( update ){
            data = $.extend({}, res.obj.data(), data);
          }
          res.obj.data(data);
        }
      },

      /** @todo */
      editRow(where){
        let vm = this,
            row = vm.getRow(where);
        bbn.fn.log("editRow");
        if ( row ){
          vm.editedRow = row.data;
        }
      },

      /** @todo */
      remove(where){
        var vm = this,
            res = this.getRow(where);
        if ( res ){
          res.obj.remove();
          vm.widget.draw();
        }
      },

      /** @todo */
      addTmp(data){
        var vm = this;
        if ( vm.tmpRow ){
          vm.removeTmp();
        }
        let row = $(this.defaultRow());
        bbn.fn.log(row, vm.table, vm.table.find("tbody:first"));
        vm.table.find("tbody:first").prepend(row);
        //vm.tmpRow = vm.widget.rows.add([vm.defaultDataSet(data)]);
        //vm.widget.draw();
      },

      /** @todo */
      removeTmp(){
        var vm = this;
        if ( vm.tmpRow ){
          vm.tmpRow.remove();
          vm.tmpRow = false;
          vm.widget.draw();
        }
      },

      /** @todo */
      editTmp(data, update){
        if ( this.tmpRow ){
          if ( update ){
            data = $.extend({}, this.tmpRow.data(), data);
          }
          this.tmpRow.data(data);
        }
      },

      /** @todo */
      getWidth(w){
        if ( typeof(w) === 'number' ){
          return w + 'px';
        }
        if ( bbn.fn.isDimension(w) ){
          return w;
        }
        return 'auto';

      },

      /** @todo */
      getColumns(){
        const vm = this;
        var res = [],
            /**
             * Number of fixed columns on the left
             * @type {number}
             *
             */
            fixedLeft = 0,
            /**
             * Number of fixed columns on the right
             * @type {number}
             */
            fixedRight = 0,
            /**
             * When false, will stop to look for fixed left columns
             * @type {boolean}
             */
            fixed = true;
        $.each(vm.cols, function(i, a){
          bbn.fn.log(a);

          if ( a.fixed && fixed ){
            fixedLeft++;
          }
          else if ( !a.fixed && fixed ){
            fixed = false;
          }
          else if ( a.fixed ){
            fixedRight++;
          }
          else if ( fixedRight ){
            fixedRight = 0;
          }
          var r = {
            data: a.field
          };
          if ( a.hidden ){
            r.visible = false;
          }
          if ( a.cls ){
            r.className = a.cls;
          }
          if ( a.title ){
            r.title = a.title;
          }
          if ( a.width ){
            r.width = typeof(a.width) === 'number' ? a.width + 'px' : a.width;
          }
          if ( a.source ){
            let obj = a.source;
            /** @todo Remove this case now that we have reactive properties */
            if ( typeof(a.source) === 'string' ){
              let v = vm;
              while ( v ){
                if ( v[a.source] !== undefined ){
                  obj = v;
                  break;
                }
                else{
                  v = v.$parent;
                }
              }
            }
            if ( obj ){
              r.render = function(data, type, row){
                if ( data ){
                  if ( $.isArray(obj[a.source]) ){
                    return bbn.fn.get_field(obj[a.source], 'value', data, 'text');
                  }
                  else if ( obj[a.source][data] !== undefined ){
                    return obj[a.source][data];
                  }
                  return bbn._("<em>?</em>");
                }
                return "<em>-</em>";
              }
            }
          }
          else if ( a.type ){
            switch ( a.type ){
              case "date":
                if ( a.format ){
                  r.render = function(data){
                    return data ? (new moment(data)).format(a.format) : '-';
                  };
                }
                r.render = function(data){
                  return data ? bbn.fn.fdate(data) : '-';
                };
                break;
              case "email":
                r.render = function(data, type, row){
                  return data ? '<a href="mailto:' + data + '">' + data + '</a>' : '-';
                };
                break;
              case "url":
                r.render = function(data, type, row){
                  return data ? '<a href="' + data + '">' + data + '</a>' : '-';
                };
                break;
              case "number":
                r.render = function(data, type, row){
                  return data ? kendo.toString(parseInt(data), "n0") + ( a.unit || vm.unit ? " " + ( a.unit || vm.unit ) : "") : '-';
                };
                break;
              case "money":
                r.render = function(data, type, row){
                  return data ?
                    bbn.fn.money(data) + (
                      a.unit || vm.currency ?
                        " " + ( a.unit || vm.currency )
                        : ""
                    )
                    : '-';
                };
                break;
              case "bool":
              case "boolean":
                r.render = function(data, type, row){
                  return data && (data !== 'false') && (data !== '0') ? bbn._("Yes") : bbn._("No");
                };
                break;
            }
          }
          else if ( a.buttons ){
            let buttons = a.buttons;
            if ( typeof(a.buttons) === 'string' ){
              try{
                buttons = eval(a.buttons);
              }
              catch ( e ){
                bbn.fn.log("Error parsing buttons", a.buttons, e);
              }
            }

            if ( $.isArray(buttons) ){
              r.render = function (data, field, row){
                return vm.buttons2String(buttons, field || '', row);
              };
            }
            else if ( $.isFunction(buttons) ){
              r.render = function (data, field, row){
                return vm.buttons2String(buttons(data, field, row), field || '', row);
              };
            }
          }
          else if ( a.render ){
            if ( $.isFunction(a.render) ){
              r.render = a.render;
            }
            else{
              var v = vm;
              while ( v ){
                if ( v[a.render] && $.isFunction(v[a.render]) ){
                  r.render = function(data, type, row){
                    return v[a.render](data, a.field, row);
                  };
                  break;
                }
                else{
                  v = v.$parent;
                }
              }
            }
            if ( !r.render ){
              r.render = function(data, type, row){
                var tmp = '(function(',
                    i = 0,
                    num = bbn.fn.countProperties(row);
                for ( var n in row ){
                  tmp += n;
                  i++;
                  if ( i !== num ){
                    tmp += ', ';
                  }
                  else{
                    tmp += '){ return (' + a.render + '); })(';
                    i = 0;
                    for ( var n in row ){
                      if ( typeof(row[n]) === 'string' ){
                        tmp += '"' + row[n].replace(/\"/g, '\\"') + '"';
                      }
                      else if ( typeof(row[n]) === "object" ){
                        tmp += JSON.stringify(row[n]);
                      }
                      else if ( row[n] === null ){
                        tmp += 'null'
                      }
                      else if ( row[n] === true ){
                        tmp += 'true'
                      }
                      else if ( row[n] === false ){
                        tmp += 'false'
                      }
                      else if ( row[n] === 0 ){
                        tmp += '0';
                      }
                      else{
                        tmp += row[n];
                      }
                      i++;

                      if ( i !== num ){
                        tmp += ', ';
                      }
                      else{
                        tmp += ');';
                      }
                    }
                    bbn.fn.log(tmp);
                  }
                }
                //bbn.fn.log(tmp);
                return eval(tmp);
              }
            }
          }
          res.push(r);
        });
        return res;
      },

      /** @todo */
      rowCallback(row, data, dataIndex){
        const vm = this;
        if ( vm.$options.propsData.trClass ){
          bbn.fn.log("trClass : ", vm.$options.propsData.trClass);
          if ( $.isFunction(vm.$options.propsData.trClass) ){
            var cls = vm.$options.propsData.trClass(data);
            if ( cls ){
              $(row).addClass(cls);
            }
          }
          else{
            $(row).addClass(vm.$options.propsData.trClass);
          }
        }
        if ( vm.$options.propsData.trCSS ){
          bbn.fn.log("trStyle : ", vm.$options.propsData.trCSS);
          if ( $.isFunction(vm.$options.propsData.trCSS) ){
            var cls = vm.$options.propsData.trCSS(data);
            if ( cls && (cls instanceof Object) ){
              $(row).css(cls);
            }
          }
          else if ( vm.$options.propsData.trCSS instanceof Object ){
            $(row).css(vm.$options.propsData.trCSS);
          }
        }
        vm.$nextTick(() => {
          new Vue({
            el: row,
            data: data,
            methods: {
              command(fn, field){
                bbn.fn.log(arguments);
                let vm0 = vm;
                while ( vm0 ){
                  if ( vm0 && vm0.$el && $.isFunction(vm0[fn]) ){
                    return vm0[fn](field ? data[field] : '', dataIndex, data);
                  }
                  vm0 = vm0.$parent;
                }
                throw new Error("Impossible to find the command " + fn)
              }
            },
            mounted(){
              const vm2 = this;
              vm.$nextTick(() => {
              })
            }
          });
        });
      },

      /** @todo */
      getFixedColumns(columns){
        const vm = this;
        var res = {},
          /**
           * Number of fixed columns on the left
           * @type {number}
           *
           */
          fixedLeft = 0,
          /**
           * Number of fixed columns on the right
           * @type {number}
           */
          fixedRight = 0;

        for ( var i = 0; i < columns.length; i++ ){
          if ( columns[i].fixed ){
            fixedLeft++;
          }
          else{
            break;
          }
        }
        for ( var i = columns.length - 1; i >= 0; i-- ){
          if ( columns[i].fixed ){
            fixedRight++;
          }
          else{
            break;
          }
        }
        if ( fixedLeft ){
          res.fixedColumns = {
            leftColumns: fixedLeft
          }
        }
        if ( fixedRight ){
          if ( !res.fixedColumns ){
            res.fixedColumns = {};
          }
          res.fixedColumns.rightColumns = fixedRight;
        }
        return res;
      },

      /** @todo */
      setHeight(){
        const vm = this;
        // Height calculation
        var $ele = $(vm.$el),
            h = $ele.height();
        $ele.children().height(h).children(".fg-toolbar").each(function(){
          $(this).find("select:visible:not('." + vm.selectDone +"')")
            .addClass(vm.selectDone)
            .kendoDropDownList();
          h -= $(this).outerHeight(true) || 0;
        });
        h -= ($ele.find(".dataTables_scrollHead:first").outerHeight(true) || 0);
        h -= ($ele.find(".dataTables_scrollFoot:first").outerHeight(true) || 0);
        h = Math.round(h);
        bbn.fn.log("H", h, ($ele.find(".dataTables_scrollHead:first").outerHeight(true) || 0), ($ele.find(".dataTables_scrollFoot:first").outerHeight(true) || 0));
        $(".dataTables_scrollBody", vm.$el)
        //.add($(settings.nScrollBody).siblings())
          .height(h)
          .css({maxHeight: h + "px"});
      },

      /** @todo */
      getConfig(){
        var
          /**
           * @type {bbn-table}
           */
          vm = this,
          /**
           * @type {HTMLElement}
           */
          $ele = $(this.$el),
          /**
           * Columns configuration
           * @type {[]}
           */
          columns = vm.getColumns();
        /**
         * The widget configuration
         * @type {{pageLength, asStripeClasses: [*], scrollY: number, scrollX: boolean, scrollCollapse: boolean, drawCallback: drawCallback}}
         */
        var cfg = {
          info: vm.info,
          paging: vm.pagination,
          searching: vm.search,
          /** @property Number of records to show */
          pageLength: this.cfg.take || 25,
          //lengthChange: false,
          /** @property Classes added on columns */
          asStripeClasses: ["", "k-alt"],
          /** @property The height of the table's body */
          scrollY: 300,
          /** @property  */
          deferRender: true,
          /** @property Do not expand cells to the whole table's height */
          scrollCollapse: true,
          /** @property Resize and restyle functions after draw */
          drawCallback: function(settings){
            // Be sure all is drawn
            // We need to resize the table to fit the container
            // Kendo styling
            $ele.find(".dataTables_filter input").addClass("k-textbox");
            $ele.find(".DTFC_Blocker:first").addClass("k-header");
            // Toolbar
            if ( vm.$options.propsData.toolbar ){
              var tb = vm.$options.propsData.toolbar,
                  tbEle = $ele.find(".fg-toolbar:first");
              if ( !$.isArray(tb) && (typeof(tb) === 'object') ){
                tb = [tb];
              }
              if ( $.isArray(tb) ){
                var target = $('<div class="bbn-table-toolbar"/>').prependTo(tbEle);
                $.each(tb, function(i, a){
                  var tmp = JSON.stringify(a);
                  if ( ($.inArray(tmp, vm.toolbarDone) === -1) && a.text && a.click ){
                    vm.toolbarDone.push(tmp);
                    target.append(
                      $('<button class="k-button"' + (a.disabled ? ' disabled="disabled"' : '' ) + '>' +
                        ( a.icon ? '<i class="' + a.icon + '" title="' + a.text + '"></i> &nbsp; ' : '' ) +
                        ( a.notext ? '' : a.text ) +
                        '</button>').click(function(){
                        if ( $.isFunction(a.click) ){
                          return a.click(vm);
                        }
                        else if ( typeof(a.click) === 'string' ){
                          if ( $.isFunction(vm.$parent[a.click]) ){
                            return vm.$parent[a.click](vm);
                          }
                          // Otherwise we check if there is a default function defined by the component
                          else if ( $.isFunction(vm[a.click]) ){
                            return vm[a.click](vm);
                          }
                        }
                      })
                    )
                  }
                })
              }
            }

            vm.setHeight(settings);
          }
        };
        if ( vm.pagination ){
          cfg.pageLength = vm.limit;
        }
        if ( vm.$options.propsData.source ){
          if ( typeof(vm.$options.propsData.source) === 'string' ){
            cfg.processing = true;
            cfg.serverSide =  true;
            cfg.ajax = {
              url: vm.$options.propsData.source,
              type: "POST"
            };
          }
          else if ( $.isArray(vm.$options.propsData.source) ){
            cfg.data = JSON.parse(JSON.stringify(vm.$options.propsData.source));
          }
          else if ( (typeof vm.$options.propsData.source === 'object') && $.isArray(vm.$options.propsData.source.data) ){
            cfg.data = JSON.parse(JSON.stringify(vm.$options.propsData.source.data));
          }
        }
        if ( vm.$options.propsData.toolbar === false ){
          cfg.sDom = "t";
        }
        if ( vm.$options.propsData.xscroll ){
          cfg.scrollX = true;
        }
        if ( columns.length ){
          cfg.columns = columns;
        }
        // Fixed columns
        $.extend(cfg, vm.getFixedColumns(columns));
        cfg.rowCallback = vm.rowCallback;
        return cfg;
      },

      /** @todo */
      addColumn(obj){
        const vm = this;
        vm.cols.push(obj);
      },

      /** @todo */
      updateScrollbars(){
        if ( !this.cols.length ){
          this.xScrollNumber = 1;
        }
        else{
          let total = this.$refs.xScroller.scrollWidth,
              visiblePart = this.$refs.xScrollbarContainer.clientWidth,
              scrollSize = Math.round(visiblePart / total * visiblePart);
          this.$refs.xScrollbar.style.width = scrollSize + 'px';
          this.xScrollNumber = Math.ceil((total - visiblePart)*100 / (visiblePart - scrollSize)) / 100;
        }
        if ( !this.total ){
          this.yScrollNumber = 1;
        }
        else{
          let total = this.$refs.yScroller.scrollHeight,
              visiblePart = this.$refs.yScrollbarContainer.clientHeight,
              scrollSize = Math.round(visiblePart / total * visiblePart);
          this.$refs.yScrollbar.style.height = scrollSize + 'px';
          this.yScrollNumber = Math.ceil((total - visiblePart)*100 / (visiblePart - scrollSize)) / 100;
        }
      },

      /** @todo */
      scrollX(){
        let pos = parseInt(this.$refs.xScrollbar.style.left);
        bbn.fn.log(pos);
        this.$refs.xScroller.scrollLeft = pos ? Math.round(pos * this.xScrollNumber) : 0;
      },

      /** @todo */
      scrollY(){
        let pos = parseInt(this.$refs.yScrollbar.style.top);
        bbn.fn.log(pos);
        this.$refs.yScroller.scrollTop = pos ? Math.round(pos * this.yScrollNumber) : 0;
      },

      /** @todo */
      scroll(){
        bbn.fn.log("SCROLLING...");
      },

      onResize(){
        this.updateScrollbars();
      },
    },

    created(){
      var vm = this;
      // Adding bbn-column from the slot
      if (vm.$slots.default){
        for ( var node of this.$slots.default ){
          //bbn.fn.log("TRYING TO ADD COLUMN", node);
          if (
            node.componentOptions &&
            (node.componentOptions.tag === 'bbn-column')
          ){
            //bbn.fn.log("ADDING COLUMN", node.componentOptions.propsData)
            vm.addColumn(node.componentOptions.propsData);
          }
          else if ( (node.tag === 'bbn-column') && node.data && node.data.attrs ){
            //bbn.fn.log("ADDING COLUMN 2", node.data.attrs)
            vm.addColumn(node.data.attrs);
          }

        }
      }
    },

    mounted(){
      this.$nextTick(() => {
        this.updateData();
        $(this.$refs.xScrollbar).draggable({
          axis: 'x',
          start: this.updateScrollbars,
          drag: this.scrollX,
          containment: 'parent'
        });
        $(this.$refs.yScrollbar).draggable({
          axis: 'y',
          start: this.updateScrollbars,
          drag: this.scrollY,
          containment: 'parent'
        });
        $(this.$refs.yScroller).width($(this.$refs.yScroller).prev().width());
        /*
        $(this.$el)
          .find(".bbn-table-main:first")
          .mCustomScrollbar({axis: "y"});
        $(this.$el)
          .children(".bbn-table-scroller")
          .mCustomScrollbar({axis: "x"});
          */

      })
    },
    watch: {
      editedRow: {
        deep: true,
        handler(newVal, oldVal){
          bbn.fn.log("editedRow is changing", newVal);
        }
      },
      currentData(){
        this.$nextTick(() => {
          this.updateScrollbars();
          /*
          $(this.$el)
            .children(".bbn-table-scroller")
            .mCustomScrollbar("update");
          $(this.$el)
            .find(".bbn-table-main:first")
            .mCustomScrollbar("update");
            */
        })
      }
    }
  });

})(jQuery, bbn);
