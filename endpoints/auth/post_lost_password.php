<?php

function api_auth_post_lost_password($request) {
  $login = sanitize_text_field($request['login'] ?? '');

  if (empty($login)) {
    return new WP_Error('error', 'Informe o usuário ou e-mail.', ['status' => 406]);
  }

  $generic = [
    'message' => 'Se a conta existir, enviamos um e-mail de recuperação.',
  ];

  $user = get_user_by('email', $login);
  if (empty($user)) {
    $user = get_user_by('login', $login);
  }

  // Same response whether or not the account exists (no user enumeration).
  if (empty($user)) {
    return rest_ensure_response($generic);
  }

  $key = get_password_reset_key($user);
  if (is_wp_error($key)) {
    return rest_ensure_response($generic);
  }

  $frontend = trailingslashit(B1A_FRONTEND_URL);
  $reset_link = $frontend . '?key=' . rawurlencode($key) . '&login=' . rawurlencode($user->user_login);

  $body = "Utilize o link abaixo para refazer a sua senha:\r\n" . $reset_link . "\r\n";
  wp_mail($user->user_email, 'Recupere sua senha | Bandas 1 Álbum', $body);

  return rest_ensure_response($generic);
}

add_action('rest_api_init', function () {
  register_rest_route('api', '/auth/lost-password', [
    'methods' => WP_REST_Server::CREATABLE,
    'callback' => 'api_auth_post_lost_password',
    'permission_callback' => 'api_permission_auth_sensitive',
  ]);
});
