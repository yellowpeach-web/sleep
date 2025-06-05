<?php
/************************************************************************************************************/
/* Excerpt Content length */
/************************************************************************************************************/

function custom_excerpt_length($length) {
    return 35;
}
add_filter('excerpt_length', 'custom_excerpt_length', 999);


/************************************************************************************************************/
/* Word / Character / Paragraph  limit */
/************************************************************************************************************/
function string_limit_words($string, $word_limit) {
    $words = explode(' ', $string, ($word_limit + 1));
    if (count($words) > $word_limit) {
        array_pop($words);
    }
    return implode(' ', $words);
}

if (!function_exists('string_limit_character')) {

    function string_limit_character($string, $limit) {
        $title_length = strlen($string);
        if ($title_length > $limit && ($title_length + 2) > $limit):
            /*new code for get string with word*/
            $s = substr($string, 0, $limit);
            $string = substr($s, 0, strrpos($s, ' '));
            
            //$string = substr($string, 0, $limit - 1);
            $string = rtrim($string) . '...';
        endif;
        return $string;
    }

}

function get_content_paragraphs($content,$number=1) {
    //$text = $content;
    if(!empty($content)) {
        $text = "";
        $data = explode('</p>', $content);
        if(isset($data) && !empty($data)):
            if(!empty($number)){
                for($i=0;$i<$number;$i++){
                    $text.= $data[$i];
                }
            }
        endif;
    }
    return wpautop(strip_shortcodes($text));
}

/************************************************************************************************************/
/* Get IDs Based on temaplate name */
/************************************************************************************************************/

function get_page_template_ids($templatename) {
    $ids = array();
    $args = array(
        'post_type' => 'page',
        'meta_query' => array(
            array(
                'key' => '_wp_page_template',
                'value' => $templatename,
                'compare' => '='
            )
        )
    );
    $pages = get_posts($args);
    if (isset($pages)):
        foreach ($pages as $p): setup_postdata($p);
            $ids[] = $p->ID;
        endforeach;
    endif;
    return $ids;
}

function seotitletag($title) {
    $tag="";
    if (!empty($title)):
        $string = preg_replace('/<' . $tag . '[^>]*>/i', '', $title);
        $string = preg_replace('/<\/' . $tag . '>/i', '', $title);
        $string = html_entity_decode($string, ENT_COMPAT, 'UTF-8');
        $title = sanitize_textarea_field(ucwords($string));
    endif;
    return $title;
}

function seoalttag($title) {
    $tag="";
    if (!empty($title)):
        $string = preg_replace('/<' . $tag . '[^>]*>/i', '', $title);
        $string = preg_replace('/<\/' . $tag . '>/i', '', $title);
        $string = html_entity_decode($string, ENT_COMPAT, 'UTF-8');
        $title = sanitize_title($string);
    endif;
    return $title;
}

/* Get text without image */
function custom_strip_image($text) {
    $text = preg_replace("/<img[^>]+\>/i", "", $text);
    return $text;
}

function remove_p_tags( $string ){
    return preg_replace('#<p>\s*+(<br\s*/*>)?\s*</p>#i', ' ', $string);
}

function get_image_name($image) {
    $info = pathinfo($image);
    $image_name = basename($image, '.' . $info['extension']);
    return $image_name;
}

function partition($list, $columns) {
    $partition = array_chunk($list, ceil(count($list) / $columns));
    return $partition;
}

function ifexists($varname){
  return(isset($$varname)?$varname:null);
}

function debug($varname){
  echo '<pre>';
  print_r($varname);
  echo '</pre>';
}
function remove_http($url) {
   $disallowed = array('http://', 'https://');
   foreach($disallowed as $d) {
      if(strpos($url, $d) === 0) {
         return str_replace($d, '', $url);
      }
   }
   return $url;
}

/************************************************************************************************************/
/* Support add svg image in media */
/************************************************************************************************************/
if (!function_exists('add_svg_to_upload_mimes')) {
    function add_svg_to_upload_mimes( $upload_mimes ) {
        $upload_mimes['svg'] = 'image/svg+xml';
        $upload_mimes['svgz'] = 'image/svg+xml';
        return $upload_mimes;
    }
    add_filter( 'upload_mimes', 'add_svg_to_upload_mimes', 10, 1 );
}

function get_link_data($button_link){
    $linkdata = array();    
    if(isset($button_link) && !empty($button_link)):
        $linkdata['title']  = !empty($button_link['title']) ? $button_link['title'] : '';
        $linkdata['url']    = !empty($button_link['url']) ? $button_link['url'] : '#';
        $linkdata['target'] = !empty($button_link['target']) ? $button_link['target'] : '_self'; 
    else:
        $linkdata['title']  = '';
        $linkdata['url']    = '#';
        $linkdata['target'] = '_self'; 
    endif;
    return $linkdata;
}