<?php

$dirbase = get_template_directory();

require_once $dirbase . '/endpoints/auth/get.php';
require_once $dirbase . '/endpoints/auth/post.php';
require_once $dirbase . '/endpoints/auth/post_lost_password.php';
require_once $dirbase . '/endpoints/auth/post_reset_password.php';
require_once $dirbase . '/endpoints/auth/update.php';
require_once $dirbase . '/endpoints/auth/delete.php';