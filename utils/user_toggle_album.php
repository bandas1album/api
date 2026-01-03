<?php

function user_toggle_album($user_id, $album_id, $type) {
  $meta_key = "{$type}_albums";

  $albums = get_user_meta($user_id, $meta_key, true);

  if (!is_array($albums)) {
    $albums = [];
  }

  if (in_array($album_id, $albums)) {
    $albums = array_values(array_diff($albums, [$album_id]));
    $status = 'removed';
  } else {
    $albums[] = (int) $album_id;
    $albums = array_values(array_unique($albums));
    $status = 'added';
  }

  update_user_meta($user_id, $meta_key, $albums);

  return [
    'status' => $status,
    'count' => count($albums),
  ];
}