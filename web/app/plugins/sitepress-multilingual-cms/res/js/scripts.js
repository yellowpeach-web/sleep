/*global jQuery, icl_ajx_saved, icl_ajx_error, icl_ajxloaderimg_src, wpml_core_strings */

var WPML_core = WPML_core || {};

WPML_core.htmlentities = function (s) {
  return jQuery("<div/>").text(s).html()
};

WPML_core.sanitize = function (s) {
  if (typeof s === 'string' || s instanceof String) {
    return s.replace(/<script[^>]*?>.*?<\/script>/gi, '')
      .replace(/<[\/\!]*?[^<>]*?>/gi, '')
      .replace(/<style[^>]*?>.*?<\/style>/gi, '')
      .replace(/<![\s\S]*?--[ \t\n\r]*>/gi, '')
      .replace(/&nbsp;/g, '');
  }

  return s;
};

jQuery(function ($) {
  // Predefine closeText of JQuery UI diaog
  jQuery.extend(jQuery.ui.dialog.prototype.options, {closeText: wpml_core_strings.dialogCloseText});


  var catAdder = jQuery('#category-adder');
  if (catAdder.html()) {
    catAdder.prepend('<p>' + icl_cat_adder_msg + '</p>');
  }
  jQuery('select[name="icl_translation_of"]').change(function () {
    jQuery('#icl_translate_options').fadeOut();
  });
  jQuery('#icl_dismiss_help').click(iclDismissHelp);
  jQuery(document).on('click', 'a.icl_toggle_show_translations', iclToggleShowTranslations);

  /* needed for tagcloud */
  oldajaxurl = false;

  jQuery(document).on('click', "#icl_make_translatable_submit", icl_make_translatable);

  jQuery('a.icl_user_notice_hide').click(icl_hide_user_notice);

  var slugTranslation = jQuery('#icl_slug_translation');
  if (slugTranslation.length) {
    iclSaveForm_success_cb.push(function (form, response) {
      if (form.attr('name') === 'icl_slug_translation') {
        if (response[1] === 1) {
          jQuery('.icl_slug_translation_choice').show();
        } else {
          jQuery('.icl_slug_translation_choice').hide();
        }
      } else if (form.attr('name') === 'icl_custom_posts_sync_options') {
        jQuery('.icl_st_slug_tr_warn').hide();
      }
    });

    slugTranslation.submit(iclSaveForm);
    jQuery('.icl_slug_translation_choice input[type=checkbox]').change(function () {
      var table_row = jQuery(this).closest('tr');
      var cpt_slugs = jQuery(table_row).find('.js-cpt-slugs');

      if (jQuery(this).prop('checked')) {
        cpt_slugs.show();
      } else {
        cpt_slugs.hide();
      }
    });
  }

  var showLangSwitcher = jQuery('#show_login_page_language_switcher_sub_section');
  var showLangSwitcherCheckbox = showLangSwitcher.find('[name="show_login_page_language_switcher"]');
  jQuery('#login_page_translation').click(function () {
    showLangSwitcher.toggle();
    var isVisible = (showLangSwitcher.is(':visible'));
    showLangSwitcherCheckbox.prop('checked', isVisible);
  });

  if (jQuery('#icl_login_page_translation').length) {
    jQuery('#icl_login_page_translation').submit(iclSaveForm);
  }

  jQuery('.icl_sync_custom_posts').change(function () {
    var val = WPML_core.sanitize(jQuery(this).val());
    var table_row = jQuery(this).closest('tr');
    var cpt_slugs = jQuery(table_row).find('.js-cpt-slugs');
    var icl_slug_translation = jQuery(table_row).find(':checkbox');
    if (val === 1) {
      icl_slug_translation.closest('.icl_slug_translation_choice').show();
      if (icl_slug_translation.prop('checked') && cpt_slugs) {
        cpt_slugs.show();
      }
    } else if (cpt_slugs) {
      icl_slug_translation.closest('.icl_slug_translation_choice').hide();
      cpt_slugs.hide();
    }

  });

  $(function () {
    $('.js-type-translation-row').each(function () {
      if ($(this).find('.js-disabled-externally').length === 3) {
        $(this).hide();
      }
    });
  });

  jQuery(document).on('focus', '.icl_error_input', function () {
    jQuery(this).removeClass('icl_error_input');
  });

  $('.js-toggle-colors-edit').on('click', function (e) {
    e.preventDefault();

    var $target = $($(this).attr('href'));
    var $caret = $(this).find('.js-arrow-toggle');

    if ($target.is(':visible')) {
      $target.slideUp();
      $caret.removeClass('dashicons-arrow-up').addClass('dashicons-arrow-down');
    } else {
      $target.slideDown();
      $caret.removeClass('dashicons-arrow-down').addClass('dashicons-arrow-up');
    }

    return false;
  });

  $('#js-post-availability').on('change', function (e) {

    var $target = $($(this).data('target'));

    if ($(this).prop('checked')) {
      $target.show();
    } else {
      $target.hide();
    }

  });

  $('.js-wpml-navigation-links a').on('click', function (e) { // prevent default scrolling for navigation links
    e.preventDefault();

    var $target = $($(this).attr('href'));

    if ($target.length !== 0) {
      var offset = 0;
      var wpAdminBar = jQuery('#wpadminbar');
      if (wpAdminBar.length !== 0) {
        offset = wpAdminBar.height();
      }

      $('html, body').animate({
        scrollTop: $target.offset().top - offset
      }, 300, function () {
        var $header = $target.find('.wpml-section-header h3');
        $header.addClass('active');
        setTimeout(function () {
          $header.removeClass('active');
        }, 700);
      });
    }

    return false;
  });

  var icl_untranslated_blog_posts = $("input[name=icl_untranslated_blog_posts]");
  var icl_untranslated_blog_posts_help = $('#icl_untranslated_blog_posts_help');

  var update_icl_untranslated_blog_posts = function () {
    //Get the value of currently selected radio option
    var value = WPML_core.sanitize(icl_untranslated_blog_posts.filter(':checked').val());

    if (value == 0) {
      icl_untranslated_blog_posts_help.fadeOut('slow');
    } else {
      icl_untranslated_blog_posts_help.fadeIn('slow');
    }
  };

  update_icl_untranslated_blog_posts();
  icl_untranslated_blog_posts.on('click', update_icl_untranslated_blog_posts);

  var last_form_id = localStorage.getItem('wpml-mlcs-last-form-id');
  if (last_form_id) {
    var last_form = jQuery("#" + last_form_id);
    if (last_form.length) {
      jQuery('html, body').animate({
        scrollTop: last_form.offset().top
      }, 1000);
    }
    localStorage.removeItem('wpml-mlcs-last-form-id');
  }

});

