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
