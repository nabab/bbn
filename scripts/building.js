/**
 * Created by BBN Solutions.
 * User: Vito Fava
 * Date: 27/06/17
 * Time: 11.40
 */


var path = require('path');
var fs = require('fs');
var browserify = require('browserify');
var vueify = require('vueify');

var babelify = require('babelify').configure({
  presets: ["es2015"]
});


var aliasify = require('aliasify').configure({
  aliases: {
    "vue": path.join(__dirname, "../", "node_modules/vue/dist/vue.js")
  }
});



vueify.compiler.applyConfig({
  aliases: {
    "vue": path.join(__dirname, "../", "node_modules/vue/dist/vue.js")
  }
})

browserify('src/main.js').transform(vueify,babelify, aliasify).bundle().pipe(fs.createWriteStream('public/bbn-components.js'));
