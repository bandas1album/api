<?php
/**
 * Importador de álbuns em lote — cole este bloco no functions.php do tema
 * (ou, melhor ainda, salve como bandas-importer.php dentro do tema e faça
 *  um `require_once get_stylesheet_directory() . '/bandas-importer.php';`
 *  no functions.php, pra não deixar o functions.php gigante).
 *
 * Cria uma página em Ferramentas > Importar Álbuns onde você sobe um CSV
 * e o processamento roda em lotes via AJAX, com barra de progresso.
 */

// ============================================================
// CONFIG — ajuste os nomes de coluna se sua planilha usar outros
// ============================================================
if ( ! defined( 'BANDAS_IMPORT_POST_TYPE' ) ) {
	define( 'BANDAS_IMPORT_POST_TYPE', 'album' ); // confirme o slug do seu CPT
}
if ( ! defined( 'BANDAS_IMPORT_BATCH_SIZE' ) ) {
	define( 'BANDAS_IMPORT_BATCH_SIZE', 5 ); // linhas processadas por requisição AJAX
}

function bandas_import_column_map() {
	return array(
		'title'     => 'Álbum',
		'artist'    => 'Artista',
		'released'  => 'Ano',
		'label'     => 'Gravadora',
		'genre'     => 'Gênero',      // separado por ; ou ,
		'country'   => 'País',
		'cover_url' => 'Capa (URL)',
		'tracklist' => 'Faixas',
		'amazon'    => 'Amazon',
		'deezer'    => 'Deezer',
		'lastfm'    => 'Last.fm',
		'spotify'   => 'Spotify',
		'youtube'   => 'Youtube',
		'wikipedia' => 'Wikipedia',
		'download'  => 'Download',
	);
}

// ============================================================
// Página no admin — item de menu principal (fora de Ferramentas)
// ============================================================
add_action( 'admin_menu', function () {
	add_menu_page(
		'Importar Álbuns',        // título da página
		'Importar Álbuns',        // texto do menu
		'manage_options',
		'bandas-importer',
		'bandas_import_render_page',
		'dashicons-upload',       // ícone (veja outros em developer.wordpress.org/resource/dashicons)
		6                          // posição no menu — ajuste se quiser mais acima/abaixo
	);
} );

