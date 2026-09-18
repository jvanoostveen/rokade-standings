(function () {
  var l10n = window.schakenStandenL10n || {};

  function text(key, fallback) {
    return l10n[key] || fallback;
  }

  function loadInline(content, url, showBack) {
    content.setAttribute('aria-busy', 'true');
    fetch(url, { credentials: 'same-origin' }).then(function (response) {
      if (!response.ok) throw new Error('Unavailable: ' + url);
      return response.text();
    }).then(function (html) {
      var embedded = document.createElement('div');
      embedded.className = 'schaken-standen__embedded';
      embedded.innerHTML = html;
      content.textContent = '';
      if (showBack) {
        var back = document.createElement('button');
        back.type = 'button';
        back.className = 'schaken-standen__back';
        back.textContent = text('back', 'Terug naar ranglijst');
        content.appendChild(back);
      }
      content.appendChild(embedded);
    }).catch(function () {
      content.textContent = text('loadError', 'Dit standenbestand kan niet worden geladen.');
    }).finally(function () { content.removeAttribute('aria-busy'); });
  }

  function endpointFor(root, file) {
    var endpoint = new URL(window.location.href);
    endpoint.searchParams.set('schaken_standen_season', root.dataset.season);
    endpoint.searchParams.set('schaken_standen_file', file);
    return endpoint.toString();
  }

  function showCompetition(root, content, file, compact) {
    content.classList.toggle('is-compact-view', Boolean(compact));
    var endpoint = endpointFor(root, file);
    if (root.dataset.mode === 'iframe') {
      var frame = document.createElement('iframe');
      frame.className = 'schaken-standen__frame';
      frame.title = text('frameTitle', 'Standen');
      frame.loading = 'lazy';
      frame.setAttribute('sandbox', 'allow-same-origin');
      frame.src = endpoint;
      content.textContent = '';
      content.appendChild(frame);
    } else {
      loadInline(content, endpoint, false);
    }
  }

  function updateViews(group, tab, activeFile) {
    var views = group.querySelector('.schaken-standen__views');
    if (!views) return;
    var content = group.querySelector('.schaken-standen__content');
    var options = [
      { label: text('ranking', 'Ranglijst'), file: tab.dataset.file, compact: false },
      { label: text('cross', 'Kruistabel'), file: tab.dataset.crossFile, compact: true },
      { label: text('score', 'Scoretabel'), file: tab.dataset.scoreFile, compact: true }
    ];
    views.textContent = '';
    options.forEach(function (option) {
      if (!option.file) return;
      var view = document.createElement('button');
      view.type = 'button';
      view.className = 'schaken-standen__view' + (option.file === activeFile ? ' is-active' : '');
      view.dataset.file = option.file;
      view.dataset.compact = option.compact ? 'true' : 'false';
      view.setAttribute('aria-pressed', option.file === activeFile ? 'true' : 'false');
      if (content && content.id) view.setAttribute('aria-controls', content.id);
      view.textContent = option.label;
      views.appendChild(view);
    });
  }

  function groupFor(root, category) {
    var groups = root.querySelectorAll('.schaken-standen__group');
    for (var index = 0; index < groups.length; index++) {
      if (groups[index].dataset.categoryPanel === category) return groups[index];
    }
    return null;
  }

  function activateCompetition(root, category, requestedFile) {
    var group = groupFor(root, category);
    if (!group) return null;

    root.querySelectorAll('.schaken-standen__category').forEach(function (item) {
      var active = item.dataset.category === category;
      item.classList.toggle('is-active', active);
      item.setAttribute('aria-pressed', active ? 'true' : 'false');
    });
    root.querySelectorAll('.schaken-standen__group').forEach(function (item) {
      item.classList.toggle('is-active', item === group);
    });

    var tabs = group.querySelectorAll('.schaken-standen__tab');
    var tab = null;
    for (var index = 0; index < tabs.length; index++) {
      if (tabs[index].dataset.file === requestedFile) {
        tab = tabs[index];
        break;
      }
    }
    tab = tab || tabs[0];
    if (!tab) return null;

    tabs.forEach(function (item) {
      var active = item === tab;
      item.classList.toggle('is-active', active);
      item.setAttribute('aria-pressed', active ? 'true' : 'false');
    });
    updateViews(group, tab, tab.dataset.file);
    showCompetition(root, group.querySelector('.schaken-standen__content'), tab.dataset.file, false);
    return { group: group, tab: tab };
  }

  function saveCompetitionInUrl(root, group, tab) {
    var url = new URL(window.location.href);
    url.searchParams.set('rokade_categorie', group.dataset.categoryPanel);
    url.searchParams.set('rokade_competitie', tab.dataset.file);
    window.history.replaceState(window.history.state, '', url.toString());
  }

  function restoreCompetitionFromUrl(root) {
    var url = new URL(window.location.href);
    var category = url.searchParams.get('rokade_categorie');
    var file = url.searchParams.get('rokade_competitie');
    if (!category && file) {
      root.querySelectorAll('.schaken-standen__tab').forEach(function (tab) {
        if (tab.dataset.file === file) category = tab.closest('.schaken-standen__group').dataset.categoryPanel;
      });
    }
    if (category) activateCompetition(root, category, file);
  }

  document.addEventListener('click', function (event) {
    var back = event.target.closest('.schaken-standen__back');
    if (back) {
      var backRoot = back.closest('.schaken-standen');
      var activeTab = backRoot.querySelector('.schaken-standen__group.is-active .schaken-standen__tab.is-active');
      if (!activeTab) return;
      showCompetition(backRoot, back.closest('.schaken-standen__content'), activeTab.dataset.file, false);
      return;
    }

    var view = event.target.closest('.schaken-standen__view');
    if (view) {
      var viewGroup = view.closest('.schaken-standen__group');
      var viewRoot = view.closest('.schaken-standen');
      viewGroup.querySelectorAll('.schaken-standen__view').forEach(function (item) {
        item.classList.toggle('is-active', item === view);
        item.setAttribute('aria-pressed', item === view ? 'true' : 'false');
      });
      showCompetition(viewRoot, viewGroup.querySelector('.schaken-standen__content'), view.dataset.file, view.dataset.compact === 'true');
      return;
    }

    var link = event.target.closest('.schaken-standen__embedded a');
    if (link) {
      var linkRoot = link.closest('.schaken-standen');
      var linkUrl = new URL(link.href, window.location.href);
      if (linkRoot && linkRoot.dataset.mode === 'inline' && linkUrl.origin === window.location.origin && linkUrl.searchParams.has('schaken_standen_file')) {
        event.preventDefault();
        loadInline(link.closest('.schaken-standen__content'), linkUrl.toString(), true);
      }
      return;
    }

    var button = event.target.closest('.schaken-standen__category, .schaken-standen__tab');
    if (!button) return;
    var root = button.closest('.schaken-standen');
    if (!root) return;

    if (button.classList.contains('schaken-standen__category')) {
      var categorySelection = activateCompetition(root, button.dataset.category, '');
      if (categorySelection) saveCompetitionInUrl(root, categorySelection.group, categorySelection.tab);
      return;
    }

    var tabGroup = button.closest('.schaken-standen__group');
    var tabSelection = activateCompetition(root, tabGroup.dataset.categoryPanel, button.dataset.file);
    if (tabSelection) saveCompetitionInUrl(root, tabSelection.group, tabSelection.tab);
  });

  document.querySelectorAll('.schaken-standen').forEach(restoreCompetitionFromUrl);
}());
