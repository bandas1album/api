<?php

add_filter('rest_url_prefix', function () {
  return 'json';
});

add_filter('posts_search', function ($search, $query) {
  global $wpdb;

  if (!$query->get('s')) {
    return $search;
  }

  // Only customize search for album queries (avoid altering admin/core searches).
  $post_type = $query->get('post_type');
  if ($post_type !== 'album' && !(is_array($post_type) && in_array('album', $post_type, true))) {
    return $search;
  }

  $like = '%' . $wpdb->esc_like($query->get('s')) . '%';

  return $wpdb->prepare(
    " AND (
      {$wpdb->posts}.post_title LIKE %s
      OR {$wpdb->posts}.post_content LIKE %s
      OR EXISTS (
        SELECT 1 FROM {$wpdb->postmeta}
        WHERE post_id = {$wpdb->posts}.ID
          AND meta_key = 'artist'
          AND meta_value LIKE %s
      )
    ) ",
    $like,
    $like,
    $like
  );
}, 10, 2);

add_filter('manage_album_posts_columns', function ($columns) {
  $new = [];

  foreach ($columns as $key => $label) {
    $new[$key] = $label;

    if ($key === 'title') {
      $new['album_artist'] = 'Artista';
    }
  }

  return $new;
});
