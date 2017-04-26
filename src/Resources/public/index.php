<?php

/** @author Martin Mozos <martinmozos@gmail.com> */
require '../src/Resources/script/loader/autoload.php';

// Other files' path and variables
putenv('DATA_DIR='.getenv('ROOT_DIR').\def::paths()['data_dir']);
putenv('HTDOCS_DIR='.getenv('ROOT_DIR').\def::paths()['htdocs_dir']);
putenv('PUBLIC_DIR='.getenv('ROOT_DIR').\def::paths()['public_resources_dir']);
putenv('TEMPLATE_CACHE_DIR='.getenv('ROOT_DIR').\def::paths()['cache_dir'].'/twig');
putenv('TEMPLATE_FILES_DIR='.getenv('ROOT_DIR').\def::paths()['templates_dir']);
putenv('TRANSLATIONS_DIR='.getenv('ROOT_DIR').\def::paths()['translations_dir']);
putenv('UPLOADS_DIR='.getenv('ROOT_DIR').\def::paths()['uploads_dir']);
putenv('USER_TABLE='.\defDb::userEntity());
putenv('EXTRA_TABLE='.\defDb::extraEntity());

$app = new Kernel\KernelBase(getenv('DEBUG'));
//$app->loadClassCache();

// Handles and sends a Request object based on the current PHP global variables
$app->handle(Symfony\Component\HttpFoundation\Request::createFromGlobals())->send();
