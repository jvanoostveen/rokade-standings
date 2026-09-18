(function (blocks, blockEditor, components, element, i18n, serverSideRender) {
  var el = element.createElement;
  var InspectorControls = blockEditor.InspectorControls;
  var useBlockProps = blockEditor.useBlockProps;
  var PanelBody = components.PanelBody;
  var SelectControl = components.SelectControl;
  var ServerSideRender = serverSideRender;
  var __ = i18n.__;
  var data = window.schakenStandenBlock || { sources: [], labels: {} };

  function sources() {
    return data.sources || [];
  }

  // An empty bron means "whatever the server picks", which is the first source.
  function sourceFor(bron) {
    var all = sources();
    for (var index = 0; index < all.length; index++) {
      if (all[index].id === bron) return all[index];
    }
    return all[0] || { seasons: [], seasonCategories: {} };
  }

  function sourceOptions() {
    var all = sources();
    var fallback = all[0] ? all[0].label : '';
    var options = [{
      value: '',
      label: (data.labels.defaultSource || __('Eerste bron', 'schaken-standen')) + (fallback ? ' (' + fallback + ')' : '')
    }];
    all.forEach(function (source) {
      options.push({ value: source.id, label: source.label });
    });
    return options;
  }

  function seasonOptions(bron) {
    var options = [{ value: '', label: data.labels.latestSeason || __('Meest recente seizoen', 'schaken-standen') }];
    (sourceFor(bron).seasons || []).slice().reverse().forEach(function (season) {
      options.push({ value: season, label: season });
    });
    return options;
  }

  function categoryOptions(bron, season) {
    var options = [{ value: '', label: data.labels.allCategories || __('Alle competities', 'schaken-standen') }];
    var source = sourceFor(bron);
    var seasons = source.seasons || [];
    var selectedSeason = season || seasons[seasons.length - 1];
    var categories = (source.seasonCategories || {})[selectedSeason] || {};
    Object.keys(categories).forEach(function (category) {
      options.push({ value: category, label: categories[category] });
    });
    return options;
  }

  function values(options) {
    return options.map(function (option) { return option.value; });
  }

  blocks.registerBlockType('schaken-standen/rokade', {
    edit: function (props) {
      var attributes = props.attributes;
      var previewRef = element.useRef(null);
      // A source or season that has since disappeared from the index would leave
      // the select showing a value it cannot offer; fall back to the default.
      var source = values(sourceOptions()).indexOf(attributes.bron) === -1 ? '' : attributes.bron;
      var season = values(seasonOptions(source)).indexOf(attributes.seizoen) === -1 ? '' : attributes.seizoen;
      var category = values(categoryOptions(source, season)).indexOf(attributes.categorie) === -1 ? '' : attributes.categorie;
      var blockProps = useBlockProps({
        className: 'schaken-standen-block-preview',
        ref: previewRef
      });

      element.useEffect(function () {
        var preview = previewRef.current;
        if (!preview) return;

        var disablePreviewControls = function () {
          preview.querySelectorAll('.schaken-standen').forEach(function (standings) {
            standings.setAttribute('inert', '');
            standings.setAttribute('aria-hidden', 'true');
          });
          preview.querySelectorAll('a, button, iframe, input, select, textarea').forEach(function (item) {
            item.setAttribute('tabindex', '-1');
          });
        };
        disablePreviewControls();

        var Observer = preview.ownerDocument.defaultView.MutationObserver;
        if (!Observer) return;
        var observer = new Observer(disablePreviewControls);
        observer.observe(preview, { childList: true, subtree: true });
        return function () { observer.disconnect(); };
      }, [attributes.bron, attributes.seizoen, attributes.categorie, attributes.modus]);

      return el(element.Fragment, {},
        el(InspectorControls, {},
          el(PanelBody, { title: __('Standen instellen', 'schaken-standen'), initialOpen: true },
            sources().length > 1 ? el(SelectControl, {
              label: __('Bron', 'schaken-standen'),
              value: source,
              options: sourceOptions(),
              onChange: function (bron) {
                // Seasons and competitions are per source, so a selection the new
                // source does not have has to be dropped along with it.
                var seasons = values(seasonOptions(bron));
                var keptSeason = seasons.indexOf(attributes.seizoen) === -1 ? '' : attributes.seizoen;
                var categories = values(categoryOptions(bron, keptSeason));
                props.setAttributes({
                  bron: bron,
                  seizoen: keptSeason,
                  categorie: categories.indexOf(attributes.categorie) === -1 ? '' : attributes.categorie
                });
              }
            }) : null,
            el(SelectControl, {
              label: __('Seizoen', 'schaken-standen'),
              value: season,
              options: seasonOptions(source),
              onChange: function (seizoen) {
                var categories = values(categoryOptions(source, seizoen));
                props.setAttributes({
                  seizoen: seizoen,
                  categorie: categories.indexOf(attributes.categorie) === -1 ? '' : attributes.categorie
                });
              }
            }),
            el(SelectControl, {
              label: __('Competitie', 'schaken-standen'),
              value: category,
              options: categoryOptions(source, season),
              onChange: function (categorie) { props.setAttributes({ categorie: categorie }); }
            }),
            el(SelectControl, {
              label: __('Weergave', 'schaken-standen'),
              value: attributes.modus,
              options: [
                { value: 'inline', label: data.labels.inline || __('Inline (in de pagina)', 'schaken-standen') },
                { value: 'iframe', label: data.labels.iframe || __('Oorspronkelijke Rokade-weergave', 'schaken-standen') }
              ],
              onChange: function (modus) { props.setAttributes({ modus: modus }); }
            })
          )
        ),
        el('div', blockProps,
          el(ServerSideRender, { block: 'schaken-standen/rokade', attributes: attributes })
        )
      );
    },
    save: function () { return null; }
  });
}(window.wp.blocks, window.wp.blockEditor, window.wp.components, window.wp.element, window.wp.i18n, window.wp.serverSideRender));
