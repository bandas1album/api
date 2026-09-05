<?php

/**
 * Extrai um YouTube video ID a partir de URL ou ID puro.
 */
function api_extract_youtube_id($input) {
  $input = trim((string) $input);
  if ($input === '') {
    return '';
  }

  if (preg_match('/^[a-zA-Z0-9_-]{11}$/', $input)) {
    return $input;
  }

  $patterns = [
    '/(?:youtube\.com\/watch\?(?:[^#]*&)?v=|youtu\.be\/|youtube\.com\/embed\/|youtube\.com\/shorts\/|youtube\.com\/live\/|music\.youtube\.com\/watch\?(?:[^#]*&)?v=)([a-zA-Z0-9_-]{11})/i',
    '/[?&]v=([a-zA-Z0-9_-]{11})/i',
  ];

  foreach ($patterns as $pattern) {
    if (preg_match($pattern, $input, $matches)) {
      return $matches[1];
    }
  }

  return '';
}

/**
 * Normaliza um item de tracklist para o formato da API.
 *
 * @param mixed $track
 * @return array{name: string, duration: string, youtube_url: string, youtube_id: string, description: string, lyrics: string}|null
 */
function api_normalize_album_track($track) {
  if (!is_array($track)) {
    return null;
  }

  $name = sanitize_text_field($track['name'] ?? '');
  if ($name === '') {
    return null;
  }

  $youtube_url_raw = trim((string) ($track['youtube_url'] ?? ''));
  // esc_url_raw pode falhar em alguns formatos; extrai o ID antes e reconstrói URL canônica
  $youtube_id_from_fields = api_extract_youtube_id(
    $youtube_url_raw !== '' ? $youtube_url_raw : ($track['youtube_id'] ?? '')
  );

  $youtube_url = '';
  if ($youtube_id_from_fields !== '') {
    $youtube_url = 'https://www.youtube.com/watch?v=' . $youtube_id_from_fields;
  } elseif ($youtube_url_raw !== '') {
    $youtube_url = esc_url_raw($youtube_url_raw) ?: '';
  }

  $youtube_id = $youtube_id_from_fields !== ''
    ? $youtube_id_from_fields
    : api_extract_youtube_id($youtube_url !== '' ? $youtube_url : ($track['youtube_id'] ?? ''));

  return [
    'name' => $name,
    'duration' => sanitize_text_field($track['duration'] ?? ''),
    'youtube_url' => $youtube_url,
    'youtube_id' => $youtube_id,
    'description' => sanitize_textarea_field($track['description'] ?? ''),
    'lyrics' => sanitize_textarea_field($track['lyrics'] ?? ''),
  ];
}

/**
 * @param mixed $tracklist
 * @return array<int, array{name: string, duration: string, youtube_url: string, youtube_id: string, description: string, lyrics: string}>
 */
function api_normalize_album_tracklist($tracklist) {
  if (is_string($tracklist)) {
    $decoded = json_decode($tracklist, true);
    $tracklist = is_array($decoded) ? $decoded : [];
  }

  if (!is_array($tracklist)) {
    return [];
  }

  $normalized = [];
  foreach ($tracklist as $track) {
    $item = api_normalize_album_track($track);
    if ($item !== null) {
      $normalized[] = $item;
    }
  }

  return $normalized;
}
