<?php

namespace WPML\UserInterface\Web\Infrastructure\WordPress\Component\Support\Tool;

use WPML\UserInterface\Web\Core\SharedKernel\Config\PageRenderInterface;

class PageIdLinkRepairController implements PageRenderInterface {

  const TOOL_SLUG = 'page-id-link-repair';

  const ROUTE = 'wpml/v1/troubleshooting/page-id-link-repair/run';


  public function render() {
      $url   = esc_url_raw( rest_url( self::ROUTE ) );
      $nonce = wp_create_nonce( 'wp_rest' );
    ?>
      <h1 class="wpml:text-2xl wpml:font-semibold wpml:text-gray-900 wpml:mb-1">
        <?php echo esc_html( self::title() ); ?>
      </h1>
      <p class="wpml:text-sm wpml:text-gray-500 wpml:mb-2">
        <?php
          /* translators: Intro of a Support tool on WPML → Support. "page_id" is a technical word in a web address and stays as it is. */
          echo esc_html( __( 'Older WPML versions could rewrite internal links in translations to addresses with several page_id parts. This tool finds them and points each one to the address of the page it was meant to open.', 'wpml' ) );
        ?>
      </p>
      <p class="wpml:text-sm wpml:text-gray-700 wpml:font-semibold wpml:mb-6">
        <?php echo esc_html( __( 'Back up your site first.', 'wpml' ) ); ?>
      </p>
      <section id="page-id-link-repair" class="wpml:bg-white wpml:border wpml:border-gray-200 wpml:rounded-md wpml:p-5 wpml:mb-5">
        <div class="wpml:flex wpml:gap-3">
          <button type="button" id="wpml-page-id-link-repair-check"
            data-rest-url="<?php echo esc_attr( $url ); ?>"
            data-rest-nonce="<?php echo esc_attr( $nonce ); ?>"
            class="wpml-button base-btn wpml-button--outlined wpml:text-sm wpml:disabled:opacity-50 wpml:disabled:cursor-not-allowed">
            <?php
              /* translators: Button on WPML → Support that counts the broken links without changing anything. */
              echo esc_html( __( 'Check', 'wpml' ) );
            ?>
          </button>
          <button type="button" id="wpml-page-id-link-repair-run" disabled
            data-rest-url="<?php echo esc_attr( $url ); ?>"
            data-rest-nonce="<?php echo esc_attr( $nonce ); ?>"
            class="wpml-button base-btn wpml-button--outlined wpml:text-sm wpml:disabled:opacity-50 wpml:disabled:cursor-not-allowed">
            <?php
              /* translators: Button on WPML → Support that repairs the links the Check found. */
              echo esc_html( __( 'Repair', 'wpml' ) );
            ?>
          </button>
        </div>
        <p id="wpml-page-id-link-repair-status" class="wpml:text-sm wpml:text-gray-700 wpml:mt-3 wpml:mb-0" aria-live="polite"></p>
        <div id="wpml-page-id-link-repair-result" class="wpml:text-sm wpml:text-gray-700 wpml:mt-3"></div>
      </section>
      <script>
      (function () {
        var check = document.getElementById('wpml-page-id-link-repair-check');
        var repair = document.getElementById('wpml-page-id-link-repair-run');
        if (!check || !repair) { return; }
        var status = document.getElementById('wpml-page-id-link-repair-status');
        var out = document.getElementById('wpml-page-id-link-repair-result');
        var i18n = <?php echo wp_json_encode( self::strings() ); ?>;
        function fmt(s) { var a = [].slice.call(arguments, 1); var n = 0; return s.replace(/%(\d+\$)?[sd]/g, function (m, pos) { var i = pos ? parseInt(pos, 10) - 1 : n++; return String(a[i]); }); }
        function el(tag, text) { var e = document.createElement(tag); e.textContent = text; return e; }
        function show(t) {
          out.textContent = '';
          var links = t.counts.content + t.counts.excerpt + t.counts.block + t.counts.string;
          if (t.dryRun && links === 0 && !t.unreadable) { out.appendChild(el('p', i18n.noneFound)); }
          if (links > 0) {
            out.appendChild(el('h3', t.dryRun ? i18n.headFound : i18n.headRepaired));
            var ul = document.createElement('ul');
            [['content', i18n.content], ['excerpt', i18n.excerpt], ['block', i18n.block], ['string', i18n.string]].forEach(function (f) {
              if (t.counts[f[0]] > 0) { ul.appendChild(el('li', fmt(f[1], t.counts[f[0]]))); }
            });
            ul.appendChild(el('li', fmt(t.dryRun ? i18n.rowsToUpdate : i18n.rowsUpdated, t.rows)));
            out.appendChild(ul);
          }
          if (t.no_match > 0) { out.appendChild(el('p', fmt(i18n.noMatch, t.no_match))); }
          if (t.target_missing > 0) { out.appendChild(el('p', fmt(i18n.targetMissing, t.target_missing))); }
          if (t.foreign > 0) { out.appendChild(el('p', fmt(i18n.foreign, t.foreign))); }
          if (t.unreadable > 0) { out.appendChild(el('p', fmt(i18n.unreadable, t.unreadable))); }
          if (t.samples.length) {
            out.appendChild(el('h3', i18n.headSamples));
            var list = document.createElement('ul');
            t.samples.forEach(function (s) {
              list.appendChild(el('li', s.type === 'string' ? fmt(i18n.sampleString, s.name, s.language, s.links) : fmt(i18n.samplePost, s.title, s.language, s.links)));
            });
            out.appendChild(list);
          }
          if (!t.dryRun && links > 0) { out.appendChild(el('p', i18n.done)); }
        }
        function go(dryRun) {
          var url = check.getAttribute('data-rest-url'), nonce = check.getAttribute('data-rest-nonce');
          var t = { dryRun: dryRun, counts: { content: 0, excerpt: 0, block: 0, string: 0 }, rows: 0, no_match: 0, target_missing: 0, foreign: 0, unreadable: 0, samples: [] };
          check.disabled = true; repair.disabled = true;
          status.textContent = dryRun ? i18n.checking : i18n.repairing;
          out.textContent = '';
          function step(cursorPosts, cursorStrings) {
            fetch(url, {
              method: 'POST',
              credentials: 'same-origin',
              headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce },
              body: JSON.stringify({ dryRun: dryRun, cursorPosts: cursorPosts, cursorStrings: cursorStrings })
            }).then(function (r) {
              if (!r.ok) { throw new Error('http ' + r.status); }
              return r.json();
            }).then(function (json) {
              var d = json && json.data ? json.data : json;
              if (!d || !d.counts) { throw new Error('no answer'); }
              Object.keys(t.counts).forEach(function (k) { t.counts[k] += d.counts[k] || 0; });
              t.rows += d.rows; t.no_match += d.no_match; t.target_missing += d.target_missing; t.foreign += d.foreign || 0; t.unreadable += d.unreadable || 0;
              (d.samples || []).forEach(function (s) { if (t.samples.length < 20) { t.samples.push(s); } });
              if (!d.done) { step(d.cursorPosts, d.cursorStrings); return; }
              status.textContent = '';
              show(t);
              check.disabled = false;
              repair.disabled = !(dryRun && t.rows > 0);
            }).catch(function () {
              status.textContent = i18n.error;
              check.disabled = false;
            });
          }
          step(0, 0);
        }
        check.addEventListener('click', function () { go(true); });
        repair.addEventListener('click', function () { go(false); });
      })();
      </script>
      <?php
  }


  public static function title(): string {
      /* translators: Name of a Support tool on WPML → Support and the heading of its screen. "page_id" is a technical word in a web address and stays as it is. Verb phrase, imperative. */
      return __( 'Repair links rewritten to page_id addresses', 'wpml' );
  }


  public static function strings(): array {
      return [
        /* translators: Status shown on WPML → Support while a check or a test runs. */
        'checking'      => __( 'Checking…', 'wpml' ),
        /* translators: Status shown on WPML → Support while a repair runs. */
        'repairing'     => __( 'Repairing…', 'wpml' ),
        /* translators: Status on WPML → Support when a request failed. */
        'error'         => __( 'The request failed. Reload the page and try again.', 'wpml' ),
        /* translators: On WPML → Support, when the Check found no link to repair. */
        'noneFound'     => __( 'No links to repair were found.', 'wpml' ),
        /* translators: Heading of the Check's result on WPML → Support. */
        'headFound'     => __( 'Links to repair', 'wpml' ),
        /* translators: Heading of the Repair's result on WPML → Support. */
        'headRepaired'  => __( 'Links repaired', 'wpml' ),
        /* translators: Line of the result on WPML → Support. %d: number of links. */
        'content'       => __( 'In page content: %d', 'wpml' ),
        /* translators: Line of the result on WPML → Support. %d: number of links. */
        'excerpt'       => __( 'In excerpts: %d', 'wpml' ),
        /* translators: Line of the result on WPML → Support. %d: number of links in the settings of blocks (the block editor). */
        'block'         => __( 'In block settings: %d', 'wpml' ),
        /* translators: Line of the result on WPML → Support. %d: number of links in translated strings. */
        'string'        => __( 'In string translations: %d', 'wpml' ),
        /* translators: Line of the Check's result on WPML → Support. %d: pages, posts and strings the Repair will update. */
        'rowsToUpdate'  => __( 'Items to update: %d', 'wpml' ),
        /* translators: Line of the Repair's result on WPML → Support. %d: pages, posts and strings updated. */
        'rowsUpdated'   => __( 'Items updated: %d', 'wpml' ),
        /* translators: On WPML → Support. %d: links that look broken but are not changed. "page_id" stays as it is. */
        'noMatch'       => __( 'Links left as they are: %d. They are not in the shape older WPML versions wrote.', 'wpml' ),
        /* translators: On WPML → Support. %d: links that are not changed. */
        'targetMissing' => __( 'Links left as they are: %d. The page they point to no longer exists.', 'wpml' ),
        /* translators: On WPML → Support. %d: links with several page_id parts that are not changed because they start with another site's address. "page_id" stays as it is. */
        'foreign'       => __( 'Links left as they are: %d. They have several page_id parts but start with another site address, for example a copy of your site moved to another domain.', 'wpml' ),
        /* translators: On WPML → Support. %d: blocks or texts too large for the tool to read, whose links were not checked. */
        'unreadable'    => __( 'Not checked: %d. Some content was too large to read, so its links were not checked or repaired.', 'wpml' ),
        /* translators: Heading of the list of the first items on WPML → Support. */
        'headSamples'   => __( 'First items', 'wpml' ),
        /* translators: One item on WPML → Support. %1$s: page or post title, %2$s: language code, %3$d: number of links. */
        'samplePost'    => __( '%1$s (%2$s). Links: %3$d', 'wpml' ),
        /* translators: One item on WPML → Support. %1$s: string name, %2$s: language code, %3$d: number of links. */
        'sampleString'  => __( 'String %1$s (%2$s). Links: %3$d', 'wpml' ),
        /* translators: On WPML → Support, after the Repair. */
        'done'          => __( 'Done. Each updated page and post has a revision you can restore.', 'wpml' ),
      ];
  }


}
