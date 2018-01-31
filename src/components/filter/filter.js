/**
 * Created by BBN on 10/02/2017.
 */
(function($, bbn, kendo){
  "use strict";
/**@todo add prefix for flexbox and inline flex http://ptb2.me/flexbox/ also for justify content and align items*/
  const
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
          case 'boolean':
            return 'boolean';
          case 'float':
          case 'decimal':
          case 'number':
          case 'money':
            return 'number';
          case 'date':
            return 'date';
          case 'datetime':
            return 'date';
          case 'time':
            return 'date';
          case 'enum':
          case 'enums':
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

   // var  borders = ['#414d40', '#5a6559', '#7f897e', '#6c7a78', '#515963']
   // var  borders = ['red', 'green', 'yellow', 'pink', 'blue']
  var borders = ['#e47777','#fa4a4a', '#8d0e0e','#b44f4f','#c16262'],
    bg_colors = ['rgba(228,119,119,0.2)', 'rgba(250,74,74,0.2)', 'rgba(141,14,14,0.2)', 'rgba(180,79,79,0.2)', 'rgba(193,98,98,0.2)'];


  Vue.component('bbn-filter', {
    mixins: [bbn.vue.basicComponent, bbn.vue.dataEditorComponent],
    name: 'bbn-filter',
    props: {
      value: {},
      operator: {},
      // Pre-existing conditions
      conditions: {
        type: Array,
        default(){
          return [];
        }
      },
      // Pre-chosen logic (AND or OR)
      logic: {
        type: String,
        default: 'AND'
      },
      // List of fields available for the filter
      fields: {
        type: [Object,Array],
        default(){
          return {}
        }
      },
      // ??
      num: {
        type: Number,
        default: 0
      },
      // ??
      index: {},
      // ??
      first: {},
      // The component used for a single filter
      component: {},
      // The component options used for a single filter
      componentOptions: {
        type: Object,
        default(){
          return {};
        }
      },
      // The column's value for a single column filter
      field: {
        type: String
      },
      // Is the component multi filter
      multi: {
        type: Boolean,
        default: true
      },
      // The type of data for the operators (see this.editorOperators)
      type: {
        type: String,
        default: 'string'
      }
    },
    data(){
      return {
        currentLogic: this.logic,
        currentValue: this.value !== undefined ? this.value : null,
        currentOperator: this.operator !== undefined ? this.value : null
      };
    },
    mounted(){
      //bbn.fn.analyzeContent(this.$el, true);
    },
    updated(){
      //bbn.fn.analyzeContent(this.$el, true);
    },
    computed: {
      border_color(){
        if ( this.num > borders.length){
          return borders[this.num % borders.length]
        }
        else{
          return borders[this.num]
        }
      },
      is_not_root: function(){
        //bbn.fn.log("ISNOT ROTT", $(this.$el).parents(".bbn-filter-control"));
        return $(this.$parent.$el).hasClass("bbn-filter-control");
      },
    },
    methods: {
      over(e){
        //bbn.fn.log('bg_color', this.bg_color)
        $(e.target).css('color' , 'red');
        $(e.target).parent().parent().find('.bbn-filter-main').eq(0).css('background-color', 'rgba(158,158,158, 0.3)' );
      },
      out(e){
        $(e.target).css('color' , 'inherit');
        $(e.target).parent().parent().find('.bbn-filter-main').eq(0).css('background-color', 'inherit');
      },
      setCondition(obj){
        if ( obj.field && obj.operator ){
          bbn.fn.log("setCondition", obj, this.multi);
          obj.time = (new Date()).getTime();
          if ( this.multi ){
            this.conditions.push(obj);
          }
          else{
            this.$emit('set', obj)
          }
        }
      },
      hasFields(){
        return this.fields && Object.keys(this.fields).length;
      },
      condition_text: function(cd){
        let st = '';
        if ( cd && cd.field ){
          let index = bbn.fn.search(this.fields, {field: cd.field});
          if ( index > -1 ){
            let f = this.fields[index];
            st += '<strong>' +
              (f.ftitle ? f.ftitle : (f.title ? f.title : cd.field)) +
              '</strong> ' +
              this.editorOperators[get_operator_type(f)][cd.operator] +
              ' <em>';
            if ( cd.value ){
              if ( cd.value === true ){
                st += 'true';
              }
              else if ( f.source ){
                if ( $.isArray(f.source) ){
                  st += bbn.fn.get_field(f.source, 'value', cd.value, 'text');
                }
                else if ( typeof f.source === 'object' ){
                  st += f.source[cd.value];
                }
              }
              else{
                st += cd.value;
              }
            }
            else if ( cd.value === 0 ){
              st += '0';
            }
            else if ( cd.value === false ){
              st += 'false';
            }
            st += '</em>';
          }
        }
        return st;
      },
      delete_full_condition(idx){
        this.$emit('unset', this.conditions.splice(idx, 1));
      },
      delete_condition: function(condition){
        bbn.fn.log(condition);
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
          if ( del(this.conditions) ){
            this.$forceUpdate();
            this.$emit('unset', condition);
          }
        }
      },
      add_group: function(idx){
        this.conditions.splice(idx, 1, {
          logic: this.currentLogic,
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
        mixins: [bbn.vue.dataEditorComponent],
        props: {
          fields: {},
          field: {
            type: String
          },
          type: {
            type: String
          },
          operator: {
            type: String
          },
          value: {},
          component: {

          },
          componentOptions: {
            type: Object,
            default(){
              return {}
            }
          },
        },
        data(){
          return {
            currentField: this.field || '',
            currentType: this.type || '',
            currentValue: this.value || '',
            currentComponent: this.component || '',
            currentComponentOptions: this.componentOptions,
            currentOperator: this.operator || '',
            currentOperators: [],
            has_group: false,
            has_condition: true,
            items: [],
            cfg: {}
          };
        },
        computed: {
          operators(){
            let cfg = this.editorGetComponentOptions({field: this.currentField, type: this.currentType, value: this.currentValue});
            return this.currentType && this.editorOperators[cfg.type || this.currentType] ?
              this.editorOperators[cfg.type || this.currentType] : [];
          },
          no_value(){
            return this.editorHasNoValue(this.operator);
          },
          columns(){
            var r = [];
            if ( $.isArray(this.fields) ){
              $.each(this.fields, (i, a) => {
                if ( a.field ){
                  r.push({
                    text: a.ftitle ? a.ftitle : (a.title ? a.title : a.field),
                    value: a.field
                  });
                }
              })
            }
            else{
              for ( var n in this.fields ){
                r.push(n);
              }
            }
            return r;
          },
          currentFullField(){
            if ( this.currentField ){
              let idx = bbn.fn.search(this.fields, {field: this.currentField});
              if ( idx > -1 ){
                return this.fields[idx];
              }
            }
            return {};
          }
        },
        methods: {
          validate(){
            if ( this.currentField && this.currentOperator && (this.editorHasNoValue(this.currentOperator) || this.currentValue) ){
              var tmp = {
                field: this.currentField,
                operator: this.currentOperator
              };
              if ( !this.editorHasNoValue(this.currentOperator) ){
                tmp.value = this.currentValue;
              }
              this.$emit('validate', tmp);
            }
            else{
              bbn.fn.alert("Valeur obligatoire, sinon vous pouvez choisir d'autres opérateurs si vous cherchez un élément nul ou vide");
            }
          }
        },
        created(){
          if ( this.type && this.editorOperators[this.type] ){
            this.currentOperators = this.editorOperators[this.type];
          }
        },
        mounted(){
          //bbn.fn.log("FILTER FORM MOUNTED", this);
        },
        watch: {
          currentField(newVal){
            let index = bbn.fn.search(this.fields, {field: newVal});
            if ( index > -1 ){
              let o = this.editorGetComponentOptions(this.fields[index]);
              if ( o ){
                bbn.fn.log("onCURRENTCOL", o);
                this.currentType = o.type;
                this.currentComponent = o.component;
                this.currentComponentOptions = o.componentOptions;
              }
            }
          }
          /*
          currentColumn(newVal){
            let ds = [],
                idx = bbn.fn.search(this.fields, {field: newVal});
            this.cfg = {};
            if ( idx > -1 ){
              let c                = this.fields[idx],
                  currentComponent = this.vueComponent;
              this.currentType = get_operator_type(c);
              if ( !newVal ){
                this.currentOperator = '';
                this.vueComponent = '';
                this.currentType = '';
              }
              else{
                bbn.fn.log("TYPE!!", c);
                switch ( c.currentType ){
                  case 'int':
                    if ( !c.signed && (c.maxlength == 1) ){
                      this.vueComponent = 'radio';
                    }
                    else if ( c.maxlength == 10 ){
                      this.vueComponent = 'tree-input';
                      this.cfg.source = 'options/tree';
                    }
                    else{
                      if ( !c.signed ){
                        this.cfg.min = 0;
                      }
                      this.cfg.max = 1;
                      for ( var i = 0; i < c.maxlength; i++ ){
                        this.cfg.max = this.cfg.max * 10;
                      }
                      this.cfg.max--;
                      this.vueComponent = 'numeric';
                    }
                    break;
                  case 'float':
                  case 'decimal':
                    this.vueComponent = 'numeric';
                    var tmp = c.maxlength.split(","),
                        max = parseInt(tmp[0]) - parseInt(tmp[1]);
                    this.cfg.format = 'n' + tmp[1];
                    if ( !c.signed ){
                      this.cfg.min = 0;
                    }
                    this.cfg.max = 1;
                    for ( var i = 0; i < max; i++ ){
                      this.cfg.max = this.cfg.max * 10;
                    }
                    this.cfg.max--;
                    this.vueComponent = 'numeric';
                    break;
                  case 'enum':
                    var tmp = eval('[' + c.extra + ']');
                    if ( $.isArray(tmp) ){
                      this.cfg.dataSource = $.map(tmp, function (a){
                        return {
                          text: a,
                          value: a
                        };
                      });
                      this.cfg.optionLabel = bbn._("Choisir une valeur");
                      this.vueComponent = 'dropdown';
                    }
                    break;
                  case 'date':
                    this.vueComponent = 'datepicker';
                    break;
                  case 'datetime':
                    this.vueComponent = 'datetimepicker';
                    break;
                  case 'time':
                    this.vueComponent = 'timepicker';
                    break;
                  default:
                    this.vueComponent = 'input';
                    break;
                }
              }

              if ( currentComponent !== this.vueComponent ){
                if ( this.$refs.value && this.$refs.value.widget ){
                  this.$refs.value.widget.destroy();
                  var $ele = $(this.$refs.value.$el);
                  kendo.unbind($ele);
                  $ele.prependTo($ele.closest(".bbn-db-value")).nextAll().remove();
                }
                this.$nextTick(() =>{
                  this.$refs.operator.widget.select(0);
                  this.$refs.operator.widget.trigger("change");
                });
              }
            }
          }
          */
        }
      }
    }
  });

})(jQuery, bbn, kendo);