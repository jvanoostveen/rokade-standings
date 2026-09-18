(function () {
  document.addEventListener('click', function (event) {
    var button = event.target.closest('.schaken-standen__category, .schaken-standen__tab');
    if (!button) return;
    var root = button.closest('.schaken-standen');
    if (!root) return;

    if (button.classList.contains('schaken-standen__category')) {
      var category = button.dataset.category;
      root.querySelectorAll('.schaken-standen__category').forEach(function (item) { item.classList.toggle('is-active', item === button); });
      root.querySelectorAll('.schaken-standen__group').forEach(function (item) { item.classList.toggle('is-active', item.dataset.categoryPanel === category); });
      return;
    }

    root.querySelectorAll('.schaken-standen__tab').forEach(function (item) {
      var active = item === button;
      item.classList.toggle('is-active', active);
      item.setAttribute('aria-selected', active ? 'true' : 'false');
    });
    var content = button.closest('.schaken-standen__group').querySelector('.schaken-standen__content');
    var season = root.dataset.season;
    var endpoint = new URL(window.location.href);
    endpoint.searchParams.set('schaken_standen_season', season);
    endpoint.searchParams.set('schaken_standen_file', button.dataset.file);
    if (root.dataset.mode === 'iframe') {
      content.innerHTML = '<iframe class="schaken-standen__frame" title="Standen" src="' + endpoint.toString() + '" loading="lazy"></iframe>';
    } else {
      content.setAttribute('aria-busy', 'true');
      fetch(endpoint.toString(), { credentials: 'same-origin' }).then(function (response) {
        if (!response.ok) throw new Error('Bestand niet beschikbaar');
        return response.text();
      }).then(function (html) {
        content.innerHTML = '<div class="schaken-standen__embedded">' + html + '</div>';
      }).catch(function () {
        content.textContent = 'Dit standenbestand kan niet worden geladen.';
      }).finally(function () { content.removeAttribute('aria-busy'); });
    }
  });
}());
