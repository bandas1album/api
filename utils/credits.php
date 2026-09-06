<?php

/**
 * Créditos do álbum (person + role + detail).
 */

function api_credit_roles() {
  return [
    'musician' => 'Músico',
    'producer' => 'Produtor',
    'engineer' => 'Engenheiro',
    'mixer' => 'Mixagem',
    'mastering' => 'Masterização',
    'composer' => 'Compositor',
    'other' => 'Outro',
  ];
}

function api_sanitize_credit_role($role) {
  $role = sanitize_key((string) $role);
  $roles = api_credit_roles();
  return isset($roles[$role]) ? $role : 'other';
}

/**
 * Busca person publicado pelo nome (case-insensitive).
 *
 * @return WP_Post|null
 */
function api_find_person_by_name($name) {
  global $wpdb;

  $name = trim((string) $name);
  if ($name === '') {
    return null;
  }

  $id = (int) $wpdb->get_var(
    $wpdb->prepare(
      "SELECT ID FROM {$wpdb->posts}
       WHERE post_type = 'person'
         AND post_status = 'publish'
         AND LOWER(post_title) = LOWER(%s)
       LIMIT 1",
      $name
    )
  );

  if ($id <= 0) {
    return null;
  }

  $post = get_post($id);
  return $post instanceof WP_Post ? $post : null;
}

/**
 * Cria ou reutiliza CPT person a partir do nome digitado.
 *
 * @return WP_Post|null
 */
function api_find_or_create_person($name) {
  $name = trim(sanitize_text_field((string) $name));
  if ($name === '') {
    return null;
  }

  $existing = api_find_person_by_name($name);
  if ($existing) {
    return $existing;
  }

  $post_id = wp_insert_post(
    [
      'post_type' => 'person',
      'post_title' => $name,
      'post_status' => 'publish',
      'post_content' => '',
    ],
    true
  );

  if (is_wp_error($post_id) || !$post_id) {
    return null;
  }

  $post = get_post($post_id);
  return $post instanceof WP_Post ? $post : null;
}

/**
 * Normaliza um crédito individual para o contrato JSON do front.
 *
 * @param array|mixed $credit
 * @return array|null
 */
function api_normalize_album_credit($credit) {
  if (!is_array($credit)) {
    return null;
  }

  $person_id = absint($credit['person_id'] ?? 0);
  $name = trim(sanitize_text_field((string) ($credit['name'] ?? '')));
  $slug = sanitize_title((string) ($credit['slug'] ?? ''));
  $role = api_sanitize_credit_role($credit['role'] ?? 'other');
  $detail = trim(sanitize_text_field((string) ($credit['detail'] ?? '')));

  if ($person_id > 0) {
    $person = get_post($person_id);
    if ($person instanceof WP_Post && $person->post_type === 'person') {
      $name = $person->post_title;
      $slug = $person->post_name;
    } else {
      $person_id = 0;
    }
  }

  if ($person_id <= 0 && $name !== '') {
    $person = api_find_or_create_person($name);
    if ($person) {
      $person_id = (int) $person->ID;
      $name = $person->post_title;
      $slug = $person->post_name;
    }
  }

  if ($person_id <= 0 || $name === '' || $slug === '') {
    return null;
  }

  return [
    'person_id' => $person_id,
    'name' => $name,
    'slug' => $slug,
    'role' => $role,
    'detail' => $detail,
  ];
}

/**
 * @param mixed $raw JSON string|array
 * @return array<int, array>
 */
function api_normalize_album_credits($raw) {
  if (is_string($raw)) {
    $decoded = json_decode($raw, true);
    $raw = is_array($decoded) ? $decoded : [];
  }

  if (!is_array($raw)) {
    return [];
  }

  $out = [];
  foreach ($raw as $item) {
    $normalized = api_normalize_album_credit($item);
    if ($normalized) {
      $out[] = $normalized;
    }
  }

  return $out;
}

/**
 * Monta créditos a partir dos arrays paralelos do metabox.
 *
 * @return array<int, array>
 */
function api_credits_from_post_arrays($person_ids, $names, $roles, $details) {
  if (!is_array($names)) {
    return [];
  }

  $person_ids = is_array($person_ids) ? $person_ids : [];
  $roles = is_array($roles) ? $roles : [];
  $details = is_array($details) ? $details : [];

  $raw = [];
  foreach ($names as $i => $name) {
    $raw[] = [
      'person_id' => $person_ids[$i] ?? 0,
      'name' => $name,
      'role' => $roles[$i] ?? 'other',
      'detail' => $details[$i] ?? '',
    ];
  }

  return api_normalize_album_credits($raw);
}

/**
 * Indexa person_id dos créditos em meta repetível para meta_query nas listagens.
 */
function api_sync_credit_person_ids($post_id) {
  $post_id = (int) $post_id;
  if ($post_id <= 0) {
    return;
  }

  $credits = api_normalize_album_credits(get_post_meta($post_id, 'credits', true));
  $ids = [];

  foreach ($credits as $credit) {
    $id = absint($credit['person_id'] ?? 0);
    if ($id > 0) {
      $ids[$id] = true;
    }
  }

  delete_post_meta($post_id, 'credit_person_id');

  foreach (array_keys($ids) as $person_id) {
    add_post_meta($post_id, 'credit_person_id', (int) $person_id, false);
  }
}

/**
 * One-shot backfill do índice credit_person_id.
 */
function api_maybe_backfill_credit_person_ids() {
  if (get_option('api_credit_person_id_backfilled')) {
    return;
  }

  $ids = get_posts([
    'post_type' => 'album',
    'post_status' => 'any',
    'posts_per_page' => -1,
    'fields' => 'ids',
    'no_found_rows' => true,
  ]);

  foreach ($ids as $id) {
    api_sync_credit_person_ids((int) $id);
  }

  update_option('api_credit_person_id_backfilled', 1, true);
}

add_action('init', 'api_maybe_backfill_credit_person_ids', 31);

/**
 * @return WP_Post|null
 */
function api_get_person_by_slug($slug) {
  $slug = sanitize_title((string) $slug);
  if ($slug === '') {
    return null;
  }

  $posts = get_posts([
    'name' => $slug,
    'post_type' => 'person',
    'post_status' => 'publish',
    'numberposts' => 1,
    'no_found_rows' => true,
  ]);

  if (empty($posts) || !($posts[0] instanceof WP_Post)) {
    return null;
  }

  return $posts[0];
}

/**
 * URL da foto (imagem destacada) da pessoa.
 *
 * @param int|WP_Post $person
 * @param string $size
 * @return string|null
 */
function api_get_person_photo_url($person, $size = 'medium') {
  $post_id = $person instanceof WP_Post ? (int) $person->ID : absint($person);
  if ($post_id <= 0 || !has_post_thumbnail($post_id)) {
    return null;
  }

  $src = wp_get_attachment_image_src(get_post_thumbnail_id($post_id), $size);
  return is_array($src) && !empty($src[0]) ? (string) $src[0] : null;
}
