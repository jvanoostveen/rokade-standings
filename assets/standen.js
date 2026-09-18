(function () {
  function loadInline(content, url, showBack) {
    content.setAttribute('aria-busy', 'true');
    fetch(url, { credentials: 'same-origin' }).then(function (response) {
      if (!response.ok) throw new Error('Bestand niet beschikbaar');
      return response.text();
    }).then(function (html) {
      var back = showBack ? '<button type="button" class="schaken-standen__back">Terug naar ranglijst</button>' : '';
      content.innerHTML = back + '<div class="schaken-standen__embedded">' + html + '</div>';
    }).catch(function () {
      content.textContent = 'Dit standenbestand kan niet worden geladen.';
    }).finally(function () { content.removeAttribute('aria-busy'); });
  }

  function endpointFor(root, file) {
    var endpoint = new URL(window.location.href);
    endpoint.searchParams.set('schaken_standen_season', root.dataset.season);
    endpoint.searchParams.set('schaken_standen_file', file);
    return endpoint.toString();
  }

  function showCompetition(root, content, file) {
    var endpoint = endpointFor(root, file);
    if (root.dataset.mode === 'iframe') {
      content.innerHTML = '<iframe class="schaken-standen__frame" title="Standen" src="' + endpoint + '" loading="lazy"></iframe>';
    } else {
      loadInline(content, endpoint, false);
    }
  }

  function updateViews(group, tab, activeFile) {
    var views = group.querySelector('.schaken-standen__views');
    if (!views) return;
    var options = [
      { label: 'Ranglijst', file: tab.dataset.file },
      { label: 'Kruistabel', file: tab.dataset.crossFile },
      { label: 'Scoretabel', file: tab.dataset.scoreFile }
    ];
    views.textContent = '';
    options.forEach(function (option) {
      if (!option.file) return;
      var view = document.createElement('button');
      view.type = 'button';
      view.className = 'schaken-standen__view' + (option.file === activeFile ? ' is-active' : '');
      view.dataset.file = option.file;
      view.textContent = option.label;
      views.appendChild(view);
    });
  }

  document.addEventListener('click', function (event) {
    var back = event.target.closest('.schaken-standen__back');
    if (back) {
      var backRoot = back.closest('.schaken-standen');
      var activeTab = backRoot.querySelector('.schaken-standen__group.is-active .schaken-standen__tab.is-active');
      if (!activeTab) return;
      showCompetition(backRoot, back.closest('.schaken-standen__content'), activeTab.dataset.file);
      return;
    }

    var view = event.target.closest('.schaken-standen__view');
    if (view) {
      var viewGroup = view.closest('.schaken-standen__group');
      var viewRoot = view.closest('.schaken-standen');
      viewGroup.querySelectorAll('.schaken-standen__view').forEach(function (item) { item.classList.toggle('is-active', item === view); });
      showCompetition(viewRoot, viewGroup.querySelector('.schaken-standen__content'), view.dataset.file);
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
      var category = button.dataset.category;
      root.querySelectorAll('.schaken-standen__category').forEach(function (item) { item.classList.toggle('is-active', item === button); });
      root.querySelectorAll('.schaken-standen__group').forEach(function (item) { item.classList.toggle('is-active', item.dataset.categoryPanel === category); });
      var group = root.querySelector('.schaken-standen__group[data-category-panel="' + category + '"]');
      var firstTab = group.querySelector('.schaken-standen__tab');
      group.querySelectorAll('.schaken-standen__tab').forEach(function (item) {
        var active = item === firstTab;
        item.classList.toggle('is-active', active);
        item.setAttribute('aria-selected', active ? 'true' : 'false');
      });
      updateViews(group, firstTab, firstTab.dataset.file);
      showCompetition(root, group.querySelector('.schaken-standen__content'), firstTab.dataset.file);
      return;
    }

    root.querySelectorAll('.schaken-standen__tab').forEach(function (item) {
      var active = item === button;
      item.classList.toggle('is-active', active);
      item.setAttribute('aria-selected', active ? 'true' : 'false');
    });
    updateViews(button.closest('.schaken-standen__group'), button, button.dataset.file);
    var content = button.closest('.schaken-standen__group').querySelector('.schaken-standen__content');
    showCompetition(root, content, button.dataset.file);
  });
}());
