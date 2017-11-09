/**
 * Created by BBN Solutions.
 * User: Loredana Bruno
 * Date: 20/02/17
 * Time: 16.21
 */


(($, bbn) => {
  "use strict";

  if ( bbn.vue.components.code.defaults === undefined ){
    bbn.vue.components.code.defaults = {};
  }
  const themes = ["3024-day","3024-night","ambiance-mobile","ambiance","base16-dark","base16-light","blackboard","cobalt","eclipse","elegant","erlang-dark","lesser-dark","mbo","midnight","monokai","neat","night","paraiso-dark","paraiso-light","pastel-on-dark","rubyblue","solarized","the-matrix","tomorrow-night-eighties","twilight","vibrant-ink","xq-dark","xq-light"];

  const defaults = {
    theme: 'pastel-on-dark'
  };

  bbn.vue.initDefaults(defaults, 'code');


  const baseCfg = {
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
      "Ctrl-S": function(cm){
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

  const modes = {
    php: {
      mode: {
        name: 'php',
        tags: {
          script: [
            ["type", /^text\/(x-)?template$/, 'php'],
            ["type", /^text\/html$/, 'php']
          ],
          style: [
            ["type", /^text\/(x-)?less$/, 'text/x-less'],
            ["type", /^text\/(x-)?scss$/, 'text/x-scss'],
            [null, null, {name: 'css'}]
          ],
        }
      },
      autoCloseBrackets: true,
      autoCloseTags: true,
      extraKeys: {
        "Ctrl-J": "toMatchingTag",
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
      autoCloseTags: true,
      extraKeys: {
        "Ctrl-J": "toMatchingTag"
      }
    },
    js: {
      mode: {
        name: 'javascript'
      },
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
    },
    json: {
      mode: {
        name: 'javascript',
        json: true
      },
      lint: true
    },
    css: {
      mode: {
        name: 'css'
      },
      lint: true
    },
    less: {
      mode: "text/x-less",
      lint: true
    },
    vue: {
      mode: "text/x-vue"
    }
  };

  let themeIndex = $.inArray(defaults.theme, themes);

  Vue.component('bbn-code', {
    template: "#bbn-tpl-component-code",
    mixins: [bbn.vue.fullComponent],
    props: {
      mode: {
        type: [String, Object],
        default: 'php'
      },
      theme: {
        type: String,
      },
      cfg: {
        type: Object,
        default: function(){
          return baseCfg;
        }
      },
      themeButton: {
        type: Boolean,
        default: false
      }
    },

    data: function(){
      return $.extend({
        widgetName: "CodeMirror",
      }, bbn.vue.treatData(this));
    },

    computed: {
      currentTheme(){
        return this.theme ? this.theme : bbn.vue.components.code.defaults.theme;
      }
    },

    methods: {
      // Gets the set of preset options for the given mode from const modes
      getMode(mode){
        if ( modes[mode] ){
          let o = $.extend({}, modes[mode]);
          o.gutters = [
            "CodeMirror-linenumbers",
            "CodeMirror-foldgutter"
          ];
          if ( o.lint ){
            o.gutters.push("CodeMirror-lint-markers")
          }
          return o;
        }
        return false;
      },
      // Gets the options for the editor
      getOptions(){
        let tmp,
            cfg = $.extend({}, baseCfg, {
              mode: this.mode,
              theme: this.currentTheme,
              value: this.value
            }, this.cfg);

        if ( tmp = this.getMode(this.mode) ){
          cfg = $.extend(true, cfg, tmp);
        }
        bbn.fn.info("Codemirror config");
        bbn.fn.log(cfg);
        return cfg;
      },

      // Returns an object with the selections, the marks (folding) and the value
      getState: function(){
        if ( this.widget ){
          let doc = this.widget.getDoc(),
              selections = doc.listSelections(),
              marks = doc.getAllMarks(),
              res = {
                selections: [],
                marks: [],
                value: this.widget.getValue()
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
        return false;
      },

      nextTheme(){
        themeIndex++;
        if ( themeIndex >= themes.length ){
          themeIndex = 0;
        }
        bbn.vue.components.code.defaults.theme = themes[themeIndex];
        //this.widget.setOption("theme", themes[themeIndex]);
      }
    },

    mounted: function(){
      this.widget = new CodeMirror(this.$refs.code, this.getOptions());
      this.widget.on("change", () => {
        this.emitInput(this.widget.doc.getValue());
      });
      this.$emit("ready", this.value);
    },

    watch: {
      currentTheme(newVal){
        this.widget.setOption("theme", newVal);
      }
    }
  });

})(jQuery, bbn);
