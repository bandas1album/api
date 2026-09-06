<?php

/**
 * Autocomplete AJAX de pessoas (CPT person) para o metabox de créditos.
 */

add_action('wp_ajax_bandas_search_persons', function () {
  if (!current_user_can('edit_posts')) {
    wp_send_json_error(['message' => 'Forbidden'], 403);
  }

  check_ajax_referer('bandas_search_persons', 'nonce');

  $term = trim(sanitize_text_field(wp_unslash($_GET['q'] ?? '')));
  if (strlen($term) < 1) {
    wp_send_json_success([]);
  }

  $query = new WP_Query([
    'post_type' => 'person',
    'post_status' => 'publish',
    'posts_per_page' => 12,
    'orderby' => 'title',
    'order' => 'ASC',
    's' => $term,
    'no_found_rows' => true,
  ]);

  $results = [];
  foreach ($query->posts as $post) {
    $results[] = [
      'person_id' => (int) $post->ID,
      'name' => $post->post_title,
      'slug' => $post->post_name,
    ];
  }

  // Fallback: match exato case-insensitive se a busca WP não achar
  if (empty($results) && function_exists('api_find_person_by_name')) {
    $exact = api_find_person_by_name($term);
    if ($exact) {
      $results[] = [
        'person_id' => (int) $exact->ID,
        'name' => $exact->post_title,
        'slug' => $exact->post_name,
      ];
    }
  }

  wp_send_json_success($results);
});
