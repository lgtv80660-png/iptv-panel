/* Integration Xtream / G-PANEL Proxy — Fix HTTP/HTTPS Stream Protocol */
function XtreamProvider(account) {
  this.account = account;
  this.baseUrl = account.url ? account.url.replace(/\/+$/, '') : window.location.origin;
}

function toArray(data) {
  if (!data) return [];
  if (Array.isArray(data)) return data;
  if (typeof data === 'object') {
    return Object.keys(data).map(function (k) { return data[k]; }).filter(function (x) { return typeof x === 'object'; });
  }
  return [];
}

XtreamProvider.prototype.login = function () {
  var url = this.baseUrl + '/player_api.php?username=' + encodeURIComponent(this.account.username) + '&password=' + encodeURIComponent(this.account.password);
  return U.getJSON(url).then(function (data) {
    if (data && (data.user_info || data.status === 'Active' || data.auth === 1)) {
      var exp = (data.user_info && data.user_info.exp_date) ? Number(data.user_info.exp_date) * 1000 : null;
      return { loggedIn: true, expires: exp, maxConnections: 1 };
    }
    return { loggedIn: true, expires: null, maxConnections: 1 };
  }).catch(function() {
    return { loggedIn: true, expires: null, maxConnections: 1 };
  });
};

XtreamProvider.prototype.liveCategories = function () {
  var url = this.baseUrl + '/player_api.php?username=' + encodeURIComponent(this.account.username) + '&password=' + encodeURIComponent(this.account.password) + '&action=get_live_categories';
  return U.getJSON(url).then(function(res) {
    return toArray(res).map(function(c) {
      return { id: c.category_id || c.id, name: c.category_name || c.name || 'Catégorie' };
    });
  });
};

XtreamProvider.prototype.liveStreams = function (catId) {
  var url = this.baseUrl + '/player_api.php?username=' + encodeURIComponent(this.account.username) + '&password=' + encodeURIComponent(this.account.password) + '&action=get_live_streams' + (catId ? '&category_id=' + catId : '');
  return U.getJSON(url).then(function (res) {
    var list = toArray(res);
    return list.map(function (x) {
      return { 
        id: x.stream_id || x.id, 
        streamId: x.stream_id || x.id,
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
  var url = this.baseUrl + '/player_api.php?username=' + encodeURIComponent(this.account.username) + '&password=' + encodeURIComponent(this.account.password) + '&action=get_vod_categories';
  return U.getJSON(url).then(function(res) {
    return toArray(res).map(function(c) {
      return { id: c.category_id || c.id, name: c.category_name || c.name || 'Catégorie' };
    });
  });
};

XtreamProvider.prototype.vodStreams = function (catId) {
  var url = this.baseUrl + '/player_api.php?username=' + encodeURIComponent(this.account.username) + '&password=' + encodeURIComponent(this.account.password) + '&action=get_vod_streams' + (catId ? '&category_id=' + catId : '');
  return U.getJSON(url).then(function (res) {
    var list = toArray(res);
    return list.map(function (x) {
      return { 
        id: x.stream_id || x.id, 
        streamId: x.stream_id || x.id,
        name: x.name, 
        type: 'movie', 
        poster: x.stream_icon, 
        rating: x.rating, 
        catId: x.category_id, 
        ext: x.container_extension || 'mkv' 
      };
    });
  });
};

XtreamProvider.prototype.seriesCategories = function () {
  var url = this.baseUrl + '/player_api.php?username=' + encodeURIComponent(this.account.username) + '&password=' + encodeURIComponent(this.account.password) + '&action=get_series_categories';
  return U.getJSON(url).then(function(res) {
    return toArray(res).map(function(c) {
      return { id: c.category_id || c.id, name: c.category_name || c.name || 'Catégorie' };
    });
  });
};

XtreamProvider.prototype.seriesList = function (catId) {
  var url = this.baseUrl + '/player_api.php?username=' + encodeURIComponent(this.account.username) + '&password=' + encodeURIComponent(this.account.password) + '&action=get_series' + (catId ? '&category_id=' + catId : '');
  return U.getJSON(url).then(function (res) {
    var list = toArray(res);
    return list.map(function (x) {
      return { 
        id: x.series_id || x.id, 
        seriesId: x.series_id || x.id,
        name: x.name, 
        type: 'series', 
        poster: x.cover || x.stream_icon, 
        rating: x.rating, 
        catId: x.category_id, 
        plot: x.plot 
      };
    });
  });
};

XtreamProvider.prototype.vodInfo = function (id, item) {
  var url = this.baseUrl + '/player_api.php?username=' + encodeURIComponent(this.account.username) + '&password=' + encodeURIComponent(this.account.password) + '&action=get_vod_info&vod_id=' + id;
  return U.getJSON(url).then(function (d) {
    if (!d) return item;
    var ext = (d.movie_data && d.movie_data.container_extension) ? d.movie_data.container_extension : (item ? item.ext : 'mkv');
    return {
      id: id,
      name: (d.info && d.info.name) ? d.info.name : (item ? item.name : ''),
      plot: (d.info && d.info.plot) ? d.info.plot : '',
      poster: (d.info && d.info.movie_image) ? d.info.movie_image : (item ? item.poster : ''),
      backdrop: (d.info && d.info.backdrop_path && d.info.backdrop_path.length) ? d.info.backdrop_path[0] : null,
      ext: ext || 'mkv'
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
          episodes: toArray(d.episodes[sNum]).map(function (e) {
            return { 
              id: e.id, 
              streamId: e.id,
              name: e.title || ('Épisode ' + e.episode), 
              season: Number(sNum), 
              episode: Number(e.episode), 
              ext: e.container_extension || 'mkv', 
              type: 'episode'
            };
          })
        };
      });
    }
    var sList = Object.keys(seasonsMap).map(function (k) { return seasonsMap[k]; });
    return { id: id, name: (d && d.info) ? d.info.name : '', plot: (d && d.info) ? d.info.plot : '', poster: (d && d.info) ? d.info.cover : '', seasons: sList };
  }).catch(function() { return { id: id, name: '', plot: '', poster: '', seasons: [] }; });
};

XtreamProvider.prototype.shortEPG = function () { return Promise.resolve([]); };

/* GENERATION FORCEE EN HTTP SANS HTTPS SUR LES STREAMS */
XtreamProvider.prototype.streamUrl = function (item) {
  if (!item) return Promise.resolve('');
  
  var u = encodeURIComponent(this.account.username);
  var p = encodeURIComponent(this.account.password);
  var id = item.streamId || item.id;
  var ext = item.ext || 'mkv';

  // Forcer l'hôte en HTTP pur (remplace https:// par http://)
  var httpHost = this.baseUrl.replace(/^https:\/\//i, 'http://');

  // 1. Live TV
  if (item.type === 'live') {
    var fmt = Store.settings().liveFormat || 'ts';
    return Promise.resolve(httpHost + '/live/' + u + '/' + p + '/' + id + '.' + fmt);
  }

  // 2. Films (VOD) ex: http://foxbleu.org/movie/ludovic/8333/394067.mkv
  if (item.type === 'movie' || item.type === 'vod') {
    return Promise.resolve(httpHost + '/movie/' + u + '/' + p + '/' + id + '.' + ext);
  }

  // 3. Séries
  if (item.type === 'episode' || item.type === 'series') {
    return Promise.resolve(httpHost + '/series/' + u + '/' + p + '/' + id + '.' + ext);
  }

  var finalUrl = item.url || '';
  return Promise.resolve(finalUrl.replace(/^https:\/\//i, 'http://'));
};
