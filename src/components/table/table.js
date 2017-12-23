/**
 * Created by BBN on 14/02/2017.
 */
(function($, bbn){
  "use strict";

  const METHODS4BUTTONS = ['insert', 'select', 'edit', 'add', 'copy', 'delete'];

  /**
   * Classic input with normalized appearance
   */
  Vue.component('bbn-table', {
    mixins: [bbn.vue.basicComponent, bbn.vue.resizerComponent, bbn.vue.dataEditorComponent, bbn.vue.localStorageComponent],
    props: {
      titleGroups: {
        type: [Array, Function]
      },
      // A function to transform the data
      map: {
        type: Function
      },
      popup: {
        type: Vue
      },
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
      multifilter: {
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
      saveable: {
        type: Boolean,
        default: false
      },
      groupable: {
        type: Boolean,
        default: false
      },
      editable: {
        type: [Boolean, String, Function]
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
        type: Array,
        default(){
          return [];
        }
      },
      filters: {
        type: Object,
        default(){
          return {
            logic: 'AND',
            conditions: []
          };
        }
      },
      selection: {
        type: [Boolean, Function],
        default: false
      },
      minimumColumnWidth: {
        type: Number,
        default: 30
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
      confirmMessage: {
        type: [String, Function]
      },
      // Vue components
      component: {

      },
      expander: {

      },
      editor: {
        type: [String, Object]
      },
      fixedDefaultSide: {
        type: String,
        default: "left"
      },
      uid: {
        type: [String, Number, Array]
      },
      toolbar: {
        type: [String, Array, Function, Object]
      },
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
      groupBy: {
        type: Number
      },
      expandedValues: {
        type: [Array, Function]
      },
      expanded: {
        type: [Boolean, Array],
        default(){
          return [];
        }
      },
      data: {
        type: [Object, Function],
        default(){
          return {};
        }
      },
      footer: {
        type: [String, Object]
      },
      groupFooter: {
        type: [String, Object]
      },
      aggregateExp: {
        type: Object,
        default(){
          return {
            tot: bbn._('Total'),
            med: bbn._('Average'),
            num: bbn._('Count'),
            max: bbn._('Maximum'),
            min: bbn._('Minimum'),
          };
        }
      },
      loadedConfig: {
        type: Object
      }
    },
    data(){
      let editable = $.isFunction(this.editable) ? this.editable() : this.editable;
      return {
        ready: false,
        currentConfig: {},
        savedConfig: false,
        defaultConfig: this.loadedConfig ? this.loadedConfig : {
          filters: this.filters,
          limit: this.limit,
          order: this.order,
          hidden: this.hidden || null,
        },
        currentFilter: false,
        floatingFilterX: 0,
        floatingFilterY: 0,
        floatingFilterTimeOut: 0,
        currentFilters: this.filters,
        currentLimit: this.limit,
        currentOrder: this.order,
        currentHidden: this.hidden || [],
        currentData: [],
        selectedRows: [],
        group: this.groupBy === undefined ? false : this.groupBy,
        limits: [10, 25, 50, 100, 250, 500],
        start: 0,
        total: 0,
        editMode: editable === true ? (this.editor ? 'popup' : 'inline') : (editable === 'popup' ? 'popup' : 'inline'),
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
        tableLeftWidth: 0,
        tableMainWidth: 0,
        tableRightWidth: 0,
        colsLeft: [],
        colsMain: [],
        colsRight: [],
        colButtons: false,
        scrollableContainer: null,
        hiddenScroll: true,
        popups: [],
        isAggregated: false,
        currentOverTr: false,
        updaterTimeout: false,
        allExpanded: this.expanded === true ? true : false,
        currentExpanded: false,
        focusedRow: false
      };
    },
    computed: {
      jsonConfig(){
        return JSON.stringify(this.currentConfig);
      },
      isSaved(){
        return this.jsonConfig === this.savedConfig;
      },
      isChanged(){
        return JSON.stringify(this.currentConfig) !== this.initialConfig;
      },
      toolbarButtons(){
        let r = [],
            ar = [];
        if ( this.toolbar ){
          ar = $.isFunction(this.toolbar) ?
            this.toolbar() : (
              Array.isArray(this.toolbar) ? this.toolbar.slice() : []
            );
          if ( !Array.isArray(ar) ){
            ar = [];
          }
          $.each(ar, (i, a) => {
            let o = $.extend({}, a);
            if ( o.command ){
              o.command = () => {
                this._execCommand(a);
              }
            }
            r.push(o);
          });
        }
        return r;
      },
      isEditedValid(){
        let ok = true;
        if ( this.tmpRow ){
          $.each(this.columns, (i, a) => {
            if ( a.field && a.required && !this.tmpRow[a.field] ){
              ok = false;
              return false;
            }
          })
        }
        return ok;
      },
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
        if ( !this.cols.length ){
          return [];
        }
        let res = [],
            isGroup = false,
            currentGroupValue,
            currentLink,
            data = this.currentData.slice(),
            o,
            realIndex = 0,
            end = data.length,
            aggregates = {
              tot: 0,
              num: 0,
              min: false,
              max: false,
              groups: []
            },
            aggregateModes = [],
            i = 0;
        if ( this.isAggregated ){
          let idx = bbn.fn.search(this.cols, {field: this.isAggregated});
          if ( idx > -1 ){
            aggregateModes = this.cols[idx].aggregate;
          }
        }
        if (
          (this.group !== false) &&
          this.cols[this.group] &&
          this.cols[this.group].field
        ){
          isGroup = true;
          let pos = bbn.fn.search(this.currentOrder, {field: this.cols[this.group].field});
          if ( pos !== 0 ){
            data = bbn.fn.order(data, this.cols[this.group].field, 'asc');
          }
          else{
            data = bbn.fn.multiorder(data, this.currentOrder);
          }
        }
        if ( this.pageable && (!this.isAjax || !this.serverPaging) ){
          i = this.start;
          end = this.start + this.currentLimit > data.length ? data.length : this.start + this.currentLimit;
        }
        let isInit = this.currentExpanded === false;
        if ( isInit ){
          this.currentExpanded = [];
        }
        if ( this.tmpRow ){
          res.push({
            index: -1,
            data: this.tmpRow,
            selected: false,
            expander: true,
            isEdited: true
          });
        }
        let currentGroupIndex = -1,
            currentGroupNum;
        while ( i < end ){
          let a = data[i];
          if ( isGroup && (currentGroupValue !== a[this.cols[this.group].field]) ){
            currentGroupValue = a[this.cols[this.group].field];
            if ( currentGroupIndex > -1 ){
              let idx = bbn.fn.search(res, {index: currentGroupIndex});
              res[idx].num = currentGroupNum;
            }
            currentGroupNum = 1;
            currentGroupIndex = i;
            let tmp = {group: true, index: i, value: currentGroupValue, data: a};
            if ( isInit ){
              if ( this.expandedValues ){
                if ( $.isFunction(this.expandedValues) ){
                  if ( this.expandedValues(currentGroupValue) ){
                    this.currentExpanded.push(tmp.index);
                  }
                }
                else if ( $.inArray(currentGroupValue, this.expandedValues) > -1 ){
                  this.currentExpanded.push(tmp.index);
                }
              }
              else if ( this.expanded === true ){
                this.currentExpanded.push(tmp.index);
              }
              else if ( $.isArray(this.expanded) && ($.inArray(tmp.index, this.expanded) > -1) ){
                this.currentExpanded.push(tmp.index);
              }
            }
            res.push(tmp);
            currentLink = i;
            realIndex++;
          }
          else{
            currentGroupNum++;
          }
          o = {index: i, data: a};
          if ( a === this.editedRow ){
            o.isEdited = true;
          }
          if ( this.selection ){
            o.selected = $.inArray(this.selectedRows, i) > -1;
            o.selection = true;
          }
          if ( isGroup ){
            o.isGrouped = true;
            o.link = currentLink;
          }
          else if ( this.expander && (
            !$.isFunction(this.expander) ||
            ($.isFunction(this.expander) && this.expander(a))
          ) ){
            o.expander = true;
          }
          res.push(o);
          realIndex++;
          if ( this.expander && (
              !$.isFunction(this.expander) ||
              ($.isFunction(this.expander) && this.expander(a))
            )
          ){
            res.push({index: i, data: a, expansion: true});
            realIndex++;
          }
          i++;
          // Group or just global aggregation
          if ( aggregateModes.length ){
            aggregates.num++;
            aggregates.tot += parseFloat(a[this.isAggregated]);
            if ( aggregates.min === false ){
              aggregates.min = parseFloat(a[this.isAggregated]);
            }
            else if ( aggregates.min > parseFloat(a[this.isAggregated]) ){
              aggregates.min = parseFloat(a[this.isAggregated])
            }
            if ( aggregates.max === false ){
              aggregates.max = parseFloat(a[this.isAggregated]);
            }
            else if ( aggregates.max < parseFloat(a[this.isAggregated]) ){
              aggregates.max = parseFloat(a[this.isAggregated])
            }
            if ( isGroup ){
              let searchRes = bbn.fn.search(aggregates.groups, {value: currentGroupValue});
              if ( searchRes === -1 ){
                searchRes = aggregates.groups.length;
                aggregates.groups.push({
                  value: currentGroupValue,
                  tot: 0,
                  num: 0,
                  min: false,
                  max: false,
                });
              }
              let b = aggregates.groups[searchRes];
              b.num++;
              b.tot += parseFloat(a[this.isAggregated]);
              if ( b.min === false ){
                b.min = parseFloat(a[this.isAggregated]);
              }
              else if ( b.min > parseFloat(a[this.isAggregated]) ){
                b.min = parseFloat(a[this.isAggregated])
              }
              if ( b.max === false ){
                b.max = parseFloat(a[this.isAggregated]);
              }
              else if ( b.max < parseFloat(a[this.isAggregated]) ){
                b.max = parseFloat(a[this.isAggregated])
              }
              if ( !data[i] || (currentGroupValue !== data[i][this.cols[this.group].field]) ){
                let b = aggregates.groups[aggregates.groups.length-1];
                b.med = b.tot / b.num;
                $.each(aggregateModes, (k, c) => {
                  let tmp = {};
                  tmp[this.isAggregated] = b[c];
                  res.push({
                    index: i-1,
                    groupAggregated: true,
                    link: currentLink,
                    value: currentGroupValue,
                    name: c,
                    data: $.extend({}, a, tmp)
                  });
                });
              }
            }
            if ( i === end ){
              aggregates.med = aggregates.tot / aggregates.num;
              $.each(aggregateModes, (k, c) => {
                let tmp = {};
                tmp[this.isAggregated] = aggregates[c];
                res.push({
                  index: i,
                  aggregated: true,
                  name: c,
                  data: $.extend({}, a, tmp)
                });
              });
            }
          }
        }
        if ( currentGroupIndex > -1 ){
          let idx = bbn.fn.search(res, {index: currentGroupIndex});
          res[idx].num = currentGroupNum;
        }
        if ( isInit ){
          this.$nextTick(() => {
            this.updateTable()
          });
          setTimeout(() => {
            this.updateTable()
          }, 1000);
        }
        return res;
      },
      hasExpander(){
        return this.expander || (
          this.groupable &&
          (typeof this.group === 'number') &&
          this.cols[this.group]
        );
      }
    },
    methods: {

      _map(data){
        return this.map ? $.map(data, this.map) : data;
      },
      _overTr(idx, remove){
        if ( remove ){
          setTimeout(() => {
            if ( this.currentOverTr === idx ){
              this.currentOverTr = false
            }
          }, 100)
        }
        else{
          this.currentOverTr = idx;
        }
      },
      /** Returns header's CSS object */
      _headStyles(col){
        let css = {
          width: this.getWidth(col.realWidth)
        };
        if ( col.hidden ){
          css.display = 'none';
        }
        return css;
      },
      /** Returns body's CSS object */
      _bodyStyles(col){
        return {};
      },
      /** @todo */
      _defaultRow(initialData){
        let res = {},
            data = initialData ? $.extend(true, {}, initialData) : {};
        $.each(this.cols, function(i, a){
          if ( a.field ){
            if ( data[a.field] !== undefined ){
              res[a.field] = data[a.field];
            }
            else if ( a.default !== undefined ){
              res[a.field] = $.isFunction(a.default) ? a.default() : a.default;
            }
            else{
              res[a.field] = '';
            }
            if ( $.isArray(res[a.field]) ){
              res[a.field] = res[a.field].splice();
            }
            else if ( res[a.field] instanceof(Date) ){
              res[a.field] = new Date(res[a.field].getTime());
            }
            else if ( typeof res[a.field] === 'object' ){
              res[a.field] = $.extend(true, {}, res[a.field]);
            }
          }
        });
        return res;
      },
      /** @todo */
      _addTmp(data){
        this._removeTmp().tmpRow = this._defaultRow(data);
        this.$nextTick(() => {
          this.updateTable();
        });
        if ( this.$refs.scrollerY ){
          this.$refs.scrollerY.scrollTo(0, true);
        }
        return this;
      },
      /** @todo */
      _removeTmp(){
        if ( this.tmpRow ){
          this.tmpRow = false;
          this.$nextTick(() => {
            this.updateTable();
          });
        }
        return this;
      },
      _checkHeaders(){
        if ( this.titleGroups ){
          let x = this.$refs.scroller.$refs.xScroller.currentScroll,
              cols = this.titleGroupsCells(),
              tot = 0;
          $.each(cols, (i, a) => {
            if ( tot + a.width > x ){
              $(".bbn-table-title-group", this.$refs.titleGroup[i]).css({left: tot < x ? x - tot : 0});
              return false;
            }
            tot += (a.width + a.colspan);
          })
        }
      },
      _execCommand(button, data, col, index){
        if ( button.command ){
          if ( $.isFunction(button.command) ){
            return button.command(data, col, index);
          }
          else if ( typeof(button.command) === 'string' ){
            switch ( button.command ){
              case 'insert':
                return this.insert(data, bbn._('New row creation'));
                break;
              case 'select':
                return this.select();
                break;
              case 'edit':
                return this.edit(data, bbn._('Row edition'));
                break;
              case 'add':
                return this.add(data);
                break;
              case 'copy':
                return this.copy(data, bbn._('Row copy'));
                break;
              case 'delete':
                return this.delete(index);
            }
          }
        }
        return false;
      },
      getPopup(){
        return this.popup || bbn.vue.closest(this, 'bbn-tab').getPopup();
      },
      titleGroupsCells(type){
        if ( this.titleGroups ){
          let cols = this.colsMain;
          if ( type === 'left' ){
            cols = this.colsLeft;
          }
          else if ( type === 'right' ){
            cols = this.colsRight;
          }
          let cells = [],
              group = null,
              corresp = {};
          $.each(cols, (i, a) => {
            if ( !a.hidden ){
              if ( a.group === group ){
                cells[cells.length-1].colspan++;
                cells[cells.length-1].width += a.realWidth;
              }
              else{
                if ( corresp[a.group] === undefined ){
                  let idx = bbn.fn.search(this.titleGroups, 'value', a.group);
                  if ( idx > -1 ){
                    corresp[a.group] = idx;
                  }
                }
                if ( corresp[a.group] !== undefined ){
                  cells.push({
                    text: this.titleGroups[corresp[a.group]].text || '&nbsp;',
                    style: this.titleGroups[corresp[a.group]].style || {},
                    cls: this.titleGroups[corresp[a.group]].cls || '',
                    colspan: 1,
                    width: a.realWidth
                  });
                }
                /*
                else if ( this.titleGroups.default ){
                  cells.push({
                    text: this.titleGroups.default.text || '&nbsp;',
                    style: this.titleGroups.default.style || {},
                    cls: this.titleGroups.default.cls || '',
                    colspan: 1,
                    width: a.realWidth
                  });
                }
                */
                else{
                  cells.push({
                    text: '&nbsp;',
                    style: '',
                    cls: '',
                    colspan: 1,
                    width: a.realWidth
                  });
                }
                group = a.group;
              }
            }
          });
          return cells;
        }
      },
      hasFilter(col){
        if ( col.field ){
          for ( let i = 0; i < this.currentFilters.conditions.length; i++ ){
            if ( this.currentFilters.conditions[i].field === col.field ){
              return true;
            }
          }
        }
        return false;
      },
      onSetFilter(filter){
        if ( filter && filter.field && filter.operator ){
          if ( this.multi ){
            this.currentFilters.conditions.push(filter);
          }
          else if ( filter.field ){
            let idx = bbn.fn.search(this.currentFilters.conditions, {field: filter.field});
            if ( idx > -1 ){
              this.currentFilters.conditions.splice(idx, 1, filter);
            }
            else{
              this.currentFilters.conditions.push(filter);
            }
          }
          //bbn.fn.log("TABLE", filter)
        }
      },
      onUnsetFilter(filter){
        bbn.fn.log("onUnset", filter);
        this.removeFilter(filter);
      },
      removeFilter(condition){
        if ( condition.time ){
          bbn.fn.log("There is the time", condition);
          let del = (arr) => {
            let idx = bbn.fn.search(arr, {time: condition.time});
            bbn.fn.log("Is there the index?", idx);
            if ( idx > -1 ){
              if ( arr[idx].conditions && arr[idx].conditions.length ){
                bbn.fn.confirm(bbn._("Êtes-vous sûr de vouloir supprimer ce groupe de conditions?"), () => {
                  arr.splice(idx, 1);
                })
              }
              else{
                arr.splice(idx, 1);
                bbn.fn.log("It seems to be deleted", arr);
              }
              return true;
            }
            for ( let i = 0; i < arr.length; i++ ){
              if ( arr[i].conditions ){
                if ( del(arr[i].conditions) ){
                  return true;
                }
              }
            }
          };
          if ( del(this.currentFilters.conditions) ){
            this.$forceUpdate();
          }
        }
      },
      checkFilterWindow(e){
        if ( this.currentFilter ){
          if (
            (e.clientX < this.floatingFilterX) ||
            (e.clientX > this.floatingFilterX + 600) ||
            (e.clientY < this.floatingFilterY) ||
            (e.clientY > this.floatingFilterY + 200)
          ){
            if ( !this.floatingFilterTimeOut ){
              this.floatingFilterTimeOut = setTimeout(() => this.currentFilter = false, 500);
            }
          }
          else{
            clearTimeout(this.floatingFilterTimeOut);
            this.floatingFilterTimeOut = 0;
          }
        }
      },
      getFilterOptions(){
        if ( this.currentFilter ){
          let o = this.editorGetComponentOptions(this.currentFilter);
          if ( o.field ){
            o.conditions = this.getColFilters(this.currentFilter);
          }
          if ( o.conditions.length ){
            o.value = o.conditions[0].value;
            o.operator = o.conditions[0].operator;
          }
          o.multi = false;
          return o;
        }
      },
      openMultiFilter(){
        this.getPopup().open({
          title: bbn._('Multi Filter'),
          component: {
            template: `<bbn-scroll><bbn-filter v-bind="source" @change="changeConditions"></bbn-filter></bbn-scroll>`,
            props: ['source'],
            methods: {
              changeConditions(o){
                bbn.vue.closest(this, 'bbn-table').currentFilters.logic = o.logic;
                bbn.fn.log("changeConditions", o)
              }
            },
          },
          source: {
            fields: $.grep(this.cols, (a) => {
              return (a.filterable !== false) && !a.buttons;
            }),
            conditions: this.currentFilters.conditions,
            logic: this.currentFilters.logic
          }
        });
      },
      getColFilters(col){
        let r= [];
        if ( col.field ){
          $.each(this.currentFilters.conditions, (i, a) => {
            if ( a.field === col.field ){
              r.push(a);
            }
          })
        }
        return r;
      },
      showFilter(col, ev){
        bbn.fn.log(ev);
        this.floatingFilterX = ev.pageX - 10 < 0 ? 0 : (ev.pageX - 10 + 600 > this.$el.clientWidth ? this.$el.clientWidth - 600 : ev.pageX - 10);
        this.floatingFilterY = ev.pageY - 10 < 0 ? 0 : (ev.pageY - 10 + 200 > this.$el.clientHeight ? this.$el.clientHeight - 200 : ev.pageY - 10);
        this.currentFilter = col;
      },
      pickableColumnList(){
        return $.map(this.cols.slice(), (i, a) => {
          return a.showable !== false;
        })
      },
      openColumnsPicker(){
        let table = this;
        this.getPopup().open({
          title: bbn._('Columns\' picker'),
          component: {
            template: `
<div class="bbn-table-column-picker bbn-full-screen">
  <bbn-scroll ref="scroll">
    <div class="bbn-padded">
      <ul v-if="source.titleGroups">
        <li v-for="(tg, idx) in source.titleGroups">
          <h3>
            <bbn-checkbox :checked="allVisible(tg.value)"
                          @change="checkAll(tg.value)"
                          :label="tg.text"
            ></bbn-checkbox>
          </h3>
          <ul>
            <li v-for="(col, i) in source.cols"
                v-if="!col.fixed && (col.group === tg.value) && (col.showable !== false) && (col.title || col.ftitle)"
            >
              <bbn-checkbox :checked="!col.hidden"
                            @change="check(col, i)"
                            :label="col.ftitle || col.title"
                            :contrary="true"
              ></bbn-checkbox>
            </li>
          </ul> 
        </li>  
      </ul>
      <ul v-else>
        <li v-for="(col, i) in source.cols"
            v-if="!col.fixed && (col.showable !== false) && (col.title || col.ftitle)"
        >
          <bbn-checkbox :checked="!col.hidden"
                        @change="check(col, i)"
                        :label="col.ftitle || col.title"
                        :contrary="true"
          ></bbn-checkbox>
        </li>
      </ul>
    </div>
  </bbn-scroll>
</div>
`,
            props: ['source'],
            data(){
              return {
                table: table
              }
            },
            methods: {
              allVisible(group){
                let ok = true;
                bbn.fn.log("allVisible", group);
                $.each(this.source.cols, (i, a) => {
                  if (
                    (a.showable !== false) &&
                    (a.group === group) &&
                    !a.fixed &&
                    a.hidden
                  ){
                    ok = false;
                    bbn.fn.log("NOT ALL VISIBLE!!!!!!!!!!!!!!!!!!!!!!", a);
                    return false;
                  }
                });
                return ok;
              },
              check(col, index){
                this.table.show([index], !col.hidden);
              },
              checkAll(group){
                let show = true,
                    shown = [];
                $.each(this.source.cols, (i, a) => {
                  if ( (a.showable !== false) && (a.group === group) && !a.fixed ){
                    if ( a.hidden ){
                      show = false;
                      return false;
                    }
                  }
                });
                $.each(this.source.cols, (i, a) => {
                  if ( (a.showable !== false) && (a.group === group) && !a.fixed ){
                    if ( (a.hidden && !show) || (!a.hidden && show) ){
                      shown.push(i);
                    }
                  }
                });
                if ( shown.length ){
                  this.table.show(shown, show);
                }
              }
            }
          },
          source: {
            cols: this.cols,
            titleGroups: this.titleGroups
          }
        });
      },
      edit(row, title, options){
        if ( !this.editable ){
          throw new Error("The table is not editable, you cannot use the edit function in bbn-table");
        }
        if ( !row ){
          this._addTmp();
          row = this.tmpRow;
        }
        this.editedRow = row;
        if ( this.editMode === 'popup' ){
          if ( typeof(title) === 'object' ){
            options = title;
            title = options.title || null;
          }
          let popup = $.extend({}, options ? options : {}, {
            source: {
              row: row,
              data: $.isFunction(this.data) ? this.data() : this.data
            },
            title: title || bbn._('Row edition')
          });
          if ( this.editor ){
            popup.component = this.editor;
          }
          else if ( this.url ){
            let me = this;
            popup.component = {
              data(){
                return {
                  fields: me.cols,
                  data: row
                }
              },
              template: '<bbn-form action="' + this.url + '" :schema="fields" :source="data"></bbn-form>'
            };
          }
          popup.afterClose = () => {
          //  this.currentData.push($.extend({}, this.tmpRow)); // <-- Error. This add a new row into table when it's in edit mode
            this._removeTmp();
            this.editedRow = false;
          };
          bbn.fn.log("beforePOPUP ", popup);
          this.getPopup().open(popup);
        }
      },
      getConfig(){
        return {
          limit: this.currentLimit,
          order: this.currentOrder,
          filters: this.currentFilters,
          hidden: this.currentHidden
        };
      },
      getColumnsConfig(){
        return JSON.parse(JSON.stringify(this.cols));
      },
      setConfig(cfg, no_storage){
        if ( cfg === false ){
          cfg = this.defaultConfig;
        }
        else if ( cfg === true ){
          cfg = this.getConfig();
        }
        if ( cfg && cfg.limit ){
          if ( this.filterable && cfg.filters && (this.currentFilters !== cfg.filters) ){
            this.currentFilters = cfg.filters;
          }
          if ( this.pageable && (this.currentLimit !== cfg.limit) ){
            this.currentLimit = cfg.limit;
          }
          if ( this.sortable && (this.currentOrder !== cfg.order) ){
            this.currentOrder = cfg.order;
          }
          if ( this.showable ){
            if ( (cfg.hidden !== undefined) && (cfg.hidden !== this.currentHidden) ){
              this.currentHidden = cfg.hidden;
            }
            $.each(this.cols, (i, a) => {
              let hidden = ($.inArray(i, this.currentHidden) > -1);
              if ( a.hidden !== hidden ){
                this.$set(this.cols[i], "hidden", hidden);
              }
            });
          }
          this.currentConfig = {
            limit: this.currentLimit,
            order: this.currentOrder,
            filters: this.currentFilters,
            hidden: this.currentHidden
          };
          if ( !no_storage ){
            this.setStorage(this.currentConfig);
          }

          this.$forceUpdate();
        }
      },
      /** @todo */
      remove(where){
        let idx;
        while ( (idx = bbn.fn.search(this.currentData, where)) > -1 ){
          this.currentData.splice(idx, 1);
        }
      },
      save(){
        this.savedConfig = this.jsonConfig;
      },
      select(){},
      /** @todo */
      add(data){
        this.currentData.push(data);
      },
      delete(index, confirm){
        if ( this.currentData[index] ){
          let ev = $.Event('delete');
          this.$emit('beforeDelete', this.currentData[index], ev);
          if ( !ev.isDefaultPrevented() ){
            if ( confirm ){
              this.getPopup().confirm(confirm, () => {
                this.currentData.splice(index, 1);
                this.$emit('delete', this.currentData[index], ev);
              })
            }
            else{
              this.currentData.splice(index, 1);
              this.$emit('delete', this.currentData[index], ev);
            }
          }
        }
      },
      insert(data, title, options){
        let d = data ? $.extend({}, data) : {};
        if ( this.uid && d[this.uid] ){
          delete d[this.uid];
        }
        this._addTmp(d);
        this.edit(this.tmpRow, title, options);
      },
      copy(data, title, options){
        bbn.fn.log("copy", arguments);
        let r = $.extend({}, data);
        if ( this.uid && r[this.uid] ){
          delete r[this.uid];
        }
        this._addTmp(r);
        this.edit(this.tmpRow, title, options);
      },
      checkSelection(index){
        bbn.fn.log("checkSelection");
        let idx = $.inArray(index, this.selectedRows),
            isSelected = false;
        if ( idx > -1 ){
          this.$emit('unselect', this.currentData[index]);
          this.selectedRows.splice(idx, 1);
        }
        else{
          this.$emit('select', this.currentData[index]);
          this.selectedRows.push(index);
          isSelected = true;
        }
        this.$emit('toggle', isSelected, this.currentData[index]);
      },
      /** Refresh the current data set */
      updateData(){
        this.currentExpanded = false;
        if ( this.isAjax && !this.isLoading ){
          this.isLoading = true;
          this._removeTmp();
          this.editedRow = false;
          this.$forceUpdate();
          this.$nextTick(() => {
            let data = {
              limit: this.currentLimit,
              start: this.start,
              data: this.data ? ($.isFunction(this.data) ? this.data() : this.data) : {}
            };
            if ( this.sortable ){
              data.order = this.currentOrder;
            }
            if ( this.filterable ){
              data.filters = this.currentFilters;
            }
            bbn.fn.post(this.source, data, (result) => {
              this.isLoading = false;
              if (
                !result ||
                result.error ||
                ((result.success !== undefined) && !result.success)
              ){
                alert(result && result.error ? result.error : "Error in updateData");
              }
              else{
                this.currentData = this._map(result.data || []);
                this.total = result.total || result.data.length || 0;
                if ( result.order ){
                  this.currentOrder.splice(0, this.currentOrder.length);
                  this.currentOrder.push({field: result.order, dir: (result.dir || '').toUpperCase() === 'DESC' ? 'DESC' : 'ASC'});
                }
              }
            })
          })
        }
        else if ( Array.isArray(this.source) ){
          this.currentData = this._map(this.source);
          if ( this.currentOrder.length ){
            this.currentData = bbn.fn.order(this.source, this.currentOrder[0].field, this.currentOrder[0].dir);
          }
          this.total = this.source.length;
        }
      },
      isSorted(col){
        if (
          this.sortable &&
          (col.sortable !== false) &&
          !col.buttons &&
          col.field
        ){
          let idx = bbn.fn.search(this.currentOrder, {field: col.field});
          if ( idx > -1 ){
            return this.currentOrder[idx];
          }
        }
        return false;
      },
      sort(col){
        if (
          !this.isLoading &&
          this.sortable &&
          col.field &&
          (col.sortable !== false)
        ){
          let f = col.field,
              pos = bbn.fn.search(this.currentOrder, {field: f});
          if ( pos > -1 ){
            if ( this.currentOrder[pos].dir === 'ASC' ){
              this.$set(this.currentOrder[pos], 'dir', 'DESC');
            }
            else{
              this.currentOrder.splice(0, this.currentOrder.length);
            }
          }
          else{
            this.currentOrder.splice(0, this.currentOrder.length);
            this.currentOrder.push({field: f, dir: 'ASC'});
          }
          this.updateData();
        }
      },
      updateTable(num){
        if ( !num ){
          num = 0;
        }
        if ( !this.isLoading && (num < 25) ){
          clearTimeout(this.updaterTimeout);
          this.updaterTimeout = setTimeout(() => {
            let trs = $("table.bbn-table-main:first > tbody > tr", this.$el);
            bbn.fn.log("trying to update table, attempt " + num);
            if ( this.colsLeft.length || this.colsRight.length ){
              trs.each((i, tr) =>{
                if ( $(tr).is(":visible") ){
                  bbn.fn.adjustHeight(
                    tr,
                    $("table.bbn-table-data-left:first > tbody > tr:eq(" + i + ")", this.$el),
                    $("table.bbn-table-data-right:first > tbody > tr:eq(" + i + ")", this.$el)
                  );
                }
              });
            }
            if (
              this.$refs.scroller &&
              $.isFunction(this.$refs.scroller.onResize)
            ){
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
          }, 100);
        }
      },
      /** Renders a cell according to column's config */
      render(data, column, index){
        let field = column && column.field ? column.field : '',
            value = data && column.field ? data[column.field] || '' : undefined;

        if ( column.render ){
          return column.render(data, index, column, value)
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
              return value ?
                bbn.fn.money(value) + (
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
        else if ( column.source ){
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
        return value;
      },
      cancel(){
        if ( this.tmpRow ){
          this._removeTmp();
        }
      },
      /** @todo */
      editTmp(data){
        if ( this.tmpRow ){
          data = $.extend(this.tmpRow, data);
        }
        return this;
      },
      saveTmp(){},
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
          if ( a.render ){
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
          else if ( a.source ){
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
          res.push(r);
        });
        return res;
      },
      reset(){
        this.ready = false;
        this.setConfig(false);
        this.$forceUpdate();
        this.$nextTick(() => {
          this.ready = true;
          this.init();
        })
      },
      /** @todo */
      addColumn(obj){
        if ( obj.aggregate && !$.isArray(obj.aggregate) ){
          obj.aggregate = [obj.aggregate];
        }
        this.cols.push(obj);
      },
      onResize(){
        this.init();
        this.updateTable();
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
          if ( (d.isGrouped || d.groupAggregated) && ($.inArray(d.link, this.currentExpanded) > -1) ){
            return true;
          }
          return false;
        }
      },
      toggleExpanded(idx){
        if ( this.currentData[idx] ){
          if ( this.allExpanded ){
            this.allExpanded = false;
          }
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
      isSelected(index){
        return this.selection && ($.inArray(index, this.selectedRows) > -1);
      },
      hasTd(data, tdIndex){
        if ( data.selection ){
          if ( tdIndex === 0 ){
            return false;
          }
          else if ( data.group || data.expander ){
            if ( tdIndex === 1 ){
              return false;
            }
          }
        }
        if ( data.group || data.expander ){
          if ( tdIndex === 0 ){
            return false;
          }
        }
        if ( data.group || data.expansion ){
          return false;
        }
        return true;
      },
      init(){
        let colsLeft = [],
            colsMain = [],
            colsRight = [],
            leftWidth = 0,
            numUnknown = 0,
            colButtons = false,
            isAggregated = false;
        if ( this.selection ){
          colsLeft.push({
            title: ' ',
            width: this.minimumColumnWidth,
            realWidth: this.minimumColumnWidth
          });
        }
        if ( this.hasExpander ){
          colsLeft.push({
            title: ' ',
            width: this.minimumColumnWidth,
            realWidth: this.minimumColumnWidth
          });
          leftWidth = this.minimumColumnWidth;
        }
        $.each(this.cols, (i, a) => {
          if ( !this.groupable || (this.group !== i) ){
            if ( a.aggregate && a.field ){
              isAggregated = a.field;
            }
            a.index = i;
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
                a.realWidth = this.minimumColumnWidth;
                numUnknown++;
              }
              if ( a.buttons ){
                colButtons = i;
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
        // We must arrive to 100% minimum
        if ( toFill > this.cols.length - this.currentHidden.length ){
          bbn.fn.log("bbn-table", "THERE IS TO FILL", toFill, numUnknown);
          if ( numUnknown ){
            let newWidth = Math.round(
              (toFill - (this.cols.length - this.currentHidden.length))
              / numUnknown
              * 100
            ) / 100;
            if ( newWidth < this.minimumColumnWidth ){
              newWidth = this.minimumColumnWidth;
            }
            $.each(this.cols, (i, a) => {
              if ( !a.hidden ){
                if ( !a.width ){
                  a.realWidth = newWidth + this.minimumColumnWidth;
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
        this.tableLeftWidth = bbn.fn.sum(colsLeft, 'realWidth', {hidden: true}, '!==') + this.numLeftVisible;
        this.tableMainWidth = bbn.fn.sum(colsMain, 'realWidth', {hidden: true}, '!==') + this.numMainVisible;
        this.tableRightWidth = bbn.fn.sum(colsRight, 'realWidth', {hidden: true}, '!==') + this.numRightVisible;
        this.colsLeft = colsLeft;
        this.colsMain = colsMain;
        this.colsRight = colsRight;
        this.colButtons = colButtons;
        this.isAggregated = isAggregated;
        this.$nextTick(() => {
          this.updateTable();
            this.$nextTick(() => {
            if ( this.$refs.scroller ){
              this.$refs.scroller.onResize();
            }
          })
        })
      },
      show(colIndexes, hide){
        if ( !$.isArray(colIndexes) ){
          colIndexes = [colIndexes];
        }
        $.each(colIndexes, (i, colIndex) => {
          if ( this.cols[colIndex] ){
            if ( (this.cols[colIndex].hidden && !hide) || (!this.cols[colIndex].hidden && hide) ){
              let idx = $.inArray(colIndex, this.currentHidden);
              if ( hide && (idx === -1) ){
                this.currentHidden.push(colIndex);
              }
              else if ( !hide && (idx > -1) ){
                this.currentHidden.splice(idx, 1);
              }
            }
          }
        });
        this.$forceUpdate();
        this.setConfig(true);
        this.init();
      },
      getEditableComponent(col, data){
        if ( col.editor ){
          return col.editor;
        }
        if ( col.type ){
          switch ( col.type ){
            case "date":
              return 'bbn-datepicker';
            case "email":
              return 'bbn-input';
            case "url":
              return 'bbn-input';
            case "number":
              return 'bbn-numeric';
            case "money":
              return 'bbn-numeric';
            case "bool":
            case "boolean":
              return 'bbn-checkbox';
          }
        }
        if ( col.source ){
          return 'bbn-dropdown';
        }
        return 'bbn-input';
      },
      getEditableOptions(col, data){
        let res = col.options ? (
          $.isFunction(col.options) ? col.options(col, data) : col.options
        ) : {};
        if ( col.type ){
          switch ( col.type ){
            case "date":
              break;
            case "email":
              $.extend(res, {type: 'email'});
              break;
            case "url":
              $.extend(res, {type: 'url'});
              break;
            case "number":
              break;
            case "money":
              break;
            case "bool":
            case "boolean":
              break;
          }
        }
        if ( col.source ){
          $.extend(res, {source: col.source});
        }
        return res;
      },
      focusRow(rowIdx){
        if ( (this.editable === 'inline') && (this.focusedRow !== rowIdx) ){
          this.focusedRow = rowIdx;
          if ( this.editedRow !== this.currentData[rowIdx] ){
            this.edit(this.currentData[rowIdx]);
          }
          bbn.fn.log("focus ok");
        }
      },
      blurRow(rowIdx){
        if ( (this.editable === 'inline') && (this.editedRow === this.currentData[rowIdx]) ){
          if ( this.colButtons === false ){
            this.save();
          }
          this.focusedRow = false;
          setTimeout(() => {
            if ( !this.focusRow ){
              this.editedRow = false;
            }
          }, 300)
          bbn.fn.log("blur ok");
        }
      },
    },

    created(){
      // Adding bbn-column from the slot
      if (this.$slots.default){
        for ( let node of this.$slots.default ){
          //bbn.fn.log("TRYING TO ADD COLUMN", node);
          if (
            node.componentOptions &&
            (node.componentOptions.tag === 'bbn-column')
          ){
            this.addColumn(node.componentOptions.propsData);
          }
          else if (
            (node.tag === 'bbn-column') &&
            node.data && node.data.attrs
          ){
            this.addColumn($.extend({}, node.data.attrs));
          }
        }
      }
      if ( this.columns.length ){
        $.each(this.columns.slice(), (i, a) => {
          this.addColumn(a);
        })
      }
      if ( this.defaultConfig.hidden === null ){
        let tmp = [];
        $.each(this.cols, (i, a) => {
          if ( a.hidden ){
            tmp.push(i);
          }
        });
        this.defaultConfig.hidden = tmp;
      }
      this.setConfig(false, true);
      this.initialConfig = this.jsonConfig;
      this.savedConfig = this.jsonConfig;
      let cfg = this.getStorage();
      this.setConfig(cfg ? cfg : false, true);
    },

    mounted(){
      this.ready = true;
      this.init();
      this.$forceUpdate();
      this.$nextTick(() => {
        this.updateData();
      });
    },

    watch: {
      editedRow: {
        deep: true,
        handler(newVal){
          bbn.fn.log("editedRow is changing", newVal);
        }
      },
      cols: {
        deep: true,
        handler(){
          this.init();
        }
      },
      currentLimit(){
        this.setConfig(true);
      },
      currentFilters: {
        deep: true,
        handler(){
          this.currentFilter = false;
          this.updateData();
          this.setConfig(true);
          this.$forceUpdate();
        }
      },
      currentOrder: {
        deep: true,
        handler(){
          this.setConfig(true);
          this.$forceUpdate();
        }
      },
      currentHidden: {
        deep: true,
        handler(){
          bbn.fn.log("CHaNGE IN CURRENT HIDEDEN");
          this.setConfig(true);
          this.$forceUpdate();
        }
      }
    },
    components: {
      'bbn-columns': {
        props: {
          width: {
            type: [String, Number],
          },
          render: {
            type: [String, Function]
          },
          title: {
            type: [String, Number],
            default: bbn._("Untitled")
          },
          ftitle: {
            type: String
          },
          tcomponent: {
            type: [String, Object]
          },
          icon: {
            type: String
          },
          cls: {
            type: String
          },
          type: {
            type: String
          },
          field: {
            type: String
          },
          fixed: {
            type: [Boolean, String],
            default: false
          },
          hidden: {
            type: Boolean
          },
          encoded: {
            type: Boolean,
            default: false
          },
          sortable: {
            type: Boolean,
            default: true
          },
          editable: {
            type: Boolean,
            default: true
          },
          filterable: {
            type: Boolean,
            default: true
          },
          resizable: {
            type: Boolean,
            default: true
          },
          showable: {
            type: Boolean,
            default: true
          },
          nullable: {
            type: Boolean,
          },
          buttons: {
            type: [Array, Function]
          },
          source: {
            type: [Array, Object, String]
          },
          required: {
            type: Boolean,
          },
          options: {
            type: [Object, Function],
            default(){
              return {};
            }
          },
          editor: {
            type: [String, Object]
          },
          maxLength: {
            type: Number
          },
          sqlType: {
            type: String
          },
          aggregate: {
            type: [String, Array]
          },
          component: {
            type: [String, Object]
          },
          mapper: {
            type: Function
          },
          group: {
            type: String
          }
        },
      }
    }
  });

})(window.jQuery, bbn);
