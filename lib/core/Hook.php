<?php

namespace core;

/*
  APIPHP开源框架

  ©2023 APIPHP.com

  框架版本号：1.0.0
*/

class Hook
{

    //初始化
    public static function initial()
    {
        $_SERVER['APIPHP']['Hook']=['History'=>[],'List'=>[]];
    }

    //执行方法
    public static function call($UnionData = [])
    {
        $HookName = Common::quickParameter($UnionData, 'name', '名称');
        $Func= Common::quickParameter($UnionData, 'function', '方法');
        $Para= Common::quickParameter($UnionData, 'parameter', '参数',FALSE,[]);

    }

    //执行方法
    public static function reg($UnionData = [])
    {
        $HookName = Common::quickParameter($UnionData, 'name', '名称');
        $Path= Common::quickParameter($UnionData, 'path', '路径');
    }

    public static function __callStatic($Method, $Parameters)
    {
        Common::unknownStaticMethod(__CLASS__, $Method);
    }
}