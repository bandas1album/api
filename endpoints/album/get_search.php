<?php

function api_album_get_search($request) {
  $q = $request['q'] ?? '';

  $response = [
    'data' => []
  ];

  $args = [
    'post_type' => 'album',
    'posts_per_page' => 5,
    's' => $q,
    'no_found_rows' => true,
  ];

  $genres = get_terms([
    'taxonomy' => 'genre',
    'name__like' => $q,
    'hide_empty' => true,
    'number' => 5
  ]);

  $countries = get_terms([
    'taxonomy' => 'country',
    'name__like' => $q,
    'hide_empty' => true,
    'number' => 5
  ]);

  $albums = new WP_Query($args);

  while ($albums->have_posts()) {
    $albums->the_post();
    $post_id = get_the_ID();
    
    $response['data']['albums'][] = [
      'title' => html_entity_decode(get_the_title()),
      'artist' => get_post_meta($post_id, 'artist', true),
      'slug' => get_post_field('post_name', $post_id),
    ];

  }
  wp_reset_postdata();

  foreach ($genres as $genre) {
    $response['data']['genres'][] = [
      'title' => $genre->name,
      'slug' => $genre->slug
    ];
  }

  foreach ($countries as $country) {
    $response['data']['countries'][] = [
      'title' => $country->name,
      'slug' => $country->slug
    ];
  }

  return rest_ensure_response($response);
}

add_action('rest_api_init', function () {
  register_rest_route('api', '/albums/search', [
    'methods' => WP_REST_Server::READABLE,
    'callback' => 'api_album_get_search',
  ]);
});