(function ($) {
  function click_on_lock() {
    var radio_name = $(this).data('radio-name'),
      radio = $('input[name="' + radio_name + '"]'),
      unlocked_name = $(this).data('unlocked-name');

    $(this)
      .prop('disabled', true)
      .find('i').removeClass('otgs-ico-lock').addClass('otgs-ico-unlock');

    radio.prop('disabled', false);
    $('input[name="' + unlocked_name + '"]').prop('value', '1');

    return false;
  }

  function sync_lock_on_custom_fields_and_terms(form_id) {
    var locks = $('#' + form_id).find('.js-wpml-sync-lock');
    locks.on('click', click_on_lock);
  }

  $(document).on('icl-bind-locks', function (e) {
    sync_lock_on_custom_fields_and_terms(e.detail);
  });

  $(document).ready(function () {
    $('#icl_custom_posts_sync_options .js-wpml-sync-lock, #icl_custom_tax_sync_options .js-wpml-sync-lock').on(
      'click',
      click_on_lock
    );
  });

})(jQuery);


/** @deprecated use {@link fadeInAndOut} */
function fadeInAjxResp(spot, msg, err){
  fadeInAndOut(spot,msg,err);
}

function fadeInAndOut(spot, msg,err){
  let col;
  if(err != undefined){
    col = jQuery(spot).css('color');
    jQuery(spot).css('color','red');
  }
  jQuery(spot).html(WPML_core.purify(msg));
  jQuery(spot).fadeIn();
  window.setTimeout(wpml_fadeOut, 3000, spot);
  if(err != undefined){
    jQuery(spot).css('color',col);
  }
}

function wpml_fadeIn(spot, content){
  jQuery(spot).html(WPML_core.purify(content));
  jQuery(spot).fadeIn();
}

function wpml_fadeOut(spot){
  jQuery(spot).fadeOut();
}

/** @deprecated use {@link wpml_fadeOut} */
function fadeOutAjxResp(spot){
  wpml_fadeOut(spot);
}


/**
 * Create custom event
 * A kind of simple "polyfill" to support IE11
 *
 * @param eventName
 * @param eventDetail
 */
