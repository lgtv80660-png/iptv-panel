/* Integration Xtream / G-PANEL Proxy — Corrigé */
function XtreamProvider(account) {
  this.account = account;
  this.baseUrl = account.url ? account.url.replace(/\/+$/, '') : window.location.origin;
}

XtreamProvider.prototype.login = function () {
  var url = this.baseUrl + '/player_api.php?username=' + encodeURIComponent(this.account.username) + '&password=' + encodeURIComponent(this.account.password);
  return U.getJSON(url).then(function (data) {
    if (data && data.user_info && (data.user_info.auth === 1 || data.user_info.status === 'Active' || data.user_info.auth === '1')) {
      var exp = data.user_info.exp_date ? Number(data.user_info.exp_date) * 1000 : null;
      return {
        loggedIn: true,
        expires: exp,
        maxConnections: data.user_info.max_connections || 1
      };
    }
    // Si G-PANEL renvoie directement { status: "Active" } à la racine
    if (data && (data.status === 'Active' || data.auth === 1)) {
      return { loggedIn: true, expires: null, maxConnections: 1 };
    }
    throw new Error((data && data.user_info && data.user_info.auth === 0) ? 'Identifiants G-PANEL incorrects' : 'Compte inactif ou introuvable');
  });
};

XtreamProvider.prototype.liveCategories = function () {
  var url = this.baseUrl + '/player_api.php?username=' + encodeURIComponent(this.account.username) + '&password=' + encodeURIComponent(this.account.password) + '&action=get_live_categories';
  return U.getJSON(url).then(function(res) { return Array.isArray(res) ? res : []; });
};

XtreamProvider.prototype.liveStreams = function (catId) {
  var url = this.baseUrl + '/player_api.php?username=' + encodeURIComponent(this.account.username) + '&password=' + encodeURIComponent(this.account.password) + '&action=get_live_streams' + (catId ? '&category_id=' + catId : '');
  return U.getJSON(url).then(function (list) {
    if (!Array.isArray(list)) return [];
    return list.map(function (x) {
      return { id: x.stream_id, name: x.name, type: 'live', logo: x.stream_icon, catId: x.category_id, epgId: x.epg_channel_id };
    });
  });
};

XtreamProvider.prototype.vodCategories = function () {
  var url = this.baseUrl + '/player_api.php?username=' + encodeURIComponent(this.account.username) + '&password=' + encodeURIComponent(this.account.password) + '&action=get_vod_categories';
  return U.getJSON(url).then(function(res) { return Array.isArray(res) ? res : []; });
};

XtreamProvider.prototype.vodStreams = function (catId) {
  var url = this.baseUrl + '/player_api.php?username=' + encodeURIComponent(this.account.username) + '&password=' + encodeURIComponent(this.account.password) + '&action=get_vod_streams' + (catId ? '&category_id=' + catId : '');
  return U.getJSON(url).then(function (list) {
    if (!Array.isArray(list)) return [];
    return list.map(function (x) {
      return { id: x.stream_id, name: x.name, type: 'movie', poster: x.stream_icon, rating: x.rating, catId: x.category_id, ext: x.container_extension || 'mp4' };
    });
  });
};

XtreamProvider.prototype.seriesCategories = function () {
  var url = this.baseUrl + '/player_api.php?username=' + encodeURIComponent(this.account.username) + '&password=' + encodeURIComponent(this.account.password) + '&action=get_series_categories';
  return U.getJSON(url).then(function(res) { return Array.isArray(res) ? res : []; });
};

XtreamProvider.prototype.seriesList = function (catId) {
  var url = this.baseUrl + '/player_api.php?username=' + encodeURIComponent(this.account.username) + '&password=' + encodeURIComponent(this.account.password) + '&action=get_series' + (catId ? '&category_id=' + catId : '');
  return U.getJSON(url).then(function (list) {
    if (!Array.isArray(list)) return [];
    return list.map(function (x) {
      return { id: x.series_id, name: x.name, type: 'series', poster: x.cover, rating: x.rating, catId: x.category_id, plot: x.plot };
    });
  });
};

XtreamProvider.prototype.vodInfo = function (id, item) {
  var url = this.baseUrl + '/player_api.php?username=' + encodeURIComponent(this.account.username) + '&password=' + encodeURIComponent(this.account.password) + '&action=get_vod_info&vod_id=' + id;
  return U.getJSON(url).then(function (d) {
    if (!d) return item;
    return {
      id: id,
      name: (d.info && d.info.name) ? d.info.name : item.name,
      plot: (d.info && d.info.plot) ? d.info.plot : '',
      poster: (d.info && d.info.movie_image) ? d.info.movie_image : item.poster,
      backdrop: (d.info && d.info.backdrop_path && d.info.backdrop_path.length) ? d.info.backdrop_path[0] : null,
      ext: (d.movie_data && d.movie_data.container_extension) ? d.movie_data.container_extension : (item.ext || 'mp4')
    };
  }).catch(function() { return item; });
};

XtreamProvider.prototype.seriesInfo = function (id) {
  var url = this.baseUrl + '/player_api.php?username=' + encodeURIComponent(this.account.username) + '&password=' + encodeURIComponent(this.account.password) + '&action=get_series_info&series_id=' + id;
  return U.getJSON(url).then(function (d) {
    var seasonsMap = {};
    if (d && d.episodes) {
      Object.keys(d.episodes).forEach(function (sNum) {
        seasonsMap[sNum] = {
          num: Number(sNum),
          episodes: d.episodes[sNum].map(function (e) {
            return { id: e.id, name: e.title, season: Number(sNum), episode: Number(e.episode), ext: e.container_extension || 'mp4', url: e.id };
          })
        };
      });
    }
    var sList = Object.keys(seasonsMap).map(function (k) { return seasonsMap[k]; });
    return { id: id, name: (d && d.info) ? d.info.name : '', plot: (d && d.info) ? d.info.plot : '', poster: (d && d.info) ? d.info.cover : '', seasons: sList };
  }).catch(function() { return { id: id, name: '', plot: '', poster: '', seasons: [] }; });
};

XtreamProvider.prototype.shortEPG = function () { return Promise.resolve([]); };

XtreamProvider.prototype.streamUrl = function (item) {
  var u = encodeURIComponent(this.account.username);
  var p = encodeURIComponent(this.account.password);

  // G-PANEL Router direct via get.php
  if (item.type === 'live') {
    return Promise.resolve(this.baseUrl + '/get.php?username=' + u + '&password=' + p + '&type=m3u_plus&stream=' + item.id);
  }
  if (item.type === 'movie') {
    return Promise.resolve(this.baseUrl + '/movie/' + u + '/' + p + '/' + item.id + '.' + (item.ext || 'mp4'));
  }
  if (item.type === 'episode') {
    return Promise.resolve(this.baseUrl + '/series/' + u + '/' + p + '/' + item.id + '.' + (item.ext || 'mp4'));
  }
  return Promise.resolve(item.url || '');
};
