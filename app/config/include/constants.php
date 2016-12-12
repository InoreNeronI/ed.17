<?php

require __DIR__.'/functions.php';

define('CONFIG_DIR', LOADER_DIR.'/config');
define('DATA_DIR', LOADER_DIR.\def::paths()['data_dir']);
define('PUBLIC_DIR', dirname(LOADER_DIR).'/public');
define('TEMPLATE_CACHE_DIR', LOADER_DIR.\def::paths()['cache_dir'].'/twig');
define('TEMPLATE_EXTENSION', 'html.twig');
define('TEMPLATE_FILES_DIR', LOADER_DIR.\def::paths()['template_dir']);
define('TRANSLATIONS_DIR', LOADER_DIR.\def::paths()['translation_dir']);
define('USER_TABLE', \def::dbCredentials()['user_table']);
define('DEBUG', in_array(@$_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1'/*, '123.456.789.0'*/]) ? true : false);