function wpmlCustomEvent(eventName, eventDetail) {
  if (!!window.MSInputMethodContext && !!document.documentMode) {
    // Internet Explorer 11
    const event = document.createEvent('CustomEvent');
    event.initCustomEvent(
      eventName,
      false,
      false,
      false
    );
    document.dispatchEvent(event);
  } else {
    document.dispatchEvent(new CustomEvent(eventName, eventDetail));
  }
}

var icl_ajxloaderimg = '<img src="' + icl_ajxloaderimg_src + '" alt="loading" width="16" height="16" />';

var iclHaltSave = false; // use this for multiple 'submit events'
var iclSaveForm_success_cb = [];

function iclSaveForm() {

  if (iclHaltSave) {
    return false;
  }
  var form_name = jQuery(this).attr('name');
  jQuery('form[name="' + form_name + '"] .icl_form_errors').html('').hide();
  var ajx_resp = jQuery('form[name="' + form_name + '"] .icl_ajx_response').attr('id');
  fadeInAjxResp('#' + ajx_resp, icl_ajxloaderimg);
  var serialized_form_data = jQuery(this).serialize();
  jQuery.ajax({
    type: "POST",
    url: ajaxurl,
    data: "action=wpml_ajx_" + jQuery(this).attr('name') + "&" + serialized_form_data,
    success: function (msg) {
      var spl = msg.split('|');
      if (parseInt(spl[0]) == 1) {
        fadeInAjxResp('#' + ajx_resp, icl_ajx_saved);
        for (var i = 0; i < iclSaveForm_success_cb.length; i++) {
          iclSaveForm_success_cb[i](jQuery('form[name="' + form_name + '"]'), spl);
        }
        if (form_name == 'icl_slug_translation' ||
          form_name == 'wpml_ls_settings_form' ||
          form_name == 'icl_custom_posts_sync_options' ||
          form_name == 'icl_custom_tax_sync_options'
        ) {
          localStorage.setItem('wpml-mlcs-last-form-id', form_name);
          location.reload(true);
        }
        wpmlCustomEvent('icl-save-form-' + form_name);
      } else {
        var icl_form_errors = jQuery('form[name="' + form_name + '"] .icl_form_errors');
        var error_html = (typeof spl[1] != 'undefined') ? spl[1] : spl[0];
        icl_form_errors.html(WPML_core.purify(error_html));
        icl_form_errors.fadeIn();
        fadeInAjxResp('#' + ajx_resp, icl_ajx_error, true);
      }
    }
  });
  return false;
}

function iclDismissHelp() {
  var thisa = jQuery(this);
  jQuery.ajax({
    type: "POST",
    url: ajaxurl,
    data: "action=wpml_ajx_dismiss_help&_icl_nonce=" + WPML_core.sanitize(jQuery('#icl_dismiss_help_nonce').val()),
    success: function (msg) {
      thisa.closest('#message').fadeOut();
    }
  });
  return false;
}

function iclToggleShowTranslations() {
  jQuery('a.icl_toggle_show_translations').toggle();
  jQuery('#icl_translations_table').toggle();
  jQuery.ajax({
    type: "POST",
    url: ajaxurl,
    data: "action=wpml_ajx_toggle_show_translations&_icl_nonce=" + WPML_core.sanitize(jQuery('#_icl_nonce_tst').val())
  });
  return false;
}

