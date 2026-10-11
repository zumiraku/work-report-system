/*
  機械室や地下で電波が切れても画面が開くように。
  部品(CSS/JS)はキャッシュ優先、画面(HTML)は通信優先でだめならキャッシュ。
  POST は絶対にキャッシュしない。タブレットは共用なのでログアウト時に全部捨てる。
*/

/* 登録URL（/sw.js?v=…）の版がそのままキャッシュ名になる。
   部品を直すと版が変わり、古いほうは activate で捨てられる */
var CACHE = 'wcr-' + (function () {
  var m = /[?&]v=([0-9a-z]+)/.exec(self.location.search || '');
  return m ? m[1] : 'v2';
}());

var SHELL = [
  '/assets/css/app.css',
  '/assets/css/sheet.css',
  '/assets/js/app.js',
  '/assets/js/offline.js',
  '/assets/js/mic.js',
  '/assets/js/sign.js',
  '/offline.html'
];

function isPrivate(url) {
  return url.pathname === '/login'
      || url.pathname === '/logout'
      || url.pathname.indexOf('/admin') === 0;
}

self.addEventListener('install', function (ev) {
  ev.waitUntil(
    caches.open(CACHE).then(function (c) { return c.addAll(SHELL); })
      .then(function () { return self.skipWaiting(); })
  );
});

self.addEventListener('activate', function (ev) {
  ev.waitUntil(
    caches.keys()
      .then(function (keys) {
        return Promise.all(keys.map(function (k) {
          return k === CACHE ? null : caches.delete(k);
        }));
      })
      .then(function () { return self.clients.claim(); })
  );
});

self.addEventListener('fetch', function (ev) {
  var req = ev.request;

  if (req.method !== 'GET') return;

  var url = new URL(req.url);
  if (url.origin !== self.location.origin) return;

  if (req.mode === 'navigate') {
    ev.respondWith(
      fetch(req)
        .then(function (res) {
          if (res.ok && !isPrivate(url)) {
            var copy = res.clone();
            caches.open(CACHE).then(function (c) { c.put(req, copy); });
          }
          return res;
        })
        .catch(function () {
          return caches.match(req).then(function (hit) {
            return hit || caches.match('/offline.html');
          });
        })
    );
    return;
  }

  ev.respondWith(
    caches.match(req, { ignoreSearch: true }).then(function (hit) {
      if (hit) return hit;
      return fetch(req).then(function (res) {
        if (res.ok) {
          var copy = res.clone();
          caches.open(CACHE).then(function (c) { c.put(req, copy); });
        }
        return res;
      });
    })
  );
});

self.addEventListener('message', function (ev) {
  if (!ev.data || ev.data.type !== 'clear') return;
  ev.waitUntil(
    caches.keys().then(function (keys) {
      return Promise.all(keys.map(function (k) { return caches.delete(k); }));
    })
  );
});
