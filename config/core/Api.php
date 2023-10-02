<?php

$_SERVER['APIPHP']['Config']['core\Api'] = [
    'template' => [
        'code' => 1,
        'status' => 'success',
        'message' => '',
        'data' => [],
    ],
    'wrong' => [
        'style' => [
            'code' => '{code}',
            'status' => 'error',
            'message' => '{info}',
            'data' => '{stack}',
            'time' => '{time}',
        ],
        'log' => 'SFA',
        'respond' => 'U',
        'replace' => [

        ],
        'ignore' => [],
    ],
    'document' => true
];