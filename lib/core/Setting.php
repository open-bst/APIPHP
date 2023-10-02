<?php

namespace core;

/*
  APIPHP开源框架

  ©2023 APIPHP.com

  框架版本号：1.0.0
*/

class Setting
{

    //检查配置文件
    private static function fileCheck($Module): bool
    {
        $FilePath = _ROOT . '/config/core/' . ucfirst($Module) . '.php';
        if (file_exists($FilePath)) {
            return true;
        }
        Api::wrong(['level' => 'F', 'detail' => 'Error#M.9.1', 'code' => 'M.9.1']);
        return true;
    }

    //数组转字符串
    private static function arrayToStr($Array)
    {
        $TempText = '[' . "\r\n";
        foreach ($Array as $K => $V) {
            if (is_string($K)) {
                $TempText .= '\'' . str_replace("'", '"', $K) . '\'=>';
            } else {
                $TempText .= $K . '=>';
            }
            if (is_string($V)) {
                $TempText .= '\'' . str_replace("'", '"', $V) . '\',' . "\r\n";
            } elseif (is_bool($V)) {
                if ($V) {
                    $TempText .= 'true,' . "\r\n";
                } else {
                    $TempText .= 'false,' . "\r\n";
                }
            } elseif (is_array($V)) {
                $TempText .= self::arrayToStr($V) . ',' . "\r\n";
            } elseif (is_int($V) || is_float($V)) {
                $TempText .= $V . ',' . "\r\n";
            } else {
                $TempText .= '\'\',' . "\r\n";
            }
        }
        $TempText = str_replace("\r\n", "\r\n    ", $TempText);
        $TempText = rtrim($TempText, ' ');
        $TempText .= ']';
        return str_replace(",\r\n]", "\r\n]", $TempText);
    }

    //变量转字符串
    private static function varToStr($ValueName, $Value): string
    {
        if (is_string($Value)) {
            return '\'' . $ValueName . '\'=>\'' . str_replace("'", '\\\'', $Value) . '\',' . "\r\n";
        } elseif (is_bool($Value)) {
            if ($Value) {
                return '\'' . $ValueName . '\'=>true,' . "\r\n";
            } else {
                return '\'' . $ValueName . '\'=>false,' . "\r\n";
            }
        } elseif (is_array($Value)) {
            return '\'' . $ValueName . '\'=>' . self::arrayToStr($Value) . ',' . "\r\n";
        } elseif (is_int($Value) || is_float($Value)) {
            return '\'' . $ValueName . '\'=>' . $Value . ',' . "\r\n";
        } else {
            return '\'' . $ValueName . '\'=>\'\',' . "\r\n";
        }
    }

    //获取配置项的值
    public static function get($UnionData = [])
    {
        $Module = Common::quickParameter($UnionData, 'module', '模块');
        $Name = Common::quickParameter($UnionData, 'name', '名称');

        self::fileCheck($Module);
        require_once(_ROOT . '/config/core/' . ucfirst($Module) . '.php');
        if (!isset($_SERVER['APIPHP']['Config'][$Module][$Name])) {
            Api::wrong(['level' => 'F', 'detail' => 'Error#M.9.2', 'code' => 'M.9.2']);
        }
        return $_SERVER['APIPHP']['Config'][$Module][$Name];
    }

    //写入配置项
    public static function set($UnionData = [])
    {
        $Namespace = Common::quickParameter($UnionData, 'namespace', '命名空间');
        $Module = Common::quickParameter($UnionData, 'path', '模块');
        $Name = Common::quickParameter($UnionData, 'name', '名称');
        $Value = Common::quickParameter($UnionData, 'value', '值');
        $Module = ucfirst($Module);

        $CodeText = self::fileCheck($Module);
        $OldValue = self::get(['module' => $Module, 'name' => $Name]);
        require_once(_ROOT . '/config/core/' . $Module . '.php');
        if (gettype($OldValue) != gettype($Value)) {
            Api::wrong(['level' => 'F', 'detail' => 'Error#M.9.3', 'code' => 'M.9.3']);
        }
        $CodeText = '<?php' . "\r\n" . '$_SERVER[\'APIPHP_CONFIG\'][\'' . $Module . '\']=[' . "\r\n";
        foreach ($_SERVER['APIPHP']['Config'][$Module] as $K => $V) {
            $CodeText .= '    ';
            if ($K != $Name) {
                $CodeText .= self::varToStr($Name, $V);
            } else {
                $CodeText .= self::varToStr($Name, $Value);
            }
        }
        $CodeText .= "\r\n];";
        $Handle = @fopen(_ROOT . '/config/core/' . $Module . '.php', 'w');
        if (!$Handle) {
            Api::wrong(['level' => 'F', 'detail' => 'Error#M.9.4', 'code' => 'M.9.4']);
        }
        fwrite($Handle, $CodeText);
        fclose($Handle);
    }

    //临时改变配置项
    public static function change($UnionData = [])
    {
        $Module = Common::quickParameter($UnionData, 'module', '模块');
        $Name = Common::quickParameter($UnionData, 'name', '名称');
        $Value = Common::quickParameter($UnionData, 'value', '值');

        if (!isset($_SERVER['APIPHP']['Config'][$Module])) {
            Api::wrong(['level' => 'F', 'detail' => 'Error#M.9.4', 'code' => 'M.9.4']);
        }
        if (!isset($_SERVER['APIPHP']['Config'][$Module][$Name])) {
            Api::wrong(['level' => 'F', 'detail' => 'Error#M.9.2', 'code' => 'M.9.2']);
        }
        if (gettype($_SERVER['APIPHP']['Config'][$Module][$Name]) != gettype($Value)) {
            Api::wrong(['level' => 'F', 'detail' => 'Error#M.9.3', 'code' => 'M.9.3']);
        }
        $_SERVER['APIPHP']['Config'][$Module][$Name] = $Value;
    }

    public static function __callStatic($Method, $Parameters)
    {
        Common::unknownStaticMethod(__CLASS__, $Method);
    }
}