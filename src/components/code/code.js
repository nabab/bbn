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
            mode: {
              name: 'php',
              tags: {
                script: [
                  ["type", /^text\/(x-)?template$/, 'text/x-php'],
                  ["type", /^text\/html$/, 'text/x-php']
                ],
                style: [
                  ["type", /^text\/(x-)?less$/, 'text/x-less'],
                  ["type", /^text\/(x-)?scss$/, 'text/x-scss'],
                  [null, null, {name: 'css'}]
                ],
              }
            },
            cfg: {
              autoCloseBrackets: true,
              autoCloseTags: true,
              extraKeys: {
                "Ctrl-J": "toMatchingTag"
              }
            }
          },
          html: {
            mode: {
              name: 'htmlmixed',
              tags: {
                script: [
                  ["type", /^text\/(x-)?template$/, 'htmlmixed'],
                  ["type", /^text\/html$/, 'htmlmixed']
                ],
                style: [
                  ["type", /^text\/(x-)?less$/, 'text/x-less'],
                  ["type", /^text\/(x-)?scss$/, 'text/x-scss'],
                  [null, null, {name: 'css'}]
                ],
              }
            },
            cfg: {
              autoCloseTags: true,
              extraKeys: {
                "Ctrl-J": "toMatchingTag"
              }
            }
          },
          js: {
            mode: {
              name: 'javascript'
            },
            cfg: {
              lint: true,
              lintWith: CodeMirror.javascriptValidator,
              autoCloseBrackets: true,
              /*
               extraKeys: {
               "'.'": function(cm){
               cm.showHint();
               }
               }
               */
            }
          },
          json: {
            mode: {
              name: 'javascript',
              json: true
            },
            cfg: {
              lint: true
            }
          },
          css: {
            mode: {
              name: 'css'
            },
            cfg: {
              lint: true
            }
          },
          less: {
            mode: {
              name: 'css',
              less: true
            },
            cfg: {
              lint: true
            }
          },
          vue: {
            mode: "text/x-vue",
            cfg: {}
          }
        };
        if ( tmp[mode] ){
          tmp[mode].cfg.gutters = [
            "CodeMirror-linenumbers",
            "CodeMirror-foldgutter"
          ];
          if ( tmp[mode].cfg.lint ){
            tmp[mode].cfg.gutters.push("CodeMirror-lint-markers")
          }
          return tmp[mode];
        }
        return false;
      };

  Vue.component('bbn-code', {
    template: "#bbn-tpl-component-code",
    mixins: [bbn.vue.fullComponent],
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
      this.$emit("ready", this.value);
    },
    data: function(){
      return $.extend({
        widgetName: "CodeMirror",
      }, bbn.vue.treatData(this));
    },
  });

})(jQuery, bbn);