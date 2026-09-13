/* RGBTv — Controller Principal (v2.0 - Auto-Login Xtream Direct) */
var App = (function () {
  // CONFIGURATION DES IDENTIFIANTS DIRECTS / HARDCODÉS
  var DIRECT_CONFIG = {
    url: window.location.origin, // Utilise le domaine G-PANEL Vercel
    username: 'zohir',           // Ton utilisateur Xtream / G-PANEL
    password: '123',          // Ton mot de passe Xtream / G-PANEL
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
  function isScreen(n) { return screen === n; }

  var NAV_ORDER = ['home', 'favorites', 'live', 'movies', 'series', 'search', 'weather', 'adhan', 'settings'];

  function showSection(name) {
    var fromLeft = NAV_ORDER.indexOf(name) < NAV_ORDER.indexOf(section); section = name;
    U.$$('.nav-item').forEach(function (b) { b.classList.toggle('active', b.getAttribute('data-section') === name); });
    U.$$('.section').forEach(function (s) { s.classList.toggle('active', s.id === 'sec-' + name); s.classList.toggle('from-left', s.id === 'sec-' + name && fromLeft); });
    switch (name) {
      case 'live': loadLive(); break; 
      case 'movies': loadMovies(); break; 
      case 'series': loadSeries(); break;
      case 'favorites': renderFavorites(); break; 
      case 'settings': renderSettings(); break; 
      case 'home': renderHome(); break;
    }
  }

  function applyTheme() {
    var s = Store.settings(); 
    document.body.setAttribute('data-theme', s.theme || 'aurora');
  }

  /* ---------- DEMARRAGE AUTOMATIQUE (AUTO-LOGIN DIRECT) ---------- */
  function init() {
    applyTheme(); 
    Player.init();
    Player.setOnEnded(onPlaybackEnded);
    bindEvents();
    
    // Lancement automatique sans afficher de sélection de profil
    startDirectLogin();
  }

  function startDirectLogin() {
    showScreen('splash');
    U.$('#splash-status').textContent = 'Connexion automatique au serveur...';

    // Création automatique du compte d'accès direct
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

    // Connexion brute directe
    provider.login().then(function (info) {
      U.$('#chip-name').textContent = account.name;
      showScreen('home'); 
      showSection('home');
    }).catch(function (e) {
      U.$('#splash-status').textContent = 'Erreur de connexion : ' + (e.message || 'Serveur injoignable');
      console.error('Erreur Xtream Direct:', e);
    });
  }

  /* ---------- NAVIGATION & CONTENU ---------- */
  function renderHome() {
    var rows = U.$('#home-rows'); 
    rows.innerHTML = '';
    UI.skeletonRows(rows, 2);

    Promise.all([
      provider.vodStreams().catch(function() { return []; }),
      provider.liveStreams().catch(function() { return []; })
    ]).then(function(res) {
      rows.innerHTML = '';
      var vods = res[0] || [];
      var lives = res[1] || [];

      if (lives.length) rows.appendChild(UI.row('Chaînes TV Direct', lives.slice(0, 20)));
      if (vods.length) rows.appendChild(UI.row('Derniers Films', vods.slice(0, 20)));
    });
  }

  function loadLive() {
    var catBox = U.$('#live-cats'), chBox = U.$('#live-channels');
    UI.skeletonList(catBox, 6); UI.skeletonList(chBox, 8);

    provider.liveCategories().then(function(cats) {
      UI.renderCats(catBox, [{id: null, name: 'Toutes les chaînes'}].concat(cats), null, 'lcat', function(c) {
        provider.liveStreams(c.id).then(function(list) {
          live.list = list;
          UI.renderChannels(chBox, list, null, function() {}, function(ch, i) { playLive(ch, i); });
        });
      });
      return provider.liveStreams();
    }).then(function(list) {
      live.list = list;
      UI.renderChannels(chBox, list, null, function() {}, function(ch, i) { playLive(ch, i); });
    });
  }

  function loadMovies() {
    var grid = U.$('#movies-grid'); UI.skeletonGrid(grid);
    provider.vodStreams().then(function(list) {
      movies.list = list;
      UI.renderGrid(grid, list, 'mgrid', function(it) { openItem(it); });
    });
  }

  function loadSeries() {
    var grid = U.$('#series-grid'); UI.skeletonGrid(grid);
    provider.seriesList().then(function(list) {
      series.list = list;
      UI.renderGrid(grid, list, 'sgrid', function(it) { openItem(it); });
    });
  }

  function renderFavorites() { }
  function renderSettings() { }

  /* ---------- LECTURE VIDEO DIRECTE ---------- */
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
        if (info.seasons && info.seasons.length) {
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
    Player.play(playable, { list: live.list, index: index || 0 });
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
    });
  }

  window.addEventListener('load', init);
  return { openItem: openItem, closePlayer: closePlayer };
})();
