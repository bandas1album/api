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

function bandas_album_credit_row_html($credit = []) {
    $person_id = absint($credit['person_id'] ?? 0);
    $name = esc_attr($credit['name'] ?? '');
    $detail = esc_attr($credit['detail'] ?? '');
    $role = api_sanitize_credit_role($credit['role'] ?? 'musician');
    $roles = api_credit_roles();

    $options = '';
    foreach ($roles as $value => $label) {
        $selected = selected($role, $value, false);
        $options .= '<option value="' . esc_attr($value) . '" ' . $selected . '>' . esc_html($label) . '</option>';
    }

    return '
        <div class="credit-row">
            <input type="hidden" name="credit_person_id[]" class="credit-person-id" value="' . esc_attr((string) $person_id) . '">
            <input type="text" name="credit_name[]" class="credit-name" placeholder="Nome (buscar pessoa)" value="' . $name . '" autocomplete="off">
            <select name="credit_role[]" class="credit-role">' . $options . '</select>
            <input type="text" name="credit_detail[]" class="credit-detail" placeholder="Detalhe (instrumento, gravação…)" value="' . $detail . '">
            <button type="button" class="button remove-credit">Remover</button>
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

    // Recuperação: se a meta atual está vazia, tenta achar faixas nas revisões.
    $tracklist_recovered = false;
    if (empty($tracklist) && function_exists('api_album_find_tracklist_in_revisions')) {
        $recovered = api_album_find_tracklist_in_revisions($post->ID);
        if (!empty($recovered)) {
            $tracklist = $recovered;
            $tracklist_recovered = true;
            update_post_meta(
                $post->ID,
                'tracklist',
                wp_json_encode($recovered, JSON_UNESCAPED_UNICODE)
            );
        }
    }

    $credits   = api_normalize_album_credits(get_post_meta($post->ID, 'credits', true));

    $link_platforms = ['amazon', 'deezer', 'lastfm', 'spotify', 'youtube', 'wikipedia', 'download'];
    $released_input = bandas_album_released_for_input($released);
    $person_search = [
        'url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('bandas_search_persons'),
        'action' => 'bandas_search_persons',
    ];
    $label_search = [
        'url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('bandas_search_labels'),
        'action' => 'bandas_search_labels',
    ];
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
        #credits-rows .credit-row {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: center;
            margin-bottom: 10px;
            padding: 10px;
            border: 1px solid #c3c4c7;
            background: #fff;
        }
        #credits-rows .credit-name { flex: 1 1 180px; min-width: 160px; }
        #credits-rows .credit-role { flex: 0 0 150px; }
        #credits-rows .credit-detail { flex: 1 1 160px; min-width: 140px; }
        .ui-autocomplete {
            z-index: 100000;
            max-height: 220px;
            overflow-y: auto;
            background: #fff;
            border: 1px solid #c3c4c7;
            box-shadow: 0 2px 6px rgba(0,0,0,.08);
            padding: 0;
            margin: 0;
            list-style: none;
        }
        .ui-autocomplete .ui-menu-item-wrapper {
            display: block;
            padding: 6px 10px;
            cursor: pointer;
        }
        .ui-autocomplete .ui-state-active,
        .ui-autocomplete .ui-menu-item-wrapper.ui-state-active {
            background: #2271b1;
            color: #fff;
        }
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
        <input type="text" name="label" id="album-label" value="<?php echo esc_attr($label); ?>" autocomplete="off">
        <p class="description">Busque uma gravadora já usada ou digite um nome novo.</p>
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
    <?php if ($tracklist_recovered) : ?>
        <div class="notice notice-warning inline"><p>
            A tracklist estava vazia neste post e foi <strong>recuperada automaticamente</strong> a partir das revisões.
            Confira as faixas e clique em Atualizar para gravar.
        </p></div>
    <?php endif; ?>
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

    <h4>Créditos</h4>
    <p class="description">
        Busque uma pessoa já cadastrada ou digite um nome novo (cria o registro automaticamente no save).
        Papel + detalhe (instrumento, gravação, etc.). O front recebe JSON com person_id, name, slug, role e detail.
    </p>
    <div id="credits-rows">
        <?php
        if (empty($credits)) {
            echo bandas_album_credit_row_html();
        } else {
            foreach ($credits as $credit) {
                echo bandas_album_credit_row_html($credit);
            }
        }
        ?>
    </div>
    <button type="button" class="button" id="add-credit">+ Adicionar crédito</button>

    <script>
    jQuery(function ($) {
        var trackRowTemplate = <?php echo wp_json_encode(bandas_album_track_row_html()); ?>;
        var creditRowTemplate = <?php echo wp_json_encode(bandas_album_credit_row_html()); ?>;
        var personSearch = <?php echo wp_json_encode($person_search); ?>;
        var labelSearch = <?php echo wp_json_encode($label_search); ?>;

        $('#album-label').autocomplete({
            minLength: 1,
            source: function (request, response) {
                $.getJSON(labelSearch.url, {
                    action: labelSearch.action,
                    nonce: labelSearch.nonce,
                    q: request.term
                }).done(function (payload) {
                    var items = (payload && payload.success && payload.data) ? payload.data : [];
                    response($.map(items, function (item) {
                        return { label: item.name, value: item.name };
                    }));
                }).fail(function () {
                    response([]);
                });
            }
        });

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

        function bindCreditAutocomplete($input) {
            if (!$input.length || !$input.autocomplete) return;
            $input.autocomplete({
                minLength: 1,
                source: function (request, response) {
                    $.getJSON(personSearch.url, {
                        action: personSearch.action,
                        nonce: personSearch.nonce,
                        q: request.term
                    }).done(function (payload) {
                        var items = (payload && payload.success && payload.data) ? payload.data : [];
                        response($.map(items, function (item) {
                            return {
                                label: item.name,
                                value: item.name,
                                person_id: item.person_id,
                                slug: item.slug
                            };
                        }));
                    }).fail(function () {
                        response([]);
                    });
                },
                select: function (_event, ui) {
                    var $row = $(this).closest('.credit-row');
                    $row.find('.credit-person-id').val(ui.item.person_id || '');
                    $(this).val(ui.item.value);
                    return false;
                },
                change: function (_event, ui) {
                    if (!ui.item) {
                        $(this).closest('.credit-row').find('.credit-person-id').val('');
                    }
                }
            });
        }

        $('#credits-rows .credit-name').each(function () {
            bindCreditAutocomplete($(this));
        });

        $('#add-credit').on('click', function () {
            var $row = $(creditRowTemplate);
            $('#credits-rows').append($row);
            bindCreditAutocomplete($row.find('.credit-name'));
        });

        $(document).on('click', '.remove-credit', function () {
            var $rows = $('#credits-rows .credit-row');
            if ($rows.length <= 1) {
                var $row = $(this).closest('.credit-row');
                $row.find('input[type=text], select').val('');
                $row.find('.credit-person-id').val('');
                $row.find('.credit-role').val('musician');
                return;
            }
            $(this).closest('.credit-row').remove();
        });

        $(document).on('input', '.credit-name', function () {
            $(this).closest('.credit-row').find('.credit-person-id').val('');
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

add_action('admin_enqueue_scripts', function ($hook) {
    if ($hook !== 'post.php' && $hook !== 'post-new.php') {
        return;
    }
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!$screen || $screen->post_type !== 'album') {
        return;
    }
    wp_enqueue_media();
    wp_enqueue_script('jquery-ui-autocomplete');
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

    // Tracklist: só atualiza se o repeater veio no POST com ao menos 1 faixa válida.
    // Nunca sobrescreve tracklist existente com [] (POST truncado / max_input_vars / UI vazia).
    if (isset($_POST['track_name']) && is_array($_POST['track_name'])) {
        $names = wp_unslash($_POST['track_name']);
        $durations = wp_unslash($_POST['track_duration'] ?? []);
        $youtube_urls = wp_unslash($_POST['track_youtube_url'] ?? []);
        $descriptions = wp_unslash($_POST['track_description'] ?? []);
        $lyrics = wp_unslash($_POST['track_lyrics'] ?? []);

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
        $existing = api_normalize_album_tracklist(get_post_meta($post_id, 'tracklist', true));

        if (!empty($tracklist)) {
            update_post_meta($post_id, 'tracklist', wp_json_encode($tracklist, JSON_UNESCAPED_UNICODE));
        } elseif (empty($existing)) {
            // Post realmente sem faixas — ok gravar vazio
            update_post_meta($post_id, 'tracklist', '[]');
        }
        // else: POST veio vazio mas já havia faixas — preserva o existente
    }

    // Créditos: só atualiza se o repeater veio no POST
    if (isset($_POST['credit_name']) && is_array($_POST['credit_name'])) {
        $credits = api_credits_from_post_arrays(
            wp_unslash($_POST['credit_person_id'] ?? []),
            wp_unslash($_POST['credit_name']),
            wp_unslash($_POST['credit_role'] ?? []),
            wp_unslash($_POST['credit_detail'] ?? [])
        );
        $existing_credits = api_normalize_album_credits(get_post_meta($post_id, 'credits', true));

        if (!empty($credits) || empty($existing_credits)) {
            update_post_meta($post_id, 'credits', wp_json_encode($credits, JSON_UNESCAPED_UNICODE));
        }
    }
});
