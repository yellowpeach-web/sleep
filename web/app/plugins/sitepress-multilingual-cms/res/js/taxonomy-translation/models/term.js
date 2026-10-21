(function () {
	TaxonomyTranslation.models.Term = Backbone.Model.extend({

		idAttribute: "term_taxonomy_id",

		defaults: function () {
			return {
				name: false,
				trid: false,
				term_taxonomy_id: false,
				language_code: false,
				slug: false,
				parent: false,
				correctedParent: false,
				description: false,
				level: 0,
				correctedLevel: 0,
				source_language_code: false,
				meta_data: false
			};
		},

		save: function (name, slug, description, meta_data) {
			var self = this;
			slug = slug ? slug : '';
			description = description ? description : '';

			if (name) {
				jQuery.ajax({
					url: ajaxurl,
					type: "POST",
					data: {
						action: 'wpml_save_term',
						name: name,
						slug: slug,
						_icl_nonce: labels.wpml_save_term_nonce,
						description: description,
						trid: self.get("trid"),
						term_language_code: self.get("language_code"),
						taxonomy: TaxonomyTranslation.classes.taxonomy.get("taxonomy"),
						meta_data: meta_data,
						force_hierarchical_sync: true
					},
					success: function (response) {
						var newTermData = response.data;

						/* A valid save may carry an empty slug: wp_insert_term stores
						   an empty slug when the name sanitizes to nothing (e.g. a name
						   of only special characters). Check presence, not truthiness,
						   or such saves are misreported as failures (wpmldev-7728). */
						if (newTermData && newTermData.language_code && newTermData.trid && typeof newTermData.slug === 'string' && newTermData.term_taxonomy_id) {
							self.set(newTermData);
							self.clearNeedsUpdate();
							self.trigger("translationSaved");
							WPML_Translate_taxonomy.callbacks.fire('wpml_tt_save_term_translation', TaxonomyTranslation.classes.taxonomy.get("taxonomy"));
						} else {
							/* A refusal the server explains (wpmldev-11306: a term
							   meta the edited text no longer decodes to) carries
							   its message under data.message; pass it to the popup
							   so the user learns why nothing was saved. */
							var message = ( response && response.success === false && response.data && typeof response.data.message === 'string' ) ? response.data.message : '';
							self.trigger("saveFailed", message);
						}
						return self;
					},
					error: function(){
						self.trigger("saveFailed");
						return self;
					}
				});
			}
		},

		/**
		 * The user just supplied the current translation by hand, so the
		 * needs-update state is resolved (the server resets the flag on the
		 * same save, wpmldev-4776). The flag lives in two places client-side:
		 * on this model (read by TermView.render) and in the parent row's
		 * per-language map, which every row re-render copies back onto the
		 * term - both must be cleared or the icon survives until a reload.
		 */
		clearNeedsUpdate: function () {
			var self = this;

			self.set( "needsUpdate", false, { silent: true } );

			var row = TaxonomyTranslation.data.termRowsCollection.find( function ( termRow ) {
				// Strict, on strings. This used to be `==` with an eqeqeq
				// suppression because the row's trid arrived as a NUMBER and
				// the term's as a string; both are strings now, and a loose
				// compare here would silently match a neighbouring group whose
				// trid rounds to the same double (wpmldev-5066).
				return String( termRow.get( "trid" ) ) === String( self.get( "trid" ) );
			} );
			if ( row ) {
				var needsUpdate = _.clone( row.get( "needsUpdate" ) || {} );
				delete needsUpdate[ self.get( "language_code" ) ];
				row.set( "needsUpdate", needsUpdate, { silent: true } );
			}
		},

		isOriginal: function() {
			return this.get( 'source_language_code' ) === null;
		},
		getNameSlugAndDescription: function () {
			var self = this;
			var term = {};
			term.slug = self.getSlug();

			term.description = self.get("description");
			if ( ! term.description ) {
				term.description = "";
			}
			term.name = self.get("name");
			if ( ! term.name) {
				term.name = "";
			}
			return term;
		},
		getSlug: function () {
			var self = this;

			var slug = self.get("slug");
			if (!slug) {
				slug = "";
			}
			slug = decodeURIComponent(slug);

			return slug;
		},
		getMetaData: function() {
			return this.get('meta_data');
		}
	});
})(TaxonomyTranslation);
