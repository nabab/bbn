/**
 * Created by BBN on 15/02/2017.
 */
(function($, bbn, kendo){
  "use strict";

  /**
   * @component
   * @param {string} url - The URL on which the tabNav will be initialized.
   * @param {boolean} autoload - Defines if the tab will be automatically loaded based on URLs. False by default
   * except if it is true for the parent.
   * @param {string} orientation - The position of the tabs' titles: top (default) or bottom.
   * @param {string} root - The root URL of the tabNav, will be only taken into account for the top parents'
   * tabNav, will be automatically calculated for the children.
   * @param {boolean} scrollable - Sets if the tabs' titles will be scrollable in case they have a greater width
   * than the page (true), or if they will be shown multilines (false, default).
   * @param {array} source - The tabs shown at init.
   * @param {string} currentURL - The URL to which the tabnav currently corresponds (its selected tab).
   * @param {string} baseURL - The parent TabNav's URL (if any) on top of which the tabNav has been built.
   * @param {array} parents - The tabs shown at init.
   * @param {array} tabs - The tabs configuration and state.
   * @param {boolean} parentTab - If the tabNav has a tabNav parent, the tab Vue object in which it stands, false
   * otherwise.
   * @param {boolean|number} selected - The index of the currently selected tab, and false otherwise.
   */
  Vue.component('bbn-tabnav', {
    //template: '#bbn-tpl-component-tabnav',
    props: {
       url: {
        type: String,
        default: ''
      },
      autoload: {
        type: Boolean,
        default: false
      },
      orientation: {
        type: String,
        default: 'top'
      },
      root: {
        type: String,
        default: ''
      },
      scrollable: {
        type: Boolean,
        default: false
      },
      source: {
        type: Array,
        default: function(){
          return [];
        }
      }
    },

    data(){
      var vm = this,
          r = bbn.vue.treatData(vm).widgetCfg || {};
      if ( vm.$options && vm.$options.props ){
        for ( var n in r ){
          if ( vm.$options.props[n] !== undefined ){
            delete r[n];
          }
        }
      }
      var baseURL = vm.root;
      while ( baseURL.substr(-1) === '/' ){
        baseURL = baseURL.substr(0, baseURL.length-1);
      }
      while ( baseURL.substr(0, 1) === '/' ){
        baseURL = baseURL.substr(1);
      }
      return $.extend({
        _bbnTabNav: {
          started: false,
          titles: '',
          num: 0
        },
        currentURL: '',
        baseURL: baseURL ? baseURL + '/' : '',
        tabs: [],
        parents: [],
        parentTab: false,
        selected: false,
        isMounted: false
      }, r);
    },

    computed: {
      fullBaseURL(){
        let vm = this,
            base = '',
            tmp;
        while ( tmp = vm.baseURL ){
          base = tmp + base;
          if ( !vm.parents.length ){
            break;
          }
          vm = vm.parents[0];
        }
        return base;
      },
    },

    methods: {
      isValidIndex(idx){
        return (typeof(idx) === "number") && (this.tabs[idx] !== undefined);
      },

      // Gets the index of a tab from various parameters: index (!), URL, a DOM element (or jQuery object) inside a tab, a tab, or the currently selected index if there is no argument
      getIndex(misc, force){
        if ( !this.tabs.length ){
          return false;
        }
        var vm = this;
        if ( !vm.isValidIndex(misc) ) {
          if ( typeof(misc) === 'string' ){
            misc = vm.search(misc);
          }
          else if ( typeof(misc) === 'object' ){
            // Vue
            if ( misc.$el ){
              misc = $(misc.$el);
            }
            // Not jQuery
            if ( !(misc instanceof jQuery) && misc.tagName ){
              misc = $(misc);
            }
            // Is element in the titles?
            var $titles = misc.closest("ul.k-tabstrip-items").children("li.k-item");
            if ( $titles.length ){
              var $title = misc.is("li.k-item") ? misc : misc.closest("li.k-item");
              misc = $titles.index($title);
            }
            // Or in the content?
            else{
              var $panel = misc.is("div.bbn-tab") ? misc : misc.closest("div.bbn-tab");
              // If the element is in full screen mode
              if ( $panel.hasClass('bbn-tab-full-screen') ){
                var $prev = $(".bbn-tab-before-full-screen:first", vm.el);
                misc = $prev.is("div.bbn-tab") ?
                  $(vm.$el).children("div.bbn-tab,div.bbn-loader").index($prev) + 1 : 0;
              }
              else if ( $panel.length ){
                misc = $(vm.$el).children("div.bbn-tab,div.bbn-loader").index($panel);
              }
            }
          }
        }
        if ( !vm.isValidIndex(misc) && force ) {
          for ( var i = 0; i < vm.tabs.length; i++ ){
            if ( !vm.tabs[i].disabled ){
              if ( vm.tabs[i].default ){
                return i;
              }
              else if ( !vm.isValidIndex(misc) ){
                misc = i;
              }
            }
          }
        }
        return vm.isValidIndex(misc) ? misc : false;
      },

      // Returns the baseURL property
      getBaseURL(){
        return this.baseURL;
      },

      getFullBaseURL(){
        return this.fullBaseURL;
      },

      getURL(idx, force){
        if ( force && !this.isValidIndex(idx) ){
          idx = this.selected;
        }
        if ( this.isValidIndex(idx) ){
          return this.tabs[idx].url;
        }
        return false;
      },

      // Returns the current URL from the root tabNav without the hostname (if it has a baseURL it will start after)
      getFullURL(idx, force){
        var url = this.getURL(idx, force);
        if ( url !== false ){
          return this.getFullBaseURL() + url;
        }
        return false;
      },

      getCurrentURL(idx, force){
        if ( force && !this.isValidIndex(idx) ){
          idx = this.selected;
        }
        if ( this.isValidIndex(idx) ){
          return this.tabs[idx].current;
        }
        return false;
      },

      getFullCurrentURL(idx, force){
        var url = this.getCurrentURL(idx, force);
        if ( url !== false ){
          return this.getFullBaseURL() + url;
        }
        return false;
      },

      // Returns the url relative to the current tabNav from the given url
      parseURL(fullURL){
        var vm = this,
            fullBaseURL = vm.fullBaseURL;
        if ( fullURL === undefined ){
          return '';
        }
        if ( typeof(fullURL) !== 'string' ){
          return fullURL.toString();
        }
        if ( fullURL.indexOf(bbn.env.root) === 0 ){
          fullURL = fullURL.substr(bbn.env.root.length);
        }
        if ( fullBaseURL === (fullURL + '/') ){
          return '';
        }
        if ( fullBaseURL && (fullURL.indexOf(fullBaseURL) === 0) ){
          return fullURL.substr(fullBaseURL.length);
        }
        /*if ( vm.baseURL && (url.indexOf(vm.baseURL) === 0) ){
         return url.substr(vm.baseURL.length);
         }
         else if ( vm.baseURL === (url + '/') ){
         return '';
         }*/
        return fullURL;
      },

      activateDefault(){
        var vm = this,
            idx = vm.getIndex('', true);
        if ( vm.isValidIndex(idx) ){
          this.activate(this.tabs[idx].current ? this.tabs[idx].current : this.tabs[idx].url);
        }
      },

      activateIndex(idx){
        if ( this.isValidIndex(idx) ){
          this.activate(this.tabs[idx].current);
        }
      },

      getTab(idx){
        if ( this.isValidIndex(idx) && this.$refs['tab-' + idx] ){
          return this.$refs['tab-' + idx];
        }
        return false;
      },

      getContainer(idx){
        if ( this.isValidIndex(idx) && this.$refs['container-' + idx] ){
          return this.$refs['container-' + idx];
        }
        return false;
      },

      getSubTabNav(idx){
        var vm = this;
        if ( vm.isValidIndex(idx) ){
          var tab = bbn.vue.getChildByKey(vm, vm.tabs[idx].url, 'bbn-tab');
          if ( tab ){
            return tab.getSubTabNav();
          }
        }
        return false;
      },

      /**
       * Called when activating a tab manually with the corresponding URL
       * Or called manually with an URL and will activate the given tab programmatically
       */
      activate(url, force){

        // if no parameter is passed we use the current url
        var vm = this,
            idx,
            tab,
            subtab;
        //bbn.fn.log("url before parse: " + url);
        url = vm.parseURL(url);
        //bbn.fn.log("url after parse: " + url);
        // either the requested url or the url corresponding to the target index

        // No URL has been given -> we activate the default tab
        if ( !url ){
          bbn.fn.log("activateDefault with no url ");
          return vm.activateDefault();
        }
        idx = vm.getIndex(url);
        bbn.fn.log("ACTIVATE", url, idx);
        // No index found: loading or error
        if ( !vm.isValidIndex(idx) ){
          for ( var i = 0; i < vm.tabs.length; i++ ){
            if (
              ((url + '/').indexOf(vm.tabs[i].url) === 0) &&
              (subtab = vm.getSubTabNav(i))
            ){
              bbn.fn.log("SUBTAB", subtab);
              vm.selected = i;
              return subtab.activate(url);
            }
          }
          // autoload is set to true we launch the link function which will activate the newly created tab
          if ( vm.autoload ){
            //alert(url);
            //bbn.fn.log("link from autoload: " + url);
            vm.load(url);
          }
          else{
            bbn.fn.log(vm.$el);
            new Error(
              "Impossible to find an index for " + url + " in element with baseURL " +
              vm.getFullBaseURL()
            );
          }
        }
        // Index exists but content not loaded yet
        else if ( vm.tabs[idx].load && !vm.tabs[idx].disabled ){
          //vm.selected = idx;
          vm.load(url);
        }
        else if ( !vm.tabs[idx].disabled ){
          var subtab = vm.getSubTabNav(idx);
          if ( subtab && subtab.rendered ){
            subtab.activate(vm.getFullBaseURL() + url);
          }
          vm.selected = idx;
        }

        return;
        var // actual tab
          $tab = vm.getTab(idx),
          // Container
          $cont = vm.getContainer(idx),
          // Previous "current url"
          oldCurrent = vm.currentURL;

        // Do nothing if the tab is already activated and force is not true or the widget loads for the first time
        if ( $tab.data("bbn-tabnav-activated") && (!force || !vm.isReady()) ){
          vm._urlActivation(url, idx, force);
          //bbn.fn.log("It seems tabnav-activated is on " + $tab.data("bbn-tabnav-activated"));
          if ( !vm.list.length ){
            throw new Error("It seems tabnav-activated is on " + $tab.data("bbn-tabnav-activated"));
          }
          return this;
        }
        // Error if one element is missing
        if ( !$cont.length || !$tab.length ){
          throw new Error("There is a problem with the widget...?");
        }

        // Checking difference between former and new URLs
        if ( oldCurrent !== url ){
          // This is the only moment where changed is set
          vm.changed = true;
          // If it's not already activated we do programmatically, it won't execute the callback function
          if ( !$tab.hasClass("k-state-active") ){
            vm.wid.activateTab($tab);
            if ( vm.isReady() || vm.parent ){
              return this;
            }
          }
        }

        // In this case the tab exists but we load its content the first time it is activated
        if ( vm.list[idx].load ){
          //bbn.fn.log("loading content from list load parameter");
          vm.list[idx].load = false;
          vm.setContent($.ui.tabNav.getLoader(), idx);
          vm.loadContent(vm.fullBaseURL + url, vm.getData(idx), vm.list[idx]);
          return vm;
        }
        // Only if either:
        // - the tabNav has never been activated
        // - the force parameter has been sent
        // - the URL is different
        // We really activate it
        if ( force || vm.isChanged() ) {
          //bbn.fn.log("***COUNT****", $tab.length, $tab.siblings().length);
          vm.rActivate(idx, url, force)
        }
        else{
          throw new Error("NOT ACTIVATED WITH " + url);
          //bbn.fn.log("NOT ACTIVATED WITH " + url, vm.$el, vm.list);
        }
        return this;
      },

      close(idx){
        var vm = this;
        if ( vm.tabs[idx] ){
          vm.tabs.splice(idx, 1);
          if ( !vm.tabs.length ){
            vm.selected = false;
          }
          else if ( !vm.tabs[idx] ){
            vm.activateIndex(idx - 1);
          }
        }
      },

      add(obj_orig, idx){
        const vm = this;
        let obj = $.extend({}, obj_orig),
            index;
        //obj must be an object with property url
        if (
          (typeof(obj) === 'object') &&
          obj.url &&
          (
            (idx === undefined) ||
            (
              (typeof(idx) === 'number') &&
              vm.tabs[idx]
            )
          )
        ){
          index = vm.search(obj.url);
          if ( !obj.menu ){
            obj.menu = [];
            if ( vm.autoload ){
              obj.menu.push({
                text: bbn._("Reload"),
                key: "reload",
                icon: "fa fa-refresh",
                click: function(a){
                  vm.reload(obj.idx);
                }
              });
            }
          }
          if ( index !== false ){
            obj.idx = index;
            obj.selected = index === vm.selected;
            $.each(obj, function(n, val){
              vm.$set(vm.tabs[index], n, obj[n]);
            })
          }
          else{
            obj.selected = false;
            if ( !obj.current ){
              obj.current = obj.url;
            }
            if ( idx === undefined ){
              obj.idx = vm.tabs.length;
              vm.tabs.push(obj);
              bbn.fn.log("ADDING", obj);
            }
            else{
              obj.idx = idx;
              $.each(vm.tabs, function(i, tab){
                if ( i >= idx ){
                  vm.$set(vm.tabs[i], "idx", tab.idx+1);
                }
              });
              vm.tabs.splice(idx, 0, obj);
            }
            if ( (vm.tabs.length === 1) && vm.isMounted ){
              vm.activateIndex(0);
            }
          }
          // We give the selected DIV a background color which corresponds to the color of the tab's text
          // (which might be undefined, so this action is necessary)
          vm.$nextTick(function(){
            $(vm.$refs['selector-' + obj.idx]).css("backgroundColor", $(vm.$refs['tab-' + obj.idx]).css("color"));
          })
        }
      },

      search(url){
        var r = bbn.fn.search(this.tabs, "url", url, "starts");
        return r === -1 ? false : r;
      },

      load(url){
        const vm = this;
        var idx = vm.search(url),
            finalURL = vm.fullBaseURL + url;
        if ( vm.isValidIndex(idx) && vm.tabs[idx].real ){
          finalURL = vm.tabs[idx].real;
        }
        return bbn.fn.post(finalURL, {_bbn_baseURL: vm.fullBaseURL}, (d) => {
          if ( d.content ){
            if ( !d.url ){
              d.url = url;
            }
            d.url = vm.parseURL(d.url);
            d.loaded = true;
            d.load = false;
            idx = vm.search(d.url);
            if ( d.data !== undefined ){
              d.source = $.extend({}, d.data);
              delete d.data;
            }
            d.current = url;
            if ( vm.isValidIndex(idx) ){
              vm.add(d, idx);
            }
            else{
              idx = vm.tabs.length;
              vm.add(d);
            }
            vm.selected = idx;
            //vm.$nextTick(() => vm.activate(d.url));
          }
        })
      },

      reload(idx){
        var vm = this;
        vm.$set(vm.tabs[idx], "load", true);
        vm.$nextTick(function(){
          vm.activateIndex(idx);
        })
      },

      checkTabsHeight(noResize){
        var vm = this;
        if ( vm.tabs[vm.selected] ){
          var tab = vm.getTab(vm.options.selected),
              h = tab.parent().outerHeight(true);
          if ( !noResize && h && (h !== vm.tabsHeight) ){
            /** @todo Check if it's right */
            bbn.fn.log("checkTabsHeight change");
            // Previous code (shit!)
            vm.resize();
          }
          vm.tabsHeight = h;
        }
        return vm;
      },

      setColorSelector(col, idx){
        if ( (idx = this.getIndex(idx)) !== false ) {
          var vm = this,
              tab = vm.getTab(idx);
          if (tab) {
            if (!vm.colorIsDone) {
              vm.bThemeColor = tab.css("backgroundColor");
              vm.fThemeColor = tab.css("color");
            }
            if (!bbn.fn.isColor(col)) {
              col = vm.fThemeColor;
            }
            $("div.ui-tabNav-tabSelected", tab[0]).css("backgroundColor", col);
            if (window.tinycolor) {
              if (!vm.colorIsDone) {
                vm.bColorIsLight = (tinycolor(vm.bThemeColor)).isLight();
                vm.fColorIsLight = (tinycolor(vm.fThemeColor)).isLight();
              }
            }
            vm.colorIsDone = true;
          }
        }
      },

      setColor(bcol, fcol, idx, dontSetSelector) {
        var vm = this;
        if ( (idx = vm.getIndex(idx)) !== false ) {
          var $tab = vm.getTab(idx);
          if ( $tab.length ) {
            $tab.css("backgroundColor", bbn.fn.isColor(bcol) ? bcol : null);
            $tab.children().not(".ui-tabNav-tabSelected").css("color", bbn.fn.isColor(fcol) ? fcol : null);
            if ( !dontSetSelector ){
              vm.setColorSelector(fcol ? fcol : false, idx);
            }
          }
        }
        return vm;
      },

      initTab(url){

      },

      navigate(){
        var vm = this,
            sub = vm.getSubTabNav(vm.selected);
        if ( sub && sub.isValidIndex(sub.selected) ){
          sub.navigate();
        }
        else if ( vm.isValidIndex(vm.selected) ){
          var url = vm.getFullCurrentURL(vm.selected);
          bbn.fn.setNavigationVars(url, vm.tabs[vm.selected].title, vm.tabs[vm.selected].source, false);
        }
      },

      setURL(){

      },

    },

    render(createElement){
      var vm = this;

      if ( !vm.rendered ){
        vm.rendered = true;
        // Examine the default slot, and if there are any parse
        // them and add the data to the workingList
        $.each(vm.source, function(i, obj){
          if ( obj.url ){
            vm.add(obj);
          }
        });
      }

      var tabs = [],
          containers = [];
      $.each(vm.tabs, (i, obj) => {
        var cfg = {
              ref: "tab-" + i,
              'class': {
                'k-item': true,
                'k-state-default': true,
                'bbn-tabnav-static': !!obj.static,
                'k-state-active': obj.selected,
                'k-last': i === (vm.tabs.length - 1),
                'k-first': i === 0
              },
              on: {
                click: function(){
                  if ( i !== vm.selected ){
                    vm.activateIndex(i)
                  }
                }
              }
            },
            title_cfg = {
              ref: "title-" + i,
              'class': {
                'k-link': true
              },
              domProps: {
                innerHTML: obj.title
              }
            },
            selected_cfg = {
              ref: "selector-" + i,
              'class': {
                'bbn-tabnav-selected': true
              },
            };
        if ( obj.bcolor ){
          cfg.style = {
            backgroundColor: obj.bcolor
          };
        }
        if ( obj.fcolor ){
          if ( !cfg.style ){
            cfg.style = {};
          }
          cfg.style.color = obj.fcolor;
          title_cfg.style = {
            color: obj.fcolor
          };
          selected_cfg.style = {
            backgroundColor: obj.fcolor
          };
        }
        tabs.push(createElement('li', cfg, [
          createElement('span', {
            ref: "spinner-" + i,
            'class': {
              'k-loading': true,
              'k-complete': true
            }
          }),
          createElement('span', title_cfg),
          createElement('div', selected_cfg),
          createElement('div', {
            'class': {
              'bbn-tabnav-icons': true
            }
          }, [
            createElement('i', {
              'class': {
                fa: true,
                'fa-times-circle': true,
                'bbn-p': true
              },
              on: {
                click: function(){
                  vm.close(i)
                }
              }
            }),
            createElement('bbn-context', {
              'class': {
                fa: true,
                'fa-caret-down': true,
                'bbn-p': true
              },
              props: {
                tag: 'i',
                source: obj.menu || []
              },
            }),
          ])
        ]));
        if ( obj.load || ((vm.selected !== i) && obj.component) ){
          containers.push(createElement('bbn-loader', {
            ref: 'container-' + i,
            props: {
              source: obj
            }
          }));
        }
        else{
          containers.push(createElement('bbn-tab', {
            ref: 'container-' + i,
            props: obj,
            key: obj.url
          }));
        }
      });

      var ulCfg = {
        'class': {
          'k-reset': true,
          'k-tabstrip-items': true,
          'k-header': true,
          'bbn-tabnav-tabs': true
        },
        ref: 'tabgroup'
      };
      if ( vm.scrollable ){
        ulCfg['style'] = {
          marginLeft: 35,
          marginRight: 38
        }
      }

      containers.unshift(createElement('ul', ulCfg, tabs));

      if ( vm.scrollable ){
        containers.push(createElement('span', {
          'class': {
            'k-button': true,
            'k-button-icon': true,
            'k-button-bare': true,
            'k-tabstrip-prev': true
          }
        }, [
          createElement('i', {
            'class': {
              'fa': true,
              'fa-angle-left': true,
              'bbn-p': true
            }
          })
        ]));
        containers.push(createElement('span', {
          'class': {
            'k-button': true,
            'k-button-icon': true,
            'k-button-bare': true,
            
            'k-tabstrip-next': true
          }
        }, [
          createElement('i', {
            'class': {
              'fa': true,
              'fa-angle-right': true,
              'bbn-p': true
            }
          })
        ]));
      }


      return createElement('div', {
        'class': {
          'bbn-tabnav': true,
          'k-widget': true,
          'k-header': true,
          'k-tabstrip': true,
          'k-floatwrap': true,
          'k-tabstrip-top': true,
          'k-tabstrip-scrollable': vm.scrollable
        }
      }, containers);
    },

    created(){
      var vm = this;
      if (vm.$slots.default){
        for ( var node of this.$slots.default ){
          // May want to check here if the node is a myCp2,
          // otherwise, grab the data
          if ( node.componentOptions && node.componentOptions.propsData.url ){
            vm.add(node.componentOptions.propsData);
          }
        }
      }
    },

    mounted(){
      var vm = this,
          parent = vm.$parent;
      // Looking for a parent tabnav to put in parentTab && parents props
      while ( parent ){
        if (
          parent.$vnode &&
          parent.$vnode.componentOptions
        ){
          if ( !vm.parentTab && (parent.$vnode.componentOptions.tag === 'bbn-tab') ){
            vm.parentTab = parent;
          }
          else if ( parent.$vnode.componentOptions.tag === 'bbn-tabnav' ){
            vm.parents.push(parent);
          }
        }
        parent = parent.$parent;
      }
      // If there is a parent tabnav we automatically give the proper baseURL
      if ( vm.parents.length ){
        var tmp = vm.parents[0].getURL(vm.parentTab.idx);
        if ( vm.baseURL !== (tmp + '/') ) {
          vm.baseURL = tmp + '/';

          /*
          if (vm.parents.autoload && (tmp.indexOf(vm.baseURL) === 0)) {
            vm.parents.setURL(vm.baseURL, vm.$el);
            vm.currentURL = tmp.substr(vm.baseURL.length + 1);
          }
          */
        }
        vm.fullBaseURL = '';
        $.each(vm.parents, function(i, a){
          vm.fullBaseURL += a.getBaseURL();
        })
      }
      // We make the tabs reorderable
      var $tabgroup = $(vm.$refs.tabgroup),
          reorderable = $tabgroup.data('kendoDraggable');
      if ( reorderable ) {
        reorderable.destroy();
      }
      $tabgroup.kendoDraggable({
        group: 'tabs',
        filter:'.k-item',
        hint: function(element) {
          return element.clone().wrap('<ul class="k-reset k-tabstrip-items bbn-tabnav-tabs"/>').parent().css({opacity: 0.8});
        }
      });
      $tabgroup.kendoDropTarget({
        group: 'tabs',
        drop: function(e){
          bbn.fn.log(e);
        }
      });
      // Giving colors

      vm.activate(vm.parseURL(bbn.env.path), true);
      vm.isMounted = true;
    },

    watch: {
      selected(newVal){
        var vm = this;
        if ( vm.tabs[newVal] ){
          if ( vm.currentURL !== vm.tabs[newVal].current ){
            vm.currentURL = vm.tabs[newVal].current;
          }
          $.each(vm.tabs, function(i, a){
            if ( vm.tabs[i].selected !== (i === newVal) ){
              vm.$set(vm.tabs[i], "selected", (i === newVal));
            }
          });
          vm.$nextTick(() => {
            bbn.fn.analyzeContent(vm.$el, true);
          })
          vm.navigate();
        }
      },
      currentURL(newVal, oldVal){
        if ( newVal !== oldVal ){
          bbn.fn.log("NEW URL", newVal);
          if ( this.isValidIndex(this.selected) ){
            var vm = this,
                tab = bbn.vue.getChildByKey(vm, vm.tabs[vm.selected].url, 'bbn-tab');
            bbn.fn.log("IS VALID", tab);
            if (
              tab &&
              (vm.tabs[vm.selected].current !== newVal) &&
              (newVal.indexOf(vm.tabs[vm.selected].url) === 0)
            ){
              vm.$set(vm.tabs[vm.selected], "current", newVal);
            }
            bbn.fn.log("CHECKING PARENTS");
            if ( vm.parents.length ){
              bbn.fn.log("CHANGING URL");
              vm.parents[0].$set(vm.parents[0], "currentURL", vm.baseURL + newVal);
            }
          }
        }
        //this.$forceUpdate();
        //bbn.fn.log("A change in tabs")
        //var vm = this;
      },
      tabs:{
        deep: true,
        handler(newVal){
          const vm = this;
          let test = vm.$data._bbnTabNav;
          // Checking if there are changes in the titles in order to recheck the measures of the tabs
          if ( !test.started ){
            test.started = true;
            test.num = newVal.length;
            let tmp = '';
            $.each(newVal, function(i, a){
              tmp += a.title;
            });
            test.titles = bbn.fn.md5(tmp);
          }
          else{
            // If a tab is added or removed the usual actions will take place, no need for this
            let tmp = '';
            $.each(newVal, function(i, a){
              tmp += a.title;
            });
            tmp = bbn.fn.md5(tmp);
            if ( test.num === newVal.length ){
              if ( test.titles !== tmp ){
                // Check if width/height have changed
                bbn.fn.log("TO DO: Check the tabs' dimensions");
              }
            }
            else{
              test.num = newVal.length;
            }
            test.titles = tmp;
          }
          if ( (newVal.idx === vm.selected) && (newVal.current !== vm.currentURL) ){
            vm.currentURL = newVal.current;
          }
        }
      }
    }
  });

})(jQuery, bbn, kendo);
