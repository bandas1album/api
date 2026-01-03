<?php

$dirbase = get_template_directory();

require_once $dirbase . '/endpoints/user/get.php';
require_once $dirbase . '/endpoints/user/get_favorited.php';
require_once $dirbase . '/endpoints/user/get_listened.php';
require_once $dirbase . '/endpoints/user/post.php';
require_once $dirbase . '/endpoints/user/patch_favorited.php';
require_once $dirbase . '/endpoints/user/patch_listened.php';
require_once $dirbase . '/endpoints/user/update.php';
require_once $dirbase . '/endpoints/user/delete.php';