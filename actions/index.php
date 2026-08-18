<?php

// remove_action('rest_api_init', 'create_initial_rest_routes', 99);

add_action('jwt_auth_expire', function() {
  return time() + (60 * 60 * 24);
});

add_action('after_setup_theme', function() {
  add_theme_support('post-thumbnails');
});

add_action('manage_album_posts_custom_column', function($column, $post_id) {
  if ($column === 'album_artist') {
    $artist = get_post_meta($post_id, 'artist', true);
    echo $artist ? esc_html($artist) : '—';
  }
}, 10, 2);