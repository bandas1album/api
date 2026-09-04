<?php

function api_album_get_favorited($request) {
  if (!is_user_logged_in()) {
    return rest_ensure_response(['active' => false]);
  }

  $album_id = absint($request['album']);
  $albums = get_user_meta(get_current_user_id(), 'favorited_albums', true);
  $albums = is_array($albums) ? array_map('absint', $albums) : [];

  return rest_ensure_response([
    'active' => in_array($album_id, $albums, true),
  ]);
}

add_action('rest_api_init', function () {
  register_rest_route('api', '/album/(?P<album>\d+)/favorited', [
    'methods' => WP_REST_Server::READABLE,
    'callback' => 'api_album_get_favorited',
    'permission_callback' => 'api_permission_public',
  ]);
});
