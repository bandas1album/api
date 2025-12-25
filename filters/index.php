<?php

add_filter('rest_url_prefix', function() {
  return 'json';
});