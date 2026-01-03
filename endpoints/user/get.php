<?php

function api_user_get($request) {
  $user = wp_get_current_user();

  if ($user->ID === 0) {
    return new WP_Error('error', 'Você não está autenticado.', ['status' => 401]);
  }
  
  $listened = get_user_meta($user->ID, 'listened_albums', true) ? get_user_meta($user->ID, 'listened_albums', true) : [];
  $favorited = get_user_meta($user->ID, 'favorited_albums', true) ? get_user_meta($user->ID, 'favorited_albums', true) : [];

  $response = [
    'id' => $user->ID,
    'username' => $user->user_login,
    'name' => $user->display_name,
    'email' => $user->user_email,
    'avatar' => get_avatar_url($user->ID),
    'stats' => [
      'listened' => (int) count($listened),
      'favorited' => (int) count($favorited),
      'published' => (int) count_user_posts($user->ID, 'album')
    ]
  ];

  return rest_ensure_response($response);
}


add_action('rest_api_init', function () {
  register_rest_route('api', '/user', [
    'methods' => WP_REST_Server::READABLE,
    'callback' => 'api_user_get',
  ]);
});