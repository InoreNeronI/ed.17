<?php

/** @author Martin Mozos <martinmozos@gmail.com> */
$kernel = require '../vendor/var/Resources/script/loader.php';
//$kernel->loadClassCache();
$kernel->handle(Symfony\Component\HttpFoundation\Request::createFromGlobals())->send();
