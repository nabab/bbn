module.exports = function( elementDom ){
  const Window = require('window');
  const window = new Window();
  const document = window.document;

  global.kendo= require('kendo-ui-core');
  global.Vue = require('vue');


  require('../core-components/bbn-vue');


  require('../core-components/kendo-ui.js');


  require("../vendor.css");

  bbn.vue.defineComponents();

  global.$ = kendo.jQuery;
  global.jQuery = global.$;

 require('../core-components/all-components');

  if ( elementDom !== undefined ){
    new Vue({
      el: elementDom,
    });
  }
};
