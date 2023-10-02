<?php

namespace core;

/*
  APIPHP开源框架

  ©2023 APIPHP.com

  框架版本号：1.0.0
*/

use CURLFile;

class Tool
{

    //随机字符
    public static function random($UnionData = [])
    {
        $Mode = Common::quickParameter($UnionData, 'mode', '模式', false, 'AaN');
        $StringLength = Common::quickParameter($UnionData, 'length', '长度', false, 32);

        $String = null;
        $NWord = '0123456789';
        $AUpperWord = 'QWERTYUIOPASDFGHJKLZXCVBNM';
        $ALowerWord = 'qwertyuiopasdfghjklzxcvbnm';
        $Word = null;
        if (strstr($Mode, 'A')) {
            $Word .= $AUpperWord;
        }
        if (strstr($Mode, 'a')) {
            $Word .= $ALowerWord;
        }
        if (strstr($Mode, 'N')) {
            $Word .= $NWord;
        }
        if (empty($Mode)) {
            $Word = $NWord . $ALowerWord . $AUpperWord;
        }
        if (!empty($Word)) {
            for ($n = 0; $n < $StringLength; $n++) {
                $Random = mt_rand(0, strlen($Word) - 1);
                $String .= $Word[$Random];
            }
        }
        return $String;
    }

    //生成UUID
    public static function uuid($UnionData = []): string
    {
        $MD5 = Common::quickParameter($UnionData, 'md5', 'md5', false, false);
        $Return = md5(
            memory_get_usage() . self::random() . uniqid('', true) . mt_rand(1, 99999) . $_SERVER['REMOTE_ADDR']
        );

        if (!$MD5) {
            $Return =
                '{' .
                substr($Return, 0, 8) . '-' .
                substr($Return, 8, 4) . '-' .
                substr($Return, 12, 4) . '-' .
                substr($Return, 16, 4) . '-' .
                substr($Return, 20, 12) .
                '}';
        }

        return $Return;
    }

    //获取Header指定字段的值
    public static function getHeader($UnionData = []): array
    {
        $Field = Common::quickParameter($UnionData, 'field', '字段', true, null, true);
        $ReturnArray = [];
        foreach ($Field as $V) {
            $FieldName = 'HTTP_' . str_replace('-', '_', strtoupper($V));

            if (isset($_SERVER[$FieldName])) {
                $ReturnArray[$V] = $_SERVER[$FieldName];
            }
        }
        return $ReturnArray;
    }

    //向目标地址发送数据
    public static function send($UnionData = [])
    {
        $Url = Common::quickParameter($UnionData, 'url', '地址', true, null, true);
        $Mode = Common::quickParameter($UnionData, 'mode', '模式', false, 'GET');
        $Data = Common::quickParameter($UnionData, 'data', '数据', false, []);
        $File = Common::quickParameter($UnionData, 'file', '文件', false, []);
        $Headers = Common::quickParameter($UnionData, 'header', 'header', false, []);
        $Encode = Common::quickParameter($UnionData, 'encode', '编码', false, true);
        $Timeout = Common::quickParameter($UnionData, 'timeout', '超时时间', false, 15);
        $SSL = Common::quickParameter($UnionData, 'ssl', 'ssl', false, false);

        $Mode = strtoupper($Mode);
        if ($Mode != 'GET' && $Mode != 'POST' && $Mode != 'PUT' && $Mode != 'DELETE') {
            return false;
        }

        if (!function_exists('curl_init')) {
            Api::wrong(['level' => 'F', 'detail' => 'Error#M.6.0', 'code' => 'M.6.0']);
        }

        $SendData = [];
        $Handle = curl_init();

        if ($Mode == 'GET') {
            if (!empty($Data)) {
                if (is_array($Data)) {
                    $Data = http_build_query($Data);
                }
                $Url .= '?' . $Data;
            }
        }

        curl_setopt($Handle, CURLOPT_CUSTOMREQUEST, $Mode);
        curl_setopt($Handle, CURLOPT_URL, $Url);
        curl_setopt($Handle, CURLOPT_CONNECTTIMEOUT, 0);
        curl_setopt($Handle, CURLOPT_TIMEOUT, $Timeout);
        curl_setopt($Handle, CURLOPT_HEADER, false);
        curl_setopt($Handle, CURLOPT_HTTPHEADER, $Headers);

        curl_setopt($Handle, CURLOPT_AUTOREFERER, true);
        curl_setopt($Handle, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($Handle, CURLOPT_MAXREDIRS, 20);
        curl_setopt($Handle, CURLOPT_RETURNTRANSFER, true);

        curl_setopt($Handle, CURLOPT_SSL_VERIFYPEER, $SSL);
        if($SSL){
            curl_setopt($Handle, CURLOPT_SSL_VERIFYHOST, 2);
        }
        else{
            curl_setopt($Handle, CURLOPT_SSL_VERIFYHOST, 0);
        }

        if ($Mode != 'GET') {
            if ($Mode == 'POST') {
                curl_setopt($Handle, CURLOPT_POST, true);

                foreach ($File as $K => $V) {
                    if (file_exists(Common::diskPath($V))) {
                        $SendData[$K] = new CURLFile(Common::diskPath($V));
                    }
                }
            }

            if (is_array($Data)) {
                foreach ($Data as $K => $V) {
                    $SendData[$K] = $V;
                }
            } else {
                if ($Encode) {
                    $Data = urlencode($Data);
                }
                $SendData = $Data;
            }

            curl_setopt($Handle, CURLOPT_POSTFIELDS, $SendData);
        }

        $Response = curl_exec($Handle);
        $CurlErrno = curl_errno($Handle);
        curl_close($Handle);
        if ($Response === false && $CurlErrno > 0) {
            Api::wrong(['level' => 'F', 'detail' => 'Error#M.6.1' . "\r\n\r\n @ " . $CurlErrno, 'code' => 'M.6.1']);
        }
        return $Response;
    }

    public static function __callStatic($Method, $Parameters)
    {
        Common::unknownStaticMethod(__CLASS__, $Method);
    }
}