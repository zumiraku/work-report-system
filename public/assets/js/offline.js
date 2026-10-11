/*
  圏外でも入力を続けられるようにする層。

  入力のたび IndexedDB に控え、電波が無いときの「つぎへ」は送信箱に溜めて次の画面へ進む
  （画面は Service Worker のキャッシュから出る）。戻ったら溜めた順に送る。
  ひとつずつ op_id を振ってあるので、届いたのに応答が返らず再送しても二重にならない。

  JavaScript が動かない端末では普通のフォーム送信として動く（オフライン対応だけ無くなる）。
*/
(function () {
  'use strict';

  var DB_NAME = 'wcr';
  var DB_VER  = 1;
  var flushing = false;

  function openDb() {
    return new Promise(function (resolve, reject) {
      var req = indexedDB.open(DB_NAME, DB_VER);
      req.onupgradeneeded = function () {
        var db = req.result;
        if (!db.objectStoreNames.contains('drafts')) {
          db.createObjectStore('drafts', { keyPath: 'key' });
        }
        if (!db.objectStoreNames.contains('outbox')) {
          db.createObjectStore('outbox', { keyPath: 'seq', autoIncrement: true });
        }
      };
      req.onsuccess = function () { resolve(req.result); };
      req.onerror = function () { reject(req.error); };
    });
  }

  function tx(store, mode, fn) {
    return openDb().then(function (db) {
      return new Promise(function (resolve, reject) {
        var t = db.transaction(store, mode);
        var s = t.objectStore(store);
        var out = fn(s);
        t.oncomplete = function () { resolve(out && out.result !== undefined ? out.result : out); };
        t.onerror = function () { reject(t.error); };
      });
    });
  }

  function all(store) {
    return openDb().then(function (db) {
      return new Promise(function (resolve, reject) {
        var req = db.transaction(store, 'readonly').objectStore(store).getAll();
        req.onsuccess = function () { resolve(req.result || []); };
        req.onerror = function () { reject(req.error); };
      });
    });
  }

  function get(store, key) {
    return openDb().then(function (db) {
      return new Promise(function (resolve, reject) {
        var req = db.transaction(store, 'readonly').objectStore(store).get(key);
        req.onsuccess = function () { resolve(req.result || null); };
        req.onerror = function () { reject(req.error); };
      });
    });
  }

  function uuid() {
    if (window.crypto && typeof crypto.randomUUID === 'function') return crypto.randomUUID();
    return Date.now().toString(16) + '-' + Math.random().toString(16).slice(2, 10);
  }

  var bar  = document.getElementById('js-sync');
  var text = document.getElementById('js-sync-text');
  var push = document.getElementById('js-sync-push');

  function paint(pending) {
    if (!bar || !text) return;

    if (!navigator.onLine) {
      bar.dataset.state = 'offline';
      text.textContent = pending > 0
        ? 'オフライン：端末に保存しています（未送信 ' + pending + '件）'
        : 'オフライン：入力はこのまま続けられます';
    } else if (pending > 0) {
      bar.dataset.state = 'local';
      text.textContent = '端末保存中（未送信 ' + pending + '件）';
    } else {
      bar.dataset.state = 'synced';
      text.textContent = 'サーバー送信済み';
    }

    if (push) {
      push.hidden = !(navigator.onLine && pending > 0);
    }
  }

  function refresh() {
    return all('outbox').then(function (rows) {
      paint(rows.length);
      return rows.length;
    }).catch(function () { paint(0); return 0; });
  }

  function enqueue(item) {
    return tx('outbox', 'readwrite', function (s) { s.add(item); });
  }

  function drop(seq) {
    return tx('outbox', 'readwrite', function (s) { s.delete(seq); });
  }

  function flush() {
    if (flushing || !navigator.onLine) return Promise.resolve(false);
    flushing = true;

    return all('outbox').then(function (rows) {
      rows.sort(function (a, b) { return a.seq - b.seq; });

      return rows.reduce(function (chain, item) {
        return chain.then(function (okSoFar) {
          if (!okSoFar) return false;
          return sendOne(item).then(function (ok) {
            return ok ? drop(item.seq).then(function () { return true; }) : false;
          });
        });
      }, Promise.resolve(true));
    }).then(function (allSent) {
      flushing = false;
      return refresh().then(function (left) {
        return { sent: allSent, left: left };
      });
    }).catch(function () {
      flushing = false;
      return { sent: false, left: -1 };
    });
  }

  function sendOne(item) {
    return fetch(item.url, { credentials: 'same-origin', headers: { 'X-Sync': '1' } })
      .then(function (res) { return res.ok ? res.text() : ''; })
      .then(function (html) {
        var body = new URLSearchParams(item.body);
        var m = html.match(/name="_csrf" value="([a-f0-9]+)"/);
        if (m) body.set('_csrf', m[1]);

        return fetch(item.url, {
          method: 'POST',
          credentials: 'same-origin',
          redirect: 'follow',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
          body: body.toString()
        });
      })
      .then(function (res) {
        return res.ok;
      })
      .catch(function () { return false; });
  }

  var form = document.querySelector('form[data-offline]');
  var draftKey = null;

  if (form) {
    draftKey = form.dataset.report + ':' + form.dataset.step;
  }

  function collect(f, submitter) {
    var data = new FormData(f);
    var body = new URLSearchParams();
    data.forEach(function (v, k) {
      if (typeof v === 'string') body.append(k, v);
    });
    if (submitter && submitter.name) {
      body.set(submitter.name, submitter.value || '1');
    }
    return body;
  }

  var saveTimer = null;
  function saveDraft() {
    if (!form || !draftKey) return;
    clearTimeout(saveTimer);
    saveTimer = setTimeout(function () {
      var body = collect(form, null);
      body.delete('_csrf');
      tx('drafts', 'readwrite', function (s) {
        s.put({ key: draftKey, body: body.toString(), savedAt: Date.now() });
      }).then(function () {
        if (!navigator.onLine) paint(-1);
      }).catch(function () {});
    }, 400);
  }

  function hydrate(pending) {
    if (!form || !draftKey || pending <= 0) {
      if (draftKey) {
        tx('drafts', 'readwrite', function (s) { s.delete(draftKey); }).catch(function () {});
      }
      return;
    }

    get('drafts', draftKey).then(function (row) {
      if (!row) return;
      var params = new URLSearchParams(row.body);

      var multi = {};
      params.forEach(function (v, k) { multi[k] = (multi[k] || 0) + 1; });

      Object.keys(multi).forEach(function (name) {
        var nodes = form.querySelectorAll('[name="' + cssEscape(name) + '"]');
        Array.prototype.forEach.call(nodes, function (el) {
          if (el.type === 'checkbox' || el.type === 'radio') el.checked = false;
        });
      });

      params.forEach(function (value, name) {
        var nodes = form.querySelectorAll('[name="' + cssEscape(name) + '"]');
        if (!nodes.length) return;
        Array.prototype.forEach.call(nodes, function (el) {
          if (el.type === 'checkbox' || el.type === 'radio') {
            if (el.value === value) el.checked = true;
          } else if (el.tagName === 'SELECT' || el.tagName === 'TEXTAREA' || el.type !== 'hidden') {
            el.value = value;
          }
        });
      });

      notice('端末に保存していた入力内容を画面に戻しました。通信が戻ると自動で送信します。', 'warn');
    }).catch(function () {});
  }

  function cssEscape(s) {
    return s.replace(/(["\\])/g, '\\$1');
  }

  var lastSubmitter = null;
  document.addEventListener('click', function (ev) {
    var b = ev.target.closest ? ev.target.closest('button[type="submit"], input[type="submit"]') : null;
    if (b) lastSubmitter = b;
  }, true);

  if (form) {
    form.addEventListener('submit', function (ev) {
      if (navigator.onLine) return;

      var submitter = ev.submitter || lastSubmitter;
      var name = submitter && submitter.name ? submitter.name : 'next';

      ev.preventDefault();

      if (name !== 'next' && name !== 'back') {
        notice('この操作は通信が戻ってからになります。入力した内容は端末に残っています。', 'warn');
        return;
      }

      var target = name === 'back' ? form.dataset.backUrl : form.dataset.nextUrl;
      var body   = collect(form, submitter);
      body.set('op_id', uuid());

      enqueue({
        url:       form.action.replace(location.origin, ''),
        body:      body.toString(),
        label:     form.dataset.step,
        createdAt: Date.now()
      }).then(function () {
        return refresh();
      }).then(function () {
        if (target) location.href = target;
      }).catch(function () {
        notice('端末に保存できませんでした。通信が戻るまでこの画面を閉じないでください。', 'error');
      });
    });

    form.addEventListener('input', saveDraft);
    form.addEventListener('change', saveDraft);
  }

  function notice(message, kind) {
    var main = document.querySelector('.app-main');
    if (!main) return;
    var box = document.getElementById('js-offline-notice');
    if (!box) {
      box = document.createElement('div');
      box.id = 'js-offline-notice';
      main.insertBefore(box, main.firstChild);
    }
    box.className = 'alert alert--' + (kind || 'info');
    box.textContent = message;
  }

  function prefetchWizard() {
    if (!form || form.dataset.step !== 'basic' || !navigator.onLine) return;
    var id = form.dataset.report;
    ['work', 'parts', 'measure', 'confirm', 'sign'].forEach(function (step) {
      fetch('/report/' + id + '/' + step, { credentials: 'same-origin' }).catch(function () {});
    });
  }

  if ('serviceWorker' in navigator) {
    var swUrl = document.body.getAttribute('data-sw') || '/sw.js';
    navigator.serviceWorker.register(swUrl).catch(function () {});
  }

  window.addEventListener('online', function () {
    paint(-1);
    flush().then(function (r) {
      if (r && r.left === 0) {
        notice('通信が回復し、溜めていた入力をサーバーへ送信しました。', 'info');
        setTimeout(function () { location.reload(); }, 900);
      }
    });
  });

  window.addEventListener('offline', function () {
    refresh();
    notice('通信が切れました。このまま入力を続けられます。内容は端末に保存されます。', 'warn');
  });

  if (push) {
    push.addEventListener('click', function () {
      push.disabled = true;
      flush().then(function () { location.reload(); });
    });
  }

  refresh().then(function (pending) {
    hydrate(pending);
    if (pending > 0 && navigator.onLine) {
      flush().then(function (r) {
        if (r && r.left === 0) location.reload();
      });
    }
    prefetchWizard();
  });

  var logoutForms = document.querySelectorAll('form[action="/logout"]');
  Array.prototype.forEach.call(logoutForms, function (f) {
    f.addEventListener('submit', function () {
      try {
        indexedDB.deleteDatabase(DB_NAME);
      } catch (e) {}
      if (navigator.serviceWorker && navigator.serviceWorker.controller) {
        navigator.serviceWorker.controller.postMessage({ type: 'clear' });
      }
    });
  });
}());
