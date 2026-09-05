<?php
// 1. Esconde o painel genérico de Custom Fields para o post type 'album'
add_action('admin_init', function () {
    remove_post_type_support('album', 'custom-fields');
});

// 2. Registra o meta box customizado
add_action('add_meta_boxes', function () {
    add_meta_box(
        'album_details',
        'Detalhes do Álbum',
        'render_album_details_metabox',
        'album',
        'normal',
        'high'
    );
});

function bandas_album_track_row_html($track = []) {
    $name = esc_attr($track['name'] ?? '');
    $duration = esc_attr($track['duration'] ?? '');
    $youtube_url = esc_attr($track['youtube_url'] ?? '');
    $description = esc_textarea($track['description'] ?? '');
    $lyrics = esc_textarea($track['lyrics'] ?? '');

    return '
        <div class="track-row">
            <div class="track-row-main">
                <input type="text" name="track_name[]" placeholder="Nome da faixa" value="' . $name . '">
                <input type="text" name="track_duration[]" placeholder="Duração (ex: 6:00)" value="' . $duration . '" style="max-width:120px;">
                <button type="button" class="button remove-track">Remover</button>
            </div>
            <div class="track-row-extra">
                <input type="url" name="track_youtube_url[]" placeholder="URL do YouTube (faixa)" value="' . $youtube_url . '">
                <textarea name="track_description[]" rows="2" placeholder="Sobre a faixa (opcional)">' . $description . '</textarea>
                <textarea name="track_lyrics[]" rows="3" placeholder="Letra (opcional)">' . $lyrics . '</textarea>
            </div>
        </div>
    ';
}

/**
 * Converte meta `released` para value de <input type="datetime-local">.
 */
function bandas_album_released_for_input($released) {
    $released = trim((string) $released);
    if ($released === '') {
        return '';
    }

    // Já está no formato datetime-local (sem segundos) ou com segundos
    if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}/', $released)) {
        return substr($released, 0, 16);
    }

    $ts = strtotime(str_replace('T', ' ', $released));
    if (!$ts) {
        return '';
    }

    return gmdate('Y-m-d\TH:i', $ts);
}

/**
 * Normaliza o valor enviado pelo datetime-local para o meta ISO usado na API.
 */
function bandas_album_normalize_released_meta($raw) {
    $raw = trim((string) $raw);
    if ($raw === '') {
        return '';
    }

    if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}/', $raw)) {
        return substr($raw, 0, 16) . ':00.000Z';
    }

    $ts = strtotime($raw);
    if (!$ts) {
        return '';
    }

    return gmdate('Y-m-d\TH:i:s', $ts) . '.000Z';
}

function render_album_details_metabox($post) {
    wp_nonce_field('album_details_save', 'album_details_nonce');

    $artist    = get_post_meta($post->ID, 'artist', true);
    $cover     = get_post_meta($post->ID, 'cover', true);
    $label     = get_post_meta($post->ID, 'label', true);
    $released  = get_post_meta($post->ID, 'released', true);
    $links     = json_decode(get_post_meta($post->ID, 'links', true), true) ?: [];
    $tracklist = api_normalize_album_tracklist(get_post_meta($post->ID, 'tracklist', true));

    $link_platforms = ['amazon', 'deezer', 'lastfm', 'spotify', 'youtube', 'wikipedia', 'download'];
    $released_input = bandas_album_released_for_input($released);
    ?>
    <style>
        .album-field { margin-bottom: 14px; }
        .album-field label { display:block; font-weight:600; margin-bottom:4px; }
        .album-field input[type=text], .album-field input[type=datetime-local] { width:100%; }
        #tracklist-rows .track-row {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-bottom: 12px;
            padding: 12px;
            border: 1px solid #c3c4c7;
            background: #fff;
        }
        #tracklist-rows .track-row-main { display:flex; gap:8px; align-items:center; }
        #tracklist-rows .track-row-main input { flex:1; }
        #tracklist-rows .track-row-extra { display:flex; flex-direction:column; gap:8px; }
        #tracklist-rows .track-row-extra input,
        #tracklist-rows .track-row-extra textarea { width:100%; }
    </style>

    <div class="album-field">
        <label>Artista</label>
        <input type="text" name="artist" value="<?php echo esc_attr($artist); ?>">
    </div>

    <div class="album-field">
        <label>Capa (ID da mídia)</label>
        <div style="display:flex; gap:8px; align-items:center;">
        <input type="text" id="cover_id" name="cover" value="<?php echo esc_attr($cover); ?>">
        <button type="button" class="button" id="upload_cover_btn">Selecionar imagem</button>
        </div>
    </div>

    <div class="album-field">
        <label>Gravadora</label>
        <input type="text" name="label" value="<?php echo esc_attr($label); ?>">
    </div>

    <div class="album-field">
        <label>Data de lançamento</label>
        <input type="datetime-local" name="released" value="<?php echo esc_attr($released_input); ?>">
    </div>

    <h4>Links</h4>
    <?php foreach ($link_platforms as $platform): ?>
        <div class="album-field">
            <label style="text-transform:capitalize;"><?php echo esc_html($platform); ?></label>
            <input type="text" name="links[<?php echo esc_attr($platform); ?>]"
                   value="<?php echo esc_attr($links[$platform] ?? ''); ?>">
        </div>
    <?php endforeach; ?>

    <h4>Faixas</h4>
    <p class="description">Campos separados por faixa. O front recebe tudo em JSON via API (inclui youtube_id normalizado).</p>
    <div id="tracklist-rows">
        <?php
        if (empty($tracklist)) {
            echo bandas_album_track_row_html();
        } else {
            foreach ($tracklist as $track) {
                echo bandas_album_track_row_html($track);
            }
        }
        ?>
    </div>
    <button type="button" class="button" id="add-track">+ Adicionar faixa</button>

    <script>
    jQuery(function ($) {
        var trackRowTemplate = <?php echo wp_json_encode(bandas_album_track_row_html()); ?>;

        $('#add-track').on('click', function () {
            $('#tracklist-rows').append(trackRowTemplate);
        });
        $(document).on('click', '.remove-track', function () {
            var $rows = $('#tracklist-rows .track-row');
            if ($rows.length <= 1) {
                $(this).closest('.track-row').find('input, textarea').val('');
                return;
            }
            $(this).closest('.track-row').remove();
        });

        var coverFrame;
        $('#upload_cover_btn').on('click', function (e) {
            e.preventDefault();
            if (coverFrame) { coverFrame.open(); return; }
            coverFrame = wp.media({ title: 'Selecionar capa', multiple: false });
            coverFrame.on('select', function () {
                var attachment = coverFrame.state().get('selection').first().toJSON();
                $('#cover_id').val(attachment.id);
                $('#cover_preview').html('<img src="' + attachment.url + '" width="80">');
            });
            coverFrame.open();
        });
    });
    </script>
    <?php
}

