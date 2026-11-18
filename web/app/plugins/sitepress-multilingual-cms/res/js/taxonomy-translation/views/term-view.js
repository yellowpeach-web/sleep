/*globals labels, TaxonomyTranslation, _, jQuery, WPML_core */

(function () {

	TaxonomyTranslation.views.TermView = Backbone.View.extend({

		tagName: 'span',
		template: WPML_core[ "templates/taxonomy-translation/term-translated.html" ],
		model: TaxonomyTranslation.models.Term,
		popUpView: false,
		needsCorrection: false,
		events: {
			"click .icl_tt_term_name": "openPopUpTerm"
		},

		initialize: function () {
			var self = this;
			self.listenTo(self.model, 'translationSaved', self.render);
			self.listenTo(self.model, 'translationSaved', function () {
				jQuery('#tax-apply').prop('disabled', false);
			});
		},
		loadSyncData: function () {
			"use strict";
			var self = this;

			var syncData = TaxonomyTranslation.data.syncData;
			var ttid = self.model.get('term_taxonomy_id');
			var parent = self.model.get('parent');
			var found = false;
			var needsCorrection = false;
			var correctParentText = false;
			var parentName = TaxonomyTranslation.classes.taxonomy.getTermName(parent);
			var removedParent = '';
			var addedParent = '';
			_.each(syncData, function (correction) {
				if (correction.translated_id == ttid) {
					found = true;

					if (parent !== 0) {
						removedParent = TaxonomyTranslation.classes.taxonomy.getTermName(parent);
						jQuery('.wpml-parent-removed').show();
					}
					if (correction.correct_parent !== 0) {
						addedParent = TaxonomyTranslation.classes.taxonomy.getTermName(correction.correct_parent);
						jQuery('.wpml-parent-added').show();
					}
					needsCorrection = true;
				}
			});

			if (needsCorrection === true) {
				self.template = WPML_core[ 'templates/taxonomy-translation/term-not-synced.html' ];
			} else {
				self.template = WPML_core[ 'templates/taxonomy-translation/term-synced.html' ];
			}

			self.$el.html(
				self.template({
					trid: self.model.get("trid"),
					lang: self.model.get("language_code"),
					name: self.model.get("name"),
					level: self.model.get("level"),
					correctedLevel: self.model.get("level"),
					correctParent: correctParentText,
					parent: parentName,
					removedParent: removedParent,
					addedParent: addedParent
				})
			);

			self.needsCorrection = needsCorrection;

			return self;
		},

		render: function () {
			var self = this;

			self.needsCorrection = false;
			var langs = TaxonomyTranslation.data.activeLanguages;
			var lang = self.model.get( "language_code" );
			var langLabel = ( langs && langs[ lang ] && langs[ lang ].label ) ? langs[ lang ].label : lang;
			var name = self.model.get( "name" );
			var stateLabel = '';

			if ( self.model.get( "inProgress" ) && ! self.model.isOriginal() ) {
				self.template = WPML_core[ "templates/taxonomy-translation/term-in-progress.html" ];
			} else if ( self.model.get( "needsUpdate" ) && ! self.model.isOriginal() ) {
				self.template = WPML_core[ "templates/taxonomy-translation/term-needs-update.html" ];
				stateLabel = self.buildStateLabel( langLabel, name, labels.needsUpdateOfTerm, labels.needsUpdate );
			} else if ( ! name ) {
				self.template = WPML_core[ "templates/taxonomy-translation/term-not-translated.html" ];
			} else if ( self.model.isOriginal() ) {
				self.template = WPML_core[ "templates/taxonomy-translation/term-original-disabled.html" ];
			} else {
				self.template = WPML_core[ "templates/taxonomy-translation/term-translated.html" ];
				stateLabel = self.buildStateLabel( langLabel, name, labels.editTranslationOfTerm, labels.editTranslation );
			}

			var html = self.template({
					trid: self.model.get("trid"),
					lang: lang,
					name: name,
					level: self.model.get("level"),
					correctedLevel: self.model.get("level"),
					langs: langs,
					stateLabel: stateLabel
				});
			self.$el.html( html );

			return self;
		},
		/**
		 * The name of one per-language control in a term row.
		 *
		 * The control is an icon on its own, so this text is everything a
		 * screen reader announces and everything a hover shows. Where there
		 * is a translation it carries the translation itself, which is the
		 * one thing the row could not say before. `withTerm` is a label with
		 * %language% and %term% placeholders; `withoutTerm` is the plain
		 * state name, used only for the case where the state says there is a
		 * translation and no term text came with it.
		 */
		buildStateLabel: function ( langLabel, name, withTerm, withoutTerm ) {
			if ( name && withTerm ) {
				return String( withTerm )
					.replace( '%language%', langLabel )
					.replace( '%term%', name );
			}

			return langLabel + ': ' + withoutTerm;
		},
		openPopUpTerm: function (e) {
			var self = this;

			e.preventDefault();
			var trid = self.model.get("trid");
			var lang = self.model.get("language_code");
			if (trid && lang) {
				if (TaxonomyTranslation.classes.termPopUpView && typeof TaxonomyTranslation.classes.termPopUpView !== 'undefined') {
					TaxonomyTranslation.classes.termPopUpView.close();
				}
				if ( self.model.isOriginal() ) {
					TaxonomyTranslation.classes.termPopUpView = new TaxonomyTranslation.views.OriginalTermPopUpView( { model: self.model } );
				} else {
					TaxonomyTranslation.classes.termPopUpView = new TaxonomyTranslation.views.TermPopUpView( { model: self.model } );
				}
				TaxonomyTranslation.classes.termPopUpView.open( trid, lang );
			}
		}
	});
})(TaxonomyTranslation);
