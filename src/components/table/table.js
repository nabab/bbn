/**
 * Created by BBN on 14/02/2017.
 */
(function($, bbn){
  "use strict";

  /**
   * Classic input with normalized appearance
   */
  Vue.component('bbn-table', {
    template: '#bbn-tpl-component-table',
    mixins: [bbn.vue.resizerComponent],
    props: {
      limit: {
        type: Number,
        default: 25
      },
      pageable: {
        type: Boolean,
        default: false
      },
      sortable: {
        type: Boolean,
        default: false
      },
      filterable: {
        type: Boolean,
        default: false
      },
      resizable: {
        type: Boolean,
        default: false
      },
      showable: {
        type: Boolean,
        default: false
      },
      groupable: {
        type: Boolean,
        default: false
      },
      serverPaging: {
        type: Boolean,
        default: true
      },
      serverSorting: {
        type: Boolean,
        default: true
      },
      serverFiltering: {
        type: Boolean,
        default: true
      },
      serverGrouping: {
        type: Boolean,
        default: true
      },
      order: {
        type: Object,
        default(){
          return {};
        }
      },
      filter: {
        type: Object,
        default(){
          return {};
        }
      },
      minimumColumnWidth: {
        type: Number,
        default: 25
      },
      defaultColumnWidth: {
        type: Number,
        default: 150
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
        type: [String, Function]
      },
      component: {
        type: [String, Boolean],
        default: false
      },
      expander: {
        type: [Object, Function]
      },
      fixedDefaultSide: {
        type: String,
        default: "left"
      },
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
      edit: {
        type: Function
      },
      groupBy: {
        type: Number
      },
      expanded: {
        type: Array,
        default(){
          return [];
        }
      }
    },
    data: function(){
      return {
        currentData: [],
        group: this.groupBy === undefined ? false : this.groupBy,
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
        cols: this.columns.slice(),
        table: false,
        isLoading: false,
        isAjax: typeof this.source === 'string',
        currentLimit: this.limit,
        currentOrder: this.order,
        tableLeftWidth: 0,
        tableMainWidth: 0,
        tableRightWidth: 0,
        colsLeft: [],
        colsMain: [],
        colsRight: [],
        scrollableContainer: null,
        hiddenScroll: true,
        currentExpanded: []
      };
    },
    computed: {
      numPages(){
        return Math.ceil(this.total/this.currentLimit);
      },
      numVisible(){
        return this.cols.length - bbn.fn.count(this.cols, {hidden: true}) + (this.hasExpander ? 1 : 0);
      },
      numLeftVisible(){
        return this.colsLeft.length - bbn.fn.count(this.colsLeft, {hidden: true});
      },
      numMainVisible(){
        return this.colsMain.length - bbn.fn.count(this.colsMain, {hidden: true});
      },
      numRightVisible(){
        return this.colsRight.length - bbn.fn.count(this.colsRight, {hidden: true});
      },
      scroller:{
        get(){
          return this.$refs.scroller instanceof Vue ? this.$refs.scroller : null;
        },
        set(){
        },
      },
      currentPage: {
        get(){
          return Math.ceil((this.start+1)/this.currentLimit);
        },
        set(val){
          this.start = val > 1 ? (val-1) * this.currentLimit : 0;
          this.updateData();
        }
      },
      currentSet(){
        let res = [],
            isGroup = false,
            currentGroupValue,
            currentLink,
            data = this.currentData.slice(),
            o,
            realIndex = 0,
            end = data.length,
            i = 0;
        if (
          (this.group !== false) &&
          (!this.isAjax  || !this.serverGrouping) &&
          this.cols[this.group] &&
          this.cols[this.group].field
        ){
          isGroup = true;
          if ( !this.currentOrder[this.cols[this.group].field] ){
            data = bbn.fn.order(data, this.cols[this.group].field);
          }
          else{
            data = bbn.fn.multiorder(data, this.currentOrder);
          }
        }
        if ( this.pageable && (!this.isAjax || !this.serverPaging) ){
          i = this.start;
          end = this.start + this.currentLimit > data.length ? data.length : this.start + this.currentLimit;
        }
        while ( i < end ){
          let a = data[i];
          if ( isGroup && (currentGroupValue !== a[this.cols[this.group].field]) ){
            currentGroupValue = a[this.cols[this.group].field];
            res.push({group: true, index: i, value: currentGroupValue, data: a});
            currentLink = i;
            realIndex++;
          }
          o = {index: i, data: a};
          if ( isGroup ){
            o.isGrouped = true;
            o.link = currentLink;
          }
          res.push(o);
          realIndex++;
          if ( this.expander && (
              !$.isFunction(this.expander) ||
              ($.isFunction(this.expander) && this.expander(a))
            )
          ){
            res.push({index: i, expander: true, data: a});
            realIndex++;
          }
          i++;
        }
        return res;
      },
      hasExpander(){
        return this.expander || (this.groupable && (typeof(this.group) === 'number') && this.cols[this.group]);
      },

    },
    methods: {
      /** i18n */
      _: bbn._,

      /** Refresh the current data set */
      updateData(){
        if ( this.isAjax && !this.isLoading ){
          this.isLoading = true;
          this.$forceUpdate();
          this.$nextTick(() => {
            let data = {
              length: this.currentLimit,
              limit: this.currentLimit,
              start: this.start,
            };
            if ( this.sortable ){
              data.order = this.currentOrder;
            }
            bbn.fn.post(this.source, data, (result) => {
              this.isLoading = false;
              if ( !result || result.error ){
                alert(result || "Error in updateData")
              }
              else{
                this.currentData = result.data || [];
                this.total = result.total || result.data.length || 0;
                if ( result.order ){
                  this.currentOrder = {};
                  this.currentOrder[result.order] = (result.dir || '').toUpperCase() === 'DESC' ? 'DESC' : 'ASC';
                }
              }
            })
          })
        }
        else if ( Array.isArray(this.source) ){
          this.currentData = this.source;
          this.total = this.source.length;
        }
      },

      sort(i){
        if (
          !this.isLoading &&
          this.cols[i] &&
          this.sortable &&
          this.cols[i].field &&
          (this.cols[i].sortable !== false)
        ){
          let f = this.cols[i].field;
          if ( this.currentOrder[f] ){
            if ( this.currentOrder[f] === 'ASC' ){
              this.currentOrder[f] = 'DESC';
            }
            else{
              this.currentOrder = {};
            }
          }
          else{
            this.currentOrder = {};
            this.currentOrder[f] = 'ASC';
          }
          this.updateData();
        }
      },

      updateTable(num){
        if ( !num ){
          num = 0;
        }
        if ( !this.isLoading && (num < 25) ){
          let tds = $("table.bbn-table-main:first > tbody > tr > td:first-child", this.$el);
          bbn.fn.log("trying to update table, attempt " + num, tds);
          if (
            (tds.length !== this.currentSet.length) ||
            !this.$refs.scroller
          ){
            setTimeout(() => {
              this.updateTable(++num);
            }, 200)
          }
          else{
            this.$nextTick(() => {
              if ( this.colsLeft.length || this.colsRight.length ){
                tds.each((i, td) =>{
                  bbn.fn.adjustHeight(
                    td,
                    $("table.bbn-table-data-left:first > tbody > tr:eq(" + i + ") > td:first-child", this.$el),
                    $("table.bbn-table-data-right:first > tbody > tr:eq(" + i + ") > td:first-child", this.$el)
                  );
                });
              }
              if (
                this.$refs.scroller &&
                $.isFunction(this.$refs.scroller.onResize)
              ){
                bbn.fn.log("RESIZING FOR UTABLKE");
                this.$refs.scroller.onResize();
                if (
                  this.$refs.scrollerY &&
                  $.isFunction(this.$refs.scrollerY.onResize)
                ){
                  bbn.fn.log("SCROLLY HERE");
                  if ( this.scrollableContainer !== this.$refs.scroller.$refs.scrollContainer ){
                    bbn.fn.log("CHANGING scrollableContainer");
                    this.scrollableContainer = this.$refs.scroller.$refs.scrollContainer;
                  }
                  this.$refs.scrollerY.onResize();
                }
              }
              this.$emit("resize");
            });
          }
        }
      },

      overTr(idx, remove){
        $(".bbn-table-main tr:eq(" + idx + ")")
          [remove ? 'removeClass' : 'addClass']("k-grid-header");
        if ( this.colsLeft.length ){
          $(".bbn-table-data-left tr:eq(" + idx + ")")
            [remove ? 'removeClass' : 'addClass']("k-grid-header");
        }
        if ( this.colsRight.length ){
          $(".bbn-table-data-right tr:eq(" + idx + ")")
            [remove ? 'removeClass' : 'addClass']("k-grid-header");
        }
      },

      /** Renders a cell according to column's config */
      render(data, column, index){
        let field = column && column.field ? column.field : '',
            value = data && column.field ? data[column.field] || '' : undefined;

        if ( column.source ){
          if ( value ){
            if ( Array.isArray(column.source) ){
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
          return column.render(data, index, column, value)
        }
        return value;
      },

      /** Returns header's CSS object */
      headStyles(col){
        let css = {
          width: this.getWidth(col.realWidth)
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

      calculateSize(){
        return;
        let leftWidth = 0,
            mainWidth = 0,
            rightWidth = 0,
            numUnknown = 0;
        $.each(this.cols, (i, a) => {
          if ( a.hidden ){
            a.realWidth = 0;
          }
          else{
            if ( a.width ){
              if ( (typeof(a.width) === 'string') && (a.width.substr(-1) === '%') ){
                a.realWidth = Math.round(this.lastKnownWidth * parseFloat(a.width) / 100);
              }
              else{
                a.realWidth = parseFloat(a.width);
              }
              if ( a.realWidth < this.minimumColumnWidth ){
                a.realWidth = this.minimumColumnWidth;
              }
            }
            else{
              a.realWidth = this.defaultColumnWidth;
              numUnknown++;
            }
            if ( a.fixed ){
              if ( (a.fixed === 'right') || (this.defaultFixedSide === 'right') ){
                rightWidth += a.realWidth;
              }
              else{
                leftWidth += a.realWidth;
              }
            }
            else{
              mainWidth += a.realWidth;
            }
          }
        });
        let toFill = this.$el.clientWidth
          - (
            leftWidth
            + mainWidth
            + rightWidth
          );
        bbn.fn.log("THERE IS NOT TO FILL", toFill, numUnknown, this.$el.clientWidth, leftWidth, mainWidth, rightWidth);
        // We must arrive to 100% minimum
        if ( toFill > 0 ){
          bbn.fn.log("THERE IS TO FILL", toFill, numUnknown);
          // If we have unknown width we fill these columns
          leftWidth = 0;
          mainWidth = 0;
          rightWidth = 0;
          if ( numUnknown ){
            let newWidth = Math.round(
              toFill
              / numUnknown
              * 100
            ) / 100;
            if ( newWidth < this.minimumColumnWidth ){
              newWidth = this.minimumColumnWidth;
            }
            $.each(this.cols, (i, a) => {
              if ( !a.hidden ){
                if ( !a.width ){
                  a.realWidth = newWidth + this.defaultColumnWidth;
                }
                if ( a.fixed ){
                  if ( (a.fixed === 'right') || (this.defaultFixedSide === 'right') ){
                    rightWidth += a.realWidth;
                  }
                  else{
                    leftWidth += a.realWidth;
                  }
                }
                else{
                  mainWidth += a.realWidth;
                }
              }
            });
          }
          // Otherwise we dispatch it through the existing column
          else{
            let bonus = Math.round(toFill / this.cols.length * 100) / 100;
            $.each(this.cols, (i, a) => {
              if ( !a.hidden ){
                a.realWidth += bonus;
                if ( a.fixedRight || (a.fixed && (this.fixedDefaultSide === 'right')) ){
                  rightWidth += bonus;
                }
                else if ( a.fixedLeft || a.fixed ){
                  leftWidth += bonus;
                }
                else{
                  mainWidth += bonus;
                }
              }
            })
          }
        }
        this.tableLeftWidth = leftWidth;
        this.tableMainWidth = mainWidth;
        this.tableRightWidth = rightWidth;
      },

      /** @todo */
      getWidth(w){
        if ( typeof(w) === 'number' ){
          return (w > 19 ? w : 20 ) + 'px';
        }
        if ( bbn.fn.isDimension(w) ){
          return w;
        }
        return '100px';
      },

      /** @todo */
      getColumns(){
        const vm = this;
        let res = [],
            fixed = true;
        $.each(vm.cols, function(i, a){
          bbn.fn.log("getColumns", a);
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
                  if ( Array.isArray(obj[a.source]) ){
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
      addColumn(obj){
        const vm = this;
        vm.cols.push(obj);
      },

      onResize(){
        if ( this.$refs.scrollerY ){
          this.$refs.scrollerY.onResize();
        }
        if ( this.$refs.scroller ){
          this.$refs.scroller.onResize();
        }
        this.updateTable();
        this.$emit("resize");
      },

      dataScrollContents(){
        if ( !this.colsLeft.length && !this.colsRight.length ){
          return null;
        }
        let r = [];
        if ( this.colsLeft.length && this.$refs.leftScroller && this.$refs.leftScroller.$refs.scrollContainer ){
          r.push(this.$refs.leftScroller.$refs.scrollContainer);
        }
        if ( this.$refs.scroller ){
          r.push(this.$refs.scroller.$refs.scrollContainer);
        }
        if ( this.colsRight.length && this.$refs.rightScroller && this.$refs.rightScroller.$refs.scrollContainer ){
          r.push(this.$refs.rightScroller.$refs.scrollContainer);
        }
        return r;
      },

      isExpanded(d){
        if ( !this.expander && (this.group === false) ){
          return true;
        }
        if ( this.expander ){
          return $.inArray(d.index, this.currentExpanded) > -1;
        }
        else{
          if ( $.inArray(d.index, this.currentExpanded) > -1 ){
            return true;
          }
          if ( d.isGrouped && ($.inArray(d.link, this.currentExpanded) > -1) ){
            return true;
          }
          return false;
        }
      },

      toggleExpanded(idx){
        if ( this.currentData[idx] ){
          let i = $.inArray(idx, this.currentExpanded);
          if ( i > -1 ){
            this.currentExpanded.splice(i, 1);
          }
          else{
            this.currentExpanded.push(idx);
          }
          this.$nextTick(() => {
            this.updateTable();
          })
        }
      },

      rowHasExpander(d){
        if ( this.hasExpander ){
          if ( !$.isFunction(this.expander) ){
            return true;
          }
          return !!this.expander(d);
        }
        return false;
      },

      init(){
        let colsLeft = [],
            colsMain = [],
            colsRight = [],
            leftWidth = 0,
            mainWidth = 0,
            rightWidth = 0,
            numUnknown = 0;
        if ( this.hasExpander ){
          colsLeft.push({
            title: ' ',
            width: 25,
            realWidth: 25
          });
          leftWidth = 25;
        }
        $.each(this.cols, (i, a) => {
          if ( !this.groupable || (this.group !== i) ){
            if ( a.hidden ){
              a.realWidth = 0;
            }
            else{
              if ( a.width ){
                if ( (typeof(a.width) === 'string') && (a.width.substr(-1) === '%') ){
                  a.realWidth = Math.round(this.lastKnownWidth * parseFloat(a.width) / 100);
                }
                else{
                  a.realWidth = parseFloat(a.width);
                }
                if ( a.realWidth < this.minimumColumnWidth ){
                  a.realWidth = this.minimumColumnWidth;
                }
              }
              else{
                a.realWidth = this.defaultColumnWidth;
                numUnknown++;
              }
            }
            if ( a.fixed ){
              if (
                (a.fixed !== 'right') &&
                ((this.fixedDefaultSide !== 'right') || (a.fixed === 'left'))
              ){
                colsLeft.push(a);
              }
              else{
                colsRight.push(a);
              }
            }
            else{
              colsMain.push(a);
            }
          }
        });
        let toFill = this.$el.clientWidth
          - (
            bbn.fn.sum(colsLeft, 'realWidth')
            + bbn.fn.sum(colsMain, 'realWidth')
            + bbn.fn.sum(colsRight, 'realWidth')
          );
        bbn.fn.log("THERE IS NOT TO FILL", toFill, numUnknown, this.$el.clientWidth, leftWidth, mainWidth, rightWidth);
        // We must arrive to 100% minimum
        if ( toFill > 0 ){
          bbn.fn.log("THERE IS TO FILL", toFill, numUnknown);
          if ( numUnknown ){
            let newWidth = Math.round(
              toFill
              / numUnknown
              * 100
            ) / 100;
            if ( newWidth < this.minimumColumnWidth ){
              newWidth = this.minimumColumnWidth;
            }
            $.each(this.cols, (i, a) => {
              if ( !a.hidden ){
                if ( !a.width ){
                  a.realWidth = newWidth + this.defaultColumnWidth;
                }
              }
            });
          }
          // Otherwise we dispatch it through the existing column
          else{
            let bonus = Math.round(
              toFill / (
                // We don't dispatch to the expander column
                this.hasExpander ? this.numVisible - 1 : this.numVisible
              ) * 100
            ) / 100;
            $.each(this.cols, (i, a) => {
              if ( !a.hidden && (!this.hasExpander || (i !== 0)) ){
                a.realWidth += bonus;
              }
            })
          }
        }
        this.tableLeftWidth = bbn.fn.sum(colsLeft, 'realWidth');
        this.tableMainWidth = bbn.fn.sum(colsMain, 'realWidth');
        this.tableRightWidth = bbn.fn.sum(colsRight, 'realWidth');
        this.colsLeft = colsLeft;
        this.colsMain = colsMain;
        this.colsRight = colsRight;
      }
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
          else if (
            (node.tag === 'bbn-column') &&
            node.data && node.data.attrs
          ){
            vm.cols.push(node.data.attrs);
            //bbn.fn.log("ADDING COLUMN 2", node.data.attrs)
          }
        }
      }
    },

    mounted(){
      this.init();
      this.$forceUpdate();
      this.$nextTick(() => {
        this.selfEmit();
        this.updateData();
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
          bbn.fn.log("WATCHER DATA");
          this.updateTable();
        })
      },
      cols: {
        deep: true,
        handler(){
          this.init();
        }
      }
    }
  });

})(jQuery, bbn);
