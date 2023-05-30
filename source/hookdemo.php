<?php

use core\Api;
use core\Hook;

//用户信息
$User = [
    'id' => '',
    'name' => '',
    'token' => ''
];

//用户积分
$UserPoints = 19;

//假定用户此时注册成功
$User['id'] = 1003;
$User['name'] = 'Jack';

//通过Hook获取用户的Token以及用户积分+10
$Result = Hook::call(
    [
        'name' => 'app_user-login_success',
        'parameter' => [
            'user' => $User,
            'points' => $UserPoints
        ]
    ]
);

$User = $Result['user'];
$UserPoints = $Result['points'];

//后续处理流程，将用户Token和积分传递给前端
Api::respond([
    'content' => [
        'data' => ['token' => $User['token'], 'points' => $UserPoints]
    ]
]);