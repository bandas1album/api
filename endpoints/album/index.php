<?php

$dirbase = get_template_directory();

require_once $dirbase . '/endpoints/album/get_by_slug.php';
require_once $dirbase . '/endpoints/album/get_all.php';
require_once $dirbase . '/endpoints/album/get_search.php';
require_once $dirbase . '/endpoints/album/post.php';
require_once $dirbase . '/endpoints/album/update.php';
require_once $dirbase . '/endpoints/album/delete.php';