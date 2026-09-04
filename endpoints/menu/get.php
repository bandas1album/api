<?php

function api_get_menu($request) {
  $type = sanitize_key($request['type'] ?? '');
  $pagination = api_sanitize_pagination($request);
  $page = $pagination['page'];
  $per_page = $pagination['per_page'];

  $allowed_types = ['album', 'genre', 'country', 'released'];
  if (!in_array($type, $allowed_types, true)) {
    return new WP_Error('error', 'Tipo de menu inválido.', ['status' => 400]);
  }

  $response = [
    'data' => [],
    'meta' => [],
  ];

    if ($type === 'album') {
    $query = new WP_Query([
      'post_type' => 'album',
      'post_status' => 'publish',
      'posts_per_page' => $per_page,
      'orderby' => 'title',
      'order' => 'ASC',
      'paged' => $page,
    ]);

    $post_ids = wp_list_pluck($query->posts, 'ID');
    $covers = api_album_cover_urls($post_ids, 'thumbnail');

    foreach ($query->posts as $post) {
      $post_id = (int) $post->ID;
      $response['data'][] = [
        'title' => html_entity_decode(get_the_title($post_id)),
        'artist' => get_post_meta($post_id, 'artist', true),
        'slug' => $post->post_name,
        'cover' => $covers[$post_id] ?? null,
      ];
    }

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
      'offset' => ($page - 1) * $per_page,
    ]);

    if (!is_wp_error($args)) {
      foreach ($args as $genre) {
        $response['data'][] = [
          'title' => $genre->name,
          'slug' => $genre->slug,
          'count' => $genre->count,
        ];
      }
    }

    $total_terms = (int) wp_count_terms([
      'taxonomy' => 'genre',
      'hide_empty' => true,
    ]);

    $response['meta']['pagination'] = [
      'page' => (int) $page,
      'per_page' => (int) $per_page,
      'total_pages' => (int) ceil($total_terms / $per_page),
      'total_items' => $total_terms,
    ];
  }

  if ($type === 'country') {
    $args = get_terms([
      'taxonomy' => 'country',
      'orderby' => 'name',
      'hide_empty' => true,
      'number' => $per_page,
      'offset' => ($page - 1) * $per_page,
    ]);

    if (!is_wp_error($args)) {
      foreach ($args as $country) {
        $response['data'][] = [
          'title' => $country->name,
          'slug' => $country->slug,
          'count' => $country->count,
        ];
      }
    }

    $total_terms = (int) wp_count_terms([
      'taxonomy' => 'country',
      'hide_empty' => true,
    ]);

    $response['meta']['pagination'] = [
      'page' => (int) $page,
      'per_page' => (int) $per_page,
      'total_pages' => (int) ceil($total_terms / $per_page),
      'total_items' => $total_terms,
    ];
  }

  if ($type === 'released') {
    global $wpdb;

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
        'slug' => explode('-', (string) $row->year)[0],
        'count' => (int) $row->total,
      ];
    }

    $response['meta']['pagination'] = [
      'page' => (int) $page,
      'per_page' => (int) $per_page,
      'total_pages' => (int) ceil($total_items / max(1, $per_page)),
      'total_items' => (int) $total_items,
    ];
  }

  return rest_ensure_response($response);
}

add_action('rest_api_init', function () {
  register_rest_route('api', '/menu', [
    'methods' => WP_REST_Server::READABLE,
    'callback' => 'api_get_menu',
    'permission_callback' => 'api_permission_public',
  ]);
});
