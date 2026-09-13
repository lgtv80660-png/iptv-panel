/* RGBTv — Controller Principal (v2.4 - Complete Fix) */
var App = (function () {
  var DIRECT_CONFIG = {
    url: window.location.origin,
    username: 'zohir',
    password: '123456',
    name: 'G-PANEL TV'
  };

  var screen = 'splash', section = 'home', account = null, provider = null;
  var live = { list: [] }, movies = { list: [] }, series = { list: [] };
  var details = { base: null, info: null };

  function showScreen(name) {
    screen = name;
    U.$$('.screen').forEach(function (s) { s.classList.toggle('active', s.id === 'screen-' + name); });
    if (window.Nav && Nav.setContainer) Nav.setContainer(activeScreen());
  }
  function activeScreen() { return U.$('#screen-' + screen); }
  function isScreen(n) { return screen === n; }

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
      case 'settings': renderSettings(); break; 
    }
  }

  function applyTheme() {
    var s = Store.settings ? Store.settings() : {}; 
    document.body.setAttribute('data-theme', s.theme || 'aurora');
    document.body.setAttribute('data-corners', s.corners || 'round');
  }

  function init() {
    applyTheme(); 
    if (window.Player && Player.init) Player.init();
    if (window.Player && Player.setOnEnded) Player.setOnEnded(onPlaybackEnded);
    
    bindEvents();
    startDirectLogin();
  }

  function startDirectLogin() {
    showScreen('splash');
    if (U.$('#splash-status')) U.$('#splash-status').textContent = 'Connexion à G-PANEL...';

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
    }).catch(function() {
      showScreen('home'); 
      showSection('home');
    });
  }

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
      if (vods.length) rows.appendChild(UI.row('Derniers Films', vods.slice(0, 20)));
      if (seriesList.length) rows.appendChild(UI.row('Séries Populaires', seriesList.slice(0, 20)));
    });
  }

  function loadLive() {
    var catBox = U.$('#live-cats'), chBox = U.$('#live-channels');
    if (!catBox || !chBox) return;
    UI.skeletonList(catBox, 6); UI.skeletonList(chBox, 8);

    provider.liveCategories().then(function(cats) {
      UI.renderCats(catBox, [{id: null, name: 'Toutes les chaînes'}].concat(cats || []), null, 'lcat', function(c) {
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

  function loadMovies() {
    var catBox = U.$('#movies-cats'), grid = U.$('#movies-grid');
    if (!grid) return;
    UI.skeletonGrid(grid);

    provider.vodCategories().then(function(cats) {
      if (catBox) {
        UI.renderCats(catBox, [{id: null, name: 'Tous les films'}].concat(cats || []), null, 'mcat', function(c) {
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

  function loadSeries() {
    var catBox = U.$('#series-cats'), grid = U.$('#series-grid');
    if (!grid) return;
    UI.skeletonGrid(grid);

    provider.seriesCategories().then(function(cats) {
      if (catBox) {
        UI.renderCats(catBox, [{id: null, name: 'Toutes les séries'}].concat(cats || []), null, 'scat', function(c) {
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

  function renderFavorites() {
    var rows = U.$('#fav-rows');
    if (!rows) return;
    rows.innerHTML = '';
    var f = Store.favorites ? Store.favorites('direct') : [];
    if (!f || !f.length) {
      rows.innerHTML = '<div class="empty">Aucun favori enregistré.</div>';
      return;
    }
    rows.appendChild(UI.row('Mes Favoris', f));
  }

  function renderSettings() {
    var info = U.$('#settings-account-info');
    if (info) info.textContent = DIRECT_CONFIG.name + ' (' + DIRECT_CONFIG.username + ')';
  }

  function openItem(it) {
    if (!it) return;
    
    // Direct TV -> Lancement direct
    if (it.type === 'live') { 
      playLive(it, 0); 
      return; 
    }
    
    // Films / VOD -> Ouverture fiche avec binding immédiat
    if (it.type === 'movie' || it.type === 'vod') { 
      details.base = it;
      showScreen('details');
      UI.renderDetails(it, it);
      
      if (U.$('#details-seasons')) U.$('#details-seasons').innerHTML = '';
      if (U.$('#details-episodes')) U.$('#details-episodes').innerHTML = '';
      
      provider.vodInfo(it.id, it).then(function(info) {
        details.info = info;
        UI.renderDetails(info, it);
      });
      return; 
    }
    
    // Séries -> Fiche avec saisons
    if (it.type === 'series') {
      details.base = it;
      showScreen('details');
      UI.renderDetails(it, it);
      provider.seriesInfo(it.id).then(function(info) {
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
    if (!window.Player) return;
    Player.reset();
    showScreen('player');
    var playable = UI.toPlayable(ch);
    Player.play(playable, { list: live.list || [playable], index: index || 0 });
  }

  function playMovie(it) {
    if (!window.Player || !it) return;
    Player.reset();
    showScreen('player');
    var playable = UI.toPlayable(it);
    Player.play(playable, { list: [playable], index: 0 });
  }

  function playEpisode(ep, list) {
    if (!window.Player) return;
    Player.reset();
    showScreen('player');
    var playable = UI.toPlayable(ep);
    playable.name = (details.base ? details.base.name : '') + ' - S' + ep.season + 'E' + ep.episode;
    Player.play(playable, { list: list || [playable], index: 0 });
  }

  function onPlaybackEnded() { closePlayer(); }
  
  function closePlayer() { 
    if (window.Player) {
      Player.stop(); 
      Player.reset(); 
    }
    showScreen('home'); 
  }

  function bindEvents() {
    document.addEventListener('click', function (ev) {
      var t = ev.target;
      while (t && t !== document && !(t.getAttribute && (t.getAttribute('data-action') || t.getAttribute('data-section') || t.id === 'details-play'))) {
        t = t.parentNode;
      }
      if (!t || t === document) return;
      
      var a = t.getAttribute('data-action') || t.id;
      var sec = t.getAttribute('data-section');

      if (sec) showSection(sec);
      
      // Fix du clic sur le bouton "Play Movie" de la fiche
      if (a === 'details-play' || a === 'hero-play') {
        if (details.base) playMovie(details.base);
      }
      if (a === 'details-back' || a === 'p-back') {
        if (screen === 'player') closePlayer();
        else if (screen === 'details') showScreen('home');
      }
    });
  }

  window.addEventListener('load', init);
  return { openItem: openItem, closePlayer: closePlayer, isScreen: isScreen };
})();
