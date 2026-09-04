<?php

/**
 * Resolve cover URLs for many albums without per-row attachment queries.
 *
 * @param int[] $post_ids
 * @return array<int, string|null> map post_id => url
 */
function api_album_cover_urls(array $post_ids, $size = 'thumbnail') {
  $post_ids = array_values(array_filter(array_map('absint', $post_ids)));
  if (!$post_ids) {
    return [];
  }

  update_meta_cache('post', $post_ids);

  $attachment_by_post = [];
  $attachment_ids = [];

  foreach ($post_ids as $post_id) {
    $cover = (int) get_post_meta($post_id, 'cover', true);
    if ($cover > 0) {
      $attachment_by_post[$post_id] = $cover;
      $attachment_ids[] = $cover;
    }
  }

  if ($attachment_ids) {
    _prime_post_caches(array_values(array_unique($attachment_ids)), false, true);
  }

  $urls = [];
  foreach ($post_ids as $post_id) {
    $attachment_id = $attachment_by_post[$post_id] ?? 0;
    if (!$attachment_id) {
      $urls[$post_id] = null;
      continue;
    }
    $src = wp_get_attachment_image_src($attachment_id, $size);
    $urls[$post_id] = is_array($src) ? $src[0] : null;
  }

  return $urls;
}

/**
 * Keep numeric released_year in sync with released datetime meta.
 */
function api_sync_released_year($post_id) {
  $post_id = (int) $post_id;
  $released = get_post_meta($post_id, 'released', true);

  if (!$released) {
    delete_post_meta($post_id, 'released_year');
    return;
  }

  $ts = strtotime((string) $released);
  if (!$ts) {
    delete_post_meta($post_id, 'released_year');
    return;
  }

  update_post_meta($post_id, 'released_year', (int) gmdate('Y', $ts));
}

/**
 * One-shot backfill of released_year for existing albums.
 */
function api_maybe_backfill_released_years() {
  if (get_option('api_released_year_backfilled')) {
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
    api_sync_released_year((int) $id);
  }

  update_option('api_released_year_backfilled', 1, true);
}

add_action('init', 'api_maybe_backfill_released_years', 30);
