<?php

function api_album_get_listened($request) {
  $user = wp_get_current_user();
  $user_id = $user->ID;

  if ($user_id === 0) {
    return rest_ensure_response(['active' => false]);
  }

  $album_id = (int) $request['album'];
  $albums = get_user_meta($user->ID, 'listened_albums', true);

  return rest_ensure_response([
    'active' => is_array($albums) && in_array($album_id, $albums),
  ]);
}


add_action('rest_api_init', function () {
  register_rest_route('api', '/album/(?P<album>\d+)/listened', [
    'methods' => WP_REST_Server::READABLE,
    'callback' => 'api_album_get_listened',
  ]);
});