function bandas_import_render_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Sem permissão.' );
	}

	// Upload do CSV (POST normal, fora do ciclo de AJAX)
	$rows_json = '';
	$parse_error = '';

	if ( isset( $_POST['bandas_import_upload'] ) && check_admin_referer( 'bandas_import_upload_action', 'bandas_import_nonce' ) ) {
		if ( empty( $_FILES['bandas_csv']['tmp_name'] ) ) {
			$parse_error = 'Nenhum arquivo enviado.';
		} else {
			$rows = bandas_import_parse_csv( $_FILES['bandas_csv']['tmp_name'] );
			if ( is_wp_error( $rows ) ) {
				$parse_error = $rows->get_error_message();
			} else {
				// guarda em transient temporária (1h) pra o AJAX consumir em lotes
				$import_id = 'bandas_import_' . wp_generate_password( 8, false );
				set_transient( $import_id, $rows, HOUR_IN_SECONDS );
				$rows_json = wp_json_encode( array(
					'import_id' => $import_id,
					'total'     => count( $rows ),
				) );
			}
		}
	}

	?>
	<div class="wrap">
		<h1>Importar Álbuns</h1>

		<h2 class="nav-tab-wrapper">
			<a href="#" class="nav-tab bandas-tab-link bandas-tab-active" data-tab="planilha">Importar planilha (CSV)</a>
			<a href="#" class="nav-tab bandas-tab-link" data-tab="individual">Cadastrar item individual</a>
		</h2>

		<div id="bandas-tab-planilha" class="bandas-tab-panel">

		<?php if ( $parse_error ) : ?>
			<div class="notice notice-error"><p><?php echo esc_html( $parse_error ); ?></p></div>
		<?php endif; ?>

		<?php if ( ! $rows_json ) : ?>
			<p>Envie um arquivo <strong>.csv</strong> (separado por vírgula, cabeçalho na primeira linha) com as colunas: 
			<?php echo esc_html( implode( ', ', bandas_import_column_map() ) ); ?></p>
			<form method="post" enctype="multipart/form-data">
				<?php wp_nonce_field( 'bandas_import_upload_action', 'bandas_import_nonce' ); ?>
				<input type="file" name="bandas_csv" accept=".csv" required>
				<p><button type="submit" name="bandas_import_upload" value="1" class="button button-primary">Carregar planilha</button></p>
			</form>
		<?php else : ?>
			<div id="bandas-import-app" data-import='<?php echo esc_attr( $rows_json ); ?>' data-nonce="<?php echo esc_attr( wp_create_nonce( 'bandas_import_batch' ) ); ?>">
				<p><strong>Total de linhas:</strong> <span id="bandas-total"></span></p>
				<progress id="bandas-progress" value="0" max="100" style="width:100%;height:24px;"></progress>
				<p id="bandas-status">Pronto para começar.</p>
				<button id="bandas-start" class="button button-primary">Iniciar importação</button>
				<h2>Log</h2>
				<table class="widefat striped">
					<thead><tr><th>Linha</th><th>Título</th><th>Status</th><th>Detalhe</th></tr></thead>
					<tbody id="bandas-log"></tbody>
				</table>
			</div>
		<?php endif; ?>

		</div><!-- /#bandas-tab-planilha -->

		<div id="bandas-tab-individual" class="bandas-tab-panel" style="display:none;">
			<p>Preencha os campos de um álbum e clique em Importar. O mesmo tratamento automático da planilha
			(busca/criação de gênero e país, download da capa pela URL, parsing da tracklist, links nulos quando vazios)
			é aplicado aqui também.</p>

			<form id="bandas-single-form" onsubmit="return false;">
				<?php wp_nonce_field( 'bandas_import_single', 'bandas_single_nonce' ); ?>
				<table class="form-table">
					<tr><th><label>Álbum (título)</label></th><td><input type="text" name="title" class="regular-text" required></td></tr>
					<tr><th><label>Artista</label></th><td><input type="text" name="artist" class="regular-text"></td></tr>
					<tr><th><label>Ano / Data</label></th><td><input type="text" name="released" class="regular-text" placeholder="1970 ou 1970-05-01"></td></tr>
					<tr><th><label>Gravadora</label></th><td><input type="text" name="label" class="regular-text"></td></tr>
					<tr><th><label>Gênero</label></th><td><input type="text" name="genre" class="regular-text" placeholder="Psychedelic Rock; Rock & Roll"></td></tr>
					<tr><th><label>País</label></th><td><input type="text" name="country" class="regular-text" placeholder="Inglaterra"></td></tr>
					<tr><th><label>Capa (URL)</label></th><td><input type="url" name="cover_url" class="regular-text" placeholder="https://..."></td></tr>
					<tr><th><label>Tracklist</label></th>
						<td>
							<textarea name="tracklist" rows="8" class="large-text" placeholder="Cole aqui direto do Discogs (nome + tab + duração), uma faixa por linha"></textarea>
						</td>
					</tr>
					<tr><th colspan="2"><h3>Links</h3></th></tr>
					<tr><th><label>Afiliado</label></th><td><input type="url" name="amazon" class="regular-text"></td></tr>
					<tr><th><label>Deezer</label></th><td><input type="url" name="deezer" class="regular-text"></td></tr>
					<tr><th><label>Last.fm</label></th><td><input type="url" name="lastfm" class="regular-text"></td></tr>
					<tr><th><label>Spotify</label></th><td><input type="url" name="spotify" class="regular-text"></td></tr>
					<tr><th><label>Youtube</label></th><td><input type="url" name="youtube" class="regular-text"></td></tr>
					<tr><th><label>Wikipedia</label></th><td><input type="url" name="wikipedia" class="regular-text"></td></tr>
					<tr><th><label>Download</label></th><td><input type="url" name="download" class="regular-text"></td></tr>
				</table>
				<p>
					<button type="submit" id="bandas-single-submit" class="button button-primary">Importar este item</button>
					<span id="bandas-single-status" style="margin-left:10px;"></span>
				</p>
			</form>

			<h2>Log</h2>
			<table class="widefat striped">
				<thead><tr><th>Título</th><th>Status</th><th>Detalhe</th></tr></thead>
				<tbody id="bandas-single-log"></tbody>
			</table>
		</div><!-- /#bandas-tab-individual -->

		<script>
		(function () {
			document.querySelectorAll('.bandas-tab-link').forEach(function (link) {
				link.addEventListener('click', function (e) {
					e.preventDefault();
					document.querySelectorAll('.bandas-tab-link').forEach(l => l.classList.remove('bandas-tab-active', 'nav-tab-active'));
					this.classList.add('bandas-tab-active', 'nav-tab-active');
					document.querySelectorAll('.bandas-tab-panel').forEach(p => p.style.display = 'none');
					document.getElementById('bandas-tab-' + this.dataset.tab).style.display = '';
				});
			});

			// --- aba: importar planilha (lote com barra de progresso) ---
			const app = document.getElementById('bandas-import-app');
			if (app) {
				const data = JSON.parse(app.dataset.import);
				const nonce = app.dataset.nonce;
				const batchSize = <?php echo (int) BANDAS_IMPORT_BATCH_SIZE; ?>;
				let offset = 0;

				document.getElementById('bandas-total').textContent = data.total;

				document.getElementById('bandas-start').addEventListener('click', function () {
					this.disabled = true;
					offset = 0;
					document.getElementById('bandas-log').innerHTML = '';
					runBatch();
				});

				function runBatch() {
					const status = document.getElementById('bandas-status');
					status.textContent = 'Processando linhas ' + offset + ' a ' + Math.min(offset + batchSize, data.total) + '...';

					const form = new FormData();
					form.append('action', 'bandas_import_batch');
					form.append('nonce', nonce);
					form.append('import_id', data.import_id);
					form.append('offset', offset);
					form.append('batch_size', batchSize);

					fetch(ajaxurl, { method: 'POST', body: form })
						.then(r => r.json())
						.then(res => {
							if (!res.success) {
								status.textContent = 'Erro: ' + (res.data || 'desconhecido');
								document.getElementById('bandas-start').disabled = false;
								return;
							}
							const body = res.data;
							body.log.forEach(function (item) {
								const tr = document.createElement('tr');
								tr.innerHTML = '<td>' + item.linha + '</td><td>' + item.titulo + '</td><td>' + item.status + '</td><td>' + item.detalhe + '</td>';
								document.getElementById('bandas-log').appendChild(tr);
							});

							offset += batchSize;
							const pct = Math.min(100, Math.round((offset / data.total) * 100));
							document.getElementById('bandas-progress').value = pct;

							if (offset < data.total) {
								runBatch();
							} else {
								status.textContent = 'Importação concluída!';
								document.getElementById('bandas-start').disabled = false;
							}
						})
						.catch(err => {
							status.textContent = 'Erro de rede: ' + err;
							document.getElementById('bandas-start').disabled = false;
						});
				}
			}

			// --- aba: cadastro individual ---
			const singleForm = document.getElementById('bandas-single-form');
			if (singleForm) {
				singleForm.addEventListener('submit', function () {
					const status = document.getElementById('bandas-single-status');
					const submitBtn = document.getElementById('bandas-single-submit');
					submitBtn.disabled = true;
					status.textContent = 'Importando...';

					const form = new FormData(singleForm);
					form.append('action', 'bandas_import_single');

					fetch(ajaxurl, { method: 'POST', body: form })
						.then(r => r.json())
						.then(res => {
							submitBtn.disabled = false;

							// resposta "-1" ou "0" (texto puro do wp_die) indica nonce
							// expirado/sessão caída — não é um erro de validação normal
							if (res === -1 || res === 0 || typeof res !== 'object' || res === null) {
								status.textContent = 'Sessão expirada — recarregue a página e tente de novo.';
								return;
							}
							if (!res.success) {
								status.textContent = 'Erro: ' + (res.data || 'desconhecido');
								return;
							}
							const item = res.data;
							status.textContent = item.status === 'criado' ? 'Criado com sucesso!' : (item.status === 'pulado' ? 'Já existia, pulado.' : 'Erro.');
							const tr = document.createElement('tr');
							tr.innerHTML = '<td>' + item.titulo + '</td><td>' + item.status + '</td><td>' + item.detalhe + '</td>';
							document.getElementById('bandas-single-log').prepend(tr);
							if (item.status === 'criado') {
								singleForm.reset();
							}
						})
						.catch(err => {
							submitBtn.disabled = false;
							status.textContent = 'Erro de rede: ' + err;
						});
				});
			}
		})();
		</script>
	<?php
}

