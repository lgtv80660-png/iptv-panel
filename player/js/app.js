/* RGBTv — Controller Principal (v1.2) */
var App = (function () {
  var screen = 'splash', section = 'home', account = null, provider = null;
  var live = { cats: [], catId: null, list: [], selected: null, previewTimer: null, epgTimer: null, previewVideo: null, previewHls: null };
  var movies = { cats: [], catId: null, list: [] }, series = { cats: [], catId: null, list: [] };
  var details = { base: null, info: null, season: null, list: null };
  var editingId = null, addType = 'xtream', addAvatar = 'red', playerReturn = null, manageMode = false;
  var pin = { buf: '', acc: null, resolve: null };

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
    var s = Store.settings(); document.body.setAttribute('data-theme', s.theme || 'aurora');
  }

  function init() {
    applyTheme(); Player.init();
    var last = Store.lastAccount();
    setTimeout(function () { 
      if (last && Store.getAccount(last)) openAccount(last).catch(function() { showAccounts(); }); 
      else showAccounts(); 
    }, 500);
  }

  /* Formulaire style Netflix avec Déblocage d'Erreur */
  function showAccounts(keepManage) {
    if (provider && provider.destroy) provider.destroy();
    provider = null; account = null; App.account = null; App.provider = null;
    var list = Store.accounts();

    if (!list || list.length === 0) {
      showScreen('accounts');
      var wrap = U.$('#screen-accounts .acc-wrap');
      if (wrap) {
        wrap.style.padding = '0';
        wrap.innerHTML = `
          <style>
            .netflix-login-box {
              background: rgba(0, 0, 0, 0.85); padding: 45px 35px; border-radius: 8px;
              width: 90%; max-width: 380px; box-shadow: 0 10px 30px rgba(0,0,0,0.9);
              border: 1px solid rgba(255,255,255,0.1); margin: 0 auto; text-align: left;
            }
            .netflix-login-box h2 { color: #fff; font-size: 26px; margin-bottom: 20px; font-weight: 700; }
            .netflix-input-group { margin-bottom: 16px; }
            .netflix-input-group input {
              width: 100%; padding: 14px; border-radius: 4px; border: 1px solid #333;
              background: #333; color: #fff; font-size: 15px; outline: none; box-sizing: border-box;
            }
            .netflix-btn-submit {
              width: 100%; padding: 14px; border-radius: 4px; border: none;
              background: #e50914; color: #fff; font-size: 16px; font-weight: bold;
              cursor: pointer; margin-top: 15px;
            }
            .netflix-btn-submit:disabled { background: #555; cursor: not-allowed; }
            .netflix-error-msg { color: #e50914; font-size: 14px; margin-top: 12px; display: none; line-height: 1.4; }
          </style>
          <div class="netflix-login-box">
            <div class="brand sm" style="margin-bottom: 25px; font-size: 30px;">RGB<span style="color:#e50914">Tv</span></div>
            <h2>Sign In</h2>
            <form id="netflix-form" autocomplete="off">
              <div class="netflix-input-group">
                <input type="text" id="net_user" placeholder="Username" required autofocus>
              </div>
              <div class="netflix-input-group">
                <input type="password" id="net_pass" placeholder="Password" required>
              </div>
              <button type="submit" class="netflix-btn-submit">Sign In</button>
              <div id="net_err" class="netflix-error-msg"></div>
            </form>
          </div>
        `;

        document.getElementById('netflix-form').addEventListener('submit', function (e) {
          e.preventDefault();
          var btn = this.querySelector('button[type="submit"]');
          var errDiv = document.getElementById('net_err');
          var u = document.getElementById('net_user').value.trim();
          var p = document.getElementById('net_pass').value.trim();
          if (!u || !p) return;

          btn.disabled = true;
          btn.textContent = 'Connecting...';
          if (errDiv) errDiv.style.display = 'none';

          var profile = {
            id: 'xtream_' + Date.now(),
            name: u,
            type: 'xtream',
            url: window.location.origin,
            username: u,
            password: p,
            avatar: 'img/largeIcon.png',
            created: Date.now()
          };

          Store.addAccount(profile);

          openAccount(profile.id, true).catch(function (err) {
            Store.removeAccount(profile.id);
            btn.disabled = false;
            btn.textContent = 'Sign In';
            if (errDiv) {
              errDiv.textContent = 'Connexion échouée : ' + (err.message || 'Serveur G-PANEL injoignable');
              errDiv.style.display = 'block';
            }
          });
        });
      }
      return;
    }

    UI.renderAccounts(list, manageMode); showScreen('accounts');
  }

  function openAccount(id, skipPin) {
    var acc = Store.getAccount(id); 
    if (!acc) return Promise.reject(new Error('Profil introuvable'));

    showScreen('splash');
    U.$('#splash-status').textContent = 'Connexion à ' + acc.name + '...';
    var p = new XtreamProvider(acc);

    return p.login().then(function (info) {
      acc.lastLogin = Date.now();
      Store.updateAccount(acc); 
      Store.setLastAccount(acc.id);
      account = acc; provider = p; App.account = acc; App.provider = p;
      
      showScreen('home'); 
      showSection('home');
      return true;
    }).catch(function (e) {
      showAccounts();
      throw e; // Intercepté par la promesse du submit pour débloquer le bouton
    });
  }

  function renderHome() { }
  function loadLive() { }
  function loadMovies() { }
  function loadSeries() { }
  function renderFavorites() { }
  function renderSettings() { }

  window.addEventListener('load', init);
  return { openAccount: openAccount, showAccounts: showAccounts };
})();
