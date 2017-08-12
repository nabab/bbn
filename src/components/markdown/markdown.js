/**
 * Created by BBN Solutions.
 * User: Loredana Bruno
 * Date: 20/02/17
 * Time: 16.21
 */


//Markdown editor use simpleMDe
(function($, bbn, SimpleMDE){
  "use strict";

  Vue.component('bbn-markdown', {
    mixins: [bbn.vue.fullComponent],
    template: '#bbn-tpl-component-markdown',
    methods: {
      test: function(){
        bbn.fn.log("test");
      }
    },
    props: {
      cfg: {
        type: Object,
        default: function(){
          return {
            spellChecker: false,
            indentWithTabs: true,
            initialValue: '',
            insertTexts: {
              horizontalRule: ["", "\n\n-----\n\n"],
              image: ["![](http://", ")"],
              link: ["[", "](http://)"],
              table: ["", "\n\n| Column 1 | Column 2 | Column 3 |\n| -------- | -------- | -------- |\n| Text     | Text      | Text     |\n\n"],
            },
            renderingConfig: {
              singleLineBreaks: true,
              codeSyntaxHighlighting: true,
            },
            status: false,
            tabSize: 2,
            toolbarTips: true,
            shortcuts: {
              drawTable: "Cmd-Alt-T"
            },

          };
        }
      },
      toolbar: {
        type: Array
      },
      hideIcons: {
        type: Array
      }
    },
    mounted(){
      let cfg = $.extend(vm.getOptions(), {
        change: function(e){
          vm.emitInput(vm.widget.value());
          return true
        }
      });
      this.widget = new SimpleMDE($.extend({
        element: this.$refs.element
      }, this.getOptions()));
      this.widget.codemirror.on("change", () => {
        this.emitInput(this.widget.value());
      });
      this.$emit("ready", this.value);
    },
    data: function(){
      return $.extend({
        widgetName: "SimpleMDE",
      }, bbn.vue.treatData(this));
    },
  });

})(jQuery, bbn, SimpleMDE);