// ============================================================
// Parser do CSV
// ============================================================
function bandas_import_parse_csv( $tmp_path ) {
	$handle = fopen( $tmp_path, 'r' );
	if ( ! $handle ) {
		return new WP_Error( 'file', 'Não consegui abrir o arquivo enviado.' );
	}
	$header = fgetcsv( $handle );
	if ( ! $header ) {
		fclose( $handle );
		return new WP_Error( 'empty', 'Planilha vazia ou inválida.' );
	}
	$header = array_map( 'trim', $header );
	$rows = array();
	while ( ( $line = fgetcsv( $handle ) ) !== false ) {
		if ( count( $line ) === 1 && trim( $line[0] ) === '' ) {
			continue; // linha em branco
		}
		$row = array();
		foreach ( $header as $i => $col ) {
			$row[ $col ] = isset( $line[ $i ] ) ? trim( $line[ $i ] ) : '';
		}
		$rows[] = $row;
	}
	fclose( $handle );
	return $rows;
}

// ============================================================
// AJAX: processa um lote de linhas (aba "Importar planilha")
// ============================================================
add_action( 'wp_ajax_bandas_import_batch', function () {
	check_ajax_referer( 'bandas_import_batch', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( 'Sem permissão.', 403 );
	}

	$import_id  = sanitize_text_field( $_POST['import_id'] ?? '' );
	$offset     = max( 0, (int) ( $_POST['offset'] ?? 0 ) );
	$batch_size = max( 1, (int) ( $_POST['batch_size'] ?? BANDAS_IMPORT_BATCH_SIZE ) );

	$rows = get_transient( $import_id );
	if ( ! is_array( $rows ) ) {
		wp_send_json_error( 'Importação expirada ou inválida. Suba a planilha de novo.' );
	}

	$slice = array_slice( $rows, $offset, $batch_size, true );
	$log = array();

	bandas_import_load_media_includes();

	foreach ( $slice as $i => $row ) {
		$linha = $offset + $i + 2; // +2 compensa cabeçalho e index 0
		$log[] = bandas_import_process_row( $linha, $row );
	}

	// se acabou, limpa a transient
	if ( $offset + $batch_size >= count( $rows ) ) {
		delete_transient( $import_id );
	}

	wp_send_json_success( array( 'log' => $log ) );
} );

