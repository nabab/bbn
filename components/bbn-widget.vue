<template>
  <div class="k-block bbn-widget">
    <div class="k-header header">
      <h4 class="ui-sortable-handle" v-html="title"></h4>
      <span class="button button-left">
        <i v-if="closable"
           :title="_('Close')"
           @click="close()"
           class="fa fa-times"></i>
        <bbn-context v-if="hasMenu"
                     :source="finalMenu"
        >
          <i :title="_('Menu')"
             @click="close()"
             class="fa fa-caret-down"> </i>
        </bbn-context>
        <i v-for="(b, idx) in buttonsLeft"
           :title="b.text"
           @click="actionButton(b.action)"
           :class="b.icon"></i>
      </span>
      <span class="button button-right">
        <i v-for="(b, idx) in buttonsRight"
           :title="b.text"
           @click="actionButton(b.action)"
           :class="b.icon"></i>
      </span>
    </div>
    <div class="content">
      <component v-if="component" :is="component" :source="source"></component>
      <div v-else-if="content" v-html="content"></div>
      <ul v-else-if="items && items.length">
        <li v-for="(it, idx) in items" v-if="currentLimit ? idx < currentLimit : true">
          <component v-if="itemComponent" :is="itemComponent" :source="it"></component>
          <span v-else v-html="it"></span>
        </li>
      </ul>
      <div v-else><slot>Nothing to display</slot></div>
      <div v-if="0 && zoomable && (items && items.length)" class="zoom">
        <i class="fa fa-arrows-alt" @click="zoom"></i>
      </div>
    </div>
  </div>
</template>
<script>
  var fancytree =  require('fancytree');


  var limits = [5, 10, 15, 20, 25, 30, 40, 50];
  export default {
    name:'bbn-widget',
    props: {
      uid: {},
      content: {},
      url: {},
      limit: {},
      start: {},
      total: {},
      template: {},
      component: {},
      itemComponent: {},
      title: {},
      buttonsLeft: {},
      buttonsRight: {},
      zoomable: {},
      closable: {},
      sortable: {},
      source: {
        type: Object,
        default: function(){
          return {};
        }
      },
      items: {
        type: Array,
        default: function(){
          return [];
        }
      },
      top: {},
      menu: {
        type: Array,
        default: function(){
          return [];
        }
      },
      bottom: {},
      position: {
        type: String
      },
      opened: {}
    },
    computed: {
      hasMenu: function(){
        return this.finalMenu.length ? true : false;
      },
      finalMenu: function(){
        var vm = this,
            tmp = vm.menu.slice();
        if ( vm.url ){
          tmp.unshift({
            text: bbn._("Reload"),
            icon: "fa fa-refresh",
            click: function(){
              vm.reload();
            }
          });
        }
        if ( vm.limit ){
          var items = [];
          $.each(limits, function(i, a){
            items.push({
              text: a.toString() + " " + bbn._("Items"),
              selected: a === vm.currentLimit,
              click: function(){
                vm.setLimit(a);
              }
            })
          });
          tmp.push({
            text: bbn._("Limit"),
            items: items,
            mode: "selection"
          });
        }
        return tmp;
      }
    },
    methods: {
      _: bbn._,
      close: function(){
        var vm = this,
            $ele = $(vm.$el);
        vm.$emit("close", vm.uid, vm);
        /*
        $ele.bbn("animateCss", "zoomOut", function () {
          $(this).hide();
          vm.$emit("close", $ele.attr("data-bbn-type") || null, vm);
        })
        */
      },
      zoom: function(){
        var vm = this,
            o = vm.getOptions(),
            $ele = $(vm.$el);

      },
      getOptions: function(){
        var vm = this,
            o = bbn.vue.getOptions(this);
        return o;
      },
      reload: function(){
        var vm = this;
        vm.items.splice(0, vm.items.length);
        vm.$nextTick(function(){
          vm.load();
        })
      },
      load: function(){
        var vm = this,
            o = vm.getOptions();
        if ( o.url ){
          var params = {
            key: vm.uid
          };
          if ( o.limit ){
            params.limit = o.limit;
            if ( o.start ){
              params.start = o.start;
            }
          }
          bbn.fn.post(o.url, params, function(d){
            if ( d.success && d.data ){
              if ( vm.dashBoard ){
                var idx = bbn.fn.search(vm.dashBoard.source, "key", vm.uid);
                if ( idx > -1 ){
                  if ( d.data.limit && vm.currentLimit ){
                    delete d.data.limit;
                  }
                  vm.dashBoard.updateWidget(vm.uid, d.data);
                }
              }
              /*
               var topSrc = vm;
               while ( topSrc.$parent && (topSrc.$parent.source !== undefined) ){
               topSrc = topSrc.$parent;
               }
               topSrc.$set(vm.$parent.source, "items", d.items);
               topSrc.$set(vm.$parent.source, "num", d.num);
               //vm.$set(vm, "num", d.num);
               //vm.$forceUpdate();
               */
            }
          })
        }
      },
      actionButton: function(name){
        var tmp = this;
        if ( $.isFunction(name) ){
          bbn.fn.log("action", name);
          return name(tmp, tmp.items);
        }
        while ( tmp ){
          if ( $.isFunction(tmp[name]) ){
            return tmp[name]();
          }
          tmp = tmp.$parent;
        }
      },
      setLimit: function(limit){
        var vm = this;
        vm.currentLimit = limit;
        if ( vm.dashBoard ){
          vm.dashBoard.updateWidget(vm.uid, {limit: limit}).then(() => {
            vm.reload();
          });
        }
        else{
          vm.reload();
        }
      },
    },
    data: function(){
      return $.extend(bbn.vue.treatData(this), {
        dashBoard: bbn.vue.closest(this, ".bbn-dashboard"),
        currentLimit: this.limit,
        lang: {
          close: bbn._("Close")
        }
      });
    },
    mounted: function(){
      this.load();
    },
  }
