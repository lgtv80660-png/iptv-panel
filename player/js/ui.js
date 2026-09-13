/* UI Components & Dynamic Rendering Helpers */
var UI = (function () {
  function renderAccounts(list, manage) {
    var box = U.$('#acc-list'); if (!box) return; box.innerHTML = '';
    list.forEach(function (acc) {
      var card = U.el('div', 'profile focusable');
      card.setAttribute('data-id', acc.id);
      card.innerHTML = '<img src="' + Avatars.url(acc.avatar) + '"><div class="name">' + U.esc(acc.name) + '</div>' + (manage ? '<div class="badge-edit">Edit</div>' : '');
      card.onclick = function () { App.openAccount(acc.id); };
      box.appendChild(card);
    });
  }

  function row(title, items, opts) {
    var r = U.el('div', 'row');
    r.innerHTML = '<div class="row-title">' + U.esc(title) + '</div>';
    var inner = U.el('div', 'row-items');
    items.forEach(function (it, i) {
      if (opts && opts.max && i >= opts.max) return;
      inner.appendChild(card(it, { nav: 'row', list: items }));
    });
    r.appendChild(inner); return r;
  }

  function card(it, opts) {
    opts = opts || {};
    var c = U.el('div', 'card focusable' + (opts.mini ? ' mini' : ''));
    c.setAttribute('data-nav', opts.nav || 'grid'); 
    
    // Attachement explicite de l'objet pour la délégation d'événements
    c._item = it;
    
    var bg = it.poster || it.backdrop || it.logo || '';
    c.innerHTML = '<div class="thumb" style="' + (bg ? 'background-image:url(\'' + U.esc(bg) + '\')' : '') + '"></div><div class="title">' + U.esc(it.name) + '</div>';
    
    c.onclick = function (e) { 
      e.stopPropagation();
      App.openItem(it, opts.list); 
    };
    return c;
  }

  function renderCats(container, cats, selId, navGroup, onSelect) {
    if (!container) return;
    container.innerHTML = '';
    cats.forEach(function (c) {
      var item = U.el('div', 'cat-item focusable' + (c.id === selId ? ' selected' : ''), U.esc(c.name));
      item.setAttribute('data-nav', navGroup);
      item.onclick = function () {
        U.$$('.cat-item', container).forEach(function (e) { e.classList.remove('selected'); });
        item.classList.add('selected'); 
        onSelect(c);
      };
      container.appendChild(item);
    });
  }

  function renderChannels(container, list, selId, onPreview, onPlay) {
    if (!container) return;
    return VList.create(container, list, 60, function (ch, i) {
      var item = U.el('div', 'ch-item focusable' + (ch.id === selId ? ' selected' : ''), U.esc(ch.name));
      item.setAttribute('data-id', ch.id); 
      item.setAttribute('data-nav', 'channel');
      item._item = ch;
      item.onfocus = function () { onPreview(ch); };
      item.onclick = function () { onPlay(ch, i); };
      return item;
    });
  }

  function renderGrid(container, list, navGroup, onClick) {
    if (!container) return;
    container.innerHTML = '';
    list.forEach(function (it) {
      var c = card(it, { nav: navGroup, list: list });
      c.onclick = function (e) { 
        e.stopPropagation();
        onClick(it, list); 
      };
      container.appendChild(c);
    });
  }

  function renderDetails(info, base) {
    var title = U.$('#det-title'), plot = U.$('#det-plot');
    var bg = U.$('#det-backdrop'), poster = U.$('#det-poster');
    
    if (title) title.textContent = info.name || base.name || '';
    if (plot) plot.textContent = info.plot || base.plot || 'Aucune description disponible.';
    
    var bgImg = info.backdrop || base.backdrop || info.poster || base.poster || '';
    var postImg = info.poster || base.poster || '';
    
    if (bg) bg.style.backgroundImage = bgImg ? 'url("' + U.esc(bgImg) + '")' : 'none';
    if (poster) poster.style.backgroundImage = postImg ? 'url("' + U.esc(postImg) + '")' : 'none';
  }

  function renderSeasons(seasons, activeNum, onSelect) {
    var box = U.$('#details-seasons'); if (!box) return; box.innerHTML = '';
    seasons.forEach(function (s) {
      var btn = U.el('button', 'btn season-btn focusable' + (s.num === activeNum ? ' active' : ''), 'Saison ' + s.num);
      btn.setAttribute('data-nav', 'seasons');
      btn.onclick = function () {
        U.$$('.season-btn', box).forEach(function (b) { b.classList.remove('active'); });
        btn.classList.add('active'); 
        onSelect(s);
      };
      box.appendChild(btn);
    });
  }

  function renderEpisodes(episodes, onPlay) {
    var box = U.$('#details-episodes'); if (!box) return; box.innerHTML = '';
    episodes.forEach(function (e) {
      var c = U.el('div', 'card ep-card focusable');
      c.setAttribute('data-nav', 'episodes');
      c.innerHTML = '<div class="title">E' + U.pad(e.episode) + ' - ' + U.esc(e.name) + '</div>';
      c.onclick = function () { onPlay(e); };
      box.appendChild(c);
    });
  }

  function toast(msg, dur, icon) {
    var t = U.el('div', 'toast', (icon ? icon + ' ' : '') + U.esc(msg));
    document.body.appendChild(t);
    setTimeout(function () { t.classList.add('show'); }, 10);
    setTimeout(function () { t.classList.remove('show'); setTimeout(function () { t.remove(); }, 300); }, dur || 3000);
  }

  function toPlayable(it) {
    return { 
      id: it.streamId || it.id, 
      streamId: it.streamId || it.id,
      name: it.name || it.title || '', 
      type: it.type || 'vod', 
      url: it.url, 
      ext: it.ext || 'mp4', 
      logo: it.logo || it.poster 
    };
  }

  function skeletonRows(container, count) {
    if (!container) return;
    container.innerHTML = '';
    for (var i = 0; i < count; i++) {
      var r = U.el('div', 'row sk-row'); 
      r.innerHTML = '<div class="sk-title"></div><div class="row-items"><div class="sk-card"></div><div class="sk-card"></div><div class="sk-card"></div></div>';
      container.appendChild(r);
    }
  }

  function skeletonList(container, count) {
    if (!container) return;
    container.classList.add('sk-list'); container.innerHTML = '';
    for (var i = 0; i < count; i++) container.appendChild(U.el('div', 'sk-item'));
  }

  function skeletonGrid(container) {
    if (!container) return;
    container.innerHTML = '';
    for (var i = 0; i < 8; i++) container.appendChild(U.el('div', 'sk-card'));
  }

  return {
    renderAccounts: renderAccounts, row: row, card: card, renderCats: renderCats,
    renderChannels: renderChannels, renderGrid: renderGrid, renderDetails: renderDetails, 
    renderSeasons: renderSeasons, renderEpisodes: renderEpisodes, toast: toast, 
    toPlayable: toPlayable, skeletonRows: skeletonRows, skeletonList: skeletonList, skeletonGrid: skeletonGrid
  };
})();
