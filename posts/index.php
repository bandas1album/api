<?php

register_post_type('album', [
  'label' => 'Álbuns',
  'public' => true,
  'show_in_rest' => true,
  'supports' => ['title', 'editor', 'thumbnail', 'custom-fields'],
  'menu_icon' => 'dashicons-album',
  'has_archive' => true,
  'rewrite' => ['slug' => 'albums'],
]);

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