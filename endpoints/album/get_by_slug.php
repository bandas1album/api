<?php

function api_album_get_by_slug($request) {
  $slug = $request['slug'];

  $album = get_page_by_path($slug, OBJECT, 'album');

  if (!$album) {
    return new WP_Error('error', 'Álbum não encontrado', ['status' => 404]);
  }

  $cover = get_post_meta($album->ID, 'cover', true);
  $cover_url = $cover ? wp_get_attachment_image_src($cover, 'large')[0] : null;

  foreach (get_the_terms($album->ID, 'genre') as $genre) {
    $genres[] = [
      'title' => $genre->name,
      'slug' => $genre->slug
    ];
  }

  foreach (get_the_terms($album->ID, 'country') as $country) {
    $country = [
      'title' => $country->name,
      'slug' => $country->slug
    ];
  }

  $response = [
    'id' => $album->ID,
    'author' => get_userdata($album->post_author)->user_nicename,
    'title' => $album->post_title,
    'description' => $album->post_content,
    'cover' => $cover_url,
    'artist' => get_post_meta($album->ID, 'artist', true),
    'genres' => $genres,
    'released' => get_post_meta($album->ID, 'released', true),
    'country' => $country,
    'label' => get_post_meta($album->ID, 'label', true),
    'links' => json_decode(get_post_meta($album->ID, 'links', true)),
    'tracklist' => json_decode(get_post_meta($album->ID, 'tracklist', true))
  ];

  return rest_ensure_response($response);
}

add_action('rest_api_init', function () {
  register_rest_route('api', '/album/(?P<slug>[a-zA-Z0-9-_]+)', [
    'methods' => WP_REST_Server::READABLE,
    'callback' => 'api_album_get_by_slug',
  ]);
});