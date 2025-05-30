<?php

namespace YPTheme;

class Pagination
{
      public static function generate($query)
      {
            $current_page = max(1, get_query_var('paged') ?: get_query_var('page'));
            $total_pages = $query->max_num_pages;

            if ($total_pages <= 1) {
                  return null;
            }

            // Determine visible pages
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
                  'pages' => array_map(function ($page) use ($current_page) {
                        return [
                              'title' => $page,
                              'link' => esc_url(get_pagenum_link($page)),
                              'current' => ($page == $current_page),
                        ];
                  }, $visible_pages),
                  'prev' => ($current_page > 1) ? ['link' => get_pagenum_link($current_page - 1)] : null,
                  'next' => ($current_page < $total_pages) ? ['link' => get_pagenum_link($current_page + 1)] : null,
                  'show_pre' => $visible_pages[0] > 1,
                  'show_post' => $visible_pages[count($visible_pages) - 1] < $total_pages,
            ];
      }
}
