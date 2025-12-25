<?php

// remove_action('rest_api_init', 'create_initial_rest_routes', 99);

add_action('jwt_auth_expire', function() {
  return time() + (60 * 60 * 24); // 24h
});