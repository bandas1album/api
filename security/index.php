<?php

/**
 * Security helpers and WordPress hardening for the API theme.
 */

if (!defined('B1A_FRONTEND_URL')) {
  define('B1A_FRONTEND_URL', 'https://bandas1album.com.br');
}

/**
 * Allowed CORS origins for the public SPA.
 */
function api_allowed_origins() {
  $origins = [
    'https://bandas1album.com.br',
    'https://www.bandas1album.com.br',
  ];

  if (defined('WP_ENVIRONMENT_TYPE') && WP_ENVIRONMENT_TYPE === 'local') {
    $origins[] = 'http://localhost:3000';
    $origins[] = 'http://127.0.0.1:3000';
  }

  /**
   * @param string[] $origins
   */
  return apply_filters('api_allowed_origins', $origins);
}

/**
 * Simple IP + bucket rate limit using transients.
 *
 * @return true|WP_Error
 */
function api_rate_limit($bucket, $max = 5, $window = 900) {
  $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
  $key = 'api_rl_' . md5($bucket . '|' . $ip);
  $count = (int) get_transient($key);

  if ($count >= $max) {
    return new WP_Error(
      'rate_limited',
      'Muitas tentativas. Tente novamente mais tarde.',
      ['status' => 429]
    );
  }

  set_transient($key, $count + 1, $window);
  return true;
}

function api_permission_public() {
  return true;
}

function api_permission_logged_in() {
  return is_user_logged_in();
}

function api_permission_rate_limited_public($request) {
  $route = $request instanceof WP_REST_Request ? $request->get_route() : 'public';
  $limit = api_rate_limit('route_' . $route, 30, 60);

  if (is_wp_error($limit)) {
    return $limit;
  }

  return true;
}

function api_permission_auth_sensitive($request) {
  $route = $request instanceof WP_REST_Request ? $request->get_route() : 'auth';
  $limit = api_rate_limit('auth_' . $route, 5, 900);

  if (is_wp_error($limit)) {
    return $limit;
  }

  return true;
}

function api_sanitize_pagination($request, $default_per_page = 10, $max_per_page = 50) {
  return [
    'page' => max(1, absint($request['page'] ?? 1)),
    'per_page' => min($max_per_page, max(1, absint($request['per_page'] ?? $default_per_page))),
  ];
}

function api_sanitize_orderby($value, $allowed = ['date', 'title', 'modified']) {
  $value = sanitize_key((string) $value);
  return in_array($value, $allowed, true) ? $value : 'date';
}

function api_sanitize_order($value) {
  return strtoupper((string) $value) === 'ASC' ? 'ASC' : 'DESC';
}

// --- Hardening ---

add_filter('xmlrpc_enabled', '__return_false');

add_filter('wp_headers', function ($headers) {
  unset($headers['X-Pingback']);
  return $headers;
});

remove_action('wp_head', 'wp_generator');
remove_action('wp_head', 'rsd_link');
remove_action('wp_head', 'wlwmanifest_link');

add_filter('rest_endpoints', function ($endpoints) {
  foreach (array_keys($endpoints) as $route) {
    if (preg_match('#^/wp/v2/users#', $route)) {
      unset($endpoints[$route]);
    }
  }
  return $endpoints;
});

add_action('rest_api_init', function () {
  remove_filter('rest_pre_serve_request', 'rest_send_cors_headers');

  add_filter('rest_pre_serve_request', function ($value) {
    $origin = get_http_origin();
    $allowed = api_allowed_origins();

    if ($origin && in_array($origin, $allowed, true)) {
      header('Access-Control-Allow-Origin: ' . $origin);
      header('Access-Control-Allow-Credentials: true');
      header('Access-Control-Allow-Methods: OPTIONS, GET, POST, PUT, PATCH, DELETE');
      header('Access-Control-Allow-Headers: Authorization, Content-Type, X-WP-Nonce');
      header('Vary: Origin', false);
    }

    return $value;
  });
}, 15);

add_filter('upload_mimes', function ($mimes) {
  if (defined('REST_REQUEST') && REST_REQUEST) {
    return [
      'jpg|jpeg|jpe' => 'image/jpeg',
      'png' => 'image/png',
      'webp' => 'image/webp',
      'gif' => 'image/gif',
    ];
  }
  return $mimes;
});

/**
 * Rate-limit JWT login (plugin route is otherwise unrestricted).
 */
add_filter('rest_pre_dispatch', function ($result, $server, $request) {
  if ($result !== null) {
    return $result;
  }

  if (
    $request->get_method() === 'POST' &&
    $request->get_route() === '/jwt-auth/v1/token'
  ) {
    $limit = api_rate_limit('jwt_token', 5, 900);
    if (is_wp_error($limit)) {
      return $limit;
    }
  }

  return $result;
}, 10, 3);
