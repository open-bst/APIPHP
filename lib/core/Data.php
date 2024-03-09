<?php

namespace core;


/*
  APIPHP开源框架

  ©2024 APIPHP.com

  框架版本号：1.0.0
*/

use Redis;

class Data
{
    private static string $Handle;
    private static Redis $Connect;

    private static function initial(): void
    {
        if (!empty($_SERVER['APIPHP']['Runtime']['core\Data']['initial'])) {
            return;
        }

        self::$Handle = strtolower($_SERVER['APIPHP']['Config']['core\Data']['handle']);

        if (self::$Handle == 'redis') {
            self::$Connect=Redis_::connect(['name'=>$_SERVER['APIPHP']['Config']['core\Data']['connect']['redis'],'return_connect'=>true]);
        }

        $_SERVER['APIPHP']['Runtime']['core\Data']['initial'] = true;
    }

    //设置
    public static function set($UnionData = []): bool
    {
        $K = Common::quickParameter($UnionData, 'key', '键');
        $Value = Common::quickParameter($UnionData, 'value', '值');
        $Time = Common::quickParameter($UnionData, 'time', '时间', false, 3600);
        $Prefix = Common::quickParameter($UnionData, 'prefix', '前缀', false, '');
        $Hash= Common::quickParameter($UnionData, 'hash', 'hash', false,false);

        self::initial();

        if ($K == '') {
            return false;
        }
        if ($Value == null) {
            $Value = '';
        }
        if (!is_bool($Value) && !is_array($Value) && !is_int($Value) && !is_float($Value) && !is_string(
                $Value
            ) && !is_object($Value)) {
            return false;
        }
        $Time = intval($Time);
        if (self::$Handle == 'file') {
            return self::setByFile($Prefix, $K, $Value, $Time);
        }
        if (self::$Handle == 'redis') {
            return self::setByRedis($Prefix, $K, $Value,$Hash, $Time);
        }
        return true;
    }

    //获取
    public static function get($UnionData = []):mixed
    {
        $K = Common::quickParameter($UnionData, 'key', '键', true, null, true);
        $Prefix = Common::quickParameter($UnionData, 'prefix', '前缀', false, '');
        $Callback = Common::quickParameter($UnionData, 'callback', '回调', false);
        $Argument = Common::quickParameter($UnionData, 'argument', '参数', false);
        $Hash= Common::quickParameter($UnionData, 'hash', 'hash', false,false);

        self::initial();

        if ($K == '') {
            return null;
        }
        if (self::$Handle == 'file') {
            $Result = self::getByFile($Prefix, $K);
        } elseif (self::$Handle == 'redis') {
            $Result = self::getByRedis($Prefix, $K,$Hash);
        } else {
            return null;
        }

        if ($Result === null && is_callable($Callback)) {

            return $Callback($Argument);
        } else {
            return $Result;
        }
    }

    //变量转字符串
    private static function varToStr($Value): string
    {
        return serialize($Value);
    }

    //字符串转变量
    private static function strToVar($String):mixed
    {
        return unserialize($String);
    }

    //获取文件缓存路径
    private static function getFilePath($Prefix, $K, $Mkdir = false): string
    {
        $MD5 = md5($K);
        $Path = _ROOT . '/temp/data/' . $Prefix;
        $Level = intval($_SERVER['APIPHP']['Config']['core\Data']['connect']['file']['level']);
        if ($Level < 1) {
            $Level = 0;
        }
        if ($Level > 15) {
            $Level = 15;
        }
        for ($i = 0; $i < $Level; $i++) {
            $Path .= '/' . $MD5[0] . $MD5[1];
            $MD5 = substr($MD5, 2);
        }
        $Path = str_replace(['\\', '//'], ['/', '/'], $Path);
        if ($Mkdir) {
            if (!is_dir($Path)) {
                mkdir($Path, 0777, true);
            }
        }

        $Path .= '/' . $MD5 . '.tmp';
        if ($Path[0] == '/') {
            $Path = substr($Path, 1);
        }
        return $Path;
    }

    //设置文件緩存
    private static function setByFile($Prefix, $K, $Value, $Time): bool
    {
        if ($Time < 1) {
            return self::deleteByFile($K, $Prefix);
        }
        $Cache = intval(_TIME) + $Time . "\r\n" . self::varToStr($Value);
        $FileHandle = fopen(self::getFilePath($Prefix, $K, true), 'w');
        if (!$FileHandle) {
            Api::wrong(['level' => 'F', 'detail' => 'Error#M.12.0', 'code' => 'M.12.0']);
        }
        fwrite($FileHandle, $Cache);
        fclose($FileHandle);
        return true;
    }

    //删除文件緩存
    private static function deleteByFile($K, $Prefix, $Path = ''): bool
    {
        if ($Path == '') {
            $Path = self::getFilePath($Prefix, $K);
        }
        if (file_exists($Path)) {
            $Result = unlink($Path);
        } else {
            $Result = true;
        }
        return $Result;
    }

    //获取文件缓存
    private static function getByFile($Prefix, $K):mixed
    {
        $FilePath = self::getFilePath($Prefix, $K);
        if (!file_exists($FilePath)) {
            return null;
        }
        $Cache = file_get_contents($FilePath);
        if ($Cache === false) {
            Api::wrong(['level' => 'F', 'detail' => 'Error#M.12.4', 'code' => 'M.12.4']);
        }
        $ExpTime = intval(strtok($Cache, "\r\n"));
        if ($ExpTime <= 0 || $ExpTime < intval(_TIME)) {
            if (mt_rand(1, $_SERVER['APIPHP']['Config']['core\Data']['connect']['file']['clean']) == 1) {
                self::deleteByFile($K, $Prefix, $FilePath);
            }
            return null;
        }
        return self::strToVar(strtok("\r\n"));
    }

    //设置Redis緩存
    private static function setByRedis($Prefix, $Key, $Value,$Hash, $Time): bool
    {
        if (!$Hash&&!empty($Prefix)) {
            $Prefix .= '_';
        }
        if ($Time < 1) {
            if(!$Hash||empty($Prefix)){
                self::$Connect->del($Prefix . md5($Key));
            }
            else{
                self::$Connect->hDel($Prefix,md5($Key));
            }
            return true;
        }
        $Data = self::varToStr($Value);
        if(!$Hash||empty($Prefix)){
            $Key=$Prefix . md5($Key);
            self::$Connect->set($Key, $Data);
            self::$Connect->expire($Key, $Time);
        }
        else{
            self::$Connect->hSet($Prefix,md5($Key), $Data);
        }
        return true;
    }

    //获取Redis缓存
    private static function getByRedis($Prefix, $Key,$Hash):mixed
    {
        if (!$Hash&&!empty($Prefix)) {
            $Prefix .= '_';
        }
        if(!$Hash||empty($Prefix)){
            $Data = self::$Connect->get($Prefix . md5($Key));
        }
        else{
            $Data = self::$Connect->hGet($Prefix,md5($Key));
        }

        if (!$Data) {
            return null;
        }
        return self::strToVar($Data);
    }

    public static function __callStatic($Method, $Parameters)
    {
        Common::unknownStaticMethod(__CLASS__, $Method);
    }
}