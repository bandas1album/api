<?php

function api_album_get_by_slug($request) {
  $slug = sanitize_title($request['slug'] ?? '');

  if ($slug === '') {
    return new WP_Error('error', 'Álbum não encontrado', ['status' => 404]);
  }

  $album = get_page_by_path($slug, OBJECT, 'album');

  if (!$album || $album->post_status !== 'publish') {
    return new WP_Error('error', 'Álbum não encontrado', ['status' => 404]);
  }

  $cover = get_post_meta($album->ID, 'cover', true);
  $cover_src = $cover ? wp_get_attachment_image_src($cover, 'large') : null;
  $cover_url = is_array($cover_src) ? $cover_src[0] : null;

  $genres = [];
  $genre_terms = get_the_terms($album->ID, 'genre');
  if (is_array($genre_terms)) {
    foreach ($genre_terms as $genre) {
      $genres[] = [
        'title' => $genre->name,
        'slug' => $genre->slug,
      ];
    }
  }

  $country = null;
  $country_terms = get_the_terms($album->ID, 'country');
  if (is_array($country_terms) && !empty($country_terms)) {
    $first = $country_terms[0];
    $country = [
      'title' => $first->name,
      'slug' => $first->slug,
    ];
  }

  $author = get_userdata($album->post_author);

  $response = [
    'id' => $album->ID,
    'author' => $author ? $author->display_name : '',
    'title' => $album->post_title,
    'description' => $album->post_content,
    'meta_description' => api_get_album_meta_description($album->ID),
    'cover' => $cover_url,
    'artist' => get_post_meta($album->ID, 'artist', true),
    'genres' => $genres,
    'released' => get_post_meta($album->ID, 'released', true),
    'country' => $country,
    'label' => get_post_meta($album->ID, 'label', true),
    'links' => json_decode(get_post_meta($album->ID, 'links', true)),
    'tracklist' => json_decode(get_post_meta($album->ID, 'tracklist', true)),
  ];

  return rest_ensure_response($response);
}

add_action('rest_api_init', function () {
  register_rest_route('api', '/album/(?P<slug>[a-zA-Z0-9-_]+)', [
    'methods' => WP_REST_Server::READABLE,
    'callback' => 'api_album_get_by_slug',
    'permission_callback' => 'api_permission_public',
  ]);
});
