<?php
$dirbase = get_template_directory();

// Security (load first)
require_once $dirbase . '/security/index.php';

// Admin
require_once $dirbase . '/admin/metaboxes/album.php';
require_once $dirbase . '/admin/metaboxes/taxonomy.php';

// Utils
require_once $dirbase . '/utils/index.php';

// Actions
require_once $dirbase . '/actions/index.php';

// Filters
require_once $dirbase . '/filters/index.php';

// Options
require_once $dirbase . '/options/index.php';

// Posts
require_once $dirbase . '/posts/index.php';

// Endpoints
require_once $dirbase . '/endpoints/auth/index.php';
require_once $dirbase . '/endpoints/user/index.php';
require_once $dirbase . '/endpoints/album/index.php';
require_once $dirbase . '/endpoints/menu/index.php';

// Importer
require_once $dirbase . '/importer/index.php';
