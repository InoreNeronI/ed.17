<?php

/** @author Martin Mozos <martinmozos@gmail.com> */
$app = require '../vendor/var/Resources/script/loader/autoload.php';
//$app->loadClassCache();
$app->handle(Symfony\Component\HttpFoundation\Request::createFromGlobals())->send();
