<?php
namespace plugin;

use core\Tool;

class User
{
    public static function loignSuccess($Para)
    {
        $Para['user']['token'] = Tool::uuid();
        $Para['points'] = $Para['points'] + 10;
        return $Para;
    }
}