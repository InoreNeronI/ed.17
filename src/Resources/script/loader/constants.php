<?php

/** @author Martin Mozos <martinmozos@gmail.com> */
require __DIR__.'/functions.php';

define('CONFIG_DIR', ROOT_DIR.'/src/Resources/config');
define('DATA_DIR', ROOT_DIR.\def::paths()['data_dir']);
define('PUBLIC_DIR', ROOT_DIR.\def::paths()['public_dir']);
define('TEMPLATE_CACHE_DIR', ROOT_DIR.\def::paths()['cache_dir'].'/twig');
define('TEMPLATE_EXTENSION', 'html.twig');
define('TEMPLATE_FILES_DIR', ROOT_DIR.\def::paths()['template_dir']);
define('USER_TABLE', \defDb::userEntity());

return new App\Kernel\BaseKernel(DEBUG);
