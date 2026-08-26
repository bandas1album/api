<?php

function api_get_yoast_meta_description($object_id, $type = 'post') {
  if ($type === 'post') {
    return get_post_meta($object_id, '_yoast_wpseo_metadesc', true) ?: '';
  }

  if ($type === 'term') {
    return get_term_meta($object_id, 'wpseo_desc', true) ?: '';
  }

  return '';
}

function api_get_home_meta() {
  $content = '';
  $description = '';

  if (get_option('show_on_front') === 'page') {
    $page_id = (int) get_option('page_on_front');

    if ($page_id) {
      $page = get_post($page_id);

      if ($page) {
        $content = $page->post_content;
        $description = api_get_yoast_meta_description($page_id, 'post');
      }
    }
  } else {
    $yoast_titles = get_option('wpseo_titles');

    if (is_array($yoast_titles) && !empty($yoast_titles['metadesc-home-wpseo'])) {
      $description = $yoast_titles['metadesc-home-wpseo'];
    }
  }

  return [
    'content' => $content,
    'description' => $description,
  ];
}
