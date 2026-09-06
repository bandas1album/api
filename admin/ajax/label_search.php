<?php

/**
 * Autocomplete AJAX de gravadoras (meta `label` já usadas em álbuns).
 */

add_action('wp_ajax_bandas_search_labels', function () {
  if (!current_user_can('edit_posts')) {
    wp_send_json_error(['message' => 'Forbidden'], 403);
  }

  check_ajax_referer('bandas_search_labels', 'nonce');

  $term = trim(sanitize_text_field(wp_unslash($_GET['q'] ?? '')));
  if (strlen($term) < 1) {
    wp_send_json_success([]);
  }

  global $wpdb;

  $like = '%' . $wpdb->esc_like($term) . '%';
  $rows = $wpdb->get_col(
    $wpdb->prepare(
      "SELECT DISTINCT meta_value
       FROM {$wpdb->postmeta} pm
       INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
       WHERE pm.meta_key = 'label'
         AND pm.meta_value <> ''
         AND p.post_type = 'album'
         AND p.post_status IN ('publish', 'draft', 'pending', 'future', 'private')
         AND pm.meta_value LIKE %s
       ORDER BY pm.meta_value ASC
       LIMIT 12",
      $like
    )
  );

  $results = [];
  $seen = [];
  foreach ($rows as $value) {
    $value = trim((string) $value);
    if ($value === '') {
      continue;
    }
    $key = strtolower($value);
    if (isset($seen[$key])) {
      continue;
    }
    $seen[$key] = true;
    $results[] = ['name' => $value];
  }

  wp_send_json_success($results);
});
