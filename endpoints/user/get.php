<?php

function api_user_get($request) {
  $user = wp_get_current_user();

  if ($user->ID === 0) {
    return new WP_Error('error', 'Você não está autenticado.', ['status' => 401]);
  }

  $response = [
    'id' => $user->ID,
    'username' => $user->user_login,
    'name' => $user->display_name,
    'email' => $user->user_email,
  ];

  return rest_ensure_response($response);
}


add_action('rest_api_init', function () {
  register_rest_route('api', '/user', [
    'methods' => WP_REST_Server::READABLE,
    'callback' => 'api_user_get',
  ]);
});