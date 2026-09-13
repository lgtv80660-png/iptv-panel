/* Video Playback Engine — Support Redirection 302 G-PANEL */
var Player = (function () {
  var v = null, currentItem = null, osdTimer = null, hls = null, playlistOpts = null, endCb = null;

  function init() {
    v = U.$('#player-video');
    if (!v) return;
    v.ontimeupdate = onTimeUpdate;
    v.onended = function () { if (endCb) endCb(); };
    v.onerror = function () { UI.toast('Erreur de lecture du flux', 3000, '⚠'); };
  }

  // Résolution de l'URL finale si le proxy G-PANEL fait une redirection 302 / Token
  function resolveStreamUrl(url) {
    return fetch(url, { method: 'HEAD', redirect: 'follow' })
      .then(function (response) {
        // Renvoie l'URL finale après redirection (ex: http://154.6.190.48/movie/...)
        return response.url || url;
      })
      .catch(function () {
        // En cas de blocage CORS sur le HEAD, on tente d'utiliser l'URL directe originale
        return url;
      });
  }

  function play(item, opts) {
    currentItem = item; 
    playlistOpts = opts || {};
    showLoading(); 
    showOsd();

    if (App.account && Store.addHistory) {
      Store.addHistory(App.account.id, item);
    }

    App.provider.streamUrl(item).then(function (rawUrl) {
      // 1. Détecter et résoudre la redirection G-PANEL (Vercel -> 154.6.190.48)
      return resolveStreamUrl(rawUrl);
    }).then(function (finalUrl) {
      console.log('Lecture URL finale résolue :', finalUrl);

      if (hls) { hls.destroy(); hls = null; }

      // 2. Si c'est un flux HLS (.m3u8)
      if (/\.m3u8(\?|$)/i.test(finalUrl) && window.Hls && Hls.isSupported()) {
        hls = new Hls({
          xhrSetup: function (xhr) {
            xhr.withCredentials = false;
          }
        });
        hls.loadSource(finalUrl); 
        hls.attachMedia(v);
      } else {
        // 3. Si c'est un fichier VOD (.mp4 / .mkv / .ts)
        v.src = finalUrl;
      }

      v.play().then(hideLoading).catch(function (err) {
        console.warn('Erreur lecture auto, tentative http direct:', err);
        hideLoading();
      });

      if (U.$('#osd-title')) U.$('#osd-title').textContent = item.name || item.title || 'Vidéo';
      if (U.$('#osd-sub')) U.$('#osd-sub').textContent = item.subtitle || '';
    }).catch(function (err) {
      hideLoading(); 
      UI.toast('Erreur de flux: ' + err.message, 3000, '⚠');
    });
  }

  function stop() { 
    if (hls) { hls.destroy(); hls = null; } 
    if (v) { v.pause(); v.removeAttribute('src'); v.load(); }
  }

  function reset() { stop(); currentItem = null; }
  function showLoading() { var l = U.$('#player-loading'); if (l) l.style.display = 'flex'; }
  function hideLoading() { var l = U.$('#player-loading'); if (l) l.style.display = 'none'; }

  function showOsd() {
    var osd = U.$('#player-osd'); 
    if (!osd) return;
    osd.classList.add('show');
    clearTimeout(osdTimer);
    osdTimer = setTimeout(function () { if (v && !v.paused) osd.classList.remove('show'); }, 5000);
  }

  function onTimeUpdate() {
    if (!v || !v.duration) return;
    var pct = (v.currentTime / v.duration) * 100;
    if (U.$('#osd-progress')) U.$('#osd-progress').style.width = pct + '%';
    if (U.$('#osd-pos')) U.$('#osd-pos').textContent = U.fmtTime(v.currentTime);
    if (U.$('#osd-dur')) U.$('#osd-dur').textContent = U.fmtTime(v.duration);
  }

  return {
    init: init, 
    play: play, 
    stop: stop, 
    reset: reset, 
    showLoading: showLoading,
    hideLoading: hideLoading, 
    showOsd: showOsd, 
    current: function () { return currentItem; },
    setOnEnded: function (fn) { endCb = fn; }
  };
})();
