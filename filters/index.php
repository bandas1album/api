<?php

add_filter('rest_url_prefix', function() {
  return 'json';
});

add_filter('posts_search', function ($search, $query) {
  global $wpdb;

  if (!$query->get('s')) return $search;

  $q = esc_sql($query->get('s'));

  return "
    AND (
      {$wpdb->posts}.post_title LIKE '%{$q}%'
      OR {$wpdb->posts}.post_content LIKE '%{$q}%'
      OR EXISTS (
        SELECT 1 FROM {$wpdb->postmeta}
        WHERE post_id = {$wpdb->posts}.ID
        AND meta_key = 'artist'
        AND meta_value LIKE '%{$q}%'
      )
    )
  ";
}, 10, 2);