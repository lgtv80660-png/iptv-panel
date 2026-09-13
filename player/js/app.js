document.getElementById('netflix-form').addEventListener('submit', function (e) {
  e.preventDefault();
  var btn = this.querySelector('button[type="submit"]');
  var errDiv = document.getElementById('net_err');
  var u = document.getElementById('net_user').value.trim();
  var p = document.getElementById('net_pass').value.trim();
  
  if (!u || !p) return;

  btn.disabled = true;
  btn.textContent = 'Connexion...';
  if (errDiv) errDiv.style.display = 'none';

  // Le serveur cible est l'adresse hébergeant G-PANEL
  var profile = {
    id: 'xtream_' + Date.now(),
    name: u,
    type: 'xtream',
    url: window.location.origin, // e.g. https://gmtv.vercel.app
    username: u,
    password: p,
    avatar: 'img/largeIcon.png',
    created: Date.now()
  };

  Store.addAccount(profile);

  openAccount(profile.id, true).catch(function (err) {
    // Si la connexion au G-PANEL échoue, on nettoie et affiche l'erreur
    Store.removeAccount(profile.id);
    btn.disabled = false;
    btn.textContent = 'Sign In';
    if (errDiv) {
      errDiv.textContent = 'Erreur G-PANEL : ' + (err.message || 'Identifiants ou serveur incorrects');
      errDiv.style.display = 'block';
    }
  });
});
