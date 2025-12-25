<?php

function api_user_post($request) {
  $email = sanitize_email($request['email']);
  $username = sanitize_text_field($request['username']);
  $password = $request['password'];

  if (empty($email) || empty($username) || empty($password)) {
    return new WP_Error('error', 'Preencha todos os campos obrigatórios para concluir o cadastro.', ['status' => 406]);
  }

  if (username_exists($username) || email_exists($email)) {
    return new WP_Error('error', 'E-mail já cadastrado.', ['status' => 403]);
  }

  $response = wp_insert_user([
    'user_login' => $username,
    'user_email' => $email,
    'user_pass'  => $password,
    'role'       => 'subscriber',
  ]);

  return rest_ensure_response($response);
}


add_action('rest_api_init', function () {
  register_rest_route('api', '/user', [
    'methods' => WP_REST_Server::CREATABLE,
    'callback' => 'api_user_post',
  ]);
});