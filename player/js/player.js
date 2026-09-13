/* Video Playback Engine — Lecture Directe & Fix Ecran Noir */
var Player = (function () {
  var v = null, currentItem = null, osdTimer = null, hls = null, playlistOpts = null, endCb = null;

  function init() {
    v = U.$('#player-video');
    if (!v) return;
    v.ontimeupdate = onTimeUpdate;
    v.onended = function () { if (endCb) endCb(); };
    v.onerror = function (e) { 
      console.error('Erreur Video Balise HTML5:', v.error);
      hideLoading();
      UI.toast('Erreur de lecture du flux', 3000, '⚠'); 
    };
  }

  function play(item, opts) {
    currentItem = item; 
    playlistOpts = opts || {};
    showLoading(); 
    showOsd();

    if (App.account && Store.addHistory) {
      Store.addHistory(App.account.id, item);
    }

    App.provider.streamUrl(item).then(function (finalUrl) {
      // Nettoyage des protocoles : forcer HTTP pour éviter le blocage SSL/CORS
      if (finalUrl.indexOf('http://') === -1 && finalUrl.indexOf('https://') === -1) {
        finalUrl = 'http://' + finalUrl;
      }

      console.log('Tentative de lecture sur URL :', finalUrl);

      if (hls) { hls.destroy(); hls = null; }

      // 1. Si c'est un flux HLS (.m3u8)
      if (/\.m3u8(\?|$)/i.test(finalUrl) && window.Hls && Hls.isSupported()) {
        hls = new Hls({
          enableWorker: true,
          lowLatencyMode: true
        });
        hls.loadSource(finalUrl); 
        hls.attachMedia(v);
        hls.on(Hls.Events.MANIFEST_PARSED, function () {
          v.play().then(hideLoading).catch(function() { hideLoading(); });
        });
        hls.on(Hls.Events.ERROR, function (event, data) {
          if (data.fatal) hideLoading();
        });
      } else {
        // 2. Si c'est un fichier VOD (.mp4 / .mkv / .ts) : injection DIRECTE dans la balise vidéo
        v.src = finalUrl;
        v.load();
        
        var playPromise = v.play();
        if (playPromise !== undefined) {
          playPromise.then(function() {
            hideLoading();
          }).catch(function (err) {
            console.warn('Lecture bloquée par l\'auto-play :', err);
            hideLoading();
          });
        } else {
          hideLoading();
        }
      }

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
