/**
 * Created by BBN Solutions.
 * User: Vito Fava
 * Date: 11/07/17
 * Time: 16.42
 */
var b = require('browserify')();

b.add('./src/all_style.js');
b.plugin(require('css-modulesify'), {
  rootDir: __dirname,
  output: './my.css'
});

b.bundle();