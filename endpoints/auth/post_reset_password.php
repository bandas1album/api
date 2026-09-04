<?php

function api_auth_post_reset_password($request) {
  $login = sanitize_text_field($request['login'] ?? '');
  $password = (string) ($request['password'] ?? '');
  $key = sanitize_text_field($request['key'] ?? '');

  if (empty($login) || empty($password) || empty($key)) {
    return new WP_Error('error', 'Dados incompletos.', ['status' => 406]);
  }

  if (strlen($password) < 8) {
    return new WP_Error('error', 'A senha deve ter pelo menos 8 caracteres.', ['status' => 406]);
  }

  $user = get_user_by('login', $login);
  if (empty($user)) {
    return new WP_Error('error', 'Token inválido ou expirado.', ['status' => 401]);
  }

  $check_key = check_password_reset_key($key, $login);
  if (is_wp_error($check_key)) {
    return new WP_Error('error', 'Token inválido ou expirado.', ['status' => 401]);
  }

  reset_password($user, $password);

  return rest_ensure_response([
    'message' => 'Senha alterada',
  ]);
}

add_action('rest_api_init', function () {
  register_rest_route('api', '/auth/reset-password', [
    'methods' => WP_REST_Server::CREATABLE,
    'callback' => 'api_auth_post_reset_password',
    'permission_callback' => 'api_permission_auth_sensitive',
  ]);
});
