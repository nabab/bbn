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
      }
    },
    data: function(){
      return {
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
        /**
         * Number of fixed columns on the left
         * @type {number}
         *
         */
        fixedLeft: 0,
        /**
         * Number of fixed columns on the right
         * @type {number}
         *
         */
        fixedRight: 0,
        currentLimit: this.limit,
        currentOrder: this.order,
        tableLeftWidth: 0,
        tableMainWidth: 0,
        tableRightWidth: 0,
        scrollableContainer: null,
        hiddenScroll: true,
        initialColumns: this.columns
      };
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
                  this.currentOrder[result.order] = result.dir === 'DESC' ? 'DESC' : 'ASC';
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
        let tds = $("table.bbn-table-main > tbody > tr > td.bbn-table-first", this.$el);
        if ( !num ){
          num = 0;
        }
        bbn.fn.log("trying to update table, attempt " + num);
        if (
          (tds.length !== this.currentData.length) ||
          !this.$refs.scroller
        ){
          setTimeout(() =>{
            this.updateTable(++num);
          }, 200)
        }
        else{
          this.$nextTick(() => {
            if ( this.fixedRight || this.fixedLeft ){
              tds.each((i, td) =>{
                bbn.fn.adjustHeight(
                  td,
                  $("table.bbn-table-data-left:first > tbody > tr:eq(" + i + ") > td.bbn-table-first", this.$el),
                  $("table.bbn-table-data-right:first > tbody > tr:eq(" + i + ") > td.bbn-table-first", this.$el)
                );
              });
            }
            if (
              this.$refs.scroller &&
              $.isFunction(this.$refs.scroller.calculateSize)
            ){
              bbn.fn.log("RESIZING FOR UTABLKE");
              this.$refs.scroller.calculateSize();
              if (
                this.$refs.scrollerY &&
                $.isFunction(this.$refs.scrollerY.calculateSize)
              ){
                bbn.fn.log("SCROLLY HERE");
                if ( this.scrollableContainer !== this.$refs.scroller.$refs.scrollContainer ){
                  bbn.fn.log("CHANGING scrollableContainer");
                  this.scrollableContainer = this.$refs.scroller.$refs.scrollContainer;
                }
                this.$refs.scrollerY.calculateSize();
              }
            }
            this.$emit("resize");
          });
        }
      },

      overTr(idx, remove){
        $(".bbn-table-main tr:eq(" + idx + ")")
          [remove ? 'removeClass' : 'addClass']("k-grid-header");
        if ( this.fixedLeft ){
          $(".bbn-table-data-left tr:eq(" + idx + ")")
            [remove ? 'removeClass' : 'addClass']("k-grid-header");
        }
        if ( this.fixedRight ){
          $(".bbn-table-data-right tr:eq(" + idx + ")")
            [remove ? 'removeClass' : 'addClass']("k-grid-header");
        }
      },

      /** Renders a cell according to column's config */
      render(data, column, index){
        let field = column && column.field ? column.field : '',
            value = data && column.field ? data[column.field] || '' : '';

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
          return column.render(value, index, data)
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
        let leftWidth = 0,
            mainWidth = 0,
            rightWidth = 0,
            fixedLeft = 0,
            fixedRight = 0,
            numUnknown = 0;
        $.each(this.cols, (i, a) => {
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
              fixedRight++;
              rightWidth += a.realWidth;
            }
            else{
              fixedLeft++;
              leftWidth += a.realWidth;
            }
          }
          else{
            mainWidth += a.realWidth;
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
            });
          }
          // Otherwise we dispatch it through the existing column
          else{
            let bonus = Math.round(toFill / this.cols.length * 100) / 100;
            $.each(this.cols, (i, a) => {
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
            })
          }
        }
        this.fixedLeft = fixedLeft;
        this.fixedRight = fixedRight;
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

      isNotFixed(idx){
        return (idx >= this.fixedLeft) &&
          (idx < (this.cols.length - this.fixedRight));
      },

      setFixedColumns(){
        let fixed = true;
        this.fixedLeft = 0;
        this.fixedLeftWidth = 0;
        this.fixedRight = 0;
        this.fixedRightWidth = 0;
        $.each(this.cols, (i, a) => {
          if ( !a.hidden ){
            if ( a.fixed && fixed ){
              this.fixedLeft++;
              this.fixedLeftWidth += (a.width ? a.width : 100);
            }
            else if ( !a.fixed && fixed ){
              fixed = false;
            }
            else if ( a.fixed ){
              this.fixedRight++;
              this.fixedRightWidth += (a.width ? a.width : 100);
            }
            else if ( this.fixedRight ){
              this.fixedRight = 0;
            }
          }
        });
        return this;
      },

      /** @todo */
      getColumns(){
        const vm = this;
        let res = [],
            fixed = true;
        this.fixedLeft = 0;
        this.fixedRight = 0;
        $.each(vm.cols, function(i, a){
          bbn.fn.log(a);

          if ( a.fixed && fixed ){
            this.fixedLeft++;
          }
          else if ( !a.fixed && fixed ){
            fixed = false;
          }
          else if ( a.fixed ){
            this.fixedRight++;
          }
          else if ( this.fixedRight ){
            this.fixedRight = 0;
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
              if ( !Array.isArray(tb) && (typeof(tb) === 'object') ){
                tb = [tb];
              }
              if ( Array.isArray(tb) ){
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
          else if ( Array.isArray(vm.$options.propsData.source) ){
            cfg.data = JSON.parse(JSON.stringify(vm.$options.propsData.source));
          }
          else if ( (typeof vm.$options.propsData.source === 'object') && Array.isArray(vm.$options.propsData.source.data) ){
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
        if ( !this.fixedLeft && !this.fixedRight ){
          return null;
        }
        let r = [];
        if ( this.fixedLeft && this.$refs.leftScroller && this.$refs.leftScroller.$refs.scrollContainer ){
          r.push(this.$refs.leftScroller.$refs.scrollContainer);
        }
        if ( this.$refs.scroller ){
          r.push(this.$refs.scroller.$refs.scrollContainer);
        }
        if ( this.fixedRight && this.$refs.rightScroller && this.$refs.rightScroller.$refs.scrollContainer ){
          r.push(this.$refs.rightScroller.$refs.scrollContainer);
        }
        return r;
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
            vm.initialColumns.push(node.data.attrs);
            //bbn.fn.log("ADDING COLUMN 2", node.data.attrs)
          }
        }
      }
      //this.setFixedColumns();
    },

    mounted(){
      this.cols = this.initialColumns;
      this.calculateSize();
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
      }
    }
  });

})(jQuery, bbn);
