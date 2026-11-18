<?php

namespace WPML\Compatibility\Divi\ThemeBuilderRepair;

use WPML\UserInterface\Web\Core\SharedKernel\Config\PageRenderInterface;

class Page implements PageRenderInterface {

	const TOOL_SLUG = 'divi-theme-builder-repair';

	public function render() {
		$url   = admin_url( 'admin-ajax.php' );
		$nonce = wp_create_nonce( \WPML\Compatibility\Divi\Hooks\ThemeBuilderRepairTool::AJAX_ACTION );
		?>
		<h1 class="wpml:text-2xl wpml:font-semibold wpml:text-gray-900 wpml:mb-1">
			<?php echo esc_html( self::title() ); ?>
		</h1>
		<p class="wpml:text-sm wpml:text-gray-500 wpml:mb-2">
			<?php echo esc_html( self::intro() ); ?>
		</p>
		<p class="wpml:text-sm wpml:text-gray-500 wpml:mb-2">
			<?php echo esc_html( self::introLayouts() ); ?>
		</p>
		<p class="wpml:text-sm wpml:text-gray-700 wpml:font-semibold wpml:mb-6">
			<?php echo esc_html( __( 'Back up your site first.', 'sitepress' ) ); ?>
		</p>
		<section id="divi-theme-builder-repair" class="wpml:bg-white wpml:border wpml:border-gray-200 wpml:rounded-md wpml:p-5 wpml:mb-5">
			<div class="wpml:flex wpml:gap-3">
				<button type="button" id="wpml-divi-tb-repair-check"
					data-url="<?php echo esc_attr( $url ); ?>"
					data-nonce="<?php echo esc_attr( $nonce ); ?>"
					class="wpml-button base-btn wpml-button--outlined wpml:text-sm wpml:disabled:opacity-50 wpml:disabled:cursor-not-allowed">
					<?php
					/* translators: Button on WPML → Support that lists what the Divi Theme Builder repair would change, without changing anything. */
					echo esc_html( __( 'Check', 'sitepress' ) );
					?>
				</button>
				<button type="button" id="wpml-divi-tb-repair-run" disabled
					class="wpml-button base-btn wpml-button--outlined wpml:text-sm wpml:disabled:opacity-50 wpml:disabled:cursor-not-allowed">
					<?php
					/* translators: Button on WPML → Support that repairs what the Check listed. */
					echo esc_html( __( 'Repair', 'sitepress' ) );
					?>
				</button>
			</div>
			<p id="wpml-divi-tb-repair-status" class="wpml:text-sm wpml:text-gray-700 wpml:mt-3 wpml:mb-0" aria-live="polite"></p>
			<div id="wpml-divi-tb-repair-result" class="wpml:text-sm wpml:text-gray-700 wpml:mt-3"></div>
		</section>
		<script>
		(function () {
			var check = document.getElementById('wpml-divi-tb-repair-check');
			var repair = document.getElementById('wpml-divi-tb-repair-run');
			if (!check || !repair) { return; }
			var status = document.getElementById('wpml-divi-tb-repair-status');
			var out = document.getElementById('wpml-divi-tb-repair-result');
			var i18n = <?php echo wp_json_encode( self::strings() ); ?>;
			function fmt(s) { var a = [].slice.call(arguments, 1); var n = 0; return s.replace(/%(\d+\$)?[sd]/g, function (m, pos) { var i = pos ? parseInt(pos, 10) - 1 : n++; return String(a[i]); }); }
			function el(tag, text) { var e = document.createElement(tag); e.textContent = text; return e; }
			function lang(code) { return code ? code : i18n.noLanguage; }
			function post(p) { return fmt(i18n.post, p.title || i18n.noTitle, p.id, lang(p.language), p.date); }
			function template(t) { return fmt(i18n.template, t.title || i18n.noTitle, t.id); }
			function list(head, items, line) {
				if (!items.length) { return; }
				out.appendChild(el('h3', head));
				var ul = document.createElement('ul');
				items.forEach(function (i) { ul.appendChild(el('li', line(i))); });
				out.appendChild(ul);
			}
			function show(d) {
				out.textContent = '';
				if (d.dryRun && !d.todo) { out.appendChild(el('p', i18n.nothing)); return; }
				if (d.keep) { out.appendChild(el('h3', i18n.headKeep)); out.appendChild(el('p', post(d.keep))); }
				list(d.dryRun ? i18n.headUnpublish : i18n.headUnpublished, d.unpublish, function (p) { return post(p) + ' ' + fmt(i18n.templates, p.templates); });
				list(i18n.headKept, d.keptTemplates, template);
				list(i18n.headOther, d.otherTemplates, template);
				var links = d.links || [];
				function kind(k) { return i18n.kinds[k] || k; }
				list(d.dryRun ? i18n.headLink : i18n.headLinked, links.filter(function (l) { return 'link' === l.action; }), function (l) { return fmt(i18n.link, l.layout, kind(l.kind), l.language, l.original); });
				list(i18n.headSkipped, links.filter(function (l) { return 'link' !== l.action; }), function (l) { return fmt(i18n.skipped, l.layout, kind(l.kind)); });
				if (d.languageRows > 0) { out.appendChild(el('p', fmt(d.dryRun ? i18n.rows : i18n.rowsDeleted, d.languageRows))); }
				if (d.settings) { out.appendChild(el('p', d.dryRun ? i18n.settings : i18n.settingsDone)); }
				if (!d.dryRun) { out.appendChild(el('p', i18n.done)); }
			}
			function go(dryRun) {
				check.disabled = true; repair.disabled = true;
				status.textContent = dryRun ? i18n.checking : i18n.repairing;
				out.textContent = '';
				var body = new URLSearchParams();
				body.append('action', <?php echo wp_json_encode( \WPML\Compatibility\Divi\Hooks\ThemeBuilderRepairTool::AJAX_ACTION ); ?>);
				body.append('nonce', check.getAttribute('data-nonce'));
				body.append('dryRun', dryRun ? '1' : '0');
				fetch(check.getAttribute('data-url'), { method: 'POST', credentials: 'same-origin', body: body })
					.then(function (r) { if (!r.ok) { throw new Error('http ' + r.status); } return r.json(); })
					.then(function (json) {
						if (!json || !json.success || !json.data) { throw new Error('no answer'); }
						status.textContent = '';
						show(json.data);
						check.disabled = false;
						repair.disabled = !(dryRun && json.data.todo);
					})
					.catch(function () { status.textContent = i18n.error; check.disabled = false; });
			}
			check.addEventListener('click', function () { go(true); });
			repair.addEventListener('click', function () { go(false); });
		})();
		</script>
		<?php
	}

