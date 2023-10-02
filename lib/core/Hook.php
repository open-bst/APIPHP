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
        $_SERVER['APIPHP']['Runtime']['core/Hook']=[];
        $FilePath=_ROOT.'/source/hook.apiphp';
        if(file_exists($FilePath)){
            require $FilePath;
        }
    }

    //添加钩子方法
    public static function add($UnionData = [])
    {
        $List = Common::quickParameter($UnionData, 'list', '列表',true,NULL,true);
        foreach ($List as $K => $V){
            if(!isset($_SERVER['APIPHP']['Runtime']['core/Hook'][$K])){
                $_SERVER['APIPHP']['Runtime']['core/Hook'][$K]=[];
            }
            $_SERVER['APIPHP']['Runtime']['core/Hook'][$K]=array_merge($_SERVER['APIPHP']['Runtime']['core/Hook'][$K],$V);
        }
    }

    //调用钩子
    public static function call($UnionData = [])
    {
        $Name = Common::quickParameter($UnionData, 'name', '名称', true, null, true);
        $Para= Common::quickParameter($UnionData, 'parameter', '参数',false,[]);
        $HookList=$_SERVER['APIPHP']['Runtime']['core/Hook'];
        if(!empty($HookList[$Name])){
            foreach ($HookList[$Name] as $V){
                $Func='plugin\\'.$V;
                if(!is_callable($Func)){
                    Api::wrong(['level' => 'F', 'detail' => 'Error#M.5.0' . "\r\n\r\n @ " . $Name .' @ ' .$Func, 'code' => 'M.5.0']);
                }
                $HookReturn=call_user_func($Func,$Para);
                if(is_array($HookReturn)){
                    $Para = $HookReturn;
                }
            }
        }
        return $Para;
    }

    public static function __callStatic($Method, $Parameters)
    {
        Common::unknownStaticMethod(__CLASS__, $Method);
    }
}