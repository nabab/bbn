/**
 * Created by BBN on 10/02/2017.
 */
(function($, bbn, kendo){
  "use strict";

  kendo.ui.AutoComplete.prototype.options.autoWidth = true;

  Vue.component('bbn-autocomplete', {
    template: '#bbn-tpl-component-autocomplete',
    mixins: [bbn.vue.vueComponent, bbn.vue.dataSourceComponent],
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
      ignoreCase: {
        type: Boolean,
        default: true
      },
      template: {
        type: [String, Function]
      },
      virtual: {
        type: [Boolean, Object],
        default: false
      }
    },
    data(){
      return {
        widgetName: 'kendoAutoComplete',
        filterValue: '',
      };
    },
    methods: {
      autocompleteSearch(e){
        bbn.fn.log("VAL", e.target.value);
        this.filterValue = e.target.value;
        if ( !this.force ){
          this.emitInput(this.filterValue);
        }
      },
      autocompleteBlur(e) {
        bbn.fn.log("BLUR", e.target.value, this.widget);
      },
      listHeight(){
        let $ele = $(this.$refs.element),
            pos = $ele.offset(),
            h = $ele.height();
        return pos ? $(window).height() - pos.top - h - 30 : 0;
      },
      getOptions(){
        let cfg = {
              dataSource: this.dataSource,
              dataTextField: this.sourceText,
              dataValueField: this.sourceValue,
              filter: this.filter,
              suggest: this.suggest,
              clearButton: this.clearButton,
              ignoreCase: this.ignoreCase,
              virtual: this.virtual
            };

        if ( this.template ){
          cfg.template = (e) => {
            return this.template(e);
          };
        }
        if ( this.delay ){
          cfg.delay = this.delay;
        }

        if ( cfg.dataSource && !$.isArray(cfg.dataSource) ){
          cfg.dataSource.options.serverFiltering = true;
          cfg.dataSource.options.serverGrouping = true;
        }
        $.extend(cfg, this.widgetOptions);
        cfg.select = (e) => {
          bbn.fn.log("SELECT", e.dataItem.toJSON()[cfg.dataValueField]);
          this.emitInput(e.dataItem.toJSON()[cfg.dataValueField]);
        };
        /*
        if ( !cfg.height ){
          cfg.height = this.listHeight();
        }
        else{
          bbn.fn.log("Height is defined: " + cfg.height);
        }
        */
        return cfg;
      }
    },
    mounted(){
      let $ele = $(this.$refs.element);
      this.widget = $ele.kendoAutoComplete(this.getOptions()).data("kendoAutoComplete");
      bbn.fn.log("OPTIONS", this.getOptions());
      $(window).resize(() => {
        this.widget.setOptions({
          height: this.listHeight()
        });
      });
    },
    computed: {
      dataSource(){
        if ( this.source ){
          return bbn.vue.toKendoDataSource(this);
        }
        return [];
      }
    },
    watch: {
      dataSource(newDataSource){
        this.widget.setDataSource(newDataSource);
      }
    }
  });

})(jQuery, bbn, kendo);
