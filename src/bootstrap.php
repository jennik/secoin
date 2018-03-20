<?php

const PROJ_ROOT = __DIR__ . '/../';

function myIp()
{
    return getHostByName(getHostName());
}

spl_autoload_register(function ($className) {
    include_once __DIR__ . '/' . $className . '.php';
});