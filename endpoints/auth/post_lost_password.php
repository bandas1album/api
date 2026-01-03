<?php

function api_auth_post_lost_password($request) {
  $login = $request['login'];
  $url = $request['url'];

  if (empty($login)) {
    $response = new WP_Error('error', 'Informe o usuário ou e-mail.', ['status' => 406]);
    return rest_ensure_response($response);
  }

  $user = get_user_by('email', $login);

  if (empty($user)) {
    $user = get_user_by('login', $login);
  }

  if (empty($user)) {
    $response = new WP_Error('error', 'Usuário não existe', ['status' => 401]);
    return rest_ensure_response($response);
  }

  $user_login = $user->user_login;
  $user_email = $user->user_email;

  $key = get_password_reset_key($user);
  $message = "Utilize o link abaixo para refazer a sua senha: \r\n";
  $url = esc_url_raw($url . "/?key=$key&login=" . rawurlencode($user_login) . "\r\n");
  $body = $message . $url;

  wp_mail($user_email, 'Recupere sua senha | Bandas 1 Álbum', $body);

  return rest_ensure_response([
    'message' => 'E-mail de recuperação de senha enviado.'
  ]);
}


add_action('rest_api_init', function () {
  register_rest_route('api', '/auth/lost-password', [
    'methods' => WP_REST_Server::CREATABLE,
    'callback' => 'api_auth_post_lost_password',
  ]);
});