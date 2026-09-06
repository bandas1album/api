<?php

function api_album_get_all($request) {
  $pagination = api_sanitize_pagination($request);
  $page = $pagination['page'];
  $per_page = $pagination['per_page'];
  $order_by = api_sanitize_orderby($request['order_by'] ?? 'date');
  $order = api_sanitize_order($request['order'] ?? 'DESC');
  $category = sanitize_key($request['category'] ?? '');
  $slug = sanitize_title($request['slug'] ?? '');

  $args = [
    'post_type' => 'album',
    'post_status' => 'publish',
    'posts_per_page' => $per_page,
    'paged' => $page,
    'orderby' => $order_by,
    'order' => $order,
  ];

  $response = [
    'data' => [],
    'meta' => [],
  ];

  if ($category === '') {
    $home_meta = api_get_home_meta();
    $response['meta']['content'] = $home_meta['content'];
    $response['meta']['description'] = $home_meta['description'];
  }

  if ($category === 'genre') {
    $args['tax_query'] = [
      [
        'taxonomy' => 'genre',
        'field' => 'slug',
        'terms' => $slug,
      ],
    ];

    $term = get_term_by('slug', $slug, 'genre');
    if ($term && !is_wp_error($term)) {
      $response['meta']['context'] = [
        'type' => 'genre',
        'page' => 'Gênero',
        'title' => $term->name,
        'slug' => $term->slug,
        'description' => api_get_genre_meta_description($term->name),
        'playlists' => api_get_term_playlists($term->term_id),
      ];
    }
  }

  if ($category === 'country') {
    $args['tax_query'] = [
      [
        'taxonomy' => 'country',
        'field' => 'slug',
        'terms' => $slug,
      ],
    ];

    $term = get_term_by('slug', $slug, 'country');
    if ($term && !is_wp_error($term)) {
      $response['meta']['context'] = [
        'type' => 'country',
        'page' => 'País de lançamento',
        'title' => $term->name,
        'slug' => $term->slug,
        'description' => api_get_country_meta_description($term->name),
        'playlists' => api_get_term_playlists($term->term_id),
      ];
    }
  }

  if ($category === 'year') {
    $year = absint($slug);
    if ($year < 1900 || $year > 2100) {
      return new WP_Error('error', 'Ano inválido.', ['status' => 400]);
    }

    $args['meta_query'][] = [
      'key' => 'released_year',
      'value' => $year,
      'compare' => '=',
      'type' => 'NUMERIC',
    ];

    $response['meta']['context'] = [
      'type' => 'year',
      'page' => 'Ano de lançamento',
      'title' => (string) $year,
      'slug' => (string) $year,
      'description' => api_get_year_meta_description((string) $year),
    ];
  }

  $query = new WP_Query($args);

  $response['meta']['pagination'] = [
    'page' => (int) $page,
    'per_page' => (int) $per_page,
    'total_pages' => (int) $query->max_num_pages,
    'total_items' => (int) $query->found_posts,
  ];

  $post_ids = wp_list_pluck($query->posts, 'ID');
  $covers = api_album_cover_urls($post_ids, 'thumbnail');

  foreach ($query->posts as $post) {
    $post_id = (int) $post->ID;
    $released = get_post_meta($post_id, 'released', true);
    $response['data'][] = [
      'title' => html_entity_decode(get_the_title($post_id)),
      'artist' => get_post_meta($post_id, 'artist', true),
      'slug' => $post->post_name,
      'cover' => $covers[$post_id] ?? null,
      'released' => $released ? (string) $released : null,
      'modified' => get_post_modified_time('c', true, $post),
    ];
  }

  return rest_ensure_response($response);
}

add_action('rest_api_init', function () {
  register_rest_route('api', '/albums', [
    'methods' => WP_REST_Server::READABLE,
    'callback' => 'api_album_get_all',
    'permission_callback' => 'api_permission_public',
  ]);
});
