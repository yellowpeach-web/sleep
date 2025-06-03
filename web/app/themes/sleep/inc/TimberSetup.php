<?php

namespace YPTheme;

use PHP_CodeSniffer\Util\Help;
use Timber\Timber;
use Twig\TwigFunction;
use DirectoryIterator;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use WP_Query;

class TimberSetup
{
    public static function init()
    {
        self::include_acf_fields(); // Include ACF fields from blocks
        Timber::init();
        add_filter('timber/twig', [self::class, 'add_twig_functions']);
        add_action('after_setup_theme', [self::class, 'register_timber_blocks']);
        add_filter('timber/context', [self::class, 'add_global_timber_context']);
        add_filter('timber/post/classmap', [self::class, 'register_classmap']);
        add_filter('timber/loader/loader', [self::class, 'register_timber_blocks_path']);
    }

    public static function add_twig_functions($twig)
    {
        $functions = [
            'home_url',
            'get_the_title',
            'get_field',
            'is_home',
            'is_category',
            'is_single',
            'get_permalink',
            'get_option'
        ];

        foreach ($functions as $fn) {
            $twig->addFunction(new TwigFunction($fn, $fn));
        }

        $twig->addFunction(new TwigFunction('d', [Debugging::class, 'd']));
        $twig->addFunction(new TwigFunction('dd', [Debugging::class, 'dd']));

        $twig->addFunction(new TwigFunction('get_link_attr', [HelperFunctions::class, 'get_link_attr']));
        $twig->addFunction(new TwigFunction('get_breadcrumb', [HelperFunctions::class, 'get_breadcrumb']));
        $twig->addFunction(new TwigFunction('responsive_img', [Images::class, 'responsive_img']));

        return $twig;
    }

    public static function register_timber_blocks()
    {
        new \YPTheme\TimberAcfBlocks();
    }

    public static function add_global_timber_context($context)
    {
        $context['general_settings'] = \YPTheme\HelperFunctions::get_general_settings();
        return $context;
    }

    public static function register_timber_blocks_path($loader)
    {
        $loader->addPath(get_template_directory() . '/views/blocks', "blocks");
        return $loader;
    }

    public static function register_classmap($classmap)
    {
        $custom_classmap = [
            'faq' => \Timber\Extensions\FaqPostType::class,
            'testimonial' => \Timber\Extensions\TestimonialPostType::class
        ];

        return array_merge($classmap, $custom_classmap);
    }

    public static function include_acf_fields()
    {
        $blocks_path = get_template_directory() . '/views/blocks';

        if (!is_dir($blocks_path)) {
            return;
        }

        $directoryIterator = new \RecursiveDirectoryIterator($blocks_path);
        $iterator = new \RecursiveIteratorIterator($directoryIterator);

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getFilename() === 'fields.php') {
                require_once $file->getPathname();
            }
        }
    }
}


class TimberAcfBlocks
{
    public function __construct()
    {
        if (is_callable('acf_register_block_type') && class_exists('Timber')) {
            add_action('init', [self::class, 'timber_block_init']);
        }
    }

