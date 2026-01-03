<?php

function api_auth_post_reset_password($request) {
  $login = $request['login'];
  $password = $request['password'];
  $key = $request['key'];
  $user = get_user_by('login', $login);

  if (empty($user)) {
    $response = new WP_Error('error', 'Usuário não existe', ['status' => 401]);
    return rest_ensure_response($response);
  }

  $check_key = check_password_reset_key($key, $login);

   if (empty($check_key)) {
    $response = new WP_Error('error', 'Token expirado.', ['status' => 401]);
    return rest_ensure_response($response);
  }

  reset_password($user, $password);

  return rest_ensure_response([
    'message' => 'Senha alterada'
  ]);
}


add_action('rest_api_init', function () {
  register_rest_route('api', '/auth/reset-password', [
    'methods' => WP_REST_Server::CREATABLE,
    'callback' => 'api_auth_post_reset_password',
  ]);
});