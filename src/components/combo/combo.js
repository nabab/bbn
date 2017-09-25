/**
 * Created by BBN on 10/02/2017.
 */
(function($, bbn, kendo){
  "use strict";

  kendo.ui.ComboBox.prototype.options.autoWidth = true;

  Vue.component('bbn-combo', {
    mixins: [bbn.vue.fullComponent, bbn.vue.dataSourceComponent],
    template: '#bbn-tpl-component-combo',
    props: {
      delay: {
        type: Number
      },
      clearButton: {
        type: Boolean,
        default: true
      },
      filter: {
        type: String,
        default: "startswith"
      },
      minLength: {
        type: Number
      },
      force: {
        type: Boolean,
        default: false
      },
      enforceMinLength: {
        type: Boolean,
        default: false
      },
      suggest: {
        type: Boolean,
        default: false
      },
      highlightFirst: {
        type: Boolean,
        default: true
      },
      ignoreCase: {
        type: Boolean,
        default: true
      },
      syncTyped: {
        type: Boolean,
        default: true
      },
      cascade: {
        type: [Boolean, Object],
        default: false
      },
      cfg: {
        type: Object,
        default: function(){
          return {};
        }
      }
    },
    data: function(){
      return $.extend({
        widgetName: "kendoComboBox"
      }, bbn.vue.treatData(this));
    },
    methods: {
      getOptions(){
        let cfg = {
          valuePrimitive: true,
          dataSource: this.dataSource,
          dataTextField: this.sourceText,
          dataValueField: this.sourceValue,
          delay: this.delay,
          filter: this.filter,
          suggest: this.suggest,
          clearButton: this.clearButton,
          ignoreCase: this.ignoreCase,
          highlightFirst: this.highlightFirst,
          virtual: this.virtual,
          cascade: this.cascade,
          syncValueAndText: this.syncTyped,
          change: () => {
            this.emitInput(this.$refs.element.value)
          }
        };
        if ( this.placeholder ){
          cfg.placeholder = this.placeholder;
        }
        if ( this.template ){
          cfg.template = e => {
            return this.template(e);
          };
        }
        else{
          cfg.template = '<span>#= text #</span>'
        }
        if ( cfg.dataSource && !Array.isArray(cfg.dataSource) ){
          cfg.dataSource.options.serverFiltering = true;
          cfg.dataSource.options.serverGrouping = true;
        }
        return bbn.vue.getOptions2(this, cfg);
      }
    },
    mounted: function(){
      this.widget = $(this.$refs.element).kendoComboBox(this.getOptions()).data("kendoComboBox");
      this.$emit("ready", this.value);
    }
  });
})(jQuery, bbn, kendo);
