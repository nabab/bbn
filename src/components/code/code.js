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
        htmlMode: {
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
      },
      autoCloseBrackets: true,
      autoCloseTags: true,
      extraKeys: {
        "Ctrl-Space": "autocomplete",
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
        },
      },
      autoCloseTags: true,
      extraKeys: {
        "Ctrl-Space": "autocomplete",
        "Ctrl-J": "toMatchingTag"
      }
    },
    js: {
      mode: {
        name: 'javascript'
      },
      /*
      lint: {
        esversion: 6
      },
      lintWith: window.jslint || CodeMirror.lint.javascript,
      */
      autoCloseBrackets: true,
      extraKeys: {
        "Ctrl-Space": function(cm) { bbn.vue.tern.complete(cm); },
        "Ctrl-I": function(cm) { bbn.vue.tern.showType(cm); },
        "Ctrl-O": function(cm) { bbn.vue.tern.showDocs(cm); },
        "Alt-.": function(cm) { bbn.vue.tern.jumpToDef(cm); },
        "Alt-,": function(cm) { bbn.vue.tern.jumpBack(cm); },
        "Ctrl-Q": function(cm) { bbn.vue.tern.rename(cm); },
        "Ctrl-.": function(cm) { bbn.vue.tern.selectName(cm); }
      }
    },
    coffee: {
      mode: 'text/coffeescript'
    },
    json: {
      mode: {
        name: 'javascript',
        json: true
      }
    },
    css: {
      mode: 'text/css',
      extraKeys: {
        "Ctrl-Space": "autocomplete",
      }
    },
    less: {
      mode: "text/x-less",
      extraKeys: {
        "Ctrl-Space": "autocomplete",
      }
    },
    scss: {
      mode: "text/x-scss",
      extraKeys: {
        "Ctrl-Space": "autocomplete",
      }
    },
    vue: {
      mode: "text/x-vue"
    }
  };

  let themeIndex = $.inArray(defaults.theme, themes);

  Vue.component('bbn-code', {
    mixins: [bbn.vue.basicComponent, bbn.vue.fullComponent],
    props: {
      ecma: {
        type: Number,
        default: 6
      },
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
            o.gutters = ["CodeMirror-lint-markers"]
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
          $.extend(true, cfg, tmp);
        }
        bbn.fn.info("Codemirror config");
        bbn.fn.log(cfg);
        return cfg;
      },
      //to place the cursor in a defined point
      cursorPosition(lineCode, position){
        let ctrl = false;
        if ( lineCode <= this.widget.doc.lineCount()-1 ){
          if ( position <= this.widget.doc.lineInfo(lineCode).text.length ){
            ctrl = true;
          }
        }
        if ( ctrl ){
          this.$nextTick(()=>{
            this.widget.focus();
            this.widget.setCursor({line: lineCode, ch: position});
          });
        }
        else{
          return false
        }
      },
      // Returns an object with the selections, the marks (folding) and the value
      getState(){
        if ( this.widget ){
          let doc = this.widget.getDoc(),
              selections = doc.listSelections(),
              marks = doc.getAllMarks(),
              info = doc.getCursor(),
              res = {
                selections: [],
                marks: [],
                line: info.line,
                char: info.ch
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
      //loading the state, such as loading last state saved 
      loadState( obj ){
        this.widget.focus();
        let doc = this.widget.getDoc();
        if ( obj.marks && obj.marks.length ){
          for(let mark of obj.marks){
            this.widget.foldCode(mark.line, 0);
          }
        }
        if( obj.line && obj.char && !obj.selections.length){
          this.cursorPosition(obj.line, obj.char);
        }
        else{
          if ( obj.selections && obj.selections.length ){
            for ( let i = 0; i < obj.selections.length; i++ ){
              doc.setSelection(obj.selections);
            }
          }
        }
      },
      nextTheme(){
        themeIndex++;
        if ( themeIndex >= themes.length ){
          themeIndex = 0;
        }
        bbn.vue.components.code.defaults.theme = themes[themeIndex];
        //this.widget.setOption("theme", themes[themeIndex]);
      },

      foldByLevel(level) {
        this.foldByNodeOrder(0);
        // initialize vars
        var cursor = this.widget.getCursor();
        cursor.ch = 0;
        cursor.line = 0;
        var range = this.widget.getViewport();
        this.foldByLevelRec(cursor, range, level);
      },

      foldByLevelRec(cursor, range, level) {
        if (level > 0) {
          var searcher = this.widget.getSearchCursor("<", cursor, false);
          while (searcher.findNext() && searcher.pos.from.line < range.to) {
            // unfold the tag
            this.widget.foldCode(searcher.pos.from, null, "unfold");
            // move the cursor into the tag
            cursor = searcher.pos.from;
            cursor.ch = searcher.pos.from.ch + 1;
            // find the closing tag
            var match = CodeMirror.findMatchingTag(this.widget, cursor, range);
            if (match) {
              if (match.close) {
                // create the inner-range and jump the searcher after the ending tag
                var innerrange = { from: range.from, to: range.to };
                innerrange.from = cursor.line + 1;
                innerrange.to = match.close.to.line;
                // the recursive call
                this.foldByLevelRec(cursor, innerrange, level - 1);
              }
              // move to the next element in the same tag of this function scope
              var nextcursor = { line: cursor.line, to: cursor.ch };
              if (match.close) {
                nextcursor.line = match.close.to.line;
              }
              nextcursor.ch = 0;
              nextcursor.line = nextcursor.line + 1;
              searcher = this.widget.getSearchCursor("<", nextcursor, false);
            }
          }
        }
      },

      foldByNodeOrder(node) {
        // 0 - fold all
        this.unfoldAll();
        node++;
        for (var l = this.widget.firstLine() ; l <= this.widget.lastLine() ; ++l){
          if ( node == 0 ){
            this.widget.foldCode({line: l, ch: 0}, null, "fold");
          }
          else{
            node--;
          }
        }
      },
      foldAll(){
        this.foldByNodeOrder(0);
      },

      unfoldAll() {
        for (var i = 0; i < this.widget.lineCount() ; i++) {
          this.widget.foldCode({ line: i, ch: 0 }, null, "unfold");
        }
      }
    },

    created(){
      if ( bbn.vue.tern === undefined ){
        let getURL = (url, c) => {
          let xhr = new XMLHttpRequest();
          xhr.open("get", url, true);
          xhr.send();
          xhr.onreadystatechange = function() {
            if (xhr.readyState != 4) return;
            if (xhr.status < 400) return c(null, xhr.responseText);
            let e = new Error(xhr.responseText || "No response");
            e.status = xhr.status;
            c(e);
          };
        };

        getURL("//ternjs.net/defs/ecmascript.json", function(err, code) {
          if (err) throw new Error("Request for ecmascript.json: " + err);
          bbn.vue.tern = new CodeMirror.TernServer({defs: [JSON.parse(code)]});
        });

      }
    },

    mounted: function(){
      this.widget = CodeMirror(this.$refs.code, this.getOptions());
      this.widget.on("change", (ins, bbb) => {
        bbn.fn.info("CODE CHANGE");
        bbn.fn.log(arguments, this.widget);
        this.emitInput(this.widget.doc.getValue());
      });
      if ( this.mode === 'js' ){
        this.widget.on("cursorActivity", function(cm) { bbn.vue.tern.updateArgHints(cm); });
      }
      //this.$emit("ready", this.value);
    },

    watch: {
      currentTheme(newVal){
        this.widget.setOption("theme", newVal);
      },
      mode(newVal){
        let mode = this.getMode(newVal);
        if ( mode ){
          $.each(mode, (i, v) => {
            this.widget.setOption(i, v);
          });
        }
      },
      value(newVal, oldVal){
        if ( (newVal !== oldVal) && ( newVal !== this.widget.getValue()) ){
          this.widget.setValue(newVal);
        }
      }
    }
  });

})(jQuery, bbn);
