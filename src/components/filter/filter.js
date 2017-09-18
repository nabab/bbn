/**
 * Created by BBN on 10/02/2017.
 */
(function($, bbn, kendo){
  "use strict";

  let
    // List of difference operators
    has_no_value = function(op){
      return $.inArray(op, bbn.var.noValueOperators) > -1;
    },
    get_operator_type = function(field){
      if ( typeof field === 'object' ){
        switch ( field.type ){
          case 'int':
            // maxlength is a string!
            if ( field.maxlength == 1 ){
              return 'boolean';
            }
            if ( (field.maxlength == 10) && field.keys ){
              return 'enums';
            }
            return 'number';
          case 'float':
          case 'decimal':
            return 'number';
          case 'date':
            return 'date';
          case 'datetime':
            return 'date';
          case 'time':
            return 'date';
          case 'enum':
            return 'enums';
          default:
            return 'string';
        }
      }
    },
    get_component_type = function(sqlType){
      switch ( sqlType ){
        case 'int':
        case 'float':
        case 'decimal':
          return 'numeric';
          return 'numeric';
        case 'date':
          return 'datepicker';
        case 'datetime':
          return 'datetimepicker';
        case 'time':
          return 'timepicker';
        default:
          return 'input';
      }
    };

  Vue.component('bbn-filter', {
    template: '#bbn-tpl-component-filter',
    name: 'bbn-filter',
    props: {
      conditions: {
        type: Array,
        default(){
          return [];
        }
      },
      concat: {

      },
      fields: {
        type: Object,
        default(){
          return {}
        }
      },
      num: {
        type: Number,
        default: 0
      },
      index: {},
      first: {},
      component: {},
      column: {
        type: String
      },
      multi: {
        type: Boolean,
        default: true
      },
      type: {
        type: String,
        default: 'string'
      }
    },
    data: function(){
      return {
        concat_value: this.$options.propsData.concat
      };
    },
    mounted: function(){
      //bbn.fn.analyzeContent(this.$el, true);
    },
    updated: function(){
      //bbn.fn.analyzeContent(this.$el, true);
    },
    computed: {
      is_not_root: function(){
        //bbn.fn.log("ISNOT ROTT", $(this.$el).parents(".bbn-filter-control"));
        return $(this.$parent.$el).hasClass("bbn-filter-control");
      },
    },
    methods: {
      hasFields(){
        return this.fields && Object.keys(this.fields).length;
      },
      condition_text: function(idx){
        var cd = this.conditions[idx],
            st = '<strong>' + cd.column + '</strong> ' + bbn.var.filters[get_operator_type(this.fields[cd.column])][cd.operator] + ' <em>';
        if ( cd.value ){
          st += cd.value === true ? 'true' : cd.value;
        }
        else if ( cd.value === 0 ){
          st += '0';
        }
        st += '</em>';
        return st;
      },
      delete_condition: function(idx){
        var vm = this;
        if ( vm.conditions[idx] ){
          if ( vm.conditions[idx].conditions && vm.conditions[idx].conditions.length ){
            bbn.fn.confirm(bbn._("Êtes-vous sûr de vouloir supprimer ce groupe de conditions?"), function(){
              vm.conditions.splice(idx, 1);
            })
          }
          else{
            vm.conditions.splice(idx, 1);
          }
        }
      },
      add_group: function(idx){
        this.conditions.splice(idx, 1, {
          concat: this.concat_value,
          conditions: [this.conditions[idx]]
        })
      },
      delete_group: function(){
        this.$parent.conditions.splice(idx, 1);
      },
    },
    components: {
      'bbn-filter-form': {
        name: 'bbn-filter-form',
        props: {
          fields: {},
          column: {
            type: String
          },
          component: {

          },
          type: {
            type: String
          },
        },
        mounted: function(){
          //bbn.fn.log("FILTER FORM MOUNTED", this);
        },
        methods: {
          validate: function(){
            if ( this.column && this.operator && (has_no_value(this.operator) || this.value) ){
              var tmp = {
                column: this.column,
                operator: this.operator
              };
              if ( !has_no_value(this.operator) ){
                tmp.value = this.value;
              }
              this.$parent.conditions.push(tmp);
              this.value = '';
              this.$refs.column.widget.select(0);
              this.$refs.column.widget.trigger("change");
            }
            else{
              bbn.fn.alert("Valeur obligatoire, sinon vous pouvez choisir d'autres opérateurs si vous cherchez un élément nul");
            }
          }
        },
        data: function (){
          return {
            currentColumn: this.column || '',
            currentType: this.type || '',
            currentComponent: this.component || '',
            operator: this.operator || '',
            value: this.value || '',
            has_group: false,
            has_condition: true,
            vueComponent: '',
            items: [],
            currentOperators: this.type && bbn.var.filters[this.type] ? bbn.var.filters[this.type] : [],
            cfg: {}
          };
        },
        computed: {
          operators(){
            return bbn.var.filters;
          },
          no_value: function(){
            return has_no_value(this.operator);
          },
          columns: function(){
            var r = [];
            for ( var n in this.fields ){
              r.push(n);
            }
            return r;
          },
        },
        watch: {
          column: function(newVal, oldVal){
            var vm = this,
                ds = [];
            vm.cfg = {};
            if ( vm.fields[newVal] ){
              var c = vm.fields[newVal],
                  currentComponent = vm.vueComponent;
              vm.type = get_operator_type(c);
              if ( bbn.var.filters[vm.type] ){
                for ( var n in bbn.var.filters[vm.type] ){
                  if ( c.null || ( (n !== 'isnull') && (n !== 'isnotnull')) ){
                    ds.push({
                      text: bbn.var.filters[vm.type][n],
                      value: n
                    });
                  }
                }
              }
            }
            vm.operators = ds;
            if ( !newVal ){
              vm.operator = '';
              vm.vueComponent = '';
              vm.type = '';
            }
            else{
              bbn.fn.log("TYPE!!", c);
              switch ( c.type ){
                case 'int':
                  if ( !c.signed && (c.maxlength == 1) ){
                    vm.vueComponent = 'radio';
                  }
                  else if ( c.maxlength == 10 ){
                    vm.vueComponent = 'tree-input';
                    vm.cfg.source = 'options/tree';
                  }
                  else{
                    if ( !c.signed ){
                      vm.cfg.min = 0;
                    }
                    vm.cfg.max = 1;
                    for ( var i = 0; i < c.maxlength; i++ ){
                      vm.cfg.max = vm.cfg.max * 10;
                    }
                    vm.cfg.max--;
                    vm.vueComponent = 'numeric';
                  }
                  break;
                case 'float':
                case 'decimal':
                  vm.vueComponent = 'numeric';
                  var tmp = c.maxlength.split(","),
                      max = parseInt(tmp[0]) - parseInt(tmp[1]);
                  vm.cfg.format = 'n' + tmp[1];
                  if ( !c.signed ){
                    vm.cfg.min = 0;
                  }
                  vm.cfg.max = 1;
                  for ( var i = 0; i < max; i++ ){
                    vm.cfg.max = vm.cfg.max * 10;
                  }
                  vm.cfg.max--;
                  vm.vueComponent = 'numeric';
                  break;
                case 'enum':
                  var tmp = eval('[' + c.extra + ']');
                  if ( $.isArray(tmp) ){
                    vm.cfg.dataSource = $.map(tmp, function(a){
                      return {
                        text: a,
                        value: a
                      };
                    });
                    vm.cfg.optionLabel = bbn._("Choisir une valeur");
                    vm.vueComponent = 'dropdown';
                  }
                  break;
                case 'date':
                  vm.vueComponent = 'datepicker';
                  break;
                case 'datetime':
                  vm.vueComponent = 'datetimepicker';
                  break;
                case 'time':
                  vm.vueComponent = 'timepicker';
                  break;
                default:
                  vm.vueComponent = 'input';
                  break;
              }
            }

            if ( currentComponent !== vm.vueComponent ){
              if ( vm.$refs.value && vm.$refs.value.widget ){
                vm.$refs.value.widget.destroy();
                var $ele = $(vm.$refs.value.$el);
                kendo.unbind($ele);
                $ele.prependTo($ele.closest(".bbn-db-value")).nextAll().remove();
              }
              vm.$nextTick(function(){
                vm.$refs.operator.widget.select(0);
                vm.$refs.operator.widget.trigger("change");
              });
            }
          }
        }
      }
    }
  });

})(jQuery, bbn, kendo);