	public static function title() {
		/* translators: Name of a Support tool on WPML → Support and the heading of its screen. "Divi" and "Theme Builder" are Divi's names. Verb phrase, imperative. */
		return __( 'Repair Divi Theme Builder', 'sitepress' );
	}

	public static function intro() {
		/* translators: Intro of a Support tool on WPML → Support. "Theme Builder" is Divi's feature name. */
		return __( 'Divi uses one Theme Builder for all languages. If your site once made the Theme Builder translatable, it can have one per language, and templates you add in one language are missing in the others. This tool keeps the Theme Builder of your default language and unpublishes the others.', 'sitepress' );
	}

	public static function introLayouts() {
		/* translators: Intro of a Support tool on WPML → Support, after the first paragraph. "Theme Builder" is Divi's feature name; layouts are Divi's header, body and footer layouts. */
		return __( "Layouts made in another language's Theme Builder become translations of your default language's layouts, so each language keeps its own.", 'sitepress' );
	}

	public static function description() {
		/* translators: Description of a Support tool in the tool list on WPML → Support. "Theme Builder" is Divi's feature name. */
		return __( 'Keep one Divi Theme Builder for all languages, so templates apply in every language.', 'sitepress' );
	}

	public static function strings() {
		return [
			/* translators: Status on WPML → Support while the Check runs. */
			'checking'        => __( 'Checking…', 'sitepress' ),
			/* translators: Status on WPML → Support while the Repair runs. */
			'repairing'       => __( 'Repairing…', 'sitepress' ),
			/* translators: Status on WPML → Support when a request failed. */
			'error'           => __( 'The request failed. Reload the page and try again.', 'sitepress' ),
			/* translators: On WPML → Support, when the Check found nothing to repair. "Theme Builder" is Divi's feature name. */
			'nothing'         => __( 'Your site has one Theme Builder for all languages. There is nothing to repair.', 'sitepress' ),
			/* translators: Heading on WPML → Support above the Divi Theme Builder that stays published. */
			'headKeep'        => __( 'Theme Builder that stays', 'sitepress' ),
			/* translators: Heading on WPML → Support above the Divi Theme Builders the Repair will unpublish. */
			'headUnpublish'   => __( 'Theme Builders to unpublish', 'sitepress' ),
			/* translators: Heading on WPML → Support above the Divi Theme Builders the Repair unpublished. */
			'headUnpublished' => __( 'Theme Builders unpublished', 'sitepress' ),
			/* translators: Heading on WPML → Support above the Divi templates the Theme Builder that stays lists. */
			'headKept'        => __( 'Templates that stay', 'sitepress' ),
			/* translators: Heading on WPML → Support above the Divi templates only an unpublished Theme Builder lists. */
			'headOther'       => __( 'Templates only the unpublished Theme Builders list', 'sitepress' ),
			/* translators: One Divi Theme Builder on WPML → Support. %1$s: its title, %2$d: its ID, %3$s: its language code, %4$s: its date. */
			'post'            => __( '%1$s (ID %2$d, language %3$s, %4$s)', 'sitepress' ),
			/* translators: Added after a Divi Theme Builder on WPML → Support. %d: the number of templates it lists. */
			'templates'       => __( 'Templates: %d', 'sitepress' ),
			/* translators: One item in a list on the WPML admin, for example a Divi template on WPML → Support or a piece of content that could not be sent for translation. %1$s: its title or its kind (for example Block), %2$d: its ID. */
			'template'        => __( '%1$s (ID %2$d)', 'sitepress' ),
			/* translators: Shown in place of the title of a piece of content that has none. The brackets show that it is not a title of its own. */
			'noTitle'         => __( '(no title)', 'sitepress' ),
			/* translators: Shown in place of a value when there is nothing to show, for example the language of a post that has none. Written in lower case because it stands where a value would. */
			'noLanguage'      => __( 'none', 'sitepress' ),
			/* translators: On WPML → Support. %d: the number of language records WPML keeps for Divi Theme Builders. */
			'rows'            => __( 'Language records to delete: %d', 'sitepress' ),
			/* translators: On WPML → Support. %d: the number of language records deleted. */
			'rowsDeleted'     => __( 'Language records deleted: %d', 'sitepress' ),
			/* translators: On WPML → Support. "Divi Theme Builder" and "Divi Templates" are the rows on WPML → Settings → Post Types Translation. */
			'settings'        => __( 'Divi Theme Builder and Divi Templates will be set back to Not translatable.', 'sitepress' ),
			/* translators: On WPML → Support, after the Repair. */
			'settingsDone'    => __( 'Divi Theme Builder and Divi Templates are set back to Not translatable.', 'sitepress' ),
			/* translators: On WPML → Support, after the Repair. "Theme Builder" is Divi's feature name. */
			'done'            => __( 'Done. Each unpublished Theme Builder is kept as a draft.', 'sitepress' ),
			/* translators: Heading on WPML → Support above the Divi layouts the Repair will make translations of the default language's layouts. */
			'headLink'        => __( 'Layouts that become translations', 'sitepress' ),
			/* translators: Heading on WPML → Support above the Divi layouts the Repair made translations of the default language's layouts. */
			'headLinked'      => __( 'Layouts that are now translations', 'sitepress' ),
			/* translators: Heading on WPML → Support above the Divi layouts the Repair cannot make translations, for example because the default language's layout already has a translation in that language. */
			'headSkipped'     => __( 'Layouts left as they are', 'sitepress' ),
			/* translators: One Divi layout on WPML → Support. %1$d: its ID, %2$s: its kind (Header, Body or Footer), %3$s: a language code, %4$d: the ID of the default language's layout. */
			'link'            => __( 'Layout %1$d (%2$s): the %3$s translation of layout %4$d', 'sitepress' ),
			/* translators: One Divi layout on WPML → Support. %1$d: its ID, %2$s: its kind (Header, Body or Footer). */
			'skipped'         => __( 'Layout %1$d (%2$s)', 'sitepress' ),
			'kinds'           => [
				/* translators: The kind of a Divi Theme Builder layout, as Divi names it. */
				'header' => __( 'Header', 'sitepress' ),
				/* translators: Used in two places: the kind of a Divi Theme Builder layout, as Divi names it, and the label of the field holding the main text of a post, in the translation editor. Noun. */
				'body'   => __( 'Body', 'sitepress' ),
				/* translators: Used in two places: the kind of a Divi Theme Builder layout, as Divi names it, and an option of the language-switcher type dropdown in Elementor (the plain list layout WPML calls the footer switcher). */
				'footer' => __( 'Footer', 'sitepress' ),
			],
		];
	}
}
