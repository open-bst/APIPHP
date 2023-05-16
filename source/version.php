<?php
use core\Api;

$Version=_VERSION;
Api::respond([
    'content'=>[
        'data'=>['version'=>$Version]
    ]
]);