function icl_copy_from_original(lang, trid) {
  jQuery('#icl_cfo').after(icl_ajxloaderimg).prop('disabled', true);

  //has visual = set to normal non-html editing mode
  var ed;
  var content_type = (typeof tinyMCE !== 'undefined' && (ed = tinyMCE.get('content')) && !ed.isHidden() && ed.hasVisual === true) ? 'rich' : 'html';
  var excerpt_type = (typeof tinyMCE !== 'undefined' && (ed = tinyMCE.get('excerpt')) && !ed.isHidden() && ed.hasVisual === true) ? 'rich' : 'html';

  // figure out all available editors and their types
  jQuery.ajax({
    type: "POST",
    dataType: 'json',
    url: ajaxurl,
    data: "action=wpml_ajx_copy_from_original&lang=" + lang + '&trid=' + trid + '&content_type=' + content_type + '&excerpt_type='
      + excerpt_type + '&target_post_id=' + WPML_core.sanitize(jQuery('#post_ID').val())
      + '&target_lang=' + WPML_core.sanitize(jQuery('#icl_post_language').val() || '')
      + '&_icl_nonce=' + WPML_core.sanitize(jQuery('#_icl_nonce_cfo_' + trid).val()),
    success: function (msg) {
      if (msg.error) {
        alert(msg.error);
      } else {
        try {
          if (msg.content) {
            if (typeof tinyMCE !== 'undefined' && (ed = tinyMCE.get('content')) && !ed.isHidden()) {
              ed.focus();
              if (tinymce.isIE) {
                ed.selection.moveToBookmark(tinymce.EditorManager.activeEditor.windowManager.bookmark);
              }
              ed.execCommand('mceInsertContent', false, msg.content);
            } else if (wpml_get_block_editor()) {
              wp.data.dispatch('core/editor').resetBlocks(wp.blocks.parse(msg.content));
            } else {
              wpActiveEditor = 'content';
              edInsertContent(edCanvas, msg.content);
            }

          }
          if (typeof msg.title !== "undefined") {
            if (wpml_get_block_editor()) {
              wp.data.dispatch('core/editor').editPost({title: msg.title});
            } else {
              jQuery('#title-prompt-text').hide();
              jQuery('#title').val(msg.title);
            }
          }
          //handling of custom fields
          //these have to be of array type with the indexes editor_type editor_name and value
          //possible types are editor or text
          //in case of text te prompt to be removed might have to be provided
          for (var element in msg.builtin_custom_fields) {
            if (msg.builtin_custom_fields.hasOwnProperty(element) && msg.builtin_custom_fields[element].editor_type === 'editor') {
              if (typeof tinyMCE !== 'undefined' && (ed = tinyMCE.get(msg.builtin_custom_fields[element].editor_name)) && !ed.isHidden()) {
                ed.focus();
                if (tinymce.isIE) {
                  ed.selection.moveToBookmark(tinymce.EditorManager.activeEditor.windowManager.bookmark);
                }
                ed.execCommand('mceInsertContent', false, msg.builtin_custom_fields[element].value);
              } else {
                wpActiveEditor = msg.builtin_custom_fields[element].editor_name;
                edInsertContent(edCanvas, msg.builtin_custom_fields[element].value);
              }
            } else {
              jQuery('#' + msg.builtin_custom_fields[element].editor_name).val(msg.builtin_custom_fields[element].value);
            }
          }

          if (msg.custom_fields_refused && typeof console !== 'undefined' && console.warn) {
            // Not an alert: the title and content DID copy. This says why the
            // custom fields did not, instead of leaving it indistinguishable
            // from "the original had none".
            console.warn('WPML: custom fields were not copied - ' + msg.custom_fields_refused);
          }
          if (typeof msg.saved_custom_fields !== "undefined") {
            wpml_show_copied_custom_fields(msg.saved_custom_fields);
          }
        } catch (err) {
        }
      }
      jQuery('#icl_cfo').next().fadeOut();
    }
  });

  return false;
}

function wpml_get_block_editor() {
  return window._wpLoadBlockEditor || window._wpLoadGutenbergEditor;
}

/**
 * Shows the custom fields the server just stored on this translation.
 *
 * The values are already saved by the time this runs, so this only mirrors them
 * into the Custom Fields metabox when that metabox is on the page. It used to be
 * the other way round -- the copy was performed BY driving the metabox form --
 * which meant nothing was copied at all whenever the panel was hidden or removed
 * (ACF removes it), and nothing was copied even when it was shown, because
 * #newmeta-submit lives outside the #newmeta table this looked in.
 */
function wpml_show_copied_custom_fields(saved_fields) {
  if (!saved_fields || !saved_fields.length) {
    return;
  }

  var list = jQuery('#postcustomstuff #the-list');
  if (!list.length) {
    return;
  }

  var rows = saved_fields.map(function (item) {
    return item.row;
  }).filter(Boolean).join('');

  if (!rows) {
    return;
  }

  // An empty metabox renders a hidden table holding a single placeholder row.
  list.find('tr').filter(function () {
    return jQuery(this).find('input, textarea').length === 0;
  }).remove();

  list.append(rows);
  jQuery('#postcustomstuff table#list-table').show();
}

/**
 * The Multilingual Content Setup box: show the outcome sentence of the
 * preference the client picks for a field (wpmldev-8627 S1). The sentences
 * are rendered with the radios; only the one for the checked value is shown.
 */
jQuery(document).on('change', '.icl_mcs_cfs', function () {
  var cell = jQuery(this).closest('td');
  var value = jQuery(this).val();
  cell.find('.icl_mcs_cf_outcome').each(function () {
    var outcome = jQuery(this);
    outcome.prop('hidden', outcome.attr('data-value') !== value);
  });
});

