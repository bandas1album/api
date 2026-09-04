<?php

add_filter('jwt_auth_expire', function () {
  return time() + DAY_IN_SECONDS;
});

add_action('after_setup_theme', function () {
  add_theme_support('post-thumbnails');
});

add_action('manage_album_posts_custom_column', function ($column, $post_id) {
  if ($column === 'album_artist') {
    $artist = get_post_meta($post_id, 'artist', true);
    echo $artist ? esc_html($artist) : '—';
  }
}, 10, 2);

add_action('admin_menu', function () {
  remove_submenu_page('edit.php?post_type=' . BANDAS_IMPORT_POST_TYPE, 'post-new.php?post_type=' . BANDAS_IMPORT_POST_TYPE);
}, 999);
