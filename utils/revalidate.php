<?php

/**
 * Dispara revalidação on-demand do front (Next.js ISR) ao criar/atualizar álbum.
 *
 * Hook: wp_after_insert_post (post type album) — não depende do metabox/tracklist.
 *
 * Configure em Álbuns → Revalidação do front, ou via constantes:
 *   define('BANDAS_REVALIDATE_URL', 'https://bandas1album.com.br/api/revalidate');
 *   define('BANDAS_REVALIDATE_SECRET', 'mesmo-valor-de-REVALIDATE_SECRET-no-Next');
 */

function bandas_get_revalidate_url() {
  if (defined('BANDAS_REVALIDATE_URL') && BANDAS_REVALIDATE_URL) {
    return (string) BANDAS_REVALIDATE_URL;
  }
  return (string) get_option('bandas_revalidate_url', '');
}

function bandas_get_revalidate_secret() {
  if (defined('BANDAS_REVALIDATE_SECRET') && BANDAS_REVALIDATE_SECRET) {
    return (string) BANDAS_REVALIDATE_SECRET;
  }
  return (string) get_option('bandas_revalidate_secret', '');
}

/**
 * @param int $post_id
 * @return string[]
 */
function bandas_album_revalidate_paths($post_id) {
  $paths = ['/'];
  $slug = get_post_field('post_name', $post_id);

  if (is_string($slug) && $slug !== '') {
    $paths[] = '/album/' . $slug;
  }

  $genres = get_the_terms($post_id, 'genre');
  if (is_array($genres)) {
    foreach ($genres as $term) {
      if (!empty($term->slug)) {
        $paths[] = '/genre/' . $term->slug;
      }
    }
  }

  $countries = get_the_terms($post_id, 'country');
  if (is_array($countries)) {
    foreach ($countries as $term) {
      if (!empty($term->slug)) {
        $paths[] = '/country/' . $term->slug;
      }
    }
  }

  $year = get_post_meta($post_id, 'released_year', true);
  if ($year) {
    $paths[] = '/year/' . absint($year);
  }

  return array_values(array_unique($paths));
}

/**
 * @param int $post_id
 */
function bandas_request_frontend_revalidate($post_id) {
  $url = bandas_get_revalidate_url();
  $secret = bandas_get_revalidate_secret();

  if ($url === '' || $secret === '') {
    return;
  }

  $post = get_post($post_id);
  if (!$post || $post->post_type !== 'album') {
    return;
  }

  // Só páginas públicas importam para o ISR
  if ($post->post_status !== 'publish') {
    return;
  }

  $slug = $post->post_name;
  if (!is_string($slug) || $slug === '') {
    return;
  }

  $body = [
    'slug' => $slug,
    'paths' => bandas_album_revalidate_paths($post_id),
  ];

  wp_remote_post($url, [
    'timeout' => 5,
    'blocking' => false,
    'headers' => [
      'Authorization' => 'Bearer ' . $secret,
      'Content-Type' => 'application/json',
    ],
    'body' => wp_json_encode($body),
  ]);
}

/**
 * Após criar ou atualizar um álbum (não amarrado ao metabox/tracklist).
 * Roda depois do save_post, quando título/status/meta já foram persistidos.
 */
add_action('wp_after_insert_post', function ($post_id, $post, $update) {
  if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
    return;
  }
  if (wp_is_post_revision($post_id)) {
    return;
  }
  if (!$post instanceof WP_Post || $post->post_type !== 'album') {
    return;
  }
  if (!current_user_can('edit_post', $post_id)) {
    return;
  }

  unset($update);
  bandas_request_frontend_revalidate($post_id);
}, 10, 3);

add_action('admin_menu', function () {
  add_submenu_page(
    'edit.php?post_type=album',
    'Revalidação do front',
    'Revalidação do front',
    'manage_options',
    'bandas-revalidate',
    'bandas_render_revalidate_settings_page'
  );
});