</script>
<style>
div.bbn-widget{
  display: inline-block;
  margin-bottom: 1em;
  width: 100%;
  page-break-inside: avoid;
  div.content{
    box-sizing: content-box;
    hyphens: auto;
    word-wrap: break-word;
    display: inline-block;
    float: left;
    padding:  0.5em  0.5em 1.5em 0.5em;
    width: ~"-webkit-calc(100% - 1em)";
    width: ~"-moz-calc(100% - 1em)";
    width: ~"calc(100% - 1em)";
    position: relative;
  }
  div.zoom{
    position: absolute;
    bottom: 0px;
    right: 2px;
    cursor: pointer;
    opacity: 0.5;
  }
  div.zoom:hover, div.zoom:focus{
    opacity: 1;
  }
  ul, li{
    margin: 0;
    padding: 0;
    list-style: none;
  }
  li{
    padding: 0.4em 0.6em;
    span:nth-child(even){
      float: right;
      clear: right;
      text-align: right;
    }
    span:nth-child(odd){
      float: left;
      clear: left;
    }
    div.dropdownstats{
      float: left;
      clear: both;
      width: 90%;
      margin: 10px 6% 5px 4%;
      span{
        float: none;
      }
    }
  }
  div.k-header{
    text-align: center;
    margin-bottom: 2px;
    h4{
      cursor: move;
      padding: 0;
      margin: 0 3em;
    }
    span.button{
      display: inline-block;
      position: absolute;
      top: 3px;
      i{
        font-size: large;
        cursor: pointer;
        opacity: 0.5;
      }
      a:hover i{
        opacity: 1;
      }
      i:hover,i:focus {
        opacity: 1;
      }
    }
    span.button-left{
      left: 5px;
      padding-right: 3px;
      float: left;
      clear: left;
    }
    span.button-right{
      float: right;
      padding-left: 3px;
      clear: right;
      text-align: right;
      right: 5px;
    }
  }
}

</style>
