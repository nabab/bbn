/**
 * Created by BBN Solutions.
 * User: Loredana Bruno
 * Date: 20/02/17
 * Time: 16.21
 */


//Markdown editor use simpleMDe
(function($, bbn, JSONEditor){
  "use strict";

  Vue.component('bbn-json-editor', {
    mixins: [bbn.vue.fullComponent],
    template: '#bbn-tpl-component-json-editor',
    methods: {
      getOptions(){
        const vm = this;
        let cfg = bbn.vue.getOptions(vm);
        if ( vm.readonly ){
          cfg.modes = [];
          cfg.mode = 'view';
        }
        else{
          cfg.onChange = () => {
            var v = vm.widget.getText();
            vm.$refs.input.value = v;
            //$(vm.$refs.input).trigger("change");
            bbn.fn.log("changing", vm, v);
            //vm.$set(vm, "value", vm.widget.getText());
            vm.$emit("change", v);
            vm.$emit("input", v);
          };
        }
        return cfg;
      }
    },
    props: {
      value: {
        type: String,
        default: "{}"
      },
      cfg: {
        type: Object,
        default(){
          return {
            onEditable: null,
            onError: null,
            onModeChange: null,
            escapeUnicode: false,
            sortObjectKeys: false,
            history: true,
            mode: "tree",
            modes: ["tree", "view", "form", "code", "text"],
            name: null,
            schema: null,
            schemaRefs: null,
            search: true,
            indentation: 2,
            theme: null,
            templates: [],
            autocomplete: null
          };
        }
      }
    },
    mounted(){
      let cfg = this.getOptions();
      bbn.fn.log("VALUE", this.value);
      this.widget = new JSONEditor(this.$refs.element, cfg);
      this.widget.setText(this.value);
      this.$emit("ready", this.value);
    },
    data(){
      return $.extend({
        widgetName: "jsoneditor"
      }, bbn.vue.treatData(this));
    },
    watch: {
      value(newVal){
        const vm = this;
        if ( vm.widget.getText() !== newVal ){
          vm.widget.setText(newVal);
        }
      }
    }
  });

})(window.jQuery, window.bbn, window.JSONEditor);