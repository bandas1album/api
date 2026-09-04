<?php

function api_album_patch_listened($request) {
  $user_id = get_current_user_id();
  $album_id = absint($request['album']);

  if (!$album_id) {
    return new WP_Error('error', esc_html__('Álbum inválido.'), ['status' => 400]);
  }

  $response = user_toggle_album($user_id, $album_id, 'listened');

  return rest_ensure_response($response);
}

add_action('rest_api_init', function () {
  register_rest_route('api', '/album/(?P<album>\d+)/listened', [
    'methods' => WP_REST_Server::EDITABLE,
    'callback' => 'api_album_patch_listened',
    'permission_callback' => 'api_permission_logged_in',
  ]);
});