add_action('admin_init', function () {
  register_setting('bandas_revalidate', 'bandas_revalidate_url', [
    'type' => 'string',
    'sanitize_callback' => 'esc_url_raw',
    'default' => '',
  ]);
  register_setting('bandas_revalidate', 'bandas_revalidate_secret', [
    'type' => 'string',
    'sanitize_callback' => 'sanitize_text_field',
    'default' => '',
  ]);
});

function bandas_render_revalidate_settings_page() {
  if (!current_user_can('manage_options')) {
    return;
  }

  $locked_url = defined('BANDAS_REVALIDATE_URL') && BANDAS_REVALIDATE_URL;
  $locked_secret = defined('BANDAS_REVALIDATE_SECRET') && BANDAS_REVALIDATE_SECRET;
  $url = bandas_get_revalidate_url();
  $secret = bandas_get_revalidate_secret();
  ?>
  <div class="wrap">
    <h1>Revalidação do front</h1>
    <p>
      Ao <strong>criar ou atualizar</strong> um álbum publicado, o WordPress chama o Next.js
      (<code>POST /api/revalidate</code>) para limpar o cache ISR da página do álbum,
      da home e das listagens de gênero/país/ano relacionadas. O hook é o
      <code>wp_after_insert_post</code> do post type <code>album</code> — independente
      do metabox de faixas.
    </p>
    <p>
      O segredo deve ser o mesmo valor de <code>REVALIDATE_SECRET</code> no front
      (Vercel / <code>.env.local</code>). A revalidação ISR só surte efeito no
      <strong>build de produção</strong> (<code>next start</code> / Vercel), não no
      <code>yarn dev</code>.
    </p>
    <?php if ($locked_url && $locked_secret): ?>
      <table class="form-table" role="presentation">
        <tr>
          <th scope="row">URL do endpoint</th>
          <td><code><?php echo esc_html($url); ?></code>
            <p class="description">Constante <code>BANDAS_REVALIDATE_URL</code>.</p></td>
        </tr>
        <tr>
          <th scope="row">Segredo</th>
          <td><code>••••••••</code>
            <p class="description">Constante <code>BANDAS_REVALIDATE_SECRET</code>.</p></td>
        </tr>
      </table>
    <?php else: ?>
    <form method="post" action="options.php">
      <?php settings_fields('bandas_revalidate'); ?>
      <table class="form-table" role="presentation">
        <tr>
          <th scope="row"><label for="bandas_revalidate_url">URL do endpoint</label></th>
          <td>
            <?php if ($locked_url): ?>
              <code><?php echo esc_html($url); ?></code>
              <input type="hidden" name="bandas_revalidate_url" value="<?php echo esc_attr($url); ?>">
              <p class="description">Constante <code>BANDAS_REVALIDATE_URL</code>.</p>
            <?php else: ?>
              <input
                type="url"
                class="regular-text"
                id="bandas_revalidate_url"
                name="bandas_revalidate_url"
                value="<?php echo esc_attr(get_option('bandas_revalidate_url', '')); ?>"
                placeholder="https://bandas1album.com.br/api/revalidate"
              >
            <?php endif; ?>
          </td>
        </tr>
        <tr>
          <th scope="row"><label for="bandas_revalidate_secret">Segredo</label></th>
          <td>
            <?php if ($locked_secret): ?>
              <code>••••••••</code>
              <input type="hidden" name="bandas_revalidate_secret" value="<?php echo esc_attr($secret); ?>">
              <p class="description">Constante <code>BANDAS_REVALIDATE_SECRET</code>.</p>
            <?php else: ?>
              <input
                type="password"
                class="regular-text"
                id="bandas_revalidate_secret"
                name="bandas_revalidate_secret"
                value="<?php echo esc_attr(get_option('bandas_revalidate_secret', '')); ?>"
                autocomplete="new-password"
              >
            <?php endif; ?>
          </td>
        </tr>
      </table>
      <?php submit_button('Salvar'); ?>
    </form>
    <?php endif; ?>
  </div>
  <?php
}
