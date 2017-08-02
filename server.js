
var path = require('path');
var express = require('express');
var app = express();
var fs = require('fs');


var dir = path.join(__dirname, 'public');

//require('bbn');
//require('bbnVue');


var mime = {
  html: 'text/html',
  txt: 'text/plain',
  css: 'text/css',
  ttf: 'application/x-font-ttf',
  woff: 'application/x-font-woff',
  gif: 'image/gif',
  jpg: 'image/jpeg',
  png: 'image/png',
  svg: 'image/svg+xml',
  js: 'application/javascript'
};

app.get('*', function (req, res) {
  var file = path.join(dir, req.path.replace(/\/$/, '/index.html'));
  if (file.indexOf(dir + path.sep) !== 0) {
    return res.status(403).end('Forbidden');
  }
  var type = mime[path.extname(file).slice(1)] || 'text/plain';
  var s = fs.createReadStream(file);
  s.on('open', function () {
    res.set('Content-Type', type);
    s.pipe(res);
  });
  s.on('error', function () {
    res.set('Content-Type', 'text/plain');
    res.status(404).end('Not found');
  });
});

app.listen(9000, function () {
  console.log('Listening on http://localhost:9000/');
});
