/**
 * Created by BBN Solutions.
 * User: Loredana Bruno
 * Date: 20/02/17
 * Time: 16.21
 */


(($, bbn) => {
  "use strict";

  const themes = ["3024-day","3024-night","ambiance-mobile","ambiance","base16-dark","base16-light","blackboard","cobalt","eclipse","elegant","erlang-dark","lesser-dark","mbo","midnight","monokai","neat","night","paraiso-dark","paraiso-light","pastel-on-dark","rubyblue","solarized","the-matrix","tomorrow-night-eighties","twilight","vibrant-ink","xq-dark","xq-light"];


  var themeIndex = 0,
      getMode = (mode) => {
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

  Vue.component('bbn-code', {
    template: "#bbn-tpl-component-code",
    mixins: [bbn.vue.vueComponent],
    props: {
      mode: {},
      theme: {},
      cfg: {
        type: Object,
        default: function(){
          const vm = this;
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
            save: false,
            /*
            keydown: false,
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
            extraKeys: {
              "Ctrl-Alt-S": function(cm){
                if ( $.isFunction(cm.options.save) ){
                  cm.options.save(cm);
                }
              },
              "Ctrl-Alt-T": function(cm){
                if ( $.isFunction(cm.options.test) ){
                  cm.options.test(cm);
                }
              },
            }
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
      },
      getState: function(){
        const vm = this,
              doc = vm.widget.getDoc(),
              selections = doc.listSelections(),
              marks = doc.getAllMarks(),
              res = {
                selections: [],
                marks: [],
                value: vm.widget.getValue()
              };
        if ( marks ){
          // We reverse the array in order to start in the last folded parts in case of nesting
          for ( let i = marks.length - 1; i >= 0; i-- ){
            if ( marks[i].collapsed && (marks[i].type === 'range') ){
              res.marks.push(marks[i].find().from);
            }
          }
        }
        if ( selections ){
          $.each(selections, function(i, a){
            res.selections.push({anchor: a.anchor, head: a.head});
          });
        }
        return res;
      }
    },
    mounted: function(){
      const vm = this;

      if ( vm.$el.style ){
        vm.$refs.code.style = vm.$el.style;
      }
      if ( vm.$el.className ){
        vm.$refs.code.className = vm.$el.className;
      }
      vm.widget = new CodeMirror(vm.$refs.code, vm.getOptions());
      vm.widget.on("change", function(){
        bbn.fn.log("CHANGE", vm.widget);
        vm.emitInput(vm.widget.doc.getValue());
      });
      $(vm.$refs.theme_button).on("click", function(){
        themeIndex++;
        if ( themeIndex >= themes.length ){
          themeIndex = 0;
        }
        vm.widget.setOption("theme", themes[themeIndex]);
      });
    },
    data: function(){
      return $.extend({
        widgetName: "CodeMirror",
      }, bbn.vue.treatData(this));
    },
  });

})(jQuery, bbn);