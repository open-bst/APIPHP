<?php

namespace core;

/*
  APIPHP开源框架

  ©2023 APIPHP.com

  框架版本号：1.0.0
*/

class Ftp
{
    private static $Instance;

    //连接
    private static function connect(){
        if(empty(self::$DbHandle)){
            self::$Instance = ftp_connect(
                $_SERVER['APIPHP']['Config']['core\Ftp']['server'],
                $_SERVER['APIPHP']['Config']['core\Ftp']['port'],
                $_SERVER['APIPHP']['Config']['core\Ftp']['timeout']
            );
            $Login = ftp_login(
                self::$Instance,
                $_SERVER['APIPHP']['Config']['core\Ftp']['user'],
                $_SERVER['APIPHP']['Config']['core\Ftp']['password']
            );
            if ((!self::$Instance) || (!$Login)) {
                Api::wrong(['level' => 'F', 'detail' => 'Error#M.1.0', 'code' => 'M.1.0']);
            }
        }
    }

    //上传
    public static function up($UnionData = []): bool
    {
        self::connect();
        $From = Common::quickParameter($UnionData, 'from', '本地路径');
        $To = Common::quickParameter($UnionData, 'to', '远程路径');

        $From = Common::diskPath($From);

        $Result = ftp_put(self::$Instance, $To, $From, FTP_ASCII);
        ftp_close(self::$Instance);
        return $Result;
    }

    //下载
    public static function down($UnionData = []): bool
    {
        self::connect();
        $From = Common::quickParameter($UnionData, 'from', '远程路径');
        $To = Common::quickParameter($UnionData, 'to', '本地路径');

        $To = Common::diskPath($To);

        $Result = ftp_get(self::$Instance, $To, $From, FTP_ASCII);
        ftp_close(self::$Instance);
        return $Result;
    }

    public static function __callStatic($Method, $Parameters)
    {
        Common::unknownStaticMethod(__CLASS__, $Method);
    }
}