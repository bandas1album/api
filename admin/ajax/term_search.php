<?php

/**
 * Autocomplete AJAX de termos (taxonomias genre / country).
 */

add_action('wp_ajax_bandas_search_terms', function () {
  if (!current_user_can('edit_posts')) {
    wp_send_json_error(['message' => 'Forbidden'], 403);
  }

  check_ajax_referer('bandas_search_terms', 'nonce');

  $taxonomy = sanitize_key(wp_unslash($_GET['taxonomy'] ?? ''));
  $allowed = ['genre', 'country'];
  if (!in_array($taxonomy, $allowed, true)) {
    wp_send_json_error(['message' => 'Taxonomia inválida'], 400);
  }

  $term = trim(sanitize_text_field(wp_unslash($_GET['q'] ?? '')));
  if (strlen($term) < 1) {
    wp_send_json_success([]);
  }

  $terms = get_terms([
    'taxonomy' => $taxonomy,
    'hide_empty' => false,
    'number' => 12,
    'name__like' => $term,
    'orderby' => 'name',
    'order' => 'ASC',
  ]);

  if (is_wp_error($terms)) {
    wp_send_json_success([]);
  }

  $results = [];
  foreach ($terms as $t) {
    $results[] = [
      'name' => $t->name,
      'slug' => $t->slug,
      'term_id' => (int) $t->term_id,
    ];
  }

  wp_send_json_success($results);
});
