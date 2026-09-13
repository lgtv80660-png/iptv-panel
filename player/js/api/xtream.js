/* Integration Xtream / G-PANEL Proxy */
function XtreamProvider(account) {
  this.account = account;
  // S'assure de pointer sur le domaine de base (ex: https://gmtv.vercel.app)
  this.baseUrl = account.url.replace(/\/+$/, '');
}

XtreamProvider.prototype.login = function () {
  var self = this;
  // Requête d'authentification envoyée au proxy player_api.php de G-PANEL
  var url = this.baseUrl + '/player_api.php?username=' + encodeURIComponent(this.account.username) + 
            '&password=' + encodeURIComponent(this.account.password);
  
  return U.getJSON(url).then(function (data) {
    if (data.user_info && (data.user_info.auth === 1 || data.user_info.status === 'Active')) {
      var exp = data.user_info.exp_date ? Number(data.user_info.exp_date) * 1000 : null;
      return { 
        loggedIn: true, 
        expires: exp, 
        maxConnections: data.user_info.max_connections || 1 
      };
    }
    throw new Error(data.user_info && data.user_info.auth === 0 ? 'Identifiants invalides' : 'Échec d’authentification G-PANEL');
  });
};

XtreamProvider.prototype.liveCategories = function () {
  var url = this.baseUrl + '/player_api.php?username=' + encodeURIComponent(this.account.username) + 
            '&password=' + encodeURIComponent(this.account.password) + '&action=get_live_categories';
  return U.getJSON(url);
};

XtreamProvider.prototype.liveStreams = function (catId) {
  var url = this.baseUrl + '/player_api.php?username=' + encodeURIComponent(this.account.username) + 
            '&password=' + encodeURIComponent(this.account.password) + '&action=get_live_streams' + 
            (catId ? '&category_id=' + catId : '');
  return U.getJSON(url).then(function (list) {
    if (!Array.isArray(list)) return [];
    return list.map(function (x) {
      return { 
        id: x.stream_id, 
        name: x.name, 
        type: 'live', 
        logo: x.stream_icon, 
        catId: x.category_id, 
        epgId: x.epg_channel_id 
      };
    });
  });
};

XtreamProvider.prototype.vodCategories = function () {
  var url = this.baseUrl + '/player_api.php?username=' + encodeURIComponent(this.account.username) + 
            '&password=' + encodeURIComponent(this.account.password) + '&action=get_vod_categories';
  return U.getJSON(url);
};

XtreamProvider.prototype.vodStreams = function (catId) {
  var url = this.baseUrl + '/player_api.php?username=' + encodeURIComponent(this.account.username) + 
            '&password=' + encodeURIComponent(this.account.password) + '&action=get_vod_streams' + 
            (catId ? '&category_id=' + catId : '');
  return U.getJSON(url).then(function (list) {
    if (!Array.isArray(list)) return [];
    return list.map(function (x) {
      return { 
        id: x.stream_id, 
        name: x.name, 
        type: 'movie', 
        poster: x.stream_icon, 
        rating: x.rating, 
        catId: x.category_id, 
        ext: x.container_extension || 'mp4' 
      };
    });
  });
};

XtreamProvider.prototype.seriesCategories = function () {
  var url = this.baseUrl + '/player_api.php?username=' + encodeURIComponent(this.account.username) + 
            '&password=' + encodeURIComponent(this.account.password) + '&action=get_series_categories';
  return U.getJSON(url);
};

XtreamProvider.prototype.seriesList = function (catId) {
  var url = this.baseUrl + '/player_api.php?username=' + encodeURIComponent(this.account.username) + 
            '&password=' + encodeURIComponent(this.account.password) + '&action=get_series' + 
            (catId ? '&category_id=' + catId : '');
  return U.getJSON(url).then(function (list) {
    if (!Array.isArray(list)) return [];
    return list.map(function (x) {
      return { 
        id: x.series_id, 
        name: x.name, 
        type: 'series', 
        poster: x.cover, 
        rating: x.rating, 
        catId: x.category_id, 
        plot: x.plot 
      };
    });
  });
};

XtreamProvider.prototype.streamUrl = function (item) {
  var fmt = Store.settings().liveFormat || 'ts';
  var u = encodeURIComponent(this.account.username);
  var p = encodeURIComponent(this.account.password);

  if (item.type === 'live') {
    return Promise.resolve(this.baseUrl + '/live/' + u + '/' + p + '/' + item.id + (fmt === 'm3u8' ? '.m3u8' : '.ts'));
  }
  if (item.type === 'movie') {
    return Promise.resolve(this.baseUrl + '/movie/' + u + '/' + p + '/' + item.id + '.' + (item.ext || 'mp4'));
  }
  if (item.type === 'episode') {
    return Promise.resolve(this.baseUrl + '/series/' + u + '/' + p + '/' + item.id + '.' + (item.ext || 'mp4'));
  }
  return Promise.resolve(item.url);
};
