<?php

/** @author Martin Mozos <martinmozos@gmail.com> */
if (PHP_VERSION_ID < 50400) {
    /* @throw \Exception */
    throw new \Exception('At least PHP 5.4 is required; using the latest version is highly recommended.');
}

if (!getenv('ROOT_DIR')) {
    putenv('ROOT_DIR='.dirname(__DIR__));
}

/* @var Composer\Autoload\ClassLoader */
require getenv('ROOT_DIR').'/src/Resources/script/loader/autoload.php';

if (getenv('DEBUG')) {
    Symfony\Component\Debug\Debug::enable();
}

if (is_file(getenv('ROOT_DIR').'/.env')) {
    (new \Symfony\Component\Dotenv\Dotenv())->load(getenv('ROOT_DIR').'/.env');
}
require getenv('ROOT_DIR').'/src/Resources/script/loader/functions.php';
putenv('CONFIG_DIR='.getenv('ROOT_DIR').\def::paths()['config_dir']);
putenv('DATA_DIR='.getenv('ROOT_DIR').\def::paths()['data_dir']);
putenv('HTDOCS_DIR='.getenv('ROOT_DIR').\def::paths()['htdocs_dir']);
putenv('PUBLIC_DIR='.getenv('ROOT_DIR').\def::paths()['public_resources_dir']);
putenv('TEMPLATE_CACHE_DIR='.getenv('ROOT_DIR').\def::paths()['cache_dir'].'/twig');
putenv('TEMPLATE_EXTENSION=html.twig');
putenv('TEMPLATE_FILES_DIR='.getenv('ROOT_DIR').\def::paths()['templates_dir']);
putenv('TRANSLATIONS_DIR='.getenv('ROOT_DIR').\def::paths()['translations_dir']);
putenv('UPLOADS_DIR='.getenv('ROOT_DIR').\def::paths()['uploads_dir']);
putenv('USER_TABLE='.\defDb::userEntity());
putenv('EXTRA_TABLE='.\defDb::extraEntity());

$app = new Kernel\KernelBase(getenv('DEBUG'));
//$app->loadClassCache();

// Handles and sends a Request object based on the current PHP global variables
$app->handle(Symfony\Component\HttpFoundation\Request::createFromGlobals())->send();
