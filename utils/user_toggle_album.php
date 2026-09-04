<?php

function user_toggle_album($user_id, $album_id, $type) {
  $allowed = ['listened', 'favorited'];
  if (!in_array($type, $allowed, true)) {
    return new WP_Error('error', 'Tipo inválido.', ['status' => 400]);
  }

  $user_id = absint($user_id);
  $album_id = absint($album_id);
  $meta_key = "{$type}_albums";

  $albums = get_user_meta($user_id, $meta_key, true);

  if (!is_array($albums)) {
    $albums = [];
  }

  if (in_array($album_id, $albums, true)) {
    $albums = array_values(array_diff($albums, [$album_id]));
    $status = 'removed';
  } else {
    $albums[] = $album_id;
    $albums = array_values(array_unique(array_map('absint', $albums)));
    $status = 'added';
  }

  update_user_meta($user_id, $meta_key, $albums);

  return [
    'status' => $status,
    'count' => count($albums),
  ];
}
