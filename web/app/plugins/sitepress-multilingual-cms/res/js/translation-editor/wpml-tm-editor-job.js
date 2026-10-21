/*global _, Backbone, jQuery, WpmlTmEditorModel, ajaxurl */

var WPML_TM = WPML_TM || {};

(function () {
	"use strict";

	WPML_TM.editorJob = Backbone.Model.extend({
		fetch: function (successCallback) {
			var self = this;

			_.each(WpmlTmEditorModel.fields, function (field) {
				field.field_finished = parseInt(field.field_finished, 10);
				self.set(field.field_type, field.field_data);
				self.set(field.field_type + '_raw', field);
			});
			self.set('layout', WpmlTmEditorModel.layout);
			successCallback();
		},
		save: function (data) {
			var self = this;
			jQuery.ajax(
				{
					type: "POST",
					url: self.url(),
					dataType: 'json',
					data: {
						data: data,
						action: 'wpml_save_job_ajax',
						_icl_nonce: self.get('nonce')
					},
					success: function (response) {
						if (response.success) {
							self.trigger('saveJobSuccess');
						} else {
							self.trigger('saveJobFailed');
						}
					}
				});
		},
		progressPercentage: function () {
			// Count every field the form will submit, not just the ones on
			// screen. The server completes a job only when EVERY submitted
			// field carries `finished`, so measuring a different (smaller) set
			// here lets the editor report 100% - and auto-tick the single
			// "Translation complete" box - over a job the server then refuses
			// to complete. With the "hide completed fields" switcher on, the
			// old `:visible` pair went to 0/0 = NaN once the last row was
			// ticked and hidden, so a fully finished job saved as still in
			// progress (wpmldev-7815).
			var finishedCheckboxes = jQuery('.icl_tm_finished');

			if (!finishedCheckboxes.length) {
				return 0;
			}

			return finishedCheckboxes.filter(':checked').length / finishedCheckboxes.length * 100;
		},
		/**
		 * Overrides the BackBone url method to use the WordPress ajax endpoint
		 *
		 * @returns {String}
		 */
		url: function () {

			return ajaxurl;
		}
	});
}());
	