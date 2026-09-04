<?php

function api_user_get($request) {
  $user = wp_get_current_user();

  $listened = get_user_meta($user->ID, 'listened_albums', true);
  $favorited = get_user_meta($user->ID, 'favorited_albums', true);
  $listened = is_array($listened) ? $listened : [];
  $favorited = is_array($favorited) ? $favorited : [];

  $response = [
    'id' => $user->ID,
    'username' => $user->user_login,
    'name' => $user->display_name,
    'email' => $user->user_email,
    'avatar' => get_avatar_url($user->ID),
    'stats' => [
      'listened' => (int) count($listened),
      'favorited' => (int) count($favorited),
      'published' => (int) count_user_posts($user->ID, 'album'),
    ],
  ];

  return rest_ensure_response($response);
}

add_action('rest_api_init', function () {
  register_rest_route('api', '/user', [
    'methods' => WP_REST_Server::READABLE,
    'callback' => 'api_user_get',
    'permission_callback' => 'api_permission_logged_in',
  ]);
});
