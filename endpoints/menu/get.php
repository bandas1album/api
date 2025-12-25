<?php

function api_get_menu($request) {
  $type = $request['type'] ?? '';
  $page = $request['page'] ?? 1;
  $per_page = $request['per_page'] ?? 10;

  $response = [
    'data' => [],
    'meta' => []
  ];

  if ($type === 'album') {
    $query = new WP_Query([
      'post_type' => 'album',
      'posts_per_page' => $per_page,
      'orderby' => 'title',
      'order' => 'ASC',
      'paged' => $page
    ]);

    while ($query->have_posts()) {
      $query->the_post();
      $post_id = get_the_ID();

      $cover = get_post_meta($post_id, 'cover', true);
      $cover_url = $cover ? wp_get_attachment_image_src($cover, 'large')[0] : null;
      
      $response['data'][] = [
        'title' => html_entity_decode(get_the_title()),
        'artist' => get_post_meta($post_id, 'artist', true),
        'slug' => get_post_field('post_name', $post_id),
        'cover' => $cover_url
      ];
    }
    wp_reset_postdata();
    
    $response['meta']['pagination'] = [
      'page' => (int) $page,
      'per_page' => (int) $per_page,
      'total_pages' => (int) $query->max_num_pages,
      'total_items' => (int) $query->found_posts,
    ];
  }


  if ($type === 'genre') {
    $args = get_terms([
      'taxonomy' => 'genre',
      'orderby' => 'name',
      'hide_empty' => true,
      'number' => $per_page,
      'offset' => ($page - 1) * $per_page
    ]);

    foreach ($args as $genre) {
      $response['data'][] = [
        'title' => $genre->name,
        'slug' => $genre->slug,
        'count' => $genre->count
      ];
    }

    $total_terms = wp_count_terms([
      'taxonomy'   => 'genre',
      'hide_empty' => true,
    ]);

    $response['meta']['pagination'] = [
      'page' => (int) $page,
      'per_page' => (int) $per_page,
      'total_pages' => (int) ceil($total_terms / $per_page),
      'total_items' => (int) $total_terms,
    ];
  }

  if ($type === 'country') {
    $args = get_terms([
      'taxonomy' => 'country',
      'orderby' => 'name',
      'hide_empty' => true,
      'number' => $per_page,
      'offset' => ($page - 1) * $per_page
    ]);

    foreach ($args as $country) {
      $response['data'][] = [
        'title' => $country->name,
        'slug' => $country->slug,
        'count' => $country->count
      ];
    }

    $total_terms = wp_count_terms([
      'taxonomy'   => 'country',
      'hide_empty' => true,
    ]);

    $response['meta']['pagination'] = [
      'page' => (int) $page,
      'per_page' => (int) $per_page,
      'total_pages' => (int) ceil($total_terms / $per_page),
      'total_items' => (int) $total_terms,
    ];
  }

  if ($type === 'released') {
    global $wpdb;

    $page = max(1, (int) $page);
    $per_page = (int) $per_page;
    $offset = ($page - 1) * $per_page;

    $total_items = (int) $wpdb->get_var("
      SELECT COUNT(DISTINCT YEAR(meta_value))
      FROM {$wpdb->postmeta}
      WHERE meta_key = 'released'
    ");

    $years = $wpdb->get_results($wpdb->prepare("
      SELECT 
        YEAR(pm.meta_value) AS year,
        COUNT(DISTINCT p.ID) AS total
      FROM {$wpdb->postmeta} pm
      INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
      WHERE pm.meta_key = 'released'
        AND p.post_type = 'album'
        AND p.post_status = 'publish'
      GROUP BY YEAR(pm.meta_value)
      ORDER BY year ASC
      LIMIT %d OFFSET %d
    ", $per_page, $offset));

    foreach ($years as $row) {
      $response['data'][] = [
        'title' => $row->year,
        'slug' => explode('-', $row->year)[0],
        'count' => (int) $row->total
      ];
    }

    $response['meta']['pagination'] = [
      'page' => (int) $page,
      'per_page' => (int) $per_page,
      'total_pages' => (int) ceil($total_items / $per_page),
      'total_items' => (int) $total_items,
    ];
  }

  return rest_ensure_response($response);
}

add_action('rest_api_init', function () {
  register_rest_route('api', '/menu', [
    'methods' => WP_REST_Server::READABLE,
    'callback' => 'api_get_menu',
  ]);
});