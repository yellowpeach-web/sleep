<?php

namespace YPTheme;

use Timber\Timber;

class AJax
{
      public static function init()
      {
            add_action('wp_ajax_filter_posts', [self::class, 'handle_feed_filter']);
            add_action('wp_ajax_nopriv_filter_posts', [self::class, 'handle_feed_filter']);
      }

      public static function handle_feed_filter()
      {
            $context = Timber::context();
            $term = isset($_POST['term']) ? sanitize_text_field($_POST['term']) : '';
            $post_id = isset($_POST['current_post_id']) ? sanitize_text_field($_POST['current_post_id']) : '';

            $args = [
                  'post_type'      => 'post',
                  'orderby'        => 'date',
                  'order'          => 'DESC',
                  'posts_per_page' => 6,
            ];

            if ($term && $term !== 'all') {
                  $args['tax_query'] = [
                        [
                              'taxonomy' => 'category',
                              'field'    => 'slug',
                              'terms'    => $term,
                        ],
                  ];
            }

            if ($post_id) {
                  $args['post__not_in'] = [$post_id];
            }

            $posts = Timber::get_posts($args);
            $context['insights'] = $posts;

            $html = Timber::compile('views/components/feed-container.twig', $context);

            wp_send_json_success([
                  'html' => $html,
            ]);

            wp_die();
      }
}