add_action('admin_enqueue_scripts', function () {
    wp_enqueue_media();
});

// 3. Salva reconstruindo o mesmo formato JSON de antes
add_action('save_post', function ($post_id) {
    if (!isset($_POST['album_details_nonce']) ||
        !wp_verify_nonce($_POST['album_details_nonce'], 'album_details_save')) {
        return;
    }
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;
    if (get_post_type($post_id) !== 'album') return;

    update_post_meta($post_id, 'artist', sanitize_text_field($_POST['artist'] ?? ''));
    update_post_meta($post_id, 'cover', absint($_POST['cover'] ?? 0));
    update_post_meta($post_id, 'label', sanitize_text_field($_POST['label'] ?? ''));

    if (array_key_exists('released', $_POST)) {
        $normalized = bandas_album_normalize_released_meta($_POST['released'] ?? '');
        if ($normalized !== '') {
            update_post_meta($post_id, 'released', sanitize_text_field($normalized));
            api_sync_released_year($post_id);
        } else {
            delete_post_meta($post_id, 'released');
            delete_post_meta($post_id, 'released_year');
        }
    }

    $allowed_platforms = ['amazon', 'deezer', 'lastfm', 'spotify', 'youtube', 'wikipedia', 'download'];
    $links = [];
    foreach ($_POST['links'] ?? [] as $platform => $url) {
        $platform = sanitize_key($platform);
        if (!in_array($platform, $allowed_platforms, true)) {
            continue;
        }
        $links[$platform] = $url !== '' ? esc_url_raw($url) : null;
    }
    update_post_meta($post_id, 'links', wp_json_encode($links));

    // Só atualiza tracklist se o repeater veio no POST (evita apagar por save sem metabox / POST truncado)
    if (!isset($_POST['track_name']) || !is_array($_POST['track_name'])) {
        return;
    }

    $names = $_POST['track_name'];
    $durations = $_POST['track_duration'] ?? [];
    $youtube_urls = $_POST['track_youtube_url'] ?? [];
    $descriptions = $_POST['track_description'] ?? [];
    $lyrics = $_POST['track_lyrics'] ?? [];

    $raw_tracks = [];
    foreach ($names as $i => $name) {
        $raw_tracks[] = [
            'name' => $name,
            'duration' => is_array($durations) ? ($durations[$i] ?? '') : '',
            'youtube_url' => is_array($youtube_urls) ? ($youtube_urls[$i] ?? '') : '',
            'description' => is_array($descriptions) ? ($descriptions[$i] ?? '') : '',
            'lyrics' => is_array($lyrics) ? ($lyrics[$i] ?? '') : '',
        ];
    }

    $tracklist = api_normalize_album_tracklist($raw_tracks);
    update_post_meta($post_id, 'tracklist', wp_json_encode($tracklist, JSON_UNESCAPED_UNICODE));
});