function icl_make_translatable() {
  var that = jQuery(this);
  jQuery(this).prop('disabled', true);
  jQuery('#icl_div_config').find('.icl_form_success').hide();
  var iclMakeTranslatable = jQuery('[name=icl_make_translatable]:checked');
  var translate_input = WPML_core.sanitize(iclMakeTranslatable.val()).split(',');
  var translate = parseInt(translate_input[1]);
  var custom_post = translate_input[0];
  var custom_taxs_on = [];
  var custom_taxs_off = [];
  jQuery(".icl_mcs_custom_taxs").each(function () {
    if (jQuery(this).prop('checked')) {
      custom_taxs_on.push(WPML_core.sanitize(jQuery(this).val()));
    } else {
      custom_taxs_off.push(WPML_core.sanitize(jQuery(this).val()));
    }

  });

  var cfnames = [];
  var cfvals = [];
  jQuery('.icl_mcs_cfs:checked').each(function () {
    if (!jQuery(this).prop('disabled')) {
      cfnames.push(jQuery(this).attr('name').replace(/^icl_mcs_cf_/, ''));
      cfvals.push(WPML_core.sanitize(jQuery(this).val()));
    }
  });

  jQuery.post(location.href,
    {
      'post_id': WPML_core.sanitize(jQuery('#post_ID').val()),
      'icl_action': 'icl_mcs_inline',
      'custom_post': custom_post,
      'translate': translate,
      'custom_taxs_on[]': custom_taxs_on,
      'custom_taxs_off[]': custom_taxs_off,
      'cfnames[]': cfnames,
      'cfvals[]': cfvals,
      '_icl_nonce': WPML_core.sanitize(jQuery('#_icl_nonce_imi').val())

    },
    function (data) {
      that.prop('disabled', false);
      if (translate) {
        var iclDiv = jQuery('#icl_div');
        if (iclDiv.length > 0) {
          iclDiv.remove();
        }
        var prependTo = jQuery('#side-sortables');
        prependTo = prependTo.html() ? prependTo : jQuery('#normal-sortables');
        prependTo.prepend(
          '<div id="icl_div" class="postbox">' + jQuery(data).find('#icl_div').html() + '</div>'
        );
        jQuery('#icl_mcs_details').html(jQuery(data).find('#icl_mcs_details').html());
      } else {
        jQuery('#icl_div').hide();
        jQuery('#icl_mcs_details').html('');
      }
      jQuery('#icl_div_config').find('.icl_form_success').fadeIn();

      WPMLMetaBox.refresh.refreshMetaBox();
    }
  );

  return false;
}

function icl_hide_user_notice() {
  var notice = jQuery(this).attr('href').replace(/^#/, '');
  var thisa = jQuery(this);

  jQuery.ajax({
    type: "POST",
    dataType: 'json',
    url: ajaxurl,
    data: "action=wpml_ajx_save_user_preferences&user_preferences[notices][" + notice + "]=1&_icl_nonce=" + WPML_core.sanitize(jQuery('#_icl_nonce_sup').val()),
    success: function (msg) {
      thisa.parent().parent().fadeOut();
    }
  });

  return false;
}

function icl_cf_translation_preferences_submit(cf, obj) {
  jQuery.ajax({
    type: 'POST',
    url: ajaxurl,
    data: 'action=wpml_ajx_wpml_cf_translation_preferences&translate_action=' +
      WPML_core.sanitize(obj.parent().children('input:[name="wpml_cf_translation_preferences[' + cf + ']"]:checked').val()) + '&' +
      WPML_core.sanitize(obj.parent().children('input:[name="wpml_cf_translation_preferences_data_' + cf + '"]').val()) +
      '&_icl_nonce=' + WPML_core.sanitize(jQuery('#_icl_nonce_cftpn').val()),
    cache: false,
    error: function (html) {
      jQuery('#wpml_cf_translation_preferences_ajax_response_' + cf).html('Error occured');
    },
    beforeSend: function (html) {
      jQuery('#wpml_cf_translation_preferences_ajax_response_' + cf).html(icl_ajxloaderimg);
    },
    success: function (html) {
      jQuery('#wpml_cf_translation_preferences_ajax_response_' + cf).html(WPML_core.purify(html));
    },
    dataType: 'html'
  });

}

// wpmldev-8430: the redirect that used to strip `lang` (and with it every
// other parameter - mode, filters, search) off upload.php is gone. The
// language cookie is written server-side on the same request, and a cookie
// cannot carry "All languages": only the URL parameter can, so it stays.
