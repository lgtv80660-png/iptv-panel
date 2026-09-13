/* RGBTv — Player: native <video> (webOS handles HLS/TS/MP4/MKV natively) with hls.js fallback */
var Player = (function () {
  var video, hls = null, osdTimer = null, current = null, playlist = [], index = -1, ratioMode = 0, RATIOS = ['Fit', 'Fill', 'Stretch'];
  var onEnded = null, posKey = null, posTimer = null, seekAccum = 0, seekTimer = null, numBuf = '', numTimer = null, zapOpen = false, trackMenuOpen = false;
  var els = {};
  /* auto-reconnect state */
  var rc = { attempts: 0, timer: null, stallTimer: null, lastTime: -1, lastProgress: 0, lastOpt: null, active: false };
  function T(k, v) { return (window.I18n && I18n.t) ? I18n.t(k, v) : k; }
  
  var RC_MAX = 10, RC_DELAYS = [400, 800, 1500, 2500, 4000, 6000, 8000, 10000, 15000, 20000], STALL_LIVE = 6000, STALL_VOD = 20000, START_LIVE = 12000, START_VOD = 45000;
  var ICON_PLAY = '<svg viewBox="0 0 24 24" width="36" height="36" fill="currentColor"><path d="M7 4v16l14-8z"/></svg>', ICON_PAUSE = '<svg viewBox="0 0 24 24" width="36" height="36" fill="currentColor"><path d="M6 5h4v14H6zm8 0h4v14h-4z"/></svg>';

  function init() {
    // Correctif ID video: support de 'player-video' ou 'video'
    video = document.getElementById('player-video') || document.getElementById('video'); 
    if (!video) return;

    try { video.preload = 'auto'; } catch (e) { }
    
    ['osd', 'osd-title', 'osd-sub', 'osd-logo', 'osd-clock', 'osd-played', 'osd-buffer', 'osd-cur', 'osd-dur', 'osd-play', 'player-loading', 'player-loading-text', 'player-error', 'zap-list', 'channel-number', 'track-menu', 'osd-fav', 'osd-ratio', 'osd-list-btn', 'osd-audio', 'osd-subs', 'osd-quality', 'stats-box', 'zap-preview', 'osd-stats', 'osd-epg', 'autonext', 'an-bar', 'an-count', 'an-title'].forEach(function (id) { 
      els[id] = document.getElementById(id); 
    });

    var bufTimer = null;
    function bufferingSoon() { 
      if (bufTimer || !current) return; 
      bufTimer = setTimeout(function () { 
        bufTimer = null; 
        if (current && !video.paused && Date.now() - rc.lastProgress > 700) loading(true, T('buffering')); 
      }, 800); 
    }

    function bufferingDone() { 
      clearTimeout(bufTimer); 
      bufTimer = null; 
      if (els['player-loading'] && els['player-loading'].classList.contains('show') && els['player-loading-text'] && /Buffering|Loading|Opening/.test(els['player-loading-text'].textContent)) loading(false); 
    }

    video.addEventListener('waiting', bufferingSoon);
    video.addEventListener('stalled', bufferingSoon);
    video.addEventListener('playing', function () { 
      bufferingDone(); 
      error(null); 
      if (els['osd-play']) els['osd-play'].innerHTML = ICON_PAUSE; 
    });
    video.addEventListener('canplay', bufferingDone);
    video.addEventListener('timeupdate', function () { 
      if (video.currentTime !== rc.lastTime && video.currentTime > 0) bufferingDone(); 
    });
    video.addEventListener('loadedmetadata', updateQualityBadge);
    video.addEventListener('resize', updateQualityBadge);
    video.addEventListener('pause', function () { 
      if (els['osd-play']) els['osd-play'].innerHTML = ICON_PLAY; 
    });
    video.addEventListener('timeupdate', updateProgress);
    video.addEventListener('progress', updateProgress);
    video.addEventListener('ended', function () { if (onEnded) onEnded(); });
    
    video.addEventListener('error', function () {
      var code = video.error && video.error.code;
      if (hls) return;
      if (current && /\.m3u8(\?|$)/i.test(current.url) && window.Hls && Hls.isSupported() && !current._triedHls) { 
        current._triedHls = true; 
        current._engine = 'hls'; 
        startHls(current.url); 
        return; 
      }
      scheduleReconnect(T('p.error') + (code ? ' (code ' + code + ')' : ''));
    });

    video.addEventListener('playing', function () { rc.attempts = 0; rc.lastProgress = Date.now(); });
    video.addEventListener('timeupdate', function () { 
      if (video.currentTime !== rc.lastTime) { 
        rc.lastTime = video.currentTime; 
        rc.lastProgress = Date.now(); 
      } 
    });

    video.addEventListener('progress', function () { 
      try { 
        var b = video.buffered, end = b.length ? b.end(b.length - 1) : 0; 
        if (end !== rc.lastBuf) { 
          rc.lastBuf = end; 
          rc.lastProgress = Date.now(); 
        } 
      } catch (e) { } 
    });

    video.addEventListener('loadedmetadata', function () { rc.lastProgress = Date.now(); rc.started = true; });

    setInterval(function () {
      if (!current || !rc.active || video.ended || rc.timer || rc.userPaused) return;
      var isLive = current.type === 'live';
      if (!rc.started && els['player-loading'] && els['player-loading'].classList.contains('show')) { 
        var secs = Math.round((Date.now() - rc.lastStart) / 1000); 
        if (secs >= 4) loading(true, T('opening', { s: secs })); 
      }
      var limit = isLive ? (rc.started ? STALL_LIVE : START_LIVE) : (rc.started ? STALL_VOD : START_VOD);
      if (video.paused && video.readyState >= 3) return;
      if (Date.now() - rc.lastProgress > limit && video.readyState < 3) scheduleReconnect(T('p.stalled'));
      else if (rc.started && !video.paused && Date.now() - rc.lastProgress > limit * 2) scheduleReconnect(T('p.stalled'));
    }, 1500);

    window.addEventListener('online', function () { 
      if (rc.timer && current) { 
        clearTimeout(rc.timer); 
        rc.timer = null; 
        doReconnect(); 
      } 
    });

    setInterval(function () { if (els['osd-clock']) els['osd-clock'].textContent = U.clock(); }, 1000);
  }

  function updateQualityBadge() {
    var w = video.videoWidth, h = video.videoHeight, el = els['osd-quality']; if (!el) return;
    var b = [], nm = current ? String((current.title || '') + ' ' + (current.subtitle || '') + ' ' + (current.name || '')) : '';
    if (w && h) { 
      var uhd = (w >= 3800 || h >= 2100); 
      b.push('<span class="osd-badge' + (uhd ? ' uhd' : '') + '">' + (uhd ? '4K UHD' : (h >= 1000 || w >= 1900) ? 'FHD' : (h >= 700 || w >= 1200) ? 'HD' : 'SD') + '</span>'); 
      b.push('<span class="osd-badge">' + w + '×' + h + '</span>'); 
    } else if (/\b(4k|uhd|2160p)\b/i.test(nm)) b.push('<span class="osd-badge uhd">4K</span>');
    if (/\b(hdr|dolby ?vision)\b/i.test(nm)) b.push('<span class="osd-badge hdr">HDR</span>');
    var subs = 0, auds = 0; 
    try { 
      subs = (hls && hls.subtitleTracks ? hls.subtitleTracks.length : 0) || (video.textTracks ? video.textTracks.length : 0); 
      auds = (hls && hls.audioTracks ? hls.audioTracks.length : 0) || (video.audioTracks ? video.audioTracks.length : 0); 
    } catch (e) { }
    if (subs) b.push('<span class="osd-badge">CC ' + subs + '</span>'); 
    if (auds > 1) b.push('<span class="osd-badge">♪ ' + auds + '</span>');
    if (!b.length) { el.innerHTML = ''; el.style.display = 'none'; return; }
    el.className = 'osd-badges'; el.innerHTML = b.join(''); el.style.display = '';
  }

  function loading(on, txt) { 
    if (els['player-loading']) els['player-loading'].classList.toggle('show', !!on); 
    if (txt && els['player-loading-text']) els['player-loading-text'].textContent = txt; 
  }
  function error(msg) { 
    if (els['player-error']) els['player-error'].classList.toggle('show', !!msg); 
    if (msg && els['player-error']) els['player-error'].textContent = msg; 
  }

  function destroyHls() { if (hls) { try { hls.destroy(); } catch (e) { } hls = null; } }
  
  function startHls(url) {
    destroyHls();
    var isLive = current && current.type === 'live';
    hls = new Hls({
      enableWorker: false, lowLatencyMode: false, backBufferLength: 30,
      maxBufferLength: isLive ? 20 : 30, maxMaxBufferLength: isLive ? 40 : 60, maxBufferHole: 1, nudgeMaxRetry: 8,
      liveSyncDurationCount: 3, liveMaxLatencyDurationCount: 8, liveDurationInfinity: true,
      manifestLoadingTimeOut: 8000, manifestLoadingMaxRetry: 2, manifestLoadingRetryDelay: 500,
      levelLoadingTimeOut: 8000, levelLoadingMaxRetry: 4, levelLoadingRetryDelay: 500,
      fragLoadingTimeOut: isLive ? 8000 : 20000, fragLoadingMaxRetry: 4, fragLoadingRetryDelay: 500, fragLoadingMaxRetryTimeout: 4000,
      startFragPrefetch: true, testBandwidth: false, abrEwmaDefaultEstimate: 5e6, startLevel: -1
    });
    hls.loadSource(url); hls.attachMedia(video);
    hls.on(Hls.Events.MANIFEST_PARSED, function () { video.play().catch(function () { }); setTimeout(updateQualityBadge, 1500); });
    hls.on(Hls.Events.ERROR, function (ev, data) {
      if (!data.fatal) return;
      if (data.type === Hls.ErrorTypes.NETWORK_ERROR) {
        if (/manifest/i.test(data.details)) scheduleReconnect(T('p.cannotLoad') + ' (' + data.details + ')');
        else { hls._softRestarts = (hls._softRestarts || 0) + 1; if (hls._softRestarts > 2) scheduleReconnect(T('p.stalled')); else hls.startLoad(-1); }
      }
      else if (data.type === Hls.ErrorTypes.MEDIA_ERROR) { if (!hls._recovered) { hls._recovered = true; hls.recoverMediaError(); } else scheduleReconnect('Media error'); }
      else scheduleReconnect('Stream error: ' + data.details);
    });
  }

  function scheduleReconnect(reason) {
    if (!current || !rc.active || rc.timer) return;
    if (rc.attempts >= RC_MAX) { rc.active = false; loading(false); error(reason + ' ' + T('p.retryHint', { max: RC_MAX })); return; }
    var delay = RC_DELAYS[Math.min(rc.attempts, RC_DELAYS.length - 1)]; rc.attempts++;
    error(null); loading(true, T('reconnecting', { n: rc.attempts, max: RC_MAX }));
    if (rc.attempts > 1 && window.UI && UI.toast) UI.toast(T('p.interrupted', { n: rc.attempts, max: RC_MAX }), 2500, '↻');
    rc.timer = setTimeout(function () { rc.timer = null; doReconnect(); }, delay);
  }

  function doReconnect() {
    if (!current) return;
    var item = current, opt = rc.lastOpt || {}, resumeAt = (item.type !== 'live' && item.type !== 'catchup' && video.currentTime > 5) ? video.currentTime : 0;
    var keepAttempts = rc.attempts;
    rc.started = false; rc.lastBuf = -1; rc.lastStart = Date.now();
    destroyHls(); try { video.pause(); video.removeAttribute('src'); video.load(); } catch (e) { }
    
    var reuse = item.url && (keepAttempts <= 2 || (opt.url && item.type === 'catchup'));
    var p = reuse ? Promise.resolve(item.url) : App.provider.streamUrl(item);
    p.then(function (url) {
      if (current !== item) return; 
      item.url = cleanUrl(url); 
      startSource(item.url, item);
      if (resumeAt) { 
        var once = function () { video.removeEventListener('loadedmetadata', once); try { video.currentTime = resumeAt; } catch (e) { } }; 
        video.addEventListener('loadedmetadata', once); 
      }
      rc.attempts = keepAttempts; rc.lastProgress = Date.now();
    }).catch(function () { rc.attempts = keepAttempts; scheduleReconnect('Cannot resolve stream'); });
  }

  function cleanUrl(url) {
    if (!url) return '';
    // Si l'application tourne sur HTTP, on s'assure que le lien de streaming reste en HTTP pur
    if (window.location.protocol === 'http:' && url.indexOf('https://') === 0) {
      return url.replace(/^https:\/\//i, 'http://');
    }
    return url;
  }

  function startSource(url, item) {
    var eng = (Store.settings && Store.settings().engine) ? Store.settings().engine : 'auto';
    var isHlsUrl = /\.m3u8(\?|$)/i.test(url);
    var hlsOk = isHlsUrl && window.Hls && Hls.isSupported();
    var useHls = hlsOk && (eng === 'hlsjs' || (eng === 'auto' && !video.canPlayType('application/vnd.apple.mpegurl')) || item._engine === 'hls');
    
    if (useHls) { 
      item._triedHls = true; 
      item._engine = 'hls'; 
      startHls(url); 
    } else { 
      item._engine = 'native'; 
      video.src = url; 
      video.load(); 
      var pp = video.play(); 
      if (pp && pp.catch) pp.catch(function () { }); 
    }
  }

  function showLoading(txt) { error(null); loading(true, txt || T('loading')); }
  function cancelReconnect() { clearTimeout(rc.timer); rc.timer = null; rc.attempts = 0; }

  function play(item, opt) {
    opt = opt || {};
    cancelReconnect(); rc.active = true; rc.userPaused = false; rc.lastOpt = opt; rc.lastProgress = Date.now(); rc.lastTime = -1;
    current = item; current._triedHls = false; current._engine = null;
    if (opt.list) { playlist = opt.list; index = opt.index != null ? opt.index : playlist.indexOf(item); }
    error(null); loading(true, T('loading'));
    stop(false);
    rc.active = true; rc.attempts = 0; rc.lastProgress = Date.now(); rc.started = false; rc.lastBuf = -1; rc.lastStart = Date.now();
    
    if (els['osd-title']) els['osd-title'].textContent = item.title || item.name || '';
    if (els['osd-sub']) els['osd-sub'].textContent = item.subtitle || ''; 
    if (els['osd-quality']) { els['osd-quality'].innerHTML = ''; els['osd-quality'].style.display = 'none'; }
    if (els['osd-epg']) { els['osd-epg'].classList.remove('show'); els['osd-epg'].innerHTML = ''; }
    hideAutoNext();
    
    if (els['osd-logo']) els['osd-logo'].style.backgroundImage = item.logo ? 'url("' + item.logo + '")' : 'none';
    if (els['osd-list-btn']) els['osd-list-btn'].style.display = item.type === 'live' ? '' : 'none';
    updateFavBtn();
    
    var isLive = item.type === 'live';
    if (U.$('.osd-progress')) U.$('.osd-progress').style.visibility = isLive ? 'hidden' : 'visible';
    if (U.$('.osd-times')) U.$('.osd-times').style.visibility = isLive ? 'hidden' : 'visible';
    posKey = (isLive || item.type === 'catchup') ? null : (item.type + ':' + item.id);
    showOsd();

    var p = opt.url ? Promise.resolve(opt.url) : App.provider.streamUrl(item);
    return p.then(function (url) {
      if (!url) throw new Error('No stream URL');
      url = cleanUrl(url);
      item.url = url; current.url = url;
      startSource(url, item);
      
      if (opt.resume && posKey && Store.getPos) {
        var saved = Store.getPos(App.account ? App.account.id : 'direct', posKey);
        if (saved && saved.pos > 10) { 
          var once = function () { video.removeEventListener('loadedmetadata', once); try { video.currentTime = saved.pos; } catch (e) { } }; 
          video.addEventListener('loadedmetadata', once); 
        }
      }
      if (posKey) { clearInterval(posTimer); posTimer = setInterval(savePos, 5000); }
      if (item.type !== 'catchup' && Store.pushHistory && App.account) {
        Store.pushHistory(App.account.id, { type: item.type, id: item.id, name: item.name, logo: item.logo, poster: item.poster, seriesId: item.seriesId, ext: item.ext, cmd: item.cmd, url: item.type === 'm3u' ? url : undefined, catId: item.catId, season: item.season, episode: item.episode });
      }
    }).catch(function (e) { scheduleReconnect('Cannot start stream: ' + e.message); });
  }

  function savePos() { if (posKey && video.duration && !isNaN(video.duration) && Store.setPos && App.account) Store.setPos(App.account.id, posKey, video.currentTime, video.duration); }
  
  function stop(clearCurrent, keepPipeline) {
    cancelReconnect(); rc.active = false;
    savePos(); clearInterval(posTimer);
    destroyHls();
    try { video.pause(); if (!keepPipeline) { video.removeAttribute('src'); while (video.firstChild) video.removeChild(video.firstChild); video.load(); } } catch (e) { }
    if (clearCurrent !== false) current = null;
    loading(false);
  }

  function togglePlay() { 
    if (!rc.active && current && els['player-error'] && els['player-error'].classList.contains('show')) { 
      rc.active = true; rc.attempts = 0; error(null); loading(true, T('retrying')); doReconnect(); return; 
    } 
    if (video.paused) { rc.userPaused = false; video.play().catch(function () { }); } 
    else { rc.userPaused = true; video.pause(); } 
    showOsd(); 
  }

  function seek(delta) {
    if (!current || current.type === 'live' || !isFinite(video.duration)) return;
    seekAccum += delta; showOsd();
    var target = U.clamp(video.currentTime + seekAccum, 0, video.duration - 1);
    if (els['osd-cur']) els['osd-cur'].textContent = U.fmtTime(target) + (seekAccum ? ' (' + (seekAccum > 0 ? '+' : '') + seekAccum + 's)' : '');
    if (els['osd-played']) els['osd-played'].style.width = (target / video.duration * 100) + '%';
    clearTimeout(seekTimer); seekTimer = setTimeout(function () { video.currentTime = target; seekAccum = 0; }, 500);
  }

  function updateProgress() {
    if (!video || !video.duration || !isFinite(video.duration)) return;
    if (!seekAccum) { 
      if (els['osd-cur']) els['osd-cur'].textContent = U.fmtTime(video.currentTime); 
      if (els['osd-played']) els['osd-played'].style.width = (video.currentTime / video.duration * 100) + '%'; 
    }
    if (els['osd-dur']) els['osd-dur'].innerHTML = U.fmtTime(video.duration) + '<span class="osd-rem">−' + U.fmtTime(Math.max(0, video.duration - video.currentTime)) + '</span>';
    try { if (video.buffered.length && els['osd-buffer']) els['osd-buffer'].style.width = (video.buffered.end(video.buffered.length - 1) / video.duration * 100) + '%'; } catch (e) { }
  }

  function showOsd(persist) {
    if (!els.osd) return;
    els.osd.classList.add('show'); clearTimeout(osdTimer);
    if (!persist) osdTimer = setTimeout(hideOsd, 5000);
    if (current && current.type === 'live') fillMiniEpg();
  }

  var epgCache = {};
  function fillMiniEpg() {
    var ch = current, box = els['osd-epg']; if (!ch || !box || !App.provider || !App.provider.shortEPG) return;
    var paint = function (list) {
      if (current !== ch) return; var now = Date.now() / 1000, cur = null, nxt = null;
      list.forEach(function (e) { if (e.start <= now && e.end > now) cur = e; else if (e.start > now && !nxt) nxt = e; });
      if (!cur && !nxt) { box.classList.remove('show'); return; }
      box.innerHTML = '<div class="oe-now"><span class="lbl">' + U.esc(T('nowLbl')) + '</span><span>' + U.esc(cur ? cur.title : '—') + '</span>' + (cur ? '<span class="oe-time">' + U.hm(cur.start) + ' – ' + U.hm(cur.end) + ' · ' + Math.max(0, Math.round((cur.end - now) / 60)) + ' ' + U.esc(T('home.min')) + '</span>' : '') + '</div><div class="oe-bar"><i style="width:' + (cur ? Math.round((now - cur.start) / (cur.end - cur.start) * 100) : 0) + '%"></i></div><div class="oe-next"><span class="lbl">' + U.esc(T('next')) + '</span>' + (nxt ? U.hm(nxt.start) + '  ' + U.esc(nxt.title) : '—') + '</div>';
      box.classList.add('show');
    };
    var c = epgCache[ch.id]; if (c && Date.now() - c.at < 5 * 60000) { paint(c.list); return; }
    App.provider.shortEPG(ch.epgId || ch.id, 4).then(function (list) { epgCache[ch.id] = { at: Date.now(), list: list || [] }; paint(list || []); }).catch(function () { });
  }

  var an = { timer: null, left: 0, onPlay: null, onCancel: null };
  function autoNext(title, onPlay, onCancel) {
    hideAutoNext(); an.left = 10; an.onPlay = onPlay; an.onCancel = onCancel;
    if (els['an-title']) els['an-title'].textContent = title || ''; 
    if (els['an-count']) els['an-count'].textContent = '10'; 
    if (els['an-bar']) { els['an-bar'].style.transition = 'none'; els['an-bar'].style.strokeDashoffset = '0'; }
    if (els.autonext) els.autonext.classList.add('show'); showOsd(true);
    setTimeout(function () { if (els['an-bar']) { els['an-bar'].style.transition = 'stroke-dashoffset 10s linear'; els['an-bar'].style.strokeDashoffset = '327'; } }, 50);
    an.timer = setInterval(function () { an.left--; if (els['an-count']) els['an-count'].textContent = String(Math.max(0, an.left)); if (an.left <= 0) { var f = an.onPlay; hideAutoNext(); if (f) f(); } }, 1000);
  }
  function hideAutoNext() { clearInterval(an.timer); an.timer = null; if (els.autonext) els.autonext.classList.remove('show'); }
  function autoNextOpen() { return !!an.timer; }
  function hideOsd() { if (els.osd) els.osd.classList.remove('show'); if (window.Nav && Nav.current() && Nav.current().getAttribute('data-nav') === 'osd') Nav.blur(); }
  function osdVisible() { return els.osd && els.osd.classList.contains('show'); }
  function ratioName(i) { return T(['p.fit', 'p.fill', 'p.stretch'][i]); }
  function cycleRatio() { ratioMode = (ratioMode + 1) % 3; video.className = ['', 'fill', 'stretch'][ratioMode]; if (els['osd-ratio']) els['osd-ratio'].textContent = ratioName(ratioMode); if (window.UI && UI.toast) UI.toast(T('p.aspect', { m: ratioName(ratioMode) })); }
  function updateFavBtn() { if (!current || !els['osd-fav'] || !Store.isFav || !App.account) return; els['osd-fav'].textContent = Store.isFav(App.account.id, current.type === 'episode' ? 'series' : current.type, current.type === 'episode' ? current.seriesId : current.id) ? '★' : '☆'; }

  function next() { if (playlist.length && current && current.type === 'live') zapTo(index + 1); else if (playlist.length) playIndex(index + 1); }
  function prev() { if (playlist.length && current && current.type === 'live') zapTo(index - 1); else if (playlist.length) playIndex(index - 1); }
  function zapTo(i) { if (!playlist.length) return; i = (i + playlist.length) % playlist.length; playIndex(i); }
  function playIndex(i) {
    if (i < 0 || i >= playlist.length) return; var it = playlist[i]; index = i;
    play(UI.toPlayable(it), { list: playlist, index: i });
  }

  function handleKey(name, code) {
    if (!App.isScreen('player')) return false;
    if (trackMenuOpen) { if (name === 'BACK' || name === 'BACK2') { closeTrackMenu(); return true; } if (name === 'UP' || name === 'DOWN' || name === 'ENTER') return false; return true; }
    if (zapOpen) {
      if (name === 'BACK' || name === 'BACK2' || name === 'LEFT') { toggleZapList(); return true; }
      if (name === 'UP' || name === 'DOWN') { Nav.move(name.toLowerCase()); var c = Nav.current(); if (c && c.getAttribute('data-nav') === 'zap') scrollZap(c); return true; }
      if (name === 'ENTER') return false;
      return true;
    }
    switch (name) {
      case 'BACK': case 'BACK2': if (osdVisible() && Nav.current() && Nav.current().getAttribute('data-nav') === 'osd') { hideOsd(); return true; } App.closePlayer(); return true;
      case 'PLAY': rc.userPaused = false; video.play(); showOsd(); return true;
      case 'PAUSE': rc.userPaused = true; video.pause(); showOsd(); return true;
      case 'PLAYPAUSE': togglePlay(); return true;
      case 'STOP': App.closePlayer(); return true;
      case 'REW': seek(-30); return true;
      case 'FF': seek(30); return true;
      case 'NEXT': case 'CH_UP': next(); return true;
      case 'PREV': case 'CH_DOWN': prev(); return true;
      case 'ENTER':
        if (!rc.active && current && els['player-error'] && els['player-error'].classList.contains('show')) { rc.active = true; rc.attempts = 0; error(null); loading(true, T('retrying')); doReconnect(); return true; }
        if (!osdVisible()) { showOsd(); if (els['osd-play']) Nav.focus(els['osd-play']); return true; }
        return false;
      case 'UP': if (!osdVisible()) { if (current && current.type === 'live') next(); else { showOsd(); if (els['osd-play']) Nav.focus(els['osd-play']); } return true; } showOsd(); return false;
      case 'DOWN': if (!osdVisible()) { showOsd(); if (els['osd-play']) Nav.focus(els['osd-play']); return true; } showOsd(); return false;
      case 'LEFT': if (!osdVisible()) { if (current && current.type !== 'live') seek(-30); else if (current) toggleZapList(); return true; } showOsd(); return false;
      case 'RIGHT': if (!osdVisible()) { if (current && current.type !== 'live') seek(30); else showOsd(); return true; } showOsd(); return false;
    }
    return false;
  }

  function action(a) {
    switch (a) {
      case 'p-play': togglePlay(); break; case 'p-rew': seek(-30); break; case 'p-ffw': seek(30); break;
      case 'p-next': next(); break; case 'p-prev': prev(); break; case 'p-ratio': cycleRatio(); break;
      case 'p-fav': if (current && current.type !== 'catchup' && Store.toggleFav && App.account) { var t = current.type === 'episode' ? 'series' : current.type; var id = current.type === 'episode' ? current.seriesId : current.id; var on = Store.toggleFav(App.account.id, { type: t, id: id, name: current.seriesName || current.name, logo: current.logo, poster: current.poster, ext: current.ext, cmd: current.cmd, url: current.url, catId: current.catId, num: current.num, epgId: current.epgId }); UI.toast(on ? 'Added to favorites' : 'Removed from favorites'); updateFavBtn(); } break;
    }
    showOsd();
  }

  function setOnEnded(fn) { onEnded = fn; }
  function getCurrent() { return current; }
  function reset() { zapOpen = false; trackMenuOpen = false; cancelZap(); hideAutoNext(); if (els['zap-list']) els['zap-list'].classList.remove('show'); if (els['track-menu']) els['track-menu'].classList.remove('show'); hideOsd(); }

  return { 
    init: init, 
    showLoading: showLoading, 
    play: play, 
    stop: stop, 
    handleKey: handleKey, 
    action: action, 
    setOnEnded: setOnEnded, 
    current: getCurrent, 
    showOsd: showOsd, 
    reset: reset, 
    autoNext: autoNext, 
    hideAutoNext: hideAutoNext, 
    video: function () { return video; } 
  };
})();
