<?php

namespace core;

/*
  APIPHP开源框架

  ©2024 APIPHP.com

  框架版本号：1.0.0
*/

use Throwable;

class Redis_
{
    private static $Connect;

    private static function initial($Name): bool
    {
        if (!empty($_SERVER['APIPHP']['Runtime']['core\Redis_']['initial'])) {
            return true;
        }

        self::connect($Name);

        $_SERVER['APIPHP']['Runtime']['core\Redis_']['initial'] = true;
        return true;
    }



    //连接Redis
    public static function connect($Name,$GetConnent=false): \Redis
    {
        $Connect= new \Redis();


        $Config=$_SERVER['APIPHP']['Config']['core\Redis_']['connect'];
        if(!isset($Config[$Name])){
            Api::wrong(['level' => 'F', 'detail' => 'Error#M.1.0' . "\r\n\r\n @ ".$Name, 'code' => 'M.1.0']);
        }

        try {
            $Connect->connect(
                $Config[$Name]['address'],
                $Config[$Name]['port'],
                $Config[$Name]['timeout']
            );
        } catch (Throwable $t) {
            Api::wrong(['level' => 'F', 'detail' => 'Error#M.1.1', 'code' => 'M.1.1']);
        }
        if ($Config[$Name]['password'] != '') {
            try {
                $Connect->auth(
                    $Config[$Name]['password']
                ) ?: Api::wrong(
                    ['level' => 'F', 'detail' => 'Error#M.1.2', 'code' => 'M.1.2']
                );
            } catch (\RedisException $e) {
            }
        }
        try {
            $Connect->select(
                $Config[$Name]['dbnumber']
            ) ?: Api::wrong(
                ['level' => 'F', 'detail' => 'Error#M.1.3', 'code' => 'M.1.3']
            );
        } catch (\RedisException $e) {
        }
        if(!$GetConnent){
            self::$Connect=$Connect;
        }
        return $Connect;
    }

    public static function __callStatic($Method, $Parameters)
    {
        Common::unknownStaticMethod(__CLASS__, $Method);
    }
}