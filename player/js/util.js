/* Helper Tools & DOM Manipulation */
var U = (function () {
  function $(sel, ctx) { return (ctx || document).querySelector(sel); }
  function $$(sel, ctx) { return Array.prototype.slice.call((ctx || document).querySelectorAll(sel)); }
  function el(tag, cls, html) {
    var e = document.createElement(tag);
    if (cls) e.className = cls;
    if (html !== undefined) e.innerHTML = html;
    return e;
  }
  function esc(s) { return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); }
  function pad(n) { return n < 10 ? '0' + n : '' + n; }
  function clock(d) { d = d || new Date(); return pad(d.getHours()) + ':' + pad(d.getMinutes()); }
  function hm(sec) { var d = new Date(sec * 1000); return pad(d.getHours()) + ':' + pad(d.getMinutes()); }
  function fmtTime(sec) {
    sec = Math.floor(sec || 0);
    var h = Math.floor(sec / 3600), m = Math.floor((sec % 3600) / 60), s = sec % 60;
    return (h > 0 ? h + ':' + pad(m) : m) + ':' + pad(s);
  }
  function qs(obj) {
    return Object.keys(obj).map(function (k) {
      return encodeURIComponent(k) + '=' + encodeURIComponent(obj[k]);
    }).join('&');
  }

  // getJSON sécurisé avec délai d'expiration (Timeout)
  function getJSON(url, timeout) {
    return new Promise(function (resolve, reject) {
      var xhr = new XMLHttpRequest();
      xhr.open('GET', url, true);
      xhr.timeout = timeout || 8000; // Timeout à 8 secondes
      xhr.onload = function () {
        if (xhr.status >= 200 && xhr.status < 300) {
          try {
            var parsed = JSON.parse(xhr.responseText);
            resolve(parsed);
          } catch (e) {
            reject(new Error('Réponse serveur non valide (Erreur JSON / HTML)'));
          }
        } else {
          reject(new Error('Erreur serveur HTTP ' + xhr.status));
        }
      };
      xhr.onerror = function () { reject(new Error('Erreur réseau ou restriction CORS')); };
      xhr.ontimeout = function () { reject(new Error('Le serveur proxy ne répond pas (Délai dépassé)')); };
      xhr.send();
    });
  }

  function fitScreen() {
    var app = $('#app');
    var w = window.innerWidth, h = window.innerHeight;
    var scale = Math.min(w / 1920, h / 1080);
    U.scale = scale;
    app.style.transform = 'scale(' + scale + ')';
    app.style.left = Math.round((w - 1920 * scale) / 2) + 'px';
    app.style.top = Math.round((h - 1080 * scale) / 2) + 'px';
  }
  function debounce(fn, wait) {
    var t;
    return function () {
      var ctx = this, args = arguments;
      clearTimeout(t);
      t = setTimeout(function () { fn.apply(ctx, args); }, wait);
    };
  }
  function isAdult(name) { return /(adult|18\+|xxx|porn|sex|erotic)/i.test(name || ''); }

  return { $: $, $$: $$, el: el, esc: esc, pad: pad, clock: clock, hm: hm, fmtTime: fmtTime, qs: qs, getJSON: getJSON, fitScreen: fitScreen, debounce: debounce, isAdult: isAdult };
})();
