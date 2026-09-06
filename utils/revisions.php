<?php

/**
 * Revisões do CPT album (editor clássico): histórico + restaurar título/conteúdo/meta.
 */

add_filter('_wp_post_revision_meta_keys', function ($keys) {
  $album_keys = [
    'artist',
    'cover',
    'label',
    'released',
    'released_year',
    'links',
    'tracklist',
    'credits',
  ];

  return array_values(array_unique(array_merge($keys, $album_keys)));
});

/** Mantém histórico amplo de versões dos álbuns. */
add_filter('wp_revisions_to_keep', function ($num, $post) {
  if ($post instanceof WP_Post && $post->post_type === 'album') {
    return -1;
  }
  return $num;
}, 10, 2);

/**
 * Garante que o metabox "Revisões" não fique oculto nas opções de tela.
 *
 * @param string[] $hidden
 * @param WP_Screen $screen
 * @return string[]
 */
function bandas_album_force_revisions_metabox_visible($hidden, $screen) {
  if (!$screen || $screen->id !== 'album') {
    return $hidden;
  }

  if (!is_array($hidden)) {
    return $hidden;
  }

  return array_values(array_diff($hidden, ['revisionsdiv']));
}

add_filter('hidden_meta_boxes', 'bandas_album_force_revisions_metabox_visible', 10, 2);
add_filter('default_hidden_meta_boxes', 'bandas_album_force_revisions_metabox_visible', 10, 2);

/** Posição estável do metabox de revisões na coluna lateral. */
add_action('add_meta_boxes_album', function () {
  remove_meta_box('revisionsdiv', 'album', 'normal');
  add_meta_box(
    'revisionsdiv',
    __('Revisions'),
    'post_revisions_meta_box',
    'album',
    'side',
    'high'
  );
}, 20);

/**
 * Após restaurar uma revisão, re-sincroniza released_year, copia metas de créditos/tracklist
 * e dispara revalidate do front.
 */
add_action('wp_restore_post_revision', function ($post_id, $revision_id) {
  if (get_post_type($post_id) !== 'album') {
    return;
  }

  // Cópia explícita — em alguns casos o core não restaura meta JSON grande.
  foreach (['tracklist', 'credits', 'links', 'artist', 'label', 'released', 'cover'] as $key) {
    $value = get_metadata('post', $revision_id, $key, true);
    if ($value === '' || $value === false || $value === null) {
      continue;
    }
    update_post_meta($post_id, $key, $value);
  }

  if (function_exists('api_sync_released_year')) {
    api_sync_released_year($post_id);
  }

  if (function_exists('api_sync_credit_person_ids')) {
    api_sync_credit_person_ids($post_id);
  }

  if (function_exists('bandas_request_frontend_revalidate')) {
    bandas_request_frontend_revalidate($post_id);
  }
}, 20, 2);