/**
 * Carrega os includes do wp-admin necessários pra media_sideload_image().
 * Compartilhado pelos dois handlers AJAX (lote e individual).
 */
function bandas_import_load_media_includes() {
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
}

// ============================================================
// AJAX: cadastra um único álbum (aba "Cadastrar item individual")
// ============================================================
add_action( 'wp_ajax_bandas_import_single', function () {
	check_ajax_referer( 'bandas_import_single', 'bandas_single_nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( 'Sem permissão.', 403 );
	}

	$map = bandas_import_column_map();

	// campos de texto curto (título, artista, ano, gravadora, gênero, país)
	$text_fields = array( 'title', 'artist', 'released', 'label', 'genre', 'country' );
	// campos de URL (capa + os 7 links)
	$url_fields = array( 'cover_url', 'amazon', 'deezer', 'lastfm', 'spotify', 'youtube', 'wikipedia', 'download' );

	$row = array();

	foreach ( $text_fields as $key ) {
		$val = isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : '';
		$row[ $map[ $key ] ] = $val;
	}

	foreach ( $url_fields as $key ) {
		$val = isset( $_POST[ $key ] ) ? esc_url_raw( wp_unslash( $_POST[ $key ] ) ) : '';
		$row[ $map[ $key ] ] = $val;
	}

	// tracklist é o único campo multi-linha — usa wp_strip_all_tags (preservando quebras de
	// linha e tabs) em vez de sanitize_text_field (que removeria as quebras/tabs do Discogs)
	$tracklist_raw = isset( $_POST['tracklist'] ) ? wp_unslash( $_POST['tracklist'] ) : '';
	$row[ $map['tracklist'] ] = wp_strip_all_tags( $tracklist_raw, false );

	if ( '' === trim( $row[ $map['title'] ] ) ) {
		wp_send_json_error( 'O título do álbum é obrigatório.' );
	}

	bandas_import_load_media_includes();

	$result = bandas_import_process_row( 'manual', $row );

	wp_send_json_success( $result );
} );

