<?php
namespace YPTheme;

class Images
{
    public static function init()
    {

        // Stop WP's pesky image sizing
        add_filter('wp_img_tag_add_auto_sizes', '__return_false');

        // Register custom image sizes
        add_image_size('extra-large', 1800, 0, false);


    }

    public static function responsive_img($image_arr, $args): ?string
    {
        if (!$image_arr) {
            return null;
        }
        $args['alt'] = $args['alt'] ?? get_bloginfo('name');
        $image_object = is_array($image_arr) ? (object)$image_arr : $image_arr;
        $class = $args['class'] ?? '';

        $image_id = is_object($image_object) ? $image_object->id : attachment_url_to_postid($image_arr);
        $image_meta = wp_get_attachment_metadata($image_id);
        $image_src = wp_get_attachment_image_url($image_id, $args['size']);
        $image_srcset = wp_get_attachment_image_srcset($image_id, $args['size']);
        $image_sizes = wp_get_attachment_image_sizes($image_id, $args['size']);
        $image_alt = get_post_meta($image_id, '_wp_attachment_image_alt', true) ?: $args['alt'];

        return '<img src="' . $image_src . '" srcset="' . $image_srcset . '" sizes="' . $image_sizes . '" height="' . $image_meta['height'] . '" width="' . $image_meta['width'] . '" alt="' . $image_alt . '" class="' . $class . '">';
    }

}