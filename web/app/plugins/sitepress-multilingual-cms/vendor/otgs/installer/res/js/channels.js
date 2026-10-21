(function($){

    var PRODUCTION = 'production';

    var updateErrors = [];
    var channelUpdateInProgress = false;

    function channelSelectorInit(){

        $('.otgs_wp_installer_table')
            .on('focus', '.installer-channel-selector', saveCurrentValue)
            .on('change', '.installer-channel-selector', maybeShowPrompt);

        $('.otgs_wp_installer_table')
            .on('click', '.installer-channel-retry', retryChannelSwitch);

        $('.installer-switch-confirmation')
            .on('click', '.js-cancel', cancelSwitch)
            .on('click', '.js-proceed', changeChannel);

        $('.otgsi_downloads_form').on('installer-update-complete', maybeShowWarn);
        $('.otgsi_downloads_form').on('installer-update-complete', hideUpdateProgress);

        $('.otgsi_downloads_form').on('installer-update-complete', showConfirmationMessage);

        $('.otgsi_downloads_form').on('installer-update-fail', logUpdateError);

    }

    function saveCurrentValue(){
        $(this).data('previous-value', $(this).val());
    }

    function maybeShowPrompt(){
        var selectorContainer = $(this).closest('.installer-channel-selector-wrap');
        var prompt = selectorContainer.find('.installer-switch-confirmation:hidden');

        if(prompt.length){
            prompt.show();
            selectorContainer.find('select').prop('disabled', true);
            var warnText = selectorContainer.find('.installer-warn-text');
            warnText.hide();
        }else{
            changeChannel(selectorContainer);
        }

    }

    // A channel switch stores the channel and refreshes the plugin list with the versions that
    // channel offers. It installs nothing: the rows that would change are pre-selected and wait
    // for the user to press Download (wpmldev-7759).
    function changeChannel(selectorContainer){

        if(selectorContainer.type == 'click'){
            var selectorContainer = $(this).closest('.installer-channel-selector-wrap');
        }

        var select = selectorContainer.find('select');
        select.prop('disabled', true);

        hideConfirmationMessage(select);
        showUpdateProgress(select);

        selectorContainer.find('.installer-switch-confirmation').hide();

        var data = {
            action: 'installer_set_channel',
            repository_id: select.data('repository-id'),
            channel: select.val(),
            nonce: select.parent().find('.nonce').val(),
            noprompt: selectorContainer.find('.js-remember').length ?
                selectorContainer.find('.js-remember').prop('checked') : 0
        };

        var tableSelector = '#installer_repo_' +  select.data('repository-id')  + ' .installer-table-wrap';
        var downloadsForm = $('#installer_repo_' +  select.data('repository-id') + ' .otgsi_downloads_form');

        var finish = function(){
            downloadsForm.trigger('installer-update-complete');
            select.prop('disabled', false);
        };

        resetUpdateErrors();
        otgs_wp_installer.reset_errors();
        channelUpdateInProgress = true;

        // save selection
        jQuery.ajax({
            url: ajaxurl,
            type: 'POST',
            dataType: 'json',
            data: data,
            success: function (ret) {
                if( ret && ret.status == 'OK'){
                    refreshDownloadsTable(tableSelector, function(refreshed){
                        if(refreshed){
                            preselectChangedRows(tableSelector);
                        }else{
                            logUpdateError();
                        }
                        finish();
                    });
                }else{
                    logUpdateError();
                    finish();
                }
            },
            error: function(){
                logUpdateError();
                finish();
            }

        });

    }

    // Fetches the current page again and swaps in the plugin table rendered on the new channel.
    // Not jQuery's .load(): that sends X-Requested-With, and WPML answers 403 to an XHR GET of its
    // own admin pages (they are not registered on ajax requests), which is where this screen lives
    // since WPML 5.0 (admin.php?page=wpml-activate-update).
    function refreshDownloadsTable(tableSelector, done){
        var url = otgs_wp_installer.sanitize(location.href);

        window.fetch(url, { credentials: 'same-origin' })
            .then(function(response){
                if(!response.ok){
                    throw new Error('HTTP ' + response.status);
                }
                return response.text();
            })
            .then(function(html){
                var table = $('<div>').append($.parseHTML(html)).find(tableSelector + ' table.widefat');
                if(!table.length){
                    throw new Error('no downloads table in the response');
                }
                $(tableSelector).empty().append(table);
                done(true);
            })
            .catch(function(){
                done(false);
            });
    }

    function retryChannelSwitch(){
        var selectorContainer = $(this).closest('.installer-channel-selector-wrap');
        changeChannel(selectorContainer);
        return false;
    }

    function cancelSwitch(){
        $(this).closest('.installer-switch-confirmation').hide();
        var select =  $(this).closest('.installer-switch-confirmation').prev().find('.installer-channel-selector');
        var previousValue = select.data('previous-value');

        select.val(previousValue).prop('disabled', false);

        if( select.val() !== PRODUCTION ){
            var selectorContainer = $(this).closest('.installer-channel-selector-wrap');
            var warnText = selectorContainer.find('.installer-warn-text');
            warnText.show();
        }

    }

    // Ticks the rows whose installed version is not the one the channel offers: newer versions
    // (installer-red-text) and, after a switch back to Production, installed pre-releases that
    // would be replaced (unstable). The reset-to-channel marker lets admin.js send the replacement
    // of a higher installed version when the user presses Download. Nothing is submitted here.
    function preselectChangedRows(downloadsTable){
        var form = $(downloadsTable).closest('form');

        $(downloadsTable + ' tr').each(
            function () {
                var changes = $(this).find(
                        'td.installer_version_installed .installer-red-text, ' +
                        'td.installer_version_installed .unstable'
                    ).length > 0;
                if (changes) {
                    $(this).find('td :checkbox').prop('disabled', false).prop('checked', true);
                }
            }
        );

        if( ! form.find('input[name="reset-to-channel"]').length ){
            form.append('<input type="hidden" name="reset-to-channel" value="1">');
        }

        // admin.js enables the Download button from the checkboxes' change event.
        form.find(':checkbox[name="downloads[]"]').first().trigger('change');

    }

    function maybeShowWarn(){

        var select = $(this)
            .closest('.otgs_wp_installer_table')
            .find('.installer-channel-selector');

        if(select.val() !== PRODUCTION && !hasUpdateErrors()){

            var warnText = select
                .closest('.installer-channel-selector-wrap')
                .find('.installer-warn-text');
            warnText.show();

        }


    }

    function showUpdateProgress(select){

        var spinner = select
            .closest('.installer-channel-selector-wrap')
            .find('.spinner-with-text');

        spinner.addClass('is-active').show();

    }

    function hideUpdateProgress(){

        var spinner = $(this)
            .closest('.otgs_wp_installer_table')
            .find('.installer-channel-selector-wrap')
            .find('.spinner-with-text');

        spinner.removeClass('is-active').hide();

    }

    function showConfirmationMessage(){

        if( ! channelUpdateInProgress ) return false;

        var selectWrap = $(this)
            .closest('.otgs_wp_installer_table')
            .find('.installer-channel-selector-wrap');

        var select = $(this)
            .closest('.otgs_wp_installer_table')
            .find('.installer-channel-selector');

        var channelName = select.find('option:selected').text();

        if( hasUpdateErrors() ) {

            var message = selectWrap.find('.installer-channel-update-fail');
            // suppress default errors
            $(this).closest('.otgs_wp_installer_table').find('.installer-error-box').hide();

            var channelType = select.val() === PRODUCTION ? 'stable' : 'unstable';
            message.html(message.data('text-' + channelType).replace(/%CHANNEL%/, channelName));

        }else{

            var message = selectWrap.find('.installer-channel-update-ok');
            message.html(message.data('text').replace(/%CHANNEL%/, channelName));
        }

        message.show();

        channelUpdateInProgress = false;
    }

    function hideConfirmationMessage(select){

        var selectWrap = select.closest('.installer-channel-selector-wrap');
        if( hasUpdateErrors() ){
            var message = selectWrap.find('.installer-channel-update-fail');
        }else{
            var message = selectWrap.find('.installer-channel-update-ok');
        }

        message.hide();
    }

    /*
    function showFailureMessage(download_form){
        var message = download_form
            .closest('.otgs_wp_installer_table')
            .find('.installer-channel-selector-wrap')
            .find('.installer-channel-update-fail');

        var channelName = $(this)
            .closest('.otgs_wp_installer_table')
            .find('.installer-channel-selector option:selected')
            .text();

        message.html( message.data('text').replace(/%CHANNEL%/, channelName) );
        message.show();
    }
    */

    function logUpdateError(){
        updateErrors.push(1);
    }

    function resetUpdateErrors(){
        updateErrors = [];
    }

    function hasUpdateErrors() {
        return updateErrors.length;
    }

    $(document).ready( channelSelectorInit );

})(jQuery);
