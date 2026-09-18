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
    endpoint.searchParams.delete('rokade_bron');
    endpoint.searchParams.delete('rokade_seizoen');
    endpoint.searchParams.delete('rokade_categorie');
    endpoint.searchParams.delete('rokade_competitie');
    endpoint.searchParams.set('schaken_standen_source', root.dataset.source);
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

  function markActiveView(group, file) {
    group.querySelectorAll('.schaken-standen__view').forEach(function (item) {
      var active = item.dataset.file === file;
      item.classList.toggle('is-active', active);
      item.setAttribute('aria-pressed', active ? 'true' : 'false');
    });
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

  function tabFor(group, competition) {
    if (!competition) return null;
    var tabs = group.querySelectorAll('.schaken-standen__tab');
    for (var index = 0; index < tabs.length; index++) {
      if (tabs[index].dataset.competition === competition) return tabs[index];
    }
    return null;
  }

  // fromUrl: this selection came from the query string rather than a click, so
  // an unknown competition must be left alone instead of falling back to the
  // first tab -- otherwise a second block on the same page, which reads the very
  // same parameters, would jump somewhere the visitor never asked for.
  function activateCompetition(root, category, requestedCompetition, fromUrl) {
    var group = groupFor(root, category);
    if (!group) return null;

    var tab = tabFor(group, requestedCompetition);
    if (!tab) {
      if (fromUrl && requestedCompetition) return null;
      tab = group.querySelector('.schaken-standen__tab');
    }
    if (!tab) return null;

    var content = group.querySelector('.schaken-standen__content');
    // The server already inlined this exact view, so restoring it needs no fetch.
    var alreadyRendered = tab.classList.contains('is-active') && content && content.children.length > 0;

    root.querySelectorAll('.schaken-standen__category').forEach(function (item) {
      var active = item.dataset.category === category;
      item.classList.toggle('is-active', active);
      item.setAttribute('aria-pressed', active ? 'true' : 'false');
    });
    root.querySelectorAll('.schaken-standen__group').forEach(function (item) {
      item.classList.toggle('is-active', item === group);
    });
    group.querySelectorAll('.schaken-standen__tab').forEach(function (item) {
      var active = item === tab;
      item.classList.toggle('is-active', active);
      item.setAttribute('aria-pressed', active ? 'true' : 'false');
    });

    if (!(fromUrl && alreadyRendered)) {
      updateViews(group, tab, tab.dataset.file);
      showCompetition(root, content, tab.dataset.file, false);
    }
    return { group: group, tab: tab };
  }

  function saveCompetitionInUrl(root, group, tab) {
    var url = new URL(window.location.href);
    url.searchParams.set('rokade_bron', root.dataset.source);
    url.searchParams.set('rokade_seizoen', root.dataset.season);
    url.searchParams.set('rokade_categorie', group.dataset.categoryPanel);
    url.searchParams.set('rokade_competitie', tab.dataset.competition);
    window.history.replaceState(window.history.state, '', url.toString());
  }

  function restoreCompetitionFromUrl(root) {
    var url = new URL(window.location.href);
    // Two blocks on one page can show different sources, or the same source in
    // two seasons; the link belongs to the block it was saved from, not to
    // whichever block happens to share a tab name.
    var source = url.searchParams.get('rokade_bron');
    if (source && source !== root.dataset.source) return;
    var season = url.searchParams.get('rokade_seizoen');
    if (season && season !== root.dataset.season) return;
    var category = url.searchParams.get('rokade_categorie');
    var competition = url.searchParams.get('rokade_competitie');
    if (!category && competition) {
      var tabs = root.querySelectorAll('.schaken-standen__tab');
      for (var index = 0; index < tabs.length && !category; index++) {
        if (tabs[index].dataset.competition === competition) {
          category = tabs[index].closest('.schaken-standen__group').dataset.categoryPanel;
        }
      }
    }
    if (category) activateCompetition(root, category, competition, true);
  }

  document.addEventListener('click', function (event) {
    var back = event.target.closest('.schaken-standen__back');
    if (back) {
      var backRoot = back.closest('.schaken-standen');
      var backGroup = back.closest('.schaken-standen__group');
      var activeTab = backRoot.querySelector('.schaken-standen__group.is-active .schaken-standen__tab.is-active');
      if (!activeTab) return;
      // This shows the ranking again, so the ranking button -- not whichever
      // table the visitor followed the link from -- is the one left pressed.
      if (backGroup) markActiveView(backGroup, activeTab.dataset.file);
      showCompetition(backRoot, back.closest('.schaken-standen__content'), activeTab.dataset.file, false);
      return;
    }

    var view = event.target.closest('.schaken-standen__view');
    if (view) {
      var viewGroup = view.closest('.schaken-standen__group');
      var viewRoot = view.closest('.schaken-standen');
      markActiveView(viewGroup, view.dataset.file);
      showCompetition(viewRoot, viewGroup.querySelector('.schaken-standen__content'), view.dataset.file, view.dataset.compact === 'true');
      return;
    }

    var link = event.target.closest('.schaken-standen__embedded a');
    if (link) {
      var linkRoot = link.closest('.schaken-standen');
      var linkUrl = new URL(link.href, window.location.href);
      if (linkRoot && linkRoot.dataset.mode === 'inline' && linkUrl.origin === window.location.origin && linkUrl.searchParams.has('schaken_standen_file')) {
        event.preventDefault();
        var linkContent = link.closest('.schaken-standen__content');
        // The detail page has its own table shape; keeping the cross table's
        // compact columns would squeeze it.
        linkContent.classList.remove('is-compact-view');
        loadInline(linkContent, linkUrl.toString(), true);
      }
      return;
    }

    var button = event.target.closest('.schaken-standen__category, .schaken-standen__tab');
    if (!button) return;
    var root = button.closest('.schaken-standen');
    if (!root) return;

    if (button.classList.contains('schaken-standen__category')) {
      var categorySelection = activateCompetition(root, button.dataset.category, null, false);
      if (categorySelection) saveCompetitionInUrl(root, categorySelection.group, categorySelection.tab);
      return;
    }

    var tabGroup = button.closest('.schaken-standen__group');
    var tabSelection = activateCompetition(root, tabGroup.dataset.categoryPanel, button.dataset.competition, false);
    if (tabSelection) saveCompetitionInUrl(root, tabSelection.group, tabSelection.tab);
  });

  document.querySelectorAll('.schaken-standen').forEach(restoreCompetitionFromUrl);
}());
