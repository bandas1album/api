<?php

function api_album_get_all($request) {
  $page = $request['page'] ?? 1;
  $per_page = $request['per_page'] ?? 10;
  $order_by = $request['order_by'] ?? 'date';
  $order = $request['order'] ?? 'DESC';
  $category = $request['category'] ?? '';
  $slug = $request['slug'] ?? '';

  $args = [
    'post_type' => 'album',
    'posts_per_page' => $per_page,
    'paged' => $page,
    'orderby' => $order_by,
    'order' => $order
  ];

  $response = [
    'data' => [],
    'meta' => []
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
        'terms' => $slug
      ]
    ];

    $term = get_term_by('slug', $slug, 'genre');
    if ($term && !is_wp_error($term)) {
      $response['meta']['context'] = [
        'type' => 'genre',
        'page' => 'Gênero',
        'title' => $term->name,
        'slug' => $term->slug,
        'description' => api_get_genre_meta_description($term->name),
      ];
    }
  }

  if ($category === 'country') {
    $args['tax_query'] = [
      [
        'taxonomy' => 'country',
        'field' => 'slug',
        'terms' => $slug
      ]
    ];

    $term = get_term_by('slug', $slug, 'country');
    if ($term && !is_wp_error($term)) {
      $response['meta']['context'] = [
        'type' => 'country',
        'page' => 'País de lançamento',
        'title' => $term->name,
        'slug' => $term->slug,
        'description' => api_get_country_meta_description($term->name),
      ];
    }
  }

  if ($category === 'year') {
    $args['meta_query'][] = [
      'key' => 'released',
      'value' => $slug,
      'compare' => 'LIKE'
    ];

    $response['meta']['context'] = [
      'type' => 'year',
      'page' => 'Ano de lançamento',
      'title' => $slug,
      'slug' => $slug,
      'description' => api_get_year_meta_description($slug),
    ];
  }

  $query = new WP_Query($args);

  $response['meta']['pagination'] = [
    'page' => (int) $page,
    'per_page' => (int) $per_page,
    'total_pages' => (int) $query->max_num_pages,
    'total_items' => (int) $query->found_posts,
  ];

  while ($query->have_posts()) {
    $query->the_post();
    $post_id = get_the_ID();
    $cover = get_post_meta($post_id, 'cover', true);
    $cover_url = $cover ? wp_get_attachment_image_src($cover, 'thumbnail')[0] : null;
    
    $response['data'][] = [
      'title' => html_entity_decode(get_the_title()),
      'artist' => get_post_meta($post_id, 'artist', true),
      'slug' => get_post_field('post_name', $post_id),
      'cover' => $cover_url,
    ];
  }
  wp_reset_postdata();

  return rest_ensure_response($response);
}

add_action('rest_api_init', function () {
  register_rest_route('api', '/albums', [
    'methods' => WP_REST_Server::READABLE,
    'callback' => 'api_album_get_all',
  ]);
});