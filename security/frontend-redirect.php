<?php

/**
 * The public site lives on B1A_FRONTEND_URL. WordPress front-end URLs on the
 * API host must not be indexed as duplicates, so they 301 to the equivalent
 * front-end page (or its home).
 */

add_filter('wp_sitemaps_enabled', '__return_false');

add_filter('rest_post_dispatch', function ($response) {
  if ($response instanceof WP_HTTP_Response) {
    $response->header('X-Robots-Tag', 'noindex, nofollow');
  }
  return $response;
});

function api_frontend_url_for_request() {
  $base = rtrim(B1A_FRONTEND_URL, '/');

  if (is_singular('album')) {
    return $base . '/album/' . get_post_field('post_name', get_queried_object_id());
  }

  if (is_singular('person')) {
    return $base . '/person/' . get_post_field('post_name', get_queried_object_id());
  }

  if (is_tax('genre') || is_tax('country')) {
    $term = get_queried_object();
    if ($term instanceof WP_Term) {
      $segment = $term->taxonomy === 'genre' ? 'genre' : 'country';
      return $base . '/' . $segment . '/' . $term->slug;
    }
  }

  return $base . '/';
}

add_action('template_redirect', function () {
  if (is_robots() || (function_exists('is_favicon') && is_favicon())) {
    return;
  }

  if (is_preview() && is_user_logged_in()) {
    return;
  }

  wp_redirect(api_frontend_url_for_request(), 301, 'Bandas 1 Album');
  exit;
}, 0);
