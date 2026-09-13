/* RGBTv — Controller Principal (v2.1 - Fix Tout-en-un G-PANEL) */
var App = (function () {
  // CONFIGURATION DES IDENTIFIANTS DIRECTS / HARDCODÉS (G-PANEL)
  var DIRECT_CONFIG = {
    url: window.location.origin, // Récupère https://gmtv.vercel.app
    username: 'zohir',           // Identifiant G-PANEL
    password: '123456',             // Mot de passe G-PANEL
    name: 'G-PANEL TV'
  };

  var screen = 'splash', section = 'home', account = null, provider = null;
  var live = { cats: [], catId: null, list: [], selected: null };
  var movies = { cats: [], catId: null, list: [] }, series = { cats: [], catId: null, list: [] };
  var details = { base: null, info: null, season: null, list: null };

  function showScreen(name) {
    screen = name;
    U.$$('.screen').forEach(function (s) { s.classList.toggle('active', s.id === 'screen-' + name); });
    Nav.setContainer(activeScreen());
  }
  function activeScreen() { return U.$('#screen-' + screen); }

  var NAV_ORDER = ['home', 'favorites', 'live', 'movies', 'series', 'search', 'weather', 'adhan', 'settings'];

  function showSection(name) {
    var fromLeft = NAV_ORDER.indexOf(name) < NAV_ORDER.indexOf(section); 
    section = name;
    
    U.$$('.nav-item').forEach(function (b) { b.classList.toggle('active', b.getAttribute('data-section') === name); });
    U.$$('.section').forEach(function (s) { s.classList.toggle('active', s.id === 'sec-' + name); s.classList.toggle('from-left', s.id === 'sec-' + name && fromLeft); });
    
    switch (name) {
      case 'home': renderHome(); break;
      case 'live': loadLive(); break; 
      case 'movies': loadMovies(); break; 
      case 'series': loadSeries(); break;
      case 'favorites': renderFavorites(); break; 
      case 'search': Nav.focus(U.$('#osk .osk-key')); break;
      case 'weather': if (window.Weather && Weather.open) Weather.open(); break;
      case 'adhan': if (window.Adhan && Adhan.open) Adhan.open(); break;
      case 'settings': renderSettings(); break; 
    }
  }

  function applyTheme() {
    var s = Store.settings(); 
    document.body.setAttribute('data-theme', s.theme || 'aurora');
    document.body.setAttribute('data-corners', s.corners || 'round');
  }

  /* ---------- DEMARRAGE AUTOMATIQUE G-PANEL ---------- */
  function init() {
    applyTheme(); 
    Player.init();
    if (Player.setOnEnded) Player.setOnEnded(onPlaybackEnded);
    
    // Initialisation des modules annexes
    if (window.Weather && Weather.refresh) setTimeout(Weather.refresh, 1000);
    if (window.Adhan && Adhan.start) Adhan.start();

    bindEvents();
    startDirectLogin();
  }

  function startDirectLogin() {
    showScreen('splash');
    U.$('#splash-status').textContent = 'Chargement de votre compte G-PANEL...';

    account = {
      id: 'xtream_direct',
      name: DIRECT_CONFIG.name,
      type: 'xtream',
      url: DIRECT_CONFIG.url,
      username: DIRECT_CONFIG.username,
      password: DIRECT_CONFIG.password,
      avatar: 'img/largeIcon.png'
    };

    provider = new XtreamProvider(account);
    App.account = account;
    App.provider = provider;

    provider.login().then(function () {
      if (U.$('#chip-name')) U.$('#chip-name').textContent = account.name;
      showScreen('home'); 
      showSection('home');
    }).catch(function (e) {
      console.error('Erreur G-PANEL Login:', e);
      // Même en cas d'erreur de statut, on force l'ouverture du menu principal
      showScreen('home');
      showSection('home');
    });
  }

  /* ---------- ACCUEIL & RECOMMANDATIONS ---------- */
  function renderHome() {
    var rows = U.$('#home-rows'); 
    if (!rows) return;
    rows.innerHTML = '';
    UI.skeletonRows(rows, 2);

    Promise.all([
      provider.vodStreams().catch(function() { return []; }),
      provider.liveStreams().catch(function() { return []; }),
      provider.seriesList().catch(function() { return []; })
    ]).then(function(res) {
      rows.innerHTML = '';
      var vods = res[0] || [];
      var lives = res[1] || [];
      var seriesList = res[2] || [];

      if (lives.length) rows.appendChild(UI.row('Chaînes en Direct', lives.slice(0, 20)));
      if (vods.length) rows.appendChild(UI.row('Films Récents', vods.slice(0, 20)));
      if (seriesList.length) rows.appendChild(UI.row('Séries Populaires', seriesList.slice(0, 20)));
      
      if (!lives.length && !vods.length && !seriesList.length) {
        rows.innerHTML = '<div class="empty">Aucun contenu trouvé sur le serveur.</div>';
      }
    });
  }

  /* ---------- DIRECT TV (LIVE) ---------- */
  function loadLive() {
    var catBox = U.$('#live-cats'), chBox = U.$('#live-channels');
    if (!catBox || !chBox) return;
    UI.skeletonList(catBox, 6); UI.skeletonList(chBox, 8);

    provider.liveCategories().then(function(cats) {
      cats = Array.isArray(cats) ? cats : [];
      UI.renderCats(catBox, [{id: null, name: 'Toutes les chaînes'}].concat(cats), null, 'lcat', function(c) {
        provider.liveStreams(c.id).then(function(list) {
          live.list = list || [];
          UI.renderChannels(chBox, live.list, null, function() {}, function(ch, i) { playLive(ch, i); });
        });
      });
      return provider.liveStreams();
    }).then(function(list) {
      live.list = list || [];
      UI.renderChannels(chBox, live.list, null, function() {}, function(ch, i) { playLive(ch, i); });
    });
  }

  /* ---------- FILMS (VOD) ---------- */
  function loadMovies() {
    var catBox = U.$('#movies-cats'), grid = U.$('#movies-grid');
    if (!grid) return;
    UI.skeletonGrid(grid);

    provider.vodCategories().then(function(cats) {
      cats = Array.isArray(cats) ? cats : [];
      if (catBox) {
        UI.renderCats(catBox, [{id: null, name: 'Tous les films'}].concat(cats), null, 'mcat', function(c) {
          provider.vodStreams(c.id).then(function(list) {
            movies.list = list || [];
            UI.renderGrid(grid, movies.list, 'mgrid', function(it) { openItem(it); });
          });
        });
      }
      return provider.vodStreams();
    }).then(function(list) {
      movies.list = list || [];
      UI.renderGrid(grid, movies.list, 'mgrid', function(it) { openItem(it); });
    });
  }

  /* ---------- SERIES ---------- */
  function loadSeries() {
    var catBox = U.$('#series-cats'), grid = U.$('#series-grid');
    if (!grid) return;
    UI.skeletonGrid(grid);

    provider.seriesCategories().then(function(cats) {
      cats = Array.isArray(cats) ? cats : [];
      if (catBox) {
        UI.renderCats(catBox, [{id: null, name: 'Toutes les séries'}].concat(cats), null, 'scat', function(c) {
          provider.seriesList(c.id).then(function(list) {
            series.list = list || [];
            UI.renderGrid(grid, series.list, 'sgrid', function(it) { openItem(it); });
          });
        });
      }
      return provider.seriesList();
    }).then(function(list) {
      series.list = list || [];
      UI.renderGrid(grid, series.list, 'sgrid', function(it) { openItem(it); });
    });
  }

  /* ---------- FAVORIS & REGLAGES ---------- */
  function renderFavorites() {
    var rows = U.$('#fav-rows');
    if (!rows) return;
    rows.innerHTML = '';
    var f = Store.favorites(account ? account.id : 'direct');
    if (!f || !f.length) {
      rows.innerHTML = '<div class="empty">Aucun favori enregistré.</div>';
      return;
    }
    rows.appendChild(UI.row('Mes Favoris', f));
  }

  function renderSettings() {
    var info = U.$('#settings-account-info');
    if (info) info.textContent = account ? (account.name + ' (' + account.username + ')') : 'G-PANEL Direct';
  }

  /* ---------- OUVERTURE DU LECTEUR VIDEO (PLAYER) ---------- */
  function openItem(it) {
    if (!it) return;
    if (it.type === 'live') {
      playLive(it, 0);
      return;
    }
    if (it.type === 'movie' || it.type === 'vod') {
      playMovie(it);
      return;
    }
    if (it.type === 'series') {
      showScreen('details');
      UI.renderDetails(it, it);
      provider.seriesInfo(it.id).then(function(info) {
        details.base = it;
        details.info = info;
        UI.renderDetails(info, it);
        if (info && info.seasons && info.seasons.length) {
          UI.renderSeasons(info.seasons, info.seasons[0].num, function(s) {
            UI.renderEpisodes(s.episodes, function(ep) { playEpisode(ep, s.episodes); });
          });
          UI.renderEpisodes(info.seasons[0].episodes, function(ep) { playEpisode(ep, info.seasons[0].episodes); });
        }
      });
    }
  }

  function playLive(ch, index) {
    Player.reset();
    showScreen('player');
    var playable = UI.toPlayable(ch);
    Player.play(playable, { list: live.list || [playable], index: index || 0 });
  }

  function playMovie(it) {
    Player.reset();
    showScreen('player');
    var playable = UI.toPlayable(it);
    Player.play(playable, { list: [playable], index: 0 });
  }

  function playEpisode(ep, list) {
    Player.reset();
    showScreen('player');
    var playable = UI.toPlayable(ep);
    playable.name = (details.base ? details.base.name : '') + ' - S' + ep.season + 'E' + ep.episode;
    Player.play(playable, { list: list || [playable], index: 0 });
  }

  function onPlaybackEnded() {
    closePlayer();
  }

  function closePlayer() {
    Player.stop();
    Player.reset();
    showScreen('home');
  }

  function bindEvents() {
    document.addEventListener('click', function (ev) {
      var t = ev.target;
      while (t && t !== document && !(t.getAttribute && (t.getAttribute('data-action') || t.getAttribute('data-section')))) {
        t = t.parentNode;
      }
      if (!t || t === document) return;
      
      var a = t.getAttribute('data-action');
      var sec = t.getAttribute('data-section');

      if (sec) showSection(sec);
      if (a === 'details-play' && details.base) playMovie(details.base);
      if (a === 'details-back' || a === 'p-back') closePlayer();
      if (a === 'clear-cache') { Store.clearCache(account.id); UI.toast('Cache nettoyé', 2000, '✓'); }
    });
  }

  window.addEventListener('load', init);
  return { openItem: openItem, closePlayer: closePlayer };
})();
