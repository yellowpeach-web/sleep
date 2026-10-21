var WPML_Media_Posts_Media_Flag = WPML_Media_Posts_Media_Flag || {};

jQuery(function ($) {

    "use strict";

	var updateContainer = $('#wpml-media-posts-media-flag');

	var updateButton = updateContainer.find('.button-primary');
	var spinner      = updateContainer.find('.spinner');

	var prepareAction = updateContainer.data('prepareAction');
	var prepareNonce  = updateContainer.data('prepareNonce');

	var processAction = updateContainer.data('processAction');
	var processNonce  = updateContainer.data('processNonce');

	var statusContainer = updateContainer.find('.status');

    function getQueryParams(qs) {
        qs = qs.split('+').join(' ');

        var params = {},
            tokens,
            re = /[?&]?([^=]+)=([^&]*)/g;

        while (tokens = re.exec(qs)) {
            params[decodeURIComponent(tokens[1])] = decodeURIComponent(tokens[2]);
        }

        return params;
    }


    var queryParams = getQueryParams(location.search);
    if (queryParams.run_setup) {
        showProgress();
        runSetup();
    }

    updateButton.on("click", function () {
        showProgress();
        runSetup();
    });

    function showProgress() {
        spinner.css({visibility: "visible"});
        updateButton.prop("disabled", true);
    }

    function hideProgress() {
        spinner.css({visibility: "hidden"});
        updateButton.prop("disabled", false);
    }

    function setStatus(statusText) {
        statusContainer.html(statusText);
    }

    function runSetup() {
        var data = {
			action: prepareAction,
			nonce: prepareNonce
        };
        $.ajax({
            url: ajaxurl,
            type: "POST",
            dataType: "json",
            data: data,
            success: function (response) {
				handleResponse(response);
				if (!response.success) {
					return;
				}

                if (response.data.status) {
                    setStatus(response.data.status);
                }
                // Straight into the batch loop. There used to be an extra
                // setInitialLanguage() call here that posted the SAME process
                // action without an offset - a full batch whose progress line
                // the browser then threw away, because it read
                // response.data.message while the endpoint answers with
                // response.data.status (wpmldev-7805 follow-up). It stayed
                // invisible while the manual run always had several batches
                // left: the batches after it reported through setHasMediaFlag()
                // and its correct read. Since the idle-admin scan is capped to
                // one batch per page view, an admin page opened before the
                // Media tab already scans a batch in the background, so the
                // manual run is often down to a SINGLE batch - the one whose
                // percentage was dropped - and the button greyed out with no
                // progress at all until the page reloaded.
                setHasMediaFlag(0);
			},
			error: function (jqXHR, status, error) {
				statusContainer.html(jqXHR.statusText || status || error);
			}
        });
    }

	function handleResponse(response) {
		var error = [];

		if (response.error) {
			error.push(response.error);
		}
		if (!response.success && response.data) {
			error.push(response.data);
		}

		if (error.length) {
			statusContainer.html('<pre>' + error.join('</pre><pre>') + '</pre>');
		}
	}

    function setHasMediaFlag(offset) {
        var data = {
			action: processAction,
			nonce: processNonce,
            offset: offset
        };
        $.ajax({
            url: ajaxurl,
            type: "POST",
            dataType: "json",
            data: data,
            success: function (response) {
				handleResponse(response);
				if (!response.success) {
					return;
				}

                if (response.data.status) {
                    setStatus(response.data.status);
                }
                if (response.data.continue) {
                    setHasMediaFlag(response.data.offset);
                } else {
                    if (queryParams.redirect_to) {
                        location.href = queryParams.redirect_to;
                    } else {
                        location.reload();
                    }
                }
			},
			error: function (jqXHR, status, error) {
				statusContainer.html(jqXHR.statusText || status || error);
			}
        });
    }

});
