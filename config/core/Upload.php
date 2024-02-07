<?php

$_SERVER['APIPHP']['Config']['core\Upload'] = [
    'ruleTable'=>'sys_upload_rules',
    'fileTable'=>'file_uploads',
    'accept' => [
        'jpeg',
        'jpg',
        'png',
        'gif',
        'webp',
        'pdf',
        'txt',
        'doc',
        'docx',
        'xls',
        'xlsx',
        'ppt',
        'pptx',
        'md',
        'zip',
        'gz',
        'rar',
        '7z',
        'mp3',
        'mp4',
        'avi',
        'rmvb',
        'mpeg'
    ]
];