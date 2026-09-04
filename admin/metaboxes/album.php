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
        'album', // ajuste se o post type tiver outro slug
        'normal',
        'high'
    );
});

function render_album_details_metabox($post) {
    wp_nonce_field('album_details_save', 'album_details_nonce');

    $artist    = get_post_meta($post->ID, 'artist', true);
    $cover     = get_post_meta($post->ID, 'cover', true);
    $label     = get_post_meta($post->ID, 'label', true);
    $released  = get_post_meta($post->ID, 'released', true);
    $links     = json_decode(get_post_meta($post->ID, 'links', true), true) ?: [];
    $tracklist = json_decode(get_post_meta($post->ID, 'tracklist', true), true) ?: [];

    $link_platforms = ['amazon', 'deezer', 'lastfm', 'spotify', 'youtube', 'wikipedia', 'download'];
    ?>
    <style>
        .album-field { margin-bottom: 14px; }
        .album-field label { display:block; font-weight:600; margin-bottom:4px; }
        .album-field input[type=text], .album-field input[type=datetime-local] { width:100%; }
        #tracklist-rows .track-row { display:flex; gap:8px; margin-bottom:6px; }
        #tracklist-rows .track-row input { flex:1; }
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
        <input type="datetime-local" name="released" value="<?php echo esc_attr(substr($released, 0, 16)); ?>">
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
    <div id="tracklist-rows">
        <?php foreach ($tracklist as $track): ?>
            <div class="track-row">
                <input type="text" name="track_name[]" placeholder="Nome da faixa"
                       value="<?php echo esc_attr($track['name'] ?? ''); ?>">
                <input type="text" name="track_duration[]" placeholder="Duração (ex: 6:00)"
                       value="<?php echo esc_attr($track['duration'] ?? ''); ?>" style="max-width:120px;">
                <button type="button" class="button remove-track">Remover</button>
            </div>
        <?php endforeach; ?>
    </div>
    <button type="button" class="button" id="add-track">+ Adicionar faixa</button>

    <script>
    jQuery(function ($) {
        $('#add-track').on('click', function () {
            $('#tracklist-rows').append(
                '<div class="track-row">' +
                '<input type="text" name="track_name[]" placeholder="Nome da faixa">' +
                '<input type="text" name="track_duration[]" placeholder="Duração (ex: 6:00)" style="max-width:120px;">' +
                '<button type="button" class="button remove-track">Remover</button>' +
                '</div>'
            );
        });
        $(document).on('click', '.remove-track', function () {
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

    if (!empty($_POST['released'])) {
        $date = str_replace('T', 'T', $_POST['released']) . ':00.000Z';
        update_post_meta($post_id, 'released', sanitize_text_field($date));
        api_sync_released_year($post_id);
    } else {
        delete_post_meta($post_id, 'released');
        delete_post_meta($post_id, 'released_year');
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

    $tracklist = [];
    $names = $_POST['track_name'] ?? [];
    $durations = $_POST['track_duration'] ?? [];
    foreach ($names as $i => $name) {
        if (trim($name) === '') continue;
        $tracklist[] = [
            'name' => sanitize_text_field($name),
            'duration' => sanitize_text_field($durations[$i] ?? ''),
        ];
    }
    update_post_meta($post_id, 'tracklist', wp_json_encode($tracklist));
});