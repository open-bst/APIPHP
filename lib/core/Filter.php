<?php

namespace core;

/*
  APIPHP开源框架

  ©2023 APIPHP.com

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

    //指定规则检查
    private static function ruleCheck($OpArray, $Value)
    {
        if (empty($OpArray[3])) {
            return true;
        } elseif (str_contains($Value, '|')) {
            $ValueList = explode('|', $Value);
            return in_array($Value, $ValueList);
        } elseif ($OpArray[3] == 'email') {
            return filter_var($Value, FILTER_VALIDATE_EMAIL);
        } elseif ($OpArray[3] == 'ip') {
            return filter_var($Value, FILTER_VALIDATE_IP);
        } elseif ($OpArray[3] == 'url') {
            return filter_var($Value, FILTER_VALIDATE_URL);
        } elseif ($OpArray[3] == 'json') {
            return json_validate($Value);
        }
        $RuleName = $OpArray[3];
        if (!empty($_SERVER['APIPHP']['Config']['core\Filter']['rule'][$RuleName])) {
            if (preg_match($_SERVER['APIPHP']['Config']['core\Filter']['rule'][$RuleName], $Value) == 0) {
                return false;
            }
        }
        return true;
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

            if (in_array($K, $Optional)) {
                if ($TempData === null) {
                    self::$LastCheck['optional'][$K] = false;
                    self::$LastCheck['result'][$K] = [false, false, false];
                    continue;
                } else {
                    self::$LastCheck['optional'][$K] = true;
                }
            }
            self::$LastCheck['result'][$K] = [
                self::emptyCheck($TempOp, $TempData),
                self::lengthCheck($TempOp, $TempData),
                self::ruleCheck($TempOp, $TempData)
            ];
            if (!self::$LastCheck['result'][$K][0] || !self::$LastCheck['result'][$K][1] || !self::$LastCheck['result'][$K][2]) {
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