    public static function timber_block_init()
    {
        $directories = self::timber_block_directory_getter();

        foreach ($directories as $dir) {
            if (!file_exists(locate_template($dir))) {
                return;
            }

            $template_directory = new DirectoryIterator(locate_template($dir));

            foreach ($template_directory as $template) {
                if ($template->isDot() || $template->isDir()) {
                    continue;
                }

                $file_parts = pathinfo($template->getFilename());
                if ('twig' !== $file_parts['extension']) {
                    continue;
                }

                $block_absolute_path = get_template_directory() . '/' . $dir;

                register_block_type($block_absolute_path, [
                    'render_callback' => [self::class, 'timber_blocks_callback'],
                    'attributes' => [
                        'style' => [
                            'type' => 'object',
                            'default' => [
                                'spacing' => [
                                    'padding' => [
                                        'top' => '80px',
                                        'bottom' => '80px',
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]);
            }
        }
    }

    public static function timber_blocks_callback($block, $content = '', $is_preview = false, $post_id = 0)
    {
        $context = Timber::context();
        $slug = str_replace('acf/', '', $block['name']);

        $context['block'] = $block;
        $context['post_id'] = $post_id;
        $context['slug'] = $slug;
        $context['is_preview'] = $is_preview;
        $context['fields'] = get_fields();


        // Get block specific posts
        $context['insights'] = self::get_posts_feed($block);
        $context['insight_terms'] = self::get_post_terms($block);
        $context['posts'] = self::get_posts_archive($block);
        $context['testimonials'] = self::get_testimonials($block);
        // $context['faqs'] = self::get_faqs($block);
        // $context['faq_terms'] = self::get_faq_terms($block);
        $faq_data = self::get_faq_data($block);
        $context['faqs'] = $faq_data['faqs'];
        $context['faq_terms'] = $faq_data['faq_terms'];

        $context['team'] = self::get_team($block);
        $context['team_terms'] = self::get_team_terms($block);
        $context['logos'] = self::get_logos($block);

        // Assign block spacing 
        $context['block_spacing'] = trim(
            self::generate_spacing_classes($block['style']['spacing']['padding']['top'] ?? '', 'padding-top') . ' ' .
                self::generate_spacing_classes($block['style']['spacing']['padding']['bottom'] ?? '', 'padding-bottom')
        );

        // Assign block color
        $backgroundColor = isset($block['backgroundColor']) ? $block['backgroundColor'] : 'bg-background-primary-bg-fill';

        if (str_starts_with($backgroundColor, 'dark-')) {
            $context['background_color'] = str_replace('dark-', '', $backgroundColor);
            $context['color_profile'] = 'dark-theme';
        } elseif (str_starts_with($backgroundColor, 'light-')) {
            $context['background_color'] = str_replace('light-', '', $backgroundColor);
            $context['color_profile'] = 'light-theme';
        } else {
            $context['background_color'] = $backgroundColor;
            $context['color_profile'] = 'dark';
        }

        $context = apply_filters('timber/acf-gutenberg-blocks-data', $context);
        Timber::render(self::timber_acf_path_render($slug, $is_preview), $context);
    }


    /**
     * Convert WordPress spacing presets to class names.
     */
    private static function generate_spacing_classes($spacing_value, $prefix)
    {
        if (strpos($spacing_value, 'var:preset|spacing|') === 0) {
            $preset_key = str_replace('var:preset|spacing|', '', $spacing_value);
            return $prefix . '-' . $preset_key;
        }
        return '';
    }

    /**
     * Custom pagination
     */
    private static function generate_pagination($query, $current_page)
    {
        $total_pages = $query->max_num_pages;
        if ($total_pages <= 1) {
            return null;
        }

        $visible_pages = [];

        if ($total_pages > 3) {
            if ($current_page == 1) {
                $visible_pages = [1, 2, 3];
            } elseif ($current_page == $total_pages) {
                $visible_pages = [$total_pages - 2, $total_pages - 1, $total_pages];
            } else {
                $visible_pages = [$current_page - 1, $current_page, $current_page + 1];
            }
        } else {
            $visible_pages = range(1, $total_pages);
        }

        return [
            'pages'     => array_map(function ($page) use ($current_page) {
                return [
                    'title'   => $page,
                    'link'    => esc_url(get_pagenum_link($page)),
                    'current' => $page == $current_page,
                ];
            }, $visible_pages),
            'prev'      => ($current_page > 1) ? ['link' => get_pagenum_link($current_page - 1)] : null,
            'next'      => ($current_page < $total_pages) ? ['link' => get_pagenum_link($current_page + 1)] : null,
            'show_pre'  => $visible_pages[0] > 1,
            'show_post' => $visible_pages[count($visible_pages) - 1] < $total_pages,
        ];
    }

    /**
     * Get posts helper
     */
    public static function get_related_posts($block, $block_name, $post_type, $field_name, $posts_amount = -1, $term_args = [])
    {
        if ($block['name'] !== $block_name) {
            return [];
        }

        $show_selected = get_field('show_selected');

        if ($show_selected) {
            $selected = get_field($field_name);
            if (!empty($selected)) {
                return Timber::get_posts([
                    'post_type'      => $post_type,
                    'post__in'       => $selected,
                    'orderby'        => 'post__in',
                    'posts_per_page' => $posts_amount
                ]);
            }
            return [];
        }

        $args = [
            'post_type'      => $post_type,
            'posts_per_page' => $posts_amount,
        ];

        // Exclude current post if on a single post page
        if (is_singular($post_type)) {
            $args['post__not_in'] = [get_the_ID()];
        }

        if (!empty($term_args['taxonomy']) && !empty($term_args['term'])) {
            $args['tax_query'] = [
                [
                    'taxonomy' => $term_args['taxonomy'],
                    'field'    => 'slug',
                    'terms'    => $term_args['term'],
                ]
            ];
        }

        return Timber::get_posts($args);
    }


    private static function get_filter_terms($taxonomy, $hide_empty = true, $uncat = false)
    {
        $args = [
            'taxonomy'   => $taxonomy,
            'hide_empty' => $hide_empty,
        ];
        $terms = Timber::get_terms($args);

        if (!$uncat && is_array($terms)) {
            $terms = array_filter($terms, function ($term) {
                return $term->slug !== 'uncategorized';
            });
        }

        return $terms;
    }


    /**
     * Block specific methods
     */

    private static function get_logos($block)
    {
        if ($block['name'] === 'acf/logo-carousel') {
            $show_selected = get_field('show_selected');
            if ($show_selected) {
                return get_field('logos');
            } else {
                return get_field('logos', 'options') ?: null;
            }
        }
        return null;
    }


    private static function get_posts_archive($block)
    {
        if ($block['name'] === 'acf/posts-archive') {
            $paged = (get_query_var('paged')) ? get_query_var('paged') : 1;
            $args = [
                'post_type' => 'post',
                'paged'     => $paged,
                'orderby'   => 'post_date',
                'order'     => 'DESC',
            ];
            $query = new WP_Query($args);
            $posts = Timber::get_posts($args);
            return [
                'posts'      => $posts,
                'yp_pagination' => self::generate_pagination($query, $paged),
            ];
        }
        return;
    }

    private static function get_team($block)
    {
        return self::get_related_posts($block, 'acf/meet-the-team', 'team', 'team');
    }


    private static function get_faq_data($block)
    {
        if ($block['name'] !== 'acf/faqs') {
            return [
                'faqs' => [],
                'faq_terms' => [],
            ];
        }

        $fields = get_fields();
        $faqs = [];
        $terms = [];

        if (!empty($fields['faq_terms'])) {
            $term_ids = array_map('intval', $fields['faq_terms']);

            // Fetch all terms by ID
            $unsorted_terms = get_terms([
                'taxonomy'   => 'filter',
                'include'    => $term_ids,
                'hide_empty' => false,
            ]);

            // Sort terms to match the selected ID order
            $terms_map = [];
            foreach ($unsorted_terms as $term) {
                $terms_map[$term->term_id] = $term;
            }

            $terms = array_values(array_filter(array_map(
                fn($id) => $terms_map[$id] ?? null,
                $term_ids
            )));

            $term_slugs = wp_list_pluck($terms, 'slug');

            if (!empty($term_slugs)) {
                $faqs = self::get_related_posts($block, 'acf/faqs', 'faq', 'faqs', -1, [
                    'taxonomy' => 'filter',
                    'term'     => $term_slugs,
                ]);
            }
        }

        return [
            'faqs' => $faqs,
            'faq_terms' => !empty($fields['include_filter']) ? $terms : [],
        ];
    }




    public static function get_post_terms($block)
    {
        if ($block['name'] == 'acf/posts-feed') {
            return self::get_filter_terms('category');
        }
    }

    private static function get_team_terms($block)
    {
        if ($block['name'] == 'acf/meet-the-team') {
            return self::get_filter_terms('department');
        }
    }

    public static function get_posts_feed($block, $term_args = [])
    {
        return self::get_related_posts($block, 'acf/posts-feed', 'post', 'posts', 6, $term_args);
    }

    private static function get_testimonials($block)
    {
        return self::get_related_posts($block, 'acf/testimonial-carousel', 'testimonial', 'testimonials');
    }


    public static function timber_acf_path_render($slug, $is_preview)
    {
        $directories = self::timber_block_directory_getter();
        $ret = [];

        $preview_identifier = apply_filters('timber/acf-gutenberg-blocks-preview-identifier', '-preview');

        foreach ($directories as $directory) {
            if ($is_preview) {
                $ret[] = $directory . "/{$slug}{$preview_identifier}.twig";
            }
            $ret[] = $directory . "/{$slug}.twig";
        }

        return $ret;
    }

    public static function timber_block_directory_getter()
    {
        $directories = apply_filters('timber/acf-gutenberg-blocks-templates', ['views/blocks']);
        return array_merge($directories, self::timber_blocks_subdirectories($directories));
    }

    public static function timber_blocks_subdirectories($directories)
    {
        $ret = [];

        foreach ($directories as $base_directory) {
            if (!file_exists(locate_template($base_directory))) {
                continue;
            }

            $template_directory = new RecursiveDirectoryIterator(
                locate_template($base_directory),
                FilesystemIterator::KEY_AS_PATHNAME | FilesystemIterator::CURRENT_AS_SELF
            );

            foreach ($template_directory as $directory) {
                if ($directory->isDir() && !$directory->isDot()) {
                    $ret[] = $base_directory . '/' . $directory->getFilename();
                }
            }
        }

        return $ret;
    }
}
