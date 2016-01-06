<?php

require_once __DIR__.'/../vendor/autoload.php';

use Humbug\FileGetContents;

var_dump(FileGetContents::getSystemCaRootBundlePath());
var_dump(FileGetContents::getNextRequestHeaders());
var_dump(FileGetContents::getLastResponseHeaders());

var_dump(humbug_get_contents('https://puli.io/download/versions.json'));

var_dump(FileGetContents::getSystemCaRootBundlePath());
var_dump(FileGetContents::getNextRequestHeaders());
var_dump(FileGetContents::getLastResponseHeaders());
