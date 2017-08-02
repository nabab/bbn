<template>
<div class="bbn-code"  style="position: relative; overflow: auto">
  <div style="position: absolute; width: 25px; height: 25px; z-index: 2" class="bbn-bg-black bbn-p bbn-middle" ref="theme_button">
    <i class="fa fa-navicon"> </i>
  </div>
  <div ref="code"></div>
  <input ref="input" type="hidden" v-bind:value="value" :name="name" :disabled="disabled ? true : false" :required="required ? true : false">
</div>
</template>
<script>
  var CodeMirror =  require('codemirror');


  const themes = ["3024-day","3024-night","ambiance-mobile","ambiance","base16-dark","base16-light","blackboard","cobalt","eclipse","elegant","erlang-dark","lesser-dark","mbo","midnight","monokai","neat","night","paraiso-dark","paraiso-light","pastel-on-dark","rubyblue","solarized","the-matrix","tomorrow-night-eighties","twilight","vibrant-ink","xq-dark","xq-light"];


  var themeIndex = 0,
      getMode = function(mode){
        var tmp = {
          php: {
            mode: 'application/x-httpd-php',
            cfg: {}
          },
          js: {
            mode: "javascript",
            cfg: {
              lint: true,
              lintWith: CodeMirror.javascriptValidator,
              gutters: [
                "CodeMirror-linenumbers",
                "CodeMirror-foldgutter",
                "CodeMirror-lint-markers"
              ],
              /*
               extraKeys: {
               "'.'": function(cm){
               cm.showHint();
               }
               }
               */
            }
          },
          css: {
            mode: "text/css",
            cfg: {
              lint: true,
              gutters: [
                "CodeMirror-linenumbers",
                "CodeMirror-foldgutter",
                "CodeMirror-lint-markers"
              ]
            }
          },
          less: {
            mode: "text/x-less",
            cfg: {
              lint: true,
              gutters: [
                "CodeMirror-linenumbers",
                "CodeMirror-foldgutter",
                "CodeMirror-lint-markers"
              ]
            }
          },
          json: {
            mode: "application/json",
            cfg: {
              lint: true,
              gutters: [
                "CodeMirror-linenumbers",
                "CodeMirror-foldgutter",
                "CodeMirror-lint-markers"
              ]
            }
          },
          html: {
            mode: "html",
            autoCloseTags: true,
            extraKeys: {
              "Ctrl-J": "toMatchingTag"
            }
          },
          vue: {
            mode: "text/x-vue",
          }
        };
        return tmp[mode] ? tmp[mode] : false;
      };

  export default {
    name:'bbn-code',
mixins: [bbn.vue.vueComponent],


    props: {
      mode: {},
      theme: {},
      cfg: {
        type: Object,
        default: function(){
          var vm = this;
          return {
            theme: "pastel-on-dark",
            mode: 'php',
            lineNumbers: true,
            tabSize: 2,
            //value: "",
            lineWrapping: true,
            //readOnly: false,
            matchBrackets: true,
            autoCloseBrackets: true,
            showTrailingSpace: true,
            styleActiveLine: true,
            /*
            keydown: false,
            save: false,
            change: false,
            changeFromOriginal: false,
            */
            foldGutter: true,
            selections: [],
            marks: [],
            gutters: [
              "CodeMirror-linenumbers",
              "CodeMirror-foldgutter"
            ],
          };
        }
      },
    },

    methods: {
      test: function(){
        bbn.fn.log("test");
      },
      getOptions: function(){
        var cfg = bbn.vue.getOptions(this),
            tmp;
        if ( cfg.mode && (tmp = getMode(cfg.mode)) ){
          cfg.mode = tmp.mode;
          $.extend(cfg, tmp.cfg);
        }
        return cfg;
      }
    },
    mounted: function(){
      var vm = this;

      vm.$nextTick(function(){
        if ( vm.$el.style ){
          vm.$refs.code.style = vm.$el.style;
        }
        if ( vm.$el.className ){
          vm.$refs.code.className = vm.$el.className;
        }
        vm.widget = new CodeMirror(vm.$refs.code, vm.getOptions());
        vm.widget.on("change", function(){
          bbn.fn.log("CHANGE", vm.widget);
          var v = vm.widget.doc.getValue();
          vm.update(v);
        });
        $(vm.$refs.theme_button).on("click", function(){
          themeIndex++;
          if ( themeIndex >= themes.length ){
            themeIndex = 0;
          }
          vm.widget.setOption("theme", themes[themeIndex]);
        });
      })
    },
    data: function(){
      return $.extend({
        widgetName: "CodeMirror",
      }, bbn.vue.treatData(this));
    },
  }
</script>
<style lang="less" scoped>
 @import "../style/bbn.less";
 .bbn-code{
   min-height: 150px;
 }
 </style>
