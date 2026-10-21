/* jslint browser: true, nomen: true, laxbreak: true */
/* global WPML_core, ajaxurl, iclSaveForm, iclSaveForm_success_cb, jQuery, alert, confirm, icl_ajx_saved, icl_ajxloaderimg, icl_default_mark, icl_ajx_error, icl_ajx_domains_not_validated, icl_ajx_domains_duplicate, icl_ajx_domains_blocked, icl_ajx_domains_blocked_hint, fadeInAjxResp */

(function () {
  jQuery(function () {
    let icl_hide_languages

    jQuery('.toggle:checkbox').click(iclHandleToggle)
    jQuery('#icl_change_default_button').click(editingDefaultLanguage)
    jQuery('#icl_save_default_button').click(saveDefaultLanguage)
    jQuery('#icl_cancel_default_button').click(doneEditingDefaultLanguage)
    jQuery('#icl_add_remove_button').click(showLanguagePicker)
    jQuery('#icl_cancel_language_selection').click(hideLanguagePicker)
    jQuery('#icl_enabled_languages').find('input').prop('disabled', true)
    jQuery('#icl_save_language_negotiation_type').submit(iclSaveLanguageNegotiationType)
    // A refused save is announced where it is written (wpmldev-6140).
    jQuery('#icl_save_language_negotiation_type .wpml-form-errors').attr('role', 'alert')
    jQuery('#icl_admin_language_options').submit(iclSaveForm)
    jQuery('#icl_lang_more_options').submit(iclSaveForm)
    jQuery('#icl_blog_posts').submit(iclSaveForm)
    icl_hide_languages = jQuery('#icl_hide_languages')
    icl_hide_languages.submit(iclHideLanguagesCallback)
    icl_hide_languages.submit(iclSaveForm)
    jQuery('#icl_adjust_ids').submit(iclSaveForm)
    jQuery('#icl_automatic_redirect').submit(iclSaveForm)
    jQuery('#icl_automatic_redirect input[name="icl_automatic_redirect"]').on('click', function () {
      const $redirect_warn = jQuery(this).parents('#icl_automatic_redirect').find('.js-redirect-warning')
      if (jQuery(this).val() != 0) {
        $redirect_warn.fadeIn()
      } else {
        $redirect_warn.fadeOut()
      }
    })
    jQuery('input[name="icl_language_negotiation_type"]').change(iclLntDomains)
    jQuery('#icl_use_directory').change(iclUseDirectoryToggle)

    jQuery('input[name="show_on_root"]').change(iclToggleShowOnRoot)
    jQuery('#wpml_show_page_on_root_details').find('a').click(function () {
      if (!jQuery('#wpml_show_on_root_page').is(':checked')) {
        alert(jQuery('#wpml_show_page_on_root_x').html())
        return false
      }
    })

    jQuery('#icl_seo_options').submit(iclSaveForm)
    jQuery('#icl_seo_head_langs').on('click', update_seo_head_langs_priority)

    jQuery('#icl_avail_languages_picker').find('li input:checkbox').click(function () {
      const checkedBoxes = jQuery('#icl_avail_languages_picker').find('li input:checkbox:checked').length
      jQuery('#icl_setup_next_1').prop('disabled', checkedBoxes <= 1)
    })

    jQuery('#icl_promote_form').submit(iclSaveForm)

    // wpmldev-7978: the `toggle_content_translation` wizard emitter was removed —
    // no server-side handler for it has existed in the product for a long time
    // (dead legacy `icl_ajx_action` branch; no UI renders
    // #icl_enable_content_translation any more).

    jQuery(document).on('click', '#installer_registration_form :submit', function () {
      jQuery('#installer_registration_form').find('input[name=button_action]').val(jQuery(this).attr('name'))
    })

    jQuery(document).on('click', '#installer_recommendations_form :submit', function () {
      jQuery('#installer_recommendations_form').find('input[name=button_action]').val(jQuery(this).attr('name'))
    })

    // Root-url and URL-format submit-button help tooltips share the common
    // pointer-tooltip behavior (res/js/tooltip/tooltip.js).
    WPMLCore.createHoverableTooltip({
      trigger:      '.js-wpml-root-url-tooltip-open',
      popover:      '.js-wpml-root-url-tooltip',
      activeClass:  'js-wpml-root-url-active-tooltip',
      pointerClass: 'js-wpml-root-url-tooltip wpml-ls-tooltip'
    })
    WPMLCore.createHoverableTooltip({
      trigger:      '.js-wpml-url-format-submit-button-tooltip-open',
      popover:      '.js-wpml-url-format-submit-button-tooltip',
      activeClass:  'js-wpml-url-format-submit-button-active-tooltip',
      pointerClass: 'js-wpml-url-format-submit-button-tooltip wpml-ls-tooltip',
      marginLeft:   '-25px'
    })

    checkLanguageDirectorySettings()
    jQuery('#icl_use_directory_wrap').click(checkLanguageDirectorySettings)
  })

  function iclHandleToggle() {
    /* jshint validthis: true */
    const self = this
    const toggleElement = jQuery(self)
    const toggle_value_name = toggleElement.data('toggle_value_name')
    const toggle_value_checked = toggleElement.data('toggle_checked_value')
    const toggle_value_unchecked = toggleElement.data('toggle_unchecked_value')
    let toggle_value = jQuery('[name="' + toggle_value_name + '"]')
    if (toggle_value.length === 0) {
      toggle_value = jQuery('<input type="hidden" name="' + toggle_value_name + '">')
      toggle_value.insertAfter(self)
    }
    if (toggleElement.is(':checked')) {
      toggle_value.val(toggle_value_checked)
    } else {
      toggle_value.val(toggle_value_unchecked)
    }
  }

  function editingDefaultLanguage() {
    jQuery('#icl_change_default_button').hide()
    jQuery('#icl_save_default_button').show()
    jQuery('#icl_cancel_default_button').show()
    const enabled_languages = jQuery('#icl_enabled_languages').find('input')
    enabled_languages.show()
    enabled_languages.prop('disabled', false)
    jQuery('#icl_add_remove_button').hide()
  }

  function doneEditingDefaultLanguage() {
    jQuery('#icl_change_default_button').show()
    jQuery('#icl_save_default_button').hide()
    jQuery('#icl_cancel_default_button').hide()
    const enabled_languages = jQuery('#icl_enabled_languages').find('input')
    enabled_languages.hide()
    enabled_languages.prop('disabled', true)
    jQuery('#icl_add_remove_button').show()
  }

  function saveDefaultLanguage() {
    let enabled_languages, arr, def_lang
    enabled_languages = jQuery('#icl_enabled_languages')
    arr = enabled_languages.find('input[type="radio"]')
    def_lang = ''
    jQuery.each(arr, function () {
      if (this.checked) {
        def_lang = this.value
      }
    })
    jQuery.ajax({
      type: 'POST',
      url: ajaxurl,
      data: {
        action: 'wpml_set_default_language',
        nonce: jQuery('#set_default_language_nonce').val(),
        language: def_lang
      },
      success: function (response) {
        if (response.success) {
          let enabled_languages_items, spl, selected_language, avail_languages_picker, selected_language_item
          selected_language = enabled_languages.find('li input[value="' + def_lang + '"]')

          fadeInAjxResp(icl_ajx_saved)
          avail_languages_picker = jQuery('#icl_avail_languages_picker')
          avail_languages_picker.find('input[value="' + response.data.previousLanguage + '"]').prop('disabled', false)
          avail_languages_picker.find('input[value="' + def_lang + '"]').prop('disabled', true)
          enabled_languages_items = jQuery('#icl_enabled_languages').find('li')
          enabled_languages_items.removeClass('selected')
          selected_language_item = selected_language.closest('li')
          selected_language_item.addClass('selected')
          selected_language_item.find('label').append(' (' + icl_default_mark + ')')
          enabled_languages_items.find('input').prop('checked', false)
          selected_language.prop('checked', true)
          enabled_languages.find('input[value="' + response.data.previousLanguage + '"]').parent().html(enabled_languages.find('input[value="' + response.data.previousLanguage + '"]').parent().html().replace('(' + icl_default_mark + ')', ''))
          doneEditingDefaultLanguage()
          fadeInAjxResp('#icl_ajx_response', icl_ajx_saved)
          location.href = WPML_core.sanitize(location.href).replace(/#[\w\W]*/, '') + '&setup=2'
        } else {
          fadeInAjxResp('#icl_ajx_response', icl_ajx_error)
        }
      }
    })
  }

  function showLanguagePicker() {
    jQuery('#icl_avail_languages_picker').slideDown()
    jQuery('#icl_add_remove_button').hide()
    jQuery('#icl_change_default_button').hide()
  }

  function hideLanguagePicker() {
    const availableLanguagesPicker = jQuery('#icl_avail_languages_picker')
    availableLanguagesPicker.slideUp()
    // Revert all check/uncheck languages on clicking Cancel button.
    const arr = availableLanguagesPicker.find('ul input[type="checkbox"]')
    jQuery.each(arr, function () {
      const element = jQuery(this)
      const wasActiveBeforeCancel = element.closest('li').hasClass('wpml-selected')
      element.prop('checked', wasActiveBeforeCancel)
    })
    jQuery('#icl_add_remove_button').fadeIn()
    jQuery('#icl_change_default_button').fadeIn()
  }

  function iclLntDomains() {
    let language_negotiation_type, icl_lnt_domains_box, icl_lnt_domains_options, icl_lnt_xdomain_options
    icl_lnt_domains_box = jQuery('#icl_lnt_domains_box')
    icl_lnt_domains_options = jQuery('#icl_lnt_domains')
    icl_lnt_xdomain_options = jQuery('#language_domain_xdomain_options')

    if (icl_lnt_domains_options.prop('checked')) {
      icl_lnt_domains_box.html(icl_ajxloaderimg)
      icl_lnt_domains_box.show()
      language_negotiation_type = jQuery('#icl_save_language_negotiation_type').find('input[type="submit"]')
      language_negotiation_type.prop('disabled', true)
      jQuery.ajax({
        type: 'POST',
        url: ajaxurl,
        data: 'action=wpml_ajx_language_domains' + '&_icl_nonce=' + jQuery('#_icl_nonce_ldom').val(),
        success: function (resp) {
          icl_lnt_domains_box.html(resp)
          language_negotiation_type.prop('disabled', false)
          icl_lnt_xdomain_options.show()
        }
      })
    } else if (icl_lnt_domains_box.length) {
      icl_lnt_domains_box.fadeOut('fast')
      icl_lnt_xdomain_options.fadeOut('fast')
    }
    /* jshint validthis: true */
    const typeSelected = jQuery(this).val()
    if (typeSelected !== '1') {
      jQuery('#icl_use_directory_wrap').hide()
    } else {
      jQuery('#icl_use_directory_wrap').fadeIn()
    }
    // wpmldev-11115: the Save gate moves with the format - leaving the
    // directory format releases a Save the now-hidden box had blocked. The
    // domain branch is skipped: its submit belongs to the domains-box ajax
    // above (re-enabled when the box answers), and its gate is off anyway.
    if (typeSelected !== '2') {
      checkLanguageDirectorySettings()
    }
  }

  function iclToggleShowOnRoot() {
    /* jshint validthis: true */
    if (jQuery(this).val() === 'page') {
      jQuery('#wpml_show_page_on_root_details').fadeIn()
      jQuery('#icl_hide_language_switchers').fadeIn()
    } else {
      jQuery('#wpml_show_page_on_root_details').fadeOut()
      jQuery('#icl_hide_language_switchers').fadeOut()
    }
  }

  function iclUseDirectoryToggle() {
    if (jQuery(this).prop('checked')) {
      jQuery('#icl_use_directory_details').fadeIn()
    } else {
      jQuery('#icl_use_directory_details').fadeOut()
    }
  }

  function iclSaveLanguageNegotiationType() {
    let validSettings = true
    let ajaxResponse
    let usedUrls
    let formErrors
    let duplicateDomain
    let formName

    let languageNegotiationType
    let rootHtmlFile
    let showOnRoot
    let useDirectories
    let domainsToValidateCount
    let domainsToValidate

    const form = jQuery('#icl_save_language_negotiation_type')

    const useDirectoryWrapper = jQuery('#icl_use_directory_wrap')
    languageNegotiationType = parseInt(form.find('input[name=icl_language_negotiation_type]:checked').val())
    useDirectoryWrapper.find('.icl_error_text').hide()

    formName = form.attr('name')
    formErrors = false
    // wpmldev-9423: domains are compared by host - lower case, in the xn-- form
    // of an international name, without the port - so `Bücher.example` and
    // `xn--bcher-kva.example` are one domain.
    usedUrls = [comparableHost(jQuery('#icl_ln_home').text())]
    jQuery('form[name="' + formName + '"] .icl_form_errors').html('').hide()
    ajaxResponse = jQuery('form[name="' + formName + '"] .icl_ajx_response').attr('id')
    fadeInAjxResp('#' + ajaxResponse, icl_ajxloaderimg)

    if (languageNegotiationType === 1) {
      useDirectories = form.find('[name=use_directory]').is(':checked')
      showOnRoot = form.find('[name=show_on_root]:checked').val()
      rootHtmlFile = form.find('[name=root_html_file_path]').val()

      if (useDirectories) {
        if (showOnRoot === 'html_file' && !rootHtmlFile) {
          validSettings = false
          useDirectoryWrapper.find('.icl_error_text.icl_error_1').fadeIn()
        } else if (showOnRoot === 'page') {
          // The screen renders this error only when the save would be rejected:
          // the configured root page is missing, trashed or not published. The
          // server makes that decision and chooses the wording, so the client only
          // shows it. Nothing to sniff from the link's href or its translated label.
          // "No option chosen" needs no branch here: checkLanguageDirectorySettings()
          // disables the Save button in that state, and the server still rejects
          // callers that are not this screen.
          const rootPageError = jQuery('#wpml_show_page_on_root_missing')
          if (rootPageError.length) {
            validSettings = false
            rootPageError.fadeIn()
          }
        }
      }

      if (validSettings === true) {
        saveLanguageForm()
      }
    }

    if (languageNegotiationType === 3) {
      saveLanguageForm()
    }

    if (languageNegotiationType === 2) {
      domainsToValidate = []

      // Every visible row drops the verdict the previous save wrote into it
      // before the queue is built. A row whose "Validate on save" box is
      // unticked never enters the queue, so without this it kept a stale
      // "Not valid" (or "Valid") from an earlier submit while the form saved
      // (wpmldev-8439, ruling: an unvalidated row shows no verdict).
      jQuery('.validate_language_domain').filter(':visible').each(function (index, element) {
        jQuery('#ajx_ld_' + jQuery(element).attr('value')).html('').removeClass('icl_error_text').removeClass('icl_valid_text')
      })

      jQuery('.validate_language_domain').filter(':visible').each(function (index, element) {
        const domainValidationCheckbox = jQuery(element)
        const lang = domainValidationCheckbox.attr('value')
        const langDomainInput = jQuery('#language_domain_' + lang)

        // Sanitises the field, and un-checks the box when it sanitises to empty,
        // so the checked state is only meaningful once this has run.
        new WpmlDomainValidation(langDomainInput, domainValidationCheckbox).run()

        if (!domainValidationCheckbox.prop('checked')) {
          return
        }

        const parentHtml = langDomainInput.parent().html()
        const subdirMatches = parentHtml.match(/<code>\/(.+)<\/code>/)
        const languageDomainURL = parentHtml.match(/<code>(.+)<\/code>/)[1] + langDomainInput.val() + '/' + (subdirMatches !== null ? subdirMatches[1] : '')

        const languageDomainHost = comparableHost(languageDomainURL)

        if (usedUrls.indexOf(languageDomainHost) !== -1) {
          jQuery('.spinner.spinner-' + lang).empty()
          formErrors = true
          duplicateDomain = langDomainInput.val()
          return
        }

        usedUrls.push(languageDomainHost)
        langDomainInput.css('color', '#000')
        domainsToValidate.push({ lang: lang, url: languageDomainURL })
      })

      domainsToValidateCount = domainsToValidate.length

      if (formErrors) {
        // Two languages point at the same domain; saving that would break the site.
        refuseLanguageDomainsSave(icl_ajx_domains_duplicate.replace('%s', duplicateDomain))
        return false
      }

      if (domainsToValidateCount === 0) {
        saveLanguageForm()
      } else {
        validateLanguageDomainsInSequence(domainsToValidate, 0, 0)
      }
    }

    return false
  }

  /**
   * Validates one language domain per request, in order, updating each domain's
   * own row as soon as its result arrives. Saves the form once every queued
   * domain has reported back, and only if all of them were valid.
   *
   * These requests must NOT run in parallel (wpmldev-4442). Each one makes the
   * server issue a blocking loopback request back to this same site, so it holds
   * two PHP workers for its duration: one for the admin-ajax call and one for the
   * loopback it waits on. Firing every domain at once therefore needed one worker
   * per domain plus a spare; on a site whose worker pool was no larger than the
   * number of domains, nothing was left to answer the loopbacks and every domain
   * failed on the server's 15s timeout — while validating the same domains one at
   * a time succeeded. Going in sequence needs two workers no matter how many
   * domains there are.
   *
   * @param queue      Array of {lang, url} to validate, in order.
   * @param index      Position in the queue to validate now.
   * @param validCount How many entries before this one came back valid.
   */
  function validateLanguageDomainsInSequence(queue, index, validCount) {
    if (index >= queue.length) {
      if (validCount === queue.length) {
        saveLanguageForm()
      } else if (everyFailureIsBlocked(queue)) {
        refuseBlockedLanguageDomainsSave(queue)
      } else {
        refuseLanguageDomainsSave(icl_ajx_domains_not_validated.replace('%s', domainsThatFailed(queue)))
      }
      return
    }

    const lang = queue[index].lang
    const spinner = jQuery('.spinner.spinner-' + lang)
    const placeholder = jQuery('#ajx_ld_' + lang)

    spinner.addClass('is-active')
    placeholder.html('').removeClass('icl_error_text').removeClass('icl_valid_text')

    // Keep going after a failure rather than stopping at the first bad domain, so
    // one save reports a verdict for every row instead of surfacing the problems
    // one reload at a time.
    const next = function (wasValid) {
      spinner.removeClass('is-active')
      validateLanguageDomainsInSequence(queue, index + 1, validCount + (wasValid ? 1 : 0))
    }

    jQuery.ajax({
      method: 'POST',
      url: ajaxurl,
      data: {
        url: queue[index].url,
        action: 'validate_language_domain',
        nonce: jQuery('#validate_language_domain_nonce').val()
      },
      success: function (resp) {
        // wpmldev-6140: a failure answers {status, message}, so a row can say
        // that a firewall or a CDN blocked the check instead of "Not valid".
        const failure = resp.data && typeof resp.data === 'object' ? resp.data : {}
        queue[index].status = resp.success ? 'valid' : (failure.status || 'unreachable')
        placeholder.text(resp.success ? resp.data : (failure.message || ''))
        placeholder.addClass(resp.success ? 'icl_valid_text' : 'icl_error_text')
        next(resp.success)
      },
      error: function (jqXHR, textStatus) {
        queue[index].status = 'unreachable'
        placeholder.html('')
        if (jqXHR === '0') {
          fadeInAjxResp('#' + textStatus, icl_ajx_error, true)
        }
        next(false)
      }
    })
  }

  /**
   * The rows of the queue that did not come back valid, as "language: domain"
   * for the refusal notice. A row that failed carries icl_error_text ("Not
   * valid"); a row whose request died carries nothing at all — either way it
   * is not icl_valid_text, and either way the form was not saved because of it.
   */
  function domainsThatFailed(queue) {
    const failed = []
    queue.forEach(function (entry) {
      if (jQuery('#ajx_ld_' + entry.lang).hasClass('icl_valid_text')) {
        return
      }
      const name = jQuery('label[for="language_domain_' + entry.lang + '"]').text().trim() || entry.lang
      failed.push(name + ': ' + jQuery('#language_domain_' + entry.lang).val())
    })
    return failed.join(', ')
  }

  /**
   * Whether every domain of the queue that did not validate is one whose check
   * a firewall or a CDN blocked while the domain itself loads (wpmldev-6140).
   * Only then does the notice point to "Validate on save": a domain that shows
   * another site, or that does not load, is never offered a way around the check.
   */
  function everyFailureIsBlocked(queue) {
    const failed = queue.filter(function (entry) {
      return entry.status !== 'valid'
    })
    return failed.length > 0 && failed.every(function (entry) {
      return entry.status === 'blocked'
    })
  }

  /**
   * Refuses the save because a firewall or a CDN blocked the check of every
   * failed domain, and says how to save them: untick "Validate on save" for
   * those domains and save again (wpmldev-6140).
   */
  function refuseBlockedLanguageDomainsSave(queue) {
    const domains = queue.filter(function (entry) {
      return entry.status === 'blocked'
    }).map(function (entry) {
      return jQuery('#language_domain_' + entry.lang).val()
    })
    refuseLanguageDomainsSave(icl_ajx_domains_blocked.replace('%s', domains.join(', ')))

    jQuery('#icl_save_language_negotiation_type .wpml-form-errors').append(
      ' ',
      jQuery('<span class="wpml-domains-blocked-hint"></span>').text(icl_ajx_domains_blocked_hint)
    )
  }

  /**
   * Refuses the save out loud (wpmldev-8550). Until now a refused save ended
   * with a bare return: the loader vanished, nothing said the form was not
   * saved, and the only trace was a "Not valid" chip beside a row that could
   * be off-screen. Nothing reaches the server on this path — the settings are
   * untouched — so the notice has to come from here: the loader becomes the
   * error banner, the form's error box carries the reason, and the first
   * failed row (or the box itself) is scrolled into view.
   *
   * @param message Already localized, already carrying the domains.
   */
  function refuseLanguageDomainsSave(message) {
    const form = jQuery('#icl_save_language_negotiation_type')
    const errorBox = form.find('.wpml-form-errors')
    errorBox.text(message)
    errorBox.show()
    fadeInAjxResp('#' + form.find('.icl_ajx_response').attr('id'), icl_ajx_error, true)
    const target = form.find('.icl_error_text').filter(':visible').first()
    const scrollTo = target.length ? target[0] : errorBox[0]
    if (scrollTo && scrollTo.scrollIntoView) {
      scrollTo.scrollIntoView({ block: 'center' })
    }
  }

  /**
   * The host a language URL is compared by: lower case, in its xn-- form,
   * without the port. A value the browser cannot parse is compared as it is,
   * in lower case (wpmldev-9423).
   */
  function comparableHost(url) {
    const value = String(url || '').trim()
    try {
      return new URL(/^[a-z][a-z0-9+.-]*:\/\//i.test(value) ? value : 'http://' + value).hostname.toLowerCase()
    } catch (e) {
      return value.toLowerCase()
    }
  }

  function saveLanguageForm() {
    let domains
    let xdomain = 0
    let useDirectory = false
    let hideSwitcher = false
    let data
    const form = jQuery('#icl_save_language_negotiation_type')
    const formName = jQuery(form).attr('name')
    const ajxResponse = jQuery(form).find('.icl_ajx_response').attr('id')

    if (form.find('input[name=use_directory]').is(':checked')) {
      useDirectory = 1
    }
    if (form.find('input[name=hide_language_switchers]').is(':checked')) {
      hideSwitcher = 1
    }
    if (form.find('input[name=icl_xdomain_data]:checked').val()) {
      xdomain = parseInt(form.find('input[name=icl_xdomain_data]:checked').val())
    }
    domains = {}
    form.find('input[name^=language_domains]').each(function () {
      const item = jQuery(this)
      domains[item.data('language')] = item.val()
    })

    data = {
      action: 'save_language_negotiation_type',
      nonce: jQuery('#save_language_negotiation_type_nonce').val(),
      icl_language_negotiation_type: form.find('input[name=icl_language_negotiation_type]:checked').val(),
      language_domains: domains,
      use_directory: useDirectory,
      show_on_root: form.find('input[name=show_on_root]:checked').val(),
      root_html_file_path: form.find('input[name=root_html_file_path]').val(),
      hide_language_switchers: hideSwitcher,
      xdomain: xdomain
    }

    jQuery.ajax({

      method: 'POST',
      url: ajaxurl,
      data: data,
      success: function (response) {
        let formErrors, rootHtmlFile, rootPage, spl
        if (response.success) {
          fadeInAjxResp('#' + ajxResponse, icl_ajx_saved)

          // LANG-17d: a save that turned the directory off or chose a root target left
          // the forgotten state, so the Save gate asks for a root choice again.
          if (useDirectory !== 1 || data.show_on_root) {
            form.find('[name=use_directory]').data('root-forgotten', 0)
          }

          if (response.data) {
            const formMessage = jQuery('form[name="' + formName + '"]').find('.wpml-form-message')
            formMessage.addClass('updated')
            formMessage.html(response.data)
            formMessage.fadeIn()
          }

          if (jQuery('input[name=use_directory]').is(':checked') && jQuery('input[name=show_on_root]').length) {
            rootHtmlFile = jQuery('#wpml_show_on_root_html_file')
            rootPage = jQuery('#wpml_show_on_root_page')
            if (rootHtmlFile.prop('checked')) {
              rootHtmlFile.addClass('active')
              rootPage.removeClass('active')
            }

            if (rootPage.prop('checked')) {
              rootPage.addClass('active')
              rootHtmlFile.removeClass('active')
            }

          }
        } else {
          formErrors = jQuery('form[name="' + formName + '"] .icl_form_errors')
          if (formErrors.length === 0) {
            formErrors = jQuery('form[name="' + formName + '"] .wpml-form-errors')
          }
          const errors = response.data.join('<br>')
          formErrors.html(errors)
          formErrors.fadeIn()
          fadeInAjxResp('#' + ajxResponse, icl_ajx_error, true)
        }
      }
    })
  }

  function iclHideLanguagesCallback() {
    iclSaveForm_success_cb.push(function (frm, res) {
      jQuery('#icl_hidden_languages_status').html(res[1])

      // Delay 2 seconds before reloading the page to allow users to read the message after hiding the language.
      setTimeout(function () {
        window.location.reload()
      }, 2000)
    })
  }

  function update_seo_head_langs_priority(event) {
    const element = jQuery(this)
    jQuery('#wpml-seo-head-langs-priority').prop('disabled', !element.prop('checked'))
  }

  function checkLanguageDirectorySettings() {
    let isShowOnRootChecked
    let useDirectories

    const form       = jQuery('#icl_save_language_negotiation_type')
    let submitButton = form.find('input[name=save]')

    useDirectories      = form.find('[name=use_directory]').is(':checked')
    isShowOnRootChecked = form.find('[name=show_on_root]').is(':checked')
    // LANG-17d: the stored root page was deleted permanently and forgotten, so the
    // section saves without a root choice. The server accepts it only in that state.
    const rootForgotten = String(form.find('[name=use_directory]').data('root-forgotten')) === '1'

    // wpmldev-11115: the checkbox lives in the directory format only. In the
    // other formats it is hidden (the wrapper is display:none) yet still
    // checked from a stored directory_for_default_language, and a field the
    // form does not show must not gate Save.
    const directoryFormatChosen = form.find('input[name=icl_language_negotiation_type]:checked').val() === '1'

    if (directoryFormatChosen && useDirectories && !isShowOnRootChecked && !rootForgotten) {
      submitButton.prop('disabled', true)
      submitButton.addClass('js-wpml-url-format-submit-button-tooltip-open')
    } else {
      form.find('input[name=save]').prop('disabled', false)
      submitButton.removeClass('js-wpml-url-format-submit-button-tooltip-open')
      jQuery('.js-wpml-url-format-submit-button-tooltip').remove()
    }
  }

}())
