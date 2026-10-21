/*globals TaxonomyTranslation, Backbone, WPML_core, labels, jQuery */

(function () {
	"use strict";
	
	TaxonomyTranslation.views.FilterView = Backbone.View.extend({

		template: WPML_core[ 'templates/taxonomy-translation/filter.html' ],
		sentenceTemplate: WPML_core[ 'templates/taxonomy-translation/status-trans-select.html' ],
		model: TaxonomyTranslation.models.Taxonomy,
		tag: "div",
		untranslated: false,
		parent: 0,
		lang: 'all',
		search: '',
		updatingFilter: false,

		events: {
			"change #child_of": "updateFilter",
			"change #status-select": "updateFilter",
			"change #in-lang": "updateLangFilter",
			"keyup #tax-search": "updateFilter",
            "click #tax-apply": "updateTaxHierachy"
		},

		initialize: function () {
			this.listenTo(this.model, 'newTaxonomySet', this.render);
			this.listenTo(this.model, 'syncDataLoaded', this.render);
			this.listenTo(this.model, 'modeChanged', this.modeChanged);
			this.listenTo(TaxonomyTranslation.classes.taxonomy, 'syncDataLoaded', this.updateLangFilter);
		},
		render: function () {
			var self = this;
			
			if ( ! self.updatingFilter ) {
				var currentTaxonomy = self.model.get("taxonomy");
	
				if (!currentTaxonomy) {
					return false;
				} else {
					currentTaxonomy = TaxonomyTranslation.data.taxonomies[currentTaxonomy];
				}
	
				self.$el.html(self.template({
					langs: TaxonomyTranslation.data.activeLanguages,
					taxonomy: currentTaxonomy,
					parents: self.model.get("parents"),
					mode: TaxonomyTranslation.mainView.mode
				}));
			}

			self.updatingFilter = false;
			return self;
		},
		updateLangFilter: function () {
			var self = this;

			if (TaxonomyTranslation.mainView.mode === 'sync') {
				var newLang = self.selectedLang();
				if (self.lang !== newLang) {
					self.lang = newLang;
					self.updatingFilter = true;
					TaxonomyTranslation.classes.taxonomy.loadSyncData(newLang);
					TaxonomyTranslation.mainView.showLoadingSpinner();
				}
			} else {
				self.updateFilter();
			}

			return self;
		},
		updateFilter: function () {
			var self = this;

			var parent = self.$el.find("#child_of").val();
			self.parent = parent != undefined && parent != -1 ? parent : 0;
			var untranslated = self.$el.find("#status-select").val();
			self.untranslated = !!(untranslated != undefined && untranslated == 1);
			self.setSelectVisibility();
			var search = self.$el.find("#tax-search").val();
			self.search = search != undefined && search.length > 1 ? search : 0;

			self.trigger("updatedFilter");

			return self;
		},

		selectedLang: function(){

			var self = this;
			var inLangSelect = self.$el.find("#in-lang");

			return inLangSelect.val();
		},
		setSelectVisibility: function(){
			var self = this;
			var showLanguage = self.untranslated || TaxonomyTranslation.mainView.mode === 'sync';
			if (TaxonomyTranslation.mainView.mode !== 'sync') {
				self.renderSentence(showLanguage);
			}
			if (showLanguage) {
				var lang = self.selectedLang();
				self.lang = lang != undefined && lang != 'all' ? lang : 'all';
			} else {
				self.lang = 'all';
			}

			return self;
		},
		/**
		 * The filter reads "Show [status]" or "Show [status] in [language]"
		 * (wpmldev-8462). Each is one translated sentence with the dropdowns
		 * where the translation puts them, so switching between them renders
		 * the sentence again, keeping the chosen values and the focus.
		 */
		renderSentence: function(showLanguage){
			var self = this;
			var sentence = self.$el.find(".wpml-tt-filter-sentence");
			if (!sentence.length || String(sentence.data("show-language")) === (showLanguage ? "1" : "0")) {
				return self;
			}
			var inLangSelect = self.$el.find("#in-lang");
			if (inLangSelect.length) {
				self.chosenLang = inLangSelect.val();
			}
			var focusedId = document.activeElement && sentence[0].contains(document.activeElement) ? document.activeElement.id : "";
			var currentTaxonomy = TaxonomyTranslation.data.taxonomies[self.model.get("taxonomy")];
			sentence.replaceWith(self.sentenceTemplate({
				taxonomy: currentTaxonomy,
				langs: TaxonomyTranslation.data.activeLanguages,
				showLanguage: showLanguage,
				untranslated: self.untranslated,
				language: self.chosenLang || "all"
			}));
			if (focusedId) {
				self.$el.find("#" + focusedId).trigger("focus");
			}

			return self;
		},
		modeChanged: function() {
			var self = this;
			if (TaxonomyTranslation.mainView.mode === 'translate') {
				self.render();
			}
		},
		updateTaxHierachy: function () {
            var self = this;

            if (TaxonomyTranslation.mainView.mode === 'sync') {
                TaxonomyTranslation.mainView.model.doSync(self.selectedLang());
            } else {
                self.updateLangFilter();
            }

            return self;
        }
	});
})(TaxonomyTranslation);
