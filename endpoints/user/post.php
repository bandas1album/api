<?php

function api_user_post($request) {
  $email = sanitize_email($request['email']);
  $username = sanitize_text_field($request['username']);
  $name = sanitize_text_field($request['name']);
  $password = $request['password'];

  if (empty($email) || empty($username) || empty($password) || empty($name)) {
    return new WP_Error('error', 'Preencha todos os campos obrigatórios para concluir o cadastro.', ['status' => 406]);
  }

  if (username_exists($username) || email_exists($email)) {
    return new WP_Error('error', 'E-mail já cadastrado.', ['status' => 403]);
  }

  $user_id = wp_insert_user([
    'user_login' => $username,
    'user_email' => $email,
    'user_pass' => $password,
    'display_name' => $name,
    'role' => 'subscriber',
  ]);

  if (is_wp_error($user_id)) {
    return $user_id;
  }

  return rest_ensure_response([
    'id' => $user_id,
    'message' => 'Usuário criado com sucesso'
  ]);
}

add_action('rest_api_init', function () {
  register_rest_route('api', '/user', [
    'methods' => WP_REST_Server::CREATABLE,
    'callback' => 'api_user_post',
  ]);
});