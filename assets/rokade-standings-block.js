(function (blocks, blockEditor, components, element, i18n, serverSideRender) {
  var el = element.createElement;
  var InspectorControls = blockEditor.InspectorControls;
  var PanelBody = components.PanelBody;
  var SelectControl = components.SelectControl;
  var ServerSideRender = serverSideRender;
  var __ = i18n.__;
  var data = window.schakenStandenBlock || { seasons: [], seasonCategories: {}, labels: {} };

  function categoriesFor(season) {
    var seasons = data.seasons || [];
    var selectedSeason = season || seasons[seasons.length - 1];
    return data.seasonCategories[selectedSeason] || {};
  }

  function seasonOptions() {
    var options = [{ value: '', label: data.labels.latestSeason || __('Meest recente seizoen', 'schaken-standen') }];
    (data.seasons || []).slice().reverse().forEach(function (season) {
      options.push({ value: season, label: season });
    });
    return options;
  }

  function categoryOptions(season) {
    var options = [{ value: '', label: data.labels.allCategories || __('Alle competities', 'schaken-standen') }];
    var categories = categoriesFor(season);
    Object.keys(categories).forEach(function (category) {
      options.push({ value: category, label: categories[category] });
    });
    return options;
  }

  blocks.registerBlockType('schaken-standen/rokade', {
    edit: function (props) {
      var attributes = props.attributes;
      var validCategories = categoryOptions(attributes.seizoen).map(function (option) { return option.value; });
      var category = validCategories.indexOf(attributes.categorie) === -1 ? '' : attributes.categorie;

      return el(element.Fragment, {},
        el(InspectorControls, {},
          el(PanelBody, { title: __('Standen instellen', 'schaken-standen'), initialOpen: true },
            el(SelectControl, {
              label: __('Seizoen', 'schaken-standen'),
              value: attributes.seizoen,
              options: seasonOptions(),
              onChange: function (seizoen) {
                var availableCategories = categoryOptions(seizoen).map(function (option) { return option.value; });
                props.setAttributes({
                  seizoen: seizoen,
                  categorie: availableCategories.indexOf(attributes.categorie) === -1 ? '' : attributes.categorie
                });
              }
            }),
            el(SelectControl, {
              label: __('Competitie', 'schaken-standen'),
              value: category,
              options: categoryOptions(attributes.seizoen),
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
        el('div', {
          className: 'schaken-standen-block-preview',
          onClickCapture: function (event) {
            event.preventDefault();
            event.stopPropagation();
          },
          onKeyDownCapture: function (event) {
            if (event.key === 'Enter' || event.key === ' ') {
              event.preventDefault();
              event.stopPropagation();
            }
          }
        },
          el(ServerSideRender, { block: 'schaken-standen/rokade', attributes: attributes })
        )
      );
    },
    save: function () { return null; }
  });
}(window.wp.blocks, window.wp.blockEditor, window.wp.components, window.wp.element, window.wp.i18n, window.wp.serverSideRender));
