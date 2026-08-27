<?php

/**
 * Playlist fields (YouTube + Spotify) on genre and country terms.
 */

function api_taxonomy_playlist_keys() {
  return ['playlist_youtube', 'playlist_spotify'];
}

function api_taxonomy_playlist_add_fields() {
  wp_nonce_field('api_taxonomy_playlist_save', 'api_taxonomy_playlist_nonce');
  ?>
  <div class="form-field">
    <label for="playlist_youtube">Playlist YouTube</label>
    <input type="url" name="playlist_youtube" id="playlist_youtube" value="" class="regular-text" placeholder="https://www.youtube.com/playlist?list=...">
    <p class="description">URL da playlist do YouTube para esta taxonomia.</p>
  </div>
  <div class="form-field">
    <label for="playlist_spotify">Playlist Spotify</label>
    <input type="url" name="playlist_spotify" id="playlist_spotify" value="" class="regular-text" placeholder="https://open.spotify.com/playlist/...">
    <p class="description">URL da playlist do Spotify para esta taxonomia.</p>
  </div>
  <?php
}

function api_taxonomy_playlist_edit_fields($term) {
  $youtube = get_term_meta($term->term_id, 'playlist_youtube', true);
  $spotify = get_term_meta($term->term_id, 'playlist_spotify', true);
  wp_nonce_field('api_taxonomy_playlist_save', 'api_taxonomy_playlist_nonce');
  ?>
  <tr class="form-field">
    <th scope="row"><label for="playlist_youtube">Playlist YouTube</label></th>
    <td>
      <input type="url" name="playlist_youtube" id="playlist_youtube" value="<?php echo esc_attr($youtube); ?>" class="regular-text" placeholder="https://www.youtube.com/playlist?list=...">
      <p class="description">URL da playlist do YouTube para esta taxonomia.</p>
    </td>
  </tr>
  <tr class="form-field">
    <th scope="row"><label for="playlist_spotify">Playlist Spotify</label></th>
    <td>
      <input type="url" name="playlist_spotify" id="playlist_spotify" value="<?php echo esc_attr($spotify); ?>" class="regular-text" placeholder="https://open.spotify.com/playlist/...">
      <p class="description">URL da playlist do Spotify para esta taxonomia.</p>
    </td>
  </tr>
  <?php
}

function api_taxonomy_playlist_save($term_id) {
  if (
    !isset($_POST['api_taxonomy_playlist_nonce']) ||
    !wp_verify_nonce($_POST['api_taxonomy_playlist_nonce'], 'api_taxonomy_playlist_save')
  ) {
    return;
  }

  if (!current_user_can('edit_term', $term_id)) {
    return;
  }

  foreach (api_taxonomy_playlist_keys() as $key) {
    $raw = isset($_POST[$key]) ? wp_unslash($_POST[$key]) : '';
    $url = is_string($raw) ? esc_url_raw(trim($raw)) : '';

    if ($url === '') {
      delete_term_meta($term_id, $key);
    } else {
      update_term_meta($term_id, $key, $url);
    }
  }
}

foreach (['genre', 'country'] as $taxonomy) {
  add_action("{$taxonomy}_add_form_fields", 'api_taxonomy_playlist_add_fields');
  add_action("{$taxonomy}_edit_form_fields", 'api_taxonomy_playlist_edit_fields');
  add_action("created_{$taxonomy}", 'api_taxonomy_playlist_save');
  add_action("edited_{$taxonomy}", 'api_taxonomy_playlist_save');
}