/**
 * Lê um campo do $row de forma segura: se a coluna não estiver mapeada em
 * $map ou não existir na planilha, retorna string vazia em vez de warning.
 */
function bandas_import_field( $row, $map, $key ) {
	$col = $map[ $key ] ?? null;
	if ( ! $col || ! isset( $row[ $col ] ) ) {
		return '';
	}
	return trim( (string) $row[ $col ] );
}

function bandas_import_process_row( $linha, $row ) {
	$map = bandas_import_column_map();
	$title = bandas_import_field( $row, $map, 'title' );

	if ( '' === $title ) {
		return array( 'linha' => $linha, 'titulo' => '', 'status' => 'erro', 'detalhe' => 'sem título' );
	}

	// evita duplicar
	$existing = get_page_by_title( $title, OBJECT, BANDAS_IMPORT_POST_TYPE );
	if ( $existing ) {
		return array( 'linha' => $linha, 'titulo' => esc_html( $title ), 'status' => 'pulado', 'detalhe' => 'já existe (post ' . $existing->ID . ')' );
	}

	$post_id = wp_insert_post( array(
		'post_type'   => BANDAS_IMPORT_POST_TYPE,
		'post_title'  => $title,
		'post_status' => 'publish',
	), true );

	if ( is_wp_error( $post_id ) ) {
		return array( 'linha' => $linha, 'titulo' => esc_html( $title ), 'status' => 'erro', 'detalhe' => $post_id->get_error_message() );
	}

	update_post_meta( $post_id, 'artist', bandas_import_field( $row, $map, 'artist' ) );
	update_post_meta( $post_id, 'label', bandas_import_field( $row, $map, 'label' ) );
	update_post_meta( $post_id, 'released', bandas_import_parse_released( bandas_import_field( $row, $map, 'released' ) ) );
	update_post_meta( $post_id, 'tracklist', bandas_import_parse_tracklist( bandas_import_field( $row, $map, 'tracklist' ) ) );
	update_post_meta( $post_id, 'links', bandas_import_build_links( $row, $map ) );

	// taxonomias — busca o termo pelo nome; se não existir, cria
	$genre_names = bandas_import_split_multi( bandas_import_field( $row, $map, 'genre' ) );
	if ( $genre_names ) {
		$genre_ids = array();
		foreach ( $genre_names as $name ) {
			$genre_ids[] = bandas_import_get_or_create_term( $name, 'genre' );
		}
		wp_set_object_terms( $post_id, array_filter( $genre_ids ), 'genre' );
	}
	$country_names = bandas_import_split_multi( bandas_import_field( $row, $map, 'country' ) );
	if ( $country_names ) {
		$country_ids = array();
		foreach ( $country_names as $name ) {
			$country_ids[] = bandas_import_get_or_create_term( $name, 'country' );
		}
		wp_set_object_terms( $post_id, array_filter( $country_ids ), 'country' );
	}

	// capa
	$cover_url = bandas_import_field( $row, $map, 'cover_url' );
	$detalhe = 'post ' . $post_id;
	if ( $cover_url ) {
		$attachment_id = media_sideload_image( $cover_url, $post_id, $title, 'id' );
		if ( is_wp_error( $attachment_id ) ) {
			$detalhe .= ' (capa falhou: ' . $attachment_id->get_error_message() . ')';
		} else {
			update_post_meta( $post_id, 'cover', $attachment_id );
		}
	}

	return array( 'linha' => $linha, 'titulo' => esc_html( $title ), 'status' => 'criado', 'detalhe' => $detalhe );
}

/**
 * Busca um termo pelo nome (case-insensitive) numa taxonomia; se não existir, cria.
 * Retorna o term_id, ou false se não conseguir nem achar nem criar.
 */
