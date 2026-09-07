<?php

/**
 * Normaliza URL ou iframe colado do Spotify (episódio/show) para URL de embed.
 *
 * @param mixed $raw
 * @return string URL https://open.spotify.com/embed/{episode|show}/{id} ou vazio
 */
function api_normalize_spotify_embed($raw) {
  $raw = trim((string) $raw);
  if ($raw === '') {
    return '';
  }

  // Aceita o HTML completo do iframe do Spotify.
  if (preg_match('/\bsrc\s*=\s*["\']([^"\']+)["\']/i', $raw, $m)) {
    $raw = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
  }

  $raw = esc_url_raw($raw);
  if ($raw === '') {
    return '';
  }

  if (
    !preg_match(
      '#(?:https?:)?//open\.spotify\.com/(?:embed/)?(episode|show)/([a-zA-Z0-9]+)#i',
      $raw,
      $m
    )
  ) {
    return '';
  }

  $type = strtolower($m[1]);
  $id = $m[2];

  return 'https://open.spotify.com/embed/' . $type . '/' . $id;
}
