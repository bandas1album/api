<?php

/**
 * Importar imagem na biblioteca de mídia a partir de uma URL
 * (mesmo fluxo da capa na importação de álbuns: media_sideload_image).
 */

add_action('post-upload-ui', 'bandas_media_from_url_ui');
add_action('admin_enqueue_scripts', 'bandas_media_from_url_assets');
add_action('wp_ajax_bandas_media_from_url', 'bandas_media_from_url_ajax');

function bandas_media_from_url_ui() {
  if (!current_user_can('upload_files')) {
    return;
  }
  ?>
  <div class="bandas-media-from-url">
    <p class="bandas-media-from-url__title">
      <strong><?php esc_html_e('Ou importar imagem por URL', 'api'); ?></strong>
    </p>
    <p class="bandas-media-from-url__row">
      <label for="bandas-media-from-url-input" class="screen-reader-text">
        <?php esc_html_e('URL da imagem', 'api'); ?>
      </label>
      <input
        type="url"
        id="bandas-media-from-url-input"
        class="bandas-media-from-url__input"
        placeholder="https://exemplo.com/imagem.jpg"
        autocomplete="off"
      />
      <button type="button" class="button bandas-media-from-url__btn">
        <?php esc_html_e('Importar URL', 'api'); ?>
      </button>
      <span class="spinner bandas-media-from-url__spinner"></span>
    </p>
    <p class="bandas-media-from-url__msg" aria-live="polite"></p>
  </div>
  <?php
}

function bandas_media_from_url_assets($hook) {
  if (!current_user_can('upload_files')) {
    return;
  }

  // upload.php, media-new.php, e telas que abrem wp.media (álbum, pessoa, etc.)
  $load = in_array($hook, ['upload.php', 'media-new.php', 'post.php', 'post-new.php'], true);
  if (!$load) {
    return;
  }

  wp_enqueue_media();
  wp_enqueue_script(
    'bandas-media-from-url',
    get_template_directory_uri() . '/admin/js/media-from-url.js',
    ['jquery', 'media-views'],
    filemtime(get_template_directory() . '/admin/js/media-from-url.js'),
    true
  );
  wp_localize_script('bandas-media-from-url', 'bandasMediaFromUrl', [
    'ajaxUrl' => admin_url('admin-ajax.php'),
    'libraryUrl' => admin_url('upload.php'),
    'action' => 'bandas_media_from_url',
    'nonce' => wp_create_nonce('bandas_media_from_url'),
    'i18n' => [
      'empty' => 'Informe a URL da imagem.',
      'invalid' => 'URL inválida.',
      'success' => 'Imagem importada para a biblioteca.',
      'error' => 'Não foi possível importar a imagem.',
    ],
  ]);

  wp_register_style('bandas-media-from-url', false, [], null);
  wp_enqueue_style('bandas-media-from-url');
  wp_add_inline_style(
    'bandas-media-from-url',
    '
    .bandas-media-from-url {
      margin: 16px auto 0;
      max-width: 640px;
      text-align: left;
    }
    .bandas-media-from-url__title {
      margin: 0 0 8px;
    }
    .bandas-media-from-url__row {
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      gap: 8px;
      margin: 0;
    }
    .bandas-media-from-url__input {
      flex: 1 1 240px;
      min-width: 200px;
      max-width: 100%;
    }
    .bandas-media-from-url__spinner {
      float: none;
      margin: 0;
      visibility: hidden;
    }
    .bandas-media-from-url__spinner.is-active {
      visibility: visible;
    }
    .bandas-media-from-url__msg {
      margin: 8px 0 0;
      min-height: 1.2em;
    }
    .bandas-media-from-url__msg.is-error {
      color: #b32d2e;
    }
    .bandas-media-from-url__msg.is-success {
      color: #007017;
    }
    .media-frame .bandas-media-from-url {
      padding: 0 16px 16px;
    }
    '
  );
}

function bandas_media_from_url_ajax() {
  check_ajax_referer('bandas_media_from_url', 'nonce');

  if (!current_user_can('upload_files')) {
    wp_send_json_error(['message' => 'Sem permissão.'], 403);
  }

  $url = isset($_POST['url']) ? esc_url_raw(wp_unslash((string) $_POST['url'])) : '';
  if ($url === '' || !wp_http_validate_url($url)) {
    wp_send_json_error(['message' => 'URL inválida.']);
  }

  $scheme = wp_parse_url($url, PHP_URL_SCHEME);
  if (!in_array(strtolower((string) $scheme), ['http', 'https'], true)) {
    wp_send_json_error(['message' => 'A URL precisa começar com http:// ou https://.']);
  }

  $post_id = absint($_POST['post_id'] ?? 0);

  if (function_exists('bandas_import_load_media_includes')) {
    bandas_import_load_media_includes();
  } else {
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';
  }

  $attachment_id = media_sideload_image($url, $post_id > 0 ? $post_id : 0, null, 'id');
  if (is_wp_error($attachment_id)) {
    wp_send_json_error(['message' => $attachment_id->get_error_message()]);
  }

  $attachment_id = (int) $attachment_id;
  if ($attachment_id <= 0) {
    wp_send_json_error(['message' => 'Falha ao criar o anexo.']);
  }

  $mime = (string) get_post_mime_type($attachment_id);
  if (strpos($mime, 'image/') !== 0) {
    wp_delete_attachment($attachment_id, true);
    wp_send_json_error(['message' => 'A URL não aponta para uma imagem válida.']);
  }

  $prepared = wp_prepare_attachment_for_js($attachment_id);
  if (!$prepared) {
    wp_send_json_error(['message' => 'Imagem importada, mas não foi possível carregar os dados.']);
  }

  wp_send_json_success([
    'attachment' => $prepared,
    'id' => $attachment_id,
  ]);
}
