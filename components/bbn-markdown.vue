<template>
  <div class="bbn-markdown">
    <textarea :value="value"
              :name="name"
              ref="element"
              :disabled="disabled ? true : false"
              :required="required ? true : false"
    ></textarea>
  </div>
</template>
<script>
  var SimpleMDE =  require('simplemde');
  export default {
    name:'bbn-markdown',
    mixins: [bbn.vue.vueComponent],
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
    mounted: function(){
      var vm = this,
        cfg = $.extend(vm.getOptions(), {
          change: function(e){
            vm.update(vm.widget.value());
            return true
          }
        });

      vm.widget = new SimpleMDE($.extend({
        element: vm.$refs.element
      }, vm.getOptions()));
      vm.widget.codemirror.on("change", function(){
        vm.update(vm.widget.value());
      });

    },
    data: function(){
      return $.extend({
        widgetName: "SimpleMDE",
      }, bbn.vue.treatData(this));
    },
  }
</script>
<style>
  .bbn-markdown{
    .editor-toolbar{
      a,a.active,a:hover{
        color: inherit !important;
      }
    }
  }
</style>
