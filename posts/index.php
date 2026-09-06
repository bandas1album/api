<?php

register_post_type('album', [
  'label' => 'Álbuns',
  'public' => true,
  'show_in_rest' => false,
  'supports' => ['title', 'editor', 'thumbnail', 'custom-fields', 'author', 'revisions'],
  'menu_icon' => 'dashicons-album',
  'has_archive' => true,
  'rewrite' => ['slug' => 'albums'],
]);

register_post_type('person', [
  'label' => 'Pessoas',
  'labels' => [
    'name' => 'Pessoas',
    'singular_name' => 'Pessoa',
    'add_new_item' => 'Adicionar pessoa',
    'edit_item' => 'Editar pessoa',
    'search_items' => 'Buscar pessoas',
    'not_found' => 'Nenhuma pessoa encontrada',
    'featured_image' => 'Foto',
    'set_featured_image' => 'Definir foto',
    'remove_featured_image' => 'Remover foto',
    'use_featured_image' => 'Usar como foto',
  ],
  'public' => true,
  'show_in_rest' => false,
  'supports' => ['title', 'thumbnail'],
  'menu_icon' => 'dashicons-groups',
  'has_archive' => false,
  'rewrite' => ['slug' => 'person'],
]);

register_taxonomy('genre', 'album', [
  'label' => 'Gênero',
  'public' => true,
  'hierarchical' => false,
  'rewrite' => ['slug' => 'genero'],
  'show_in_rest' => false,
]);

register_taxonomy('country', 'album', [
  'label' => 'País',
  'public' => true,
  'hierarchical' => false,
  'rewrite' => ['slug' => 'pais'],
  'show_in_rest' => false,
]);