function bandas_import_get_or_create_term( $name, $taxonomy ) {
	$name = trim( $name );
	if ( '' === $name ) {
		return false;
	}

	// busca exata pelo nome
	$term = get_term_by( 'name', $name, $taxonomy );
	if ( $term && ! is_wp_error( $term ) ) {
		return (int) $term->term_id;
	}

	// fallback: busca case-insensitive (get_term_by é case-sensitive dependendo do collation)
	$existing = get_terms( array(
		'taxonomy'   => $taxonomy,
		'hide_empty' => false,
		'name__like' => $name,
	) );
	if ( ! is_wp_error( $existing ) ) {
		foreach ( $existing as $t ) {
			if ( 0 === strcasecmp( $t->name, $name ) ) {
				return (int) $t->term_id;
			}
		}
	}

	// não achou — cria
	$inserted = wp_insert_term( $name, $taxonomy );
	if ( is_wp_error( $inserted ) ) {
		// se o erro for "termo já existe" (corrida entre requisições), reaproveita o ID informado
		if ( isset( $inserted->error_data['term_exists'] ) ) {
			return (int) $inserted->error_data['term_exists'];
		}
		return false;
	}

	return (int) $inserted['term_id'];
}

function bandas_import_split_multi( $raw ) {
	if ( '' === trim( (string) $raw ) ) {
		return array();
	}
	$parts = preg_split( '/[;,]/', $raw );
	return array_values( array_filter( array_map( 'trim', $parts ) ) );
}

function bandas_import_parse_released( $raw ) {
	$raw = trim( (string) $raw );
	if ( '' === $raw ) {
		return '';
	}
	if ( preg_match( '/^\d{4}$/', $raw ) ) {
		return $raw . '-01-01T00:00:00.000Z';
	}
	$ts = strtotime( $raw );
	if ( $ts ) {
		return gmdate( 'Y-m-d', $ts ) . 'T00:00:00.000Z';
	}
	return $raw; // deixa como veio se não reconhecer o formato
}

function bandas_import_parse_tracklist( $raw ) {
	$raw = trim( (string) $raw );
	if ( '' === $raw ) {
		return '[]';
	}
	if ( '[' === $raw[0] ) {
		json_decode( $raw );
		if ( JSON_ERROR_NONE === json_last_error() ) {
			return $raw; // já é JSON válido
		}
	}
	$tracks = array();
	foreach ( preg_split( '/\r\n|\r|\n/', $raw ) as $line ) {
		$line = trim( $line );
		if ( '' === $line ) {
			continue;
		}

		// formato colado do Discogs: "Nome<TAB>3:15"
		if ( false !== strpos( $line, "\t" ) ) {
			$parts = array_values( array_filter( array_map( 'trim', explode( "\t", $line ) ), function ( $p ) {
				return '' !== $p;
			} ) );
			$last = end( $parts );
			if ( count( $parts ) >= 2 && preg_match( '/^\d{1,3}:\d{2}$/', $last ) ) {
				$name = implode( ' ', array_slice( $parts, 0, -1 ) );
				// remove número de faixa no início SÓ quando seguido de ponto e espaço (ex: "1. Nome"),
				// pra não cortar dígitos que fazem parte do nome (ex: "4AM", "50,000 Kilowatts")
				$name = preg_replace( '/^\d+\.\s+/', '', $name );
				$tracks[] = array( 'name' => $name, 'duration' => $last );
				continue;
			}
		}

		// múltiplas faixas na mesma célula separadas por ";"
		foreach ( explode( ';', $line ) as $sub ) {
			$sub = trim( $sub );
			if ( '' === $sub ) {
				continue;
			}
			if ( preg_match( '/^(.*?)[\s-]+(\d{1,2}:\d{2})\s*$/', $sub, $m ) ) {
				$tracks[] = array( 'name' => trim( $m[1], " -" ), 'duration' => $m[2] );
			} else {
				$tracks[] = array( 'name' => $sub, 'duration' => '' );
			}
		}
	}
	return wp_json_encode( $tracks, JSON_UNESCAPED_UNICODE );
}

function bandas_import_build_links( $row, $map ) {
	$keys = array( 'amazon', 'deezer', 'lastfm', 'spotify', 'youtube', 'wikipedia', 'download' );
	$links = array();
	foreach ( $keys as $k ) {
		// coluna pode nem existir no $map, ou existir e vir vazia/nula na planilha — em qualquer
		// um desses casos o link fica null, sem gerar warning nem quebrar o JSON
		$col = $map[ $k ] ?? null;
		$val = ( $col && isset( $row[ $col ] ) ) ? trim( (string) $row[ $col ] ) : '';
		$links[ $k ] = '' === $val ? null : $val;
	}
	return wp_json_encode( $links, JSON_UNESCAPED_UNICODE );
}