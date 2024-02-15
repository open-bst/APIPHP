<?php

namespace core;

/*
  APIPHP开源框架

  ©2024 APIPHP.com

  框架版本号：1.0.0
*/

class Upload
{
    //获取规则
    public static function getRule($UnionData = [])
    {
        $Code = Common::quickParameter($UnionData, 'code', '代码');
        $Refresh= Common::quickParameter($UnionData, 'refresh', '刷新',false,false);
        if($Refresh){
            Data::Set([
                'prefix'=>'upload_rules',
                'key'=>$Code,
                'value'=>'',
                'time'=>0
            ]);
        }
        return Data::get([
            'prefix'=>'upload_rules',
            'key'=>$Code,
            'callback'=>function($Code){
                $Result= Db::select([
                    '表'=>[$_SERVER['APIPHP']['Config']['core\Upload']['ruleTable']],
                    '字段'=>['code'],
                    '值'=>[$Code],
                ]);
                if(!$Result){
                    return false;
                }

                Data::Set([
                    'prefix'=>'upload_rules',
                    'key'=>$Code,
                    'value'=>$Result,
                    'time'=>86400000
                ]);
                return $Result;
            },
            'argument'=>$Code
        ]);
    }

    //查询文件
    public static function query($UnionData = []): false|array
    {
        $Token = Common::quickParameter($UnionData, 'token', 'token');
        $Code = Common::quickParameter($UnionData, 'code', '代码');
        $Refresh= Common::quickParameter($UnionData, 'refresh', '刷新',false,false);

        if($Refresh){
            Data::Set([
                'prefix'=>'upload_files',
                'key'=>$Token,
                'value'=>'',
                'time'=>0
            ]);
        }
        $File=Data::get([
            'prefix'=>'upload_files',
            'key'=>$Token,
            'callback'=>function($Token){
                $Result= Db::select([
                    '表'=>[$_SERVER['APIPHP']['Config']['core\Upload']['fileTable']],
                    '字段'=>['token'],
                    '值'=>[$Token],
                ]);
                if(!$Result){
                    return false;
                }

                Data::Set([
                    'prefix'=>'upload_files',
                    'key'=>$Token,
                    'value'=>$Result,
                    'time'=>86400000
                ]);
                return $Result;
            },
            'argument'=>$Token
        ]);

        if(!$File){
            return false;
        }

        $Rule=self::getRule(['code'=>$File['rule']]);
        if(!$Rule||$File['rule']!=$Code){
            return false;
        }

        return ['path'=>'/asset'.$Rule['save_path'].'/'.$File['save_name'],'uid'=>$File['uid']];
    }

    //上传
    public static function load($UnionData = []): string
    {
        $Code = Common::quickParameter($UnionData, 'code', '代码');
        $Field = Common::quickParameter($UnionData, 'field', '字段');
        $UID = Common::quickParameter($UnionData, 'uid', 'uid',false,'');

        $Rule=self::getRule(['code'=>$Code]);
        if(!$Rule){
            Api::wrong(
                [
                    'level' => 'F',
                    'detail' => 'Error#M.14.0' . "\r\n\r\n @ ".$Code,
                    'code' => 'M.14.0'
                ]
            );
        }
        $Accept=json_decode($Rule['file_type'], true);
        foreach ($Accept as $V){
            if(!in_array($V,$_SERVER['APIPHP']['Config']['core\Upload']['accept'])){
                Api::wrong(
                    [
                        'level' => 'F',
                        'detail' => 'Error#M.14.1' . "\r\n\r\n @ ".$Code,
                        'code' => 'M.14.1'
                    ]
                );
            }
        }

        $Filename=\core\Load::up([
            'field'=>[$Field.',TRUE'],
            'path'=>'/asset'.$Rule['save_path'],
            'type'=>implode(',',$Accept),
            'size'=>$Rule['size']
        ]);

        $Token=Tool::uuid(['type'=>'string']);

        Db::insert([
            '表'=>[$_SERVER['APIPHP']['Config']['core\Upload']['fileTable']],
            '数据'=>[
                'token'=>$Token,
                'uid'=>$UID,
                'original_name'=>$Rule['original']==1?substr($Filename[$Field][0][0],0,500):'',
                'save_name'=>$Filename[$Field][0][1],
                'rule'=>$Code,
                'status'=>$Rule['default_status'],
                'time'=>time()
            ],
        ]);

        return $Token;
    }


    public static function __callStatic($Method, $Parameters)
    {
        Common::unknownStaticMethod(__CLASS__, $Method);
    }
}