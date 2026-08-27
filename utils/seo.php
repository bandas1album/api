<?php

function api_join_pt(array $items) {
  $items = array_values(array_filter(array_map('trim', $items)));
  $count = count($items);

  if ($count === 0) {
    return '';
  }

  if ($count === 1) {
    return $items[0];
  }

  if ($count === 2) {
    return $items[0] . ' e ' . $items[1];
  }

  $last = array_pop($items);
  return implode(', ', $items) . ' e ' . $last;
}

function api_get_album_meta_description($album_id) {
  $title = html_entity_decode(get_the_title($album_id));
  $artist = get_post_meta($album_id, 'artist', true) ?: '';
  $released = get_post_meta($album_id, 'released', true);
  $year = $released ? date('Y', strtotime($released)) : '';

  $genres = [];
  $genre_terms = get_the_terms($album_id, 'genre');

  if ($genre_terms && !is_wp_error($genre_terms)) {
    foreach ($genre_terms as $term) {
      $genres[] = $term->name;
    }
  }

  $genres_str = api_join_pt($genres);

  return sprintf(
    '%s — único álbum de %s, lançado em %s. Conheça essa pérola de %s no Bandas 1 Álbum.',
    $title,
    $artist,
    $year,
    $genres_str
  );
}

/**
 * YouTube / Spotify playlist URLs stored on genre or country terms.
 *
 * @return array{youtube: ?string, spotify: ?string}
 */
function api_get_term_playlists($term_id) {
  $youtube = get_term_meta($term_id, 'playlist_youtube', true);
  $spotify = get_term_meta($term_id, 'playlist_spotify', true);

  return [
    'youtube' => $youtube ? (string) $youtube : null,
    'spotify' => $spotify ? (string) $spotify : null,
  ];
}

function api_get_genre_meta_description($genre_name) {
  return sprintf(
    'Descubra álbuns de %s de bandas e artistas que lançaram apenas um álbum na carreira. Conheça essas pérolas no Bandas 1 Álbum.',
    $genre_name
  );
}

function api_get_year_meta_description($year) {
  return sprintf(
    'Descubra álbuns lançados em %s por bandas e artistas que deixaram apenas um álbum na carreira. Conheça essas pérolas no Bandas 1 Álbum.',
    $year
  );
}

function api_get_country_meta_description($country_name) {
  return sprintf(
    'Descubra álbuns lançados em %s por bandas e artistas que deixaram apenas um álbum na carreira. Conheça essas pérolas no Bandas 1 Álbum.',
    $country_name
  );
}

function api_get_home_meta() {
  $content = '';

  if (get_option('show_on_front') === 'page') {
    $page_id = (int) get_option('page_on_front');

    if ($page_id) {
      $page = get_post($page_id);

      if ($page) {
        $content = $page->post_content;
      }
    }
  }

  return [
    'content' => $content,
    'description' => '',
  ];
}
