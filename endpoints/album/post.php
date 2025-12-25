<?php

function api_album_post($request) {
  $user = wp_get_current_user();
  $user_id = $user->ID;

  if ($user_id === 0) {
    return new WP_Error('error', esc_html__('Você não está autenticado.'), ['status' => 401]);
  }

  $title = sanitize_text_field($request['title']);
  $description = sanitize_text_field($request['description']);
  $artist = sanitize_text_field($request['artist']);
  $genres = $request['genres'];
  $released = sanitize_text_field($request['released']);
  $tracklist = $request['tracklist'];
  $files = $request->get_file_params();

  if (empty($title) || empty($artist) || empty($released) || empty($files)) {
    return new WP_Error('error', esc_html__('Preencha todos os campos para publicar este álbum.'), ['status' => 400]);
  }

  $response = [
    'post_author' => $user_id,
    'post_title' => wp_strip_all_tags($title),
    'post_type' => 'album',
    'post_status' => 'draft',
    'post_content' => $description,
    'files' => $files,
    'meta_input' => [
      'artist' => $artist,
      'genres' => $genres,
      'released' => $released,
      'tracklist' => $tracklist,
    ],
  ];
  $post_id = wp_insert_post($response);

  require_once ABSPATH . 'wp-admin/includes/image.php';
  require_once ABSPATH . 'wp-admin/includes/file.php';
  require_once ABSPATH . 'wp-admin/includes/media.php';

  $cover_id = media_handle_upload('cover', $post_id);
  update_post_meta($post_id, 'cover', $cover_id);

  return rest_ensure_response($response);
}


add_action('rest_api_init', function () {
  register_rest_route('api', '/album', [
    'methods' => WP_REST_Server::CREATABLE,
    'callback' => 'api_album_post',
  ]);
});