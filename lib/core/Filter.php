<?php

namespace core;

/*
  APIPHP开源框架

  ©2024 APIPHP.com

  框架版本号：1.0.0
*/

class Filter
{

    private static $LastCheck;

    //非空检查
    private static function emptyCheck($OpArray, $Value): bool
    {
        if (isset($OpArray[0]) && strtolower(
                $OpArray[0]
            ) == 'true' && ($Value === '' || $Value === null || $Value === [])) {
            return false;
        }
        return true;
    }

    //长度检查
    private static function lengthCheck($OpArray, $Value): bool
    {
        $Value = strval($Value);
        $StrLen = mb_strlen($Value);
        if (
            (isset($OpArray[1]) && $StrLen < intval($OpArray[1])) ||
            (isset($OpArray[2]) && intval($OpArray[2]) > 0 && $StrLen > intval($OpArray[2]))) {
            return false;
        }
        return true;
    }

    //数值检查
    private static function ValueCheck($Tag,$Val)
    {
        if($Tag==''||
            ($Tag == '+'&&$Val>0)||
            ($Tag == '-'&&$Val<0)||
            ($Tag == '^'&&$Val>=0)){
            return true;
        }
        return false;

    }

    //指定规则检查
    private static function ruleCheck($OpArray, $Value)
    {
        if (empty($OpArray[3])) {
            return true;
        }
        $Rule=$OpArray[3];
        if (str_contains($Rule, '|')) {
            $ValueList = explode('|', $Value);
            return in_array($Value, $ValueList);
        } elseif ($Rule == 'email') {
            return filter_var($Value, FILTER_VALIDATE_EMAIL);
        } elseif ($Rule == 'ip') {
            return filter_var($Value, FILTER_VALIDATE_IP);
        } elseif ($Rule == 'mac') {
            return filter_var($Value, FILTER_VALIDATE_MAC)!==false;
        } elseif ($Rule == 'json') {
            return json_validate($Value);
        } elseif ($Rule == 'bool') {
            if(is_bool($Value)){
                return true;
            }
            if($Value==trim($Value)){
                $Value=strtolower($Value);
                return $Value==='true'||$Value==='false';
            }
        } elseif ($Rule == 'type_bool') {
            return is_bool($Value);
        } elseif ($Rule == 'number'&&$Value==trim($Value)) {
            return filter_var($Value, FILTER_VALIDATE_INT)!==false||filter_var($Value, FILTER_VALIDATE_FLOAT)!==false;
        } elseif ($Rule == 'array') {
            return is_array($Value);
        } elseif (in_array($Rule,['int','+int','-int','^int'])&&$Value==trim($Value)) {
            return filter_var($Value, FILTER_VALIDATE_INT)!==false&&
                self::ValueCheck(str_replace('int','',$Rule),intval($Value));
        } elseif (in_array($Rule,['float','+float','-float','^float'])&&$Value==trim($Value)) {
            return filter_var($Value, FILTER_VALIDATE_FLOAT)!==false&&
                self::ValueCheck(str_replace('float','',$Rule),floatval($Value));
        } elseif (in_array($Rule,['type_int','+type_int','-type_int','^type_int'])) {
            return is_int($Value)&&self::ValueCheck(str_replace('type_int','',$Rule),$Value);
        } elseif (in_array($Rule,['type_float','+type_float','-type_float','^type_float'])) {
            return is_float($Value)&&self::ValueCheck(str_replace('type_float','',$Rule),$Value);
        }
        if (!empty($_SERVER['APIPHP']['Config']['core\Filter']['rule'][$Rule])&&preg_match($_SERVER['APIPHP']['Config']['core\Filter']['rule'][$Rule], $Value)) {
                return true;
        }
        return false;
    }

    //按模式检查
    public static function byMode($UnionData = []): bool
    {
        $Field = Common::quickParameter($UnionData, 'field', '字段');
        $Optional = Common::quickParameter($UnionData, 'optional', '可选', false, []);
        $Mode = Common::quickParameter($UnionData, 'mode', '模式');
        $Mode = strtolower($Mode);

        self::$LastCheck = ['result' => [], 'optional' => []];

        if ($Mode != 'get' && $Mode != 'post' && $Mode != 'header') {
            Api::wrong(['level' => 'F', 'detail' => 'Error#M.7.0' . "\r\n\r\n @ " . $Mode, 'code' => 'M.7.0']);
        }
        foreach ($Field as $K => $V) {
            $TempOp = explode(',', $V);
            $TempData = null;
            if ($Mode == 'post' && isset($_POST[$K])) {
                $TempData = $_POST[$K];
            } elseif ($Mode == 'get' && isset($_GET[$K])) {
                $TempData = $_GET[$K];
            } elseif ($Mode == 'header') {
                $KeyName = 'HTTP_' . str_replace('-', '_', strtoupper($K));
                if (isset($_SERVER[$KeyName])) {
                    $TempData = $_SERVER[$KeyName];
                }
            }

            self::$LastCheck['result'][$K] = [];
            if (in_array($K, $Optional)) {
                if ($TempData === null) {
                    self::$LastCheck['optional'][$K] = false;
                    continue;
                } else {
                    self::$LastCheck['optional'][$K] = true;
                }
            }
            if ((!self::$LastCheck['result'][$K]['exist']=$TempData !== null)||
                (!self::$LastCheck['result'][$K]['empty']=self::emptyCheck($TempOp, $TempData) )||
                (!self::$LastCheck['result'][$K]['length']=self::lengthCheck($TempOp, $TempData))||
                (!self::$LastCheck['result'][$K]['rule']=self::ruleCheck($TempOp, $TempData))) {
                Hook::call(
                    [
                        'name' => 'apiphp_filter_mode-verify-failed',
                        'parameter' => ['field' => $K, 'result' => self::$LastCheck['result'][$K]]
                    ]
                );
                return false;
            }
        }
        return true;
    }

    //返回最后一次运行Filter::byMode()的校验结果
    public static function lastCheck($UnionData = []): array
    {
        return self::$LastCheck;
    }

    //从数据检查
    public static function byData($UnionData = []): bool
    {
        $Data = Common::quickParameter($UnionData, 'data', '数据');
        $Check = Common::quickParameter($UnionData, 'check', '校验');
        $CheckOp = explode(',', $Check);

        if (!self::emptyCheck($CheckOp, $Data) || !self::lengthCheck($CheckOp, $Data) || !self::ruleCheck(
                $CheckOp,
                $Data
            )) {
            return false;
        }
        return true;
    }

    public static function __callStatic($Method, $Parameters)
    {
        Common::unknownStaticMethod(__CLASS__, $Method);
    }
}