<?php
/*
 * example of a timber extensions this one is extending the Post objects,
 *
 * see functions/timber-class-map.php to see how to register a class
 *
 * Docs here https://timber.github.io/docs/v2/guides/extending-timber/
 */

namespace Timber\Extensions;

/**
 * Class PostContent for single post content
 */
class Post_Timber_Extension extends \Timber\Post
{
  public function read_time()
  {
    $words_per_minute = 228;

    $words = str_word_count(wp_strip_all_tags($this->content()));
    $minutes = round($words / $words_per_minute);
    if ($minutes < 1) {
      return 'Under 1 Min Read';
    } else {
      return sprintf(_n('%s Min Read', '%s Min Read', $minutes), (int) $minutes);
    }
  }
}
