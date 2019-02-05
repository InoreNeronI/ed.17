<?php

/* @author Martin Mozos <martinmozos@gmail.com> */
// Set php-script files' path
putenv('SCRIPTS_DIR='.__DIR__);
// Set resources dir
putenv('RESOURCES_DIR='.dirname(getenv('SCRIPTS_DIR')));
// Set root dir
putenv('ROOT_DIR='.(dirname(dirname(dirname(getenv('RESOURCES_DIR'))))));
// Set child dirs
putenv('CONFIG_DIR='.getenv('RESOURCES_DIR').'/config');
putenv('PUBLIC_DIR='.getenv('RESOURCES_DIR').'/public');
putenv('TEMPLATE_FILES_DIR='.getenv('RESOURCES_DIR').'/view');
putenv('TRANSLATIONS_DIR='.getenv('RESOURCES_DIR').'/translation');

// Require error handler
require getenv('SCRIPTS_DIR').'/misc/errorHandler.php';

// Require loader if needed
/* @var Composer\Autoload\ClassLoader */
if (!class_exists('ClassLoader') && !$loader = require(getenv('ROOT_DIR').'/vendor/autoload.php')) {
    /* @throw \Exception */
    throw new \Exception(
        'You must set up the project dependencies, run the following commands:'.PHP_EOL.
        'curl -s http://getcomposer.org/installer | php'.PHP_EOL.
        'php composer.phar install'.PHP_EOL);
}

// Require classes and functions
require getenv('SCRIPTS_DIR').'/misc/functions.php';

// Other files' path and variables
putenv('TEMPLATE_CACHE_DIR='.getenv('ROOT_DIR').\def::paths()['cache_dir'].'/twig');
putenv('UPLOADS_DIR='.getenv('ROOT_DIR').\def::paths()['uploads_dir']);
putenv('HTDOCS_DIR='.getenv('ROOT_DIR').\def::paths()['htdocs_dir']);
putenv('DATA_DIR='.getenv('ROOT_DIR').\def::paths()['data_dir']);
putenv('USER_TABLE='.\defDb::userEntity());
putenv('EXTRA_TABLE='.\defDb::extraEntity());

// @see http://stackoverflow.com/a/5879078
if (getenv('DEBUG') && 'false' !== getenv('DEBUG')) {
    putenv('DEBUG='.true);
    Symfony\Component\Debug\Debug::enable();
} else {
    putenv('DEBUG='.false);
}

if (!getenv('SESSION_STORAGE')) {
    putenv('SESSION_STORAGE=filesystem');
}

return new Kernel\KernelBase((bool) getenv('DEBUG'));
