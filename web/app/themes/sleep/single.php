<?php



use Timber\Timber;
use YPTheme\HelperFunctions;
use YPTheme\TimberAcfBlocks;
use YPTheme\TimberSetup;

$context = Timber::context();
$template = 'templates/single.twig';

//social sharing
$post = Timber::get_post();
$title = $post->title;
$permalink = $post->link;
$share_links = [
    [
        'icon' => 'resources/images/icons/socials/facebook.svg',
        'name' => 'Facebook',
        'url' => 'https://www.facebook.com/sharer/sharer.php?u=' . urlencode($permalink),
    ],
    [
        'icon' => 'resources/images/icons/socials/x_social.svg',
        'name' => 'X',
        'url' => 'https://twitter.com/intent/tweet?url=' . urlencode($permalink)
    ],
    [
        'icon' => 'resources/images/icons/socials/instagram.svg',
        'name' => 'Instagram',
        'url' => 'https://www.instagram.com/',
    ],
    [
        'icon' => 'resources/images/icons/socials/threads.svg',
        'name' => 'Threads',
        'url' => 'https://www.threads.net/@yourusername',
    ],
    [
        'icon' => 'resources/images/icons/socials/whatsapp.svg',
        'name' => 'WhatsApp',
        'url' => 'https://wa.me/?text=' . rawurlencode($title . ' ' . $permalink),
    ]
];
$context['share_links'] = $share_links;

// single fields
if ($post->post_type === 'post') {
    $fields = HelperFunctions::get_insight_single_fields();
    // feed fields
    $context['block_name'] = "blocks/posts-feed/posts-feed.twig";
    $block['name'] = 'acf/posts-feed';
    $context['insight_terms'] = TimberAcfBlocks::get_post_terms($block);
    $context['insights'] = TimberAcfBlocks::get_posts_feed($block);
} elseif ($post->post_type === 'news_media') {
    $fields = HelperFunctions::get_media_single_fields();
    // feed fields
    $context['block_name'] = "blocks/media-entry-feed/media-entry-feed.twig";
    $block['name'] = 'acf/media-entry-feed';
    //current term
    $terms = wp_get_post_terms($post->ID, 'news_media_categories');
    if (!empty($terms) && !is_wp_error($terms)) {
        $current_term_id = $terms[0]->term_id;
    }
    $context['media'] = TimberAcfBlocks::get_media($block, $current_term_id);
} else {
    $fields = [];
}

if ($fields) {
    if ($post->meta('overide_sidebard_cta')) {
        $context['cta'] = $post->meta('single_cta');
    } else {
        $context['cta'] = $fields['cta'];
    }
    $context['subheading'] = $fields['subheading'];
    $context['feed'] = $fields['feed'];
    $context['gravity_cta'] = $fields['gravity_cta'];
}

Timber::render($template, $context);
