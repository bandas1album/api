<?php

function api_album_post($request) {
  $user_id = get_current_user_id();

  $title = sanitize_text_field($request['title'] ?? '');
  $description = wp_kses_post($request['description'] ?? '');
  $artist = sanitize_text_field($request['artist'] ?? '');
  $released = sanitize_text_field($request['released'] ?? '');
  $files = $request->get_file_params();

  $genres = $request['genres'] ?? [];
  if (is_string($genres)) {
    $genres = array_filter(array_map('sanitize_text_field', explode(',', $genres)));
  } elseif (is_array($genres)) {
    $genres = array_map('sanitize_text_field', $genres);
  } else {
    $genres = [];
  }

  $tracklist = $request['tracklist'] ?? [];
  if (is_string($tracklist)) {
    $decoded = json_decode($tracklist, true);
    $tracklist = is_array($decoded) ? $decoded : [];
  }
  if (!is_array($tracklist)) {
    $tracklist = [];
  }
  $tracklist = array_values(array_filter(array_map(function ($track) {
    if (!is_array($track)) {
      return null;
    }
    return [
      'name' => sanitize_text_field($track['name'] ?? ''),
      'duration' => sanitize_text_field($track['duration'] ?? ''),
    ];
  }, $tracklist)));

  if (empty($title) || empty($artist) || empty($released) || empty($files['cover'])) {
    return new WP_Error('error', esc_html__('Preencha todos os campos para publicar este álbum.'), ['status' => 400]);
  }

  $post_id = wp_insert_post([
    'post_author' => $user_id,
    'post_title' => wp_strip_all_tags($title),
    'post_type' => 'album',
    'post_status' => 'draft',
    'post_content' => $description,
    'meta_input' => [
      'artist' => $artist,
      'genres' => $genres,
      'released' => $released,
      'tracklist' => wp_json_encode($tracklist),
    ],
  ]);

  if (is_wp_error($post_id)) {
    return $post_id;
  }

  require_once ABSPATH . 'wp-admin/includes/image.php';
  require_once ABSPATH . 'wp-admin/includes/file.php';
  require_once ABSPATH . 'wp-admin/includes/media.php';

  $cover_id = media_handle_upload('cover', $post_id);
  if (is_wp_error($cover_id)) {
    wp_delete_post($post_id, true);
    return $cover_id;
  }

  $mime = get_post_mime_type($cover_id);
  if (!$mime || strpos($mime, 'image/') !== 0) {
    wp_delete_attachment($cover_id, true);
    wp_delete_post($post_id, true);
    return new WP_Error('error', 'A capa deve ser uma imagem.', ['status' => 400]);
  }

  update_post_meta($post_id, 'cover', absint($cover_id));

  return rest_ensure_response([
    'id' => $post_id,
    'message' => 'Álbum enviado para revisão.',
  ]);
}

add_action('rest_api_init', function () {
  register_rest_route('api', '/album', [
    'methods' => WP_REST_Server::CREATABLE,
    'callback' => 'api_album_post',
    'permission_callback' => 'api_permission_logged_in',
  ]);
});
