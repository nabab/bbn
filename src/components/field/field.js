/**
 * Created by BBN on 15/02/2017.
 */
(($, bbn) => {
  "use strict";

  /**
   * Classic input with normalized appearance
   */
  Vue.component('bbn-field', {
    mixins: [bbn.vue.basicComponent],
    props: {
      data: {},
      value: {},
      mode: {
        type: String,
        default: 'read'
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
      encoded: {
        type: Boolean,
        default: false
      },
      editable: {
        type: Boolean,
        default: true
      },
      nullable: {
        type: Boolean,
      },
      source: {
        type: [Array, Object, String]
      },
      required: {
        type: Boolean,
      },
      index: {
        type: Number,
        default: 0
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
      component: {
        type: [String, Object]
      },
    },
    data(){
      return {
        renderedComponent: false,
        renderedContent: '',
        renderedOptions: {}
      }
    },
    computed: {
      actualData(){
        if ( this.data ){
          return this.data;
        }
        if ( this.field && (this.value !== undefined) ){
          let d = {};
          d[this.field] = this.value;
          return d;
        }
      },
      actualValue(){
        return this.value === undefined ? (this.data && this.field && (this.value === undefined) ? this.data[this.field] || '' : undefined) : this.value;
      }
    },
    methods: {
      init(){
        this.renderedOptions = {};
        if ( this.field ){
          if ( (this.mode === 'write') && this.editable ){
            if ( this.editor ){
              return this.editor;
            }

            else if ( this.render !== undefined ){
              this.renderedComponent = 'div';
              this.renderedContent = this.render(this.actualData, this.index, this.field, this.value);
            }
            else if ( this.type ){
              switch ( this.type ){
                case "date":
                  this.renderedComponent = 'bbn-datepicker';
                  break;
                case "time":
                  this.renderedComponent = 'bbn-timepicker';
                  break;
                case "email":
                  this.renderedComponent ='bbn-input';
                  break;
                case "url":
                  this.renderedComponent = 'bbn-input';
                  break;
                case "number":
                  this.renderedComponent = 'bbn-numeric';
                  break;
                case "money":
                  this.renderedComponent = 'bbn-numeric';
                  break;
                case "bool":
                case "boolean":
                  this.renderedComponent = 'bbn-checkbox';
                  break;
              }
            }
            else if ( this.source ){
              this.renderedComponent = 'bbn-dropdown';
              this.renderedOptions.source = this.source;
            }
            else{
              this.renderedComponent  = 'bbn-input'
            }
            if( this.renderedComponent !== undefined){
              this.renderedOptions.value = this.value
            }
          }
          else {

            if ( this.component ){
              this.renderedComponent = this.component;
              this.renderedContent = this.render(this.actualData, this.index, this.field, this.value);
            }
            else if ( this.render !== undefined ){
              this.renderedComponent = 'div';
              this.renderedContent = this.render(this.actualData, this.index, this.field, this.value);
            }
            else if ( this.type ){
              switch ( this.type ){
                case "date":
                  return this.renderedComponent = 'bbn-datepicker';
                case "time":
                  return this.renderedComponent = 'bbn-timepicker';
                case "email":
                  this.renderedComponent ='bbn-input';
                  break;
                case "url":
                  return this.renderedComponent = 'bbn-input';
                case "number":
                  return this.renderedComponent = 'bbn-numeric';
                case "money":
                  return this.renderedComponent = 'bbn-numeric';
                case "bool":
                case "boolean":
                  return this.renderedComponent = 'bbn-checkbox';
              }
            }
            if( this.renderedComponent !== undefined){
              this.renderedOptions.value = this.value
            }
          }
        }
      },
    },
    watch:{
     /* value(val, oldVal){
        if(val !== oldVal){
          return renderedOptions.
        }
      }*/
    },
    created(){
      this.init();
    }
  });

})(jQuery, bbn, kendo);
