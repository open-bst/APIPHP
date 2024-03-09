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
    public static function getRule($UnionData = []):mixed
    {
        $RuleCode = Common::quickParameter($UnionData, 'rule', '规则');
        $Refresh= Common::quickParameter($UnionData, 'refresh', '刷新',false,false);
        if($Refresh){
            Data::Set([
                'prefix'=>$_SERVER['APIPHP']['Config']['core\Upload']['rule_prefix'],
                'key'=>$RuleCode,
                'value'=>'',
                'hash'=>true,
                'time'=>0
            ]);
        }
        return Data::get([
            'prefix'=>$_SERVER['APIPHP']['Config']['core\Upload']['rule_prefix'],
            'key'=>$RuleCode,
            'hash'=>true,
            'callback'=>function($RuleCode){
                $Result= Db::select([
                    '表'=>[$_SERVER['APIPHP']['Config']['core\Upload']['rule_table']],
                    '字段'=>['code'],
                    '值'=>[$RuleCode],
                    'json'=>['file_type','expand']
                ]);
                if(!$Result){
                    return false;
                }

                Data::Set([
                    'prefix'=>$_SERVER['APIPHP']['Config']['core\Upload']['rule_prefix'],
                    'key'=>$RuleCode,
                    'value'=>$Result,
                    'hash'=>true,
                ]);
                return $Result;
            },
            'argument'=>$RuleCode
        ]);
    }

    //查询文件
    public static function query($UnionData = []): false|array
    {
        $Token = Common::quickParameter($UnionData, 'token', 'token');
        $RuleCode = Common::quickParameter($UnionData, 'rule', '规则');
        $Reference=Common::quickParameter($UnionData, 'reference', '引用',false,false);
        $Refresh= Common::quickParameter($UnionData, 'refresh', '刷新',false,false);
        $Field = Common::quickParameter($UnionData, 'field', '字段',false,[]);

        if($Reference){
            $Config=[
                '表'=>[$_SERVER['APIPHP']['Config']['core\Upload']['file_table']],
                '字段'=>['token'],
                '值'=>[$Token],
                '数据'=>[
                    'reference'=>1
                ],
            ];

            Db::update($Config);
        }
        if($Refresh||$Reference){
            Data::Set([
                'prefix'=>$_SERVER['APIPHP']['Config']['core\Upload']['file_prefix'],
                'key'=>$Token,
                'value'=>'',
                'hash'=>true,
                'time'=>0
            ]);
        }
        $File=Data::get([
            'prefix'=>$_SERVER['APIPHP']['Config']['core\Upload']['file_prefix'],
            'key'=>$Token,
            'hash'=>true,
            'callback'=>function($Argument){
                $Config=[
                    '表'=>[$_SERVER['APIPHP']['Config']['core\Upload']['file_table']],
                    '字段'=>['token'],
                    '值'=>[$Argument['token']],
                ];
                $Result= Db::select($Config);
                if(!$Result){
                    return false;
                }

                Data::Set([
                    'prefix'=>$_SERVER['APIPHP']['Config']['core\Upload']['file_prefix'],
                    'key'=>$Argument['token'],
                    'value'=>$Result,
                    'hash'=>true,
                ]);
                return $Result;
            },
            'argument'=>['token'=>$Token]
        ]);

        if(!$File){
            return false;
        }

        $Rule=self::getRule(['rule'=>$File['rule']]);
        if(!$Rule||$File['rule']!=$RuleCode){
            return false;
        }

        $Return=[
            'path'=>'/asset'.$Rule['save_path'].'/'.$File['save_name'],
            'time'=>$File['time'],
            'data'=>[]
        ];

        foreach ($Field as $V){
            if(isset($File[$V])){
                $Return['data'][$V]=$File[$V];
            }
        }

        return $Return;
    }

    //上传
    public static function load($UnionData = []): string
    {
        $RuleCode = Common::quickParameter($UnionData, 'rule', '规则');
        $Name = Common::quickParameter($UnionData, 'name', '名称');
        $Data = Common::quickParameter($UnionData, 'data', '数据',false,[]);

        $Rule=self::getRule(['rule'=>$RuleCode]);
        if(!$Rule){
            Api::wrong(
                [
                    'level' => 'F',
                    'detail' => 'Error#M.14.0' . "\r\n\r\n @ ".$RuleCode,
                    'code' => 'M.14.0'
                ]
            );
        }
        foreach ($Rule['file_type'] as $V){
            if(!isset($_SERVER['APIPHP']['Config']['core\Upload']['accept'][$V])){
                Api::wrong(
                    [
                        'level' => 'F',
                        'detail' => 'Error#M.14.1' . "\r\n\r\n @ ".$RuleCode,
                        'code' => 'M.14.1'
                    ]
                );
            }
        }

        $Filename= Load::up([
            'field'=>[$Name.',TRUE'],
            'path'=>'/asset'.$Rule['save_path'],
            'type'=>implode(',',$Rule['file_type']),
            'size'=>$Rule['size']
        ]);

        $Token=Tool::uuid(['type'=>'string']);

        $Config=[
            '表'=>[$_SERVER['APIPHP']['Config']['core\Upload']['file_table']],
            '数据'=>[
                'token'=>$Token,
                'original_name'=>$Rule['original']==1?substr($Filename[$Name][0][0],0,500):'',
                'save_name'=>$Filename[$Name][0][1],
                'rule'=>$RuleCode,
                'status'=>$Rule['default_status'],
                'time'=>time()
            ],
        ];
        if(!empty($Data)){
            $Config['数据']=$Config['数据']+$Data;
        }

        Db::insert($Config);

        return $Token;
    }

    public static function getAccept()
    {
        return $_SERVER['APIPHP']['Config']['core\Upload']['accept'];
    }


    public static function __callStatic($Method, $Parameters)
    {
        Common::unknownStaticMethod(__CLASS__, $Method);
    }
}