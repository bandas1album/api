<?php

register_post_type('album', [
  'label' => 'Álbuns',
  'public' => true,
  'show_in_rest' => true,
  'supports' => ['title', 'editor', 'thumbnail', 'custom-fields', 'author'],
  'menu_icon' => 'dashicons-album',
  'has_archive' => true,
  'rewrite' => ['slug' => 'albums'],
]);

// Habilita a imagem destacada (thumbnail). Sem isso, mesmo o post type
// declarando suporte a 'thumbnail', a metabox não aparece no admin.
add_action('after_setup_theme', function() {
  add_theme_support('post-thumbnails');
});

// Adiciona colunas de capa e artista na listagem do post type "album".
add_filter('manage_album_posts_columns', function($columns) {
  $new = [];

  foreach ($columns as $key => $label) {
    if ($key === 'title') {
      $new['album_thumbnail'] = 'Capa';
    }

    $new[$key] = $label;

    if ($key === 'title') {
      $new['album_artist'] = 'Artista';
    }
  }

  return $new;
});

add_action('manage_album_posts_custom_column', function($column, $post_id) {
  if ($column === 'album_thumbnail') {
    echo has_post_thumbnail($post_id)
      ? get_the_post_thumbnail($post_id, [50, 50])
      : '—';
  }

  if ($column === 'album_artist') {
    $artist = get_post_meta($post_id, 'artist', true);
    echo $artist ? esc_html($artist) : '—';
  }
}, 10, 2);

register_taxonomy('genre', 'album', [
  'label'        => 'Gênero',
  'public'       => true,
  'hierarchical' => false,
  'rewrite'      => ['slug' => 'genero'],
  'show_in_rest' => true,
]);

register_taxonomy('country', 'album', [
  'label'        => 'País',
  'public'       => true,
  'hierarchical' => false,
  'rewrite'      => ['slug' => 'pais'],
  'show_in_rest' => true,
]);
