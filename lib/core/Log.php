<?php

namespace core;


/*
  APIPHP开源框架

  ©2024 APIPHP.com

  框架版本号：1.0.0
*/

class Log
{

    //获取等级
    private static function getLevel($LevelName): int
    {
        if (strtolower($LevelName) == 'debug') {
            return 0;
        } elseif (strtolower($LevelName) == 'info') {
            return 1;
        } elseif (strtolower($LevelName) == 'notice') {
            return 2;
        } elseif (strtolower($LevelName) == 'warning') {
            return 3;
        } elseif (strtolower($LevelName) == 'error') {
            return 4;
        } else {
            return -1;
        }
    }

    //添加记录
    public static function add($UnionData = []): bool
    {
        $Info = Common::quickParameter($UnionData, 'info', '内容', false, '');
        $LevelName = Common::quickParameter($UnionData, 'level', '等级', false, 'info');
        $Level = self::getLevel($LevelName);

        if ($Level === -1) {
            return false;
        }

        $_SERVER['APIPHP']['Log'][] = [
            'LevelName' => $LevelName,
            'Level' => $Level,
            'Content' => $Info,
            'Time' => (intval(microtime(true) * 1000) - intval(_TIME * 1000)) / 1000
        ];
        return true;
    }

    //写入文件
    public static function output(): bool
    {
        if (strlen($_SERVER['APIPHP']['Config']['core\Initial']['safeCode']) < 10) {
            return false;
        }


        if (strtoupper($_SERVER['APIPHP']['Config']['core\Log']['interval']) == 'H') {
            $LogFileName = date('H\H', _TIME);
        } elseif (strtoupper($_SERVER['APIPHP']['Config']['core\Log']['interval']) == 'M') {
            $LogFileName = date('H\H_i', _TIME);
        } elseif (strtoupper($_SERVER['APIPHP']['Config']['core\Log']['interval']) == 'HM') {
            $LogFileName = date('H\H_i', _TIME);
            if (_TIME % 60 < 30) {
                $LogFileName .= '_(1)';
            } else {
                $LogFileName .= '_(2)';
            }
        } else {
            $LogFileName = 'applog';
        }

        $AccessInfo = '';

        if ($_SERVER['APIPHP']['Config']['core\Log']['access']) {
            $AccessInfo =
                '[access] IP:' . $_SERVER['REMOTE_ADDR'] .
                ' | HOST:' . isset($_SERVER['HTTP_X_FORWARDED_HOST']) ? $_SERVER['HTTP_X_FORWARDED_HOST'] : ($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] . ($_SERVER['SERVER_PORT'] == '80' || $_SERVER['SERVER_PORT'] == '443' ? '' : ':' . $_SERVER['SERVER_PORT'])) .
                ' | METHOD:' . $_SERVER['REQUEST_METHOD'] .
                ' | REFERER:' . ((empty($_SERVER['HTTP_REFERER'])) ? '' : $_SERVER['HTTP_REFERER']) .
                ' | UA:' . ((empty($_SERVER['HTTP_USER_AGENT'])) ? '' : $_SERVER['HTTP_USER_AGENT']) .
                "\r\n";
        }

        $FilePath = '/temp/log/' . $_SERVER['APIPHP']['Config']['core\Initial']['safeCode'] . date('/Y-m/d', _TIME);
        if (!file_exists(_ROOT . $FilePath)) {
            mkdir(_ROOT . $FilePath, 0777, true);
        }

        $Content = '### ' . date(
                'Y-m-d H:i:s',
                _TIME
            ) . ' (' . _TIME . ")\r\n[path] " . _URI . "\r\n" . $AccessInfo;

        $ConfigLevel = self::getLevel($_SERVER['APIPHP']['Config']['core\Log']['level']);

        if ($ConfigLevel === -1) {
            return false;
        }

        foreach ($_SERVER['APIPHP']['Log'] as $V) {
            if ($V['Level'] >= $ConfigLevel) {
                $Content .= '[' . $V['LevelName'] . '] ' . $V['Content'] . "\r\n<" . $V['Time'] . "s>\r\n";
            }
        }

        $Content .= "\r\n";

        $_SERVER['APIPHP']['Log'] = [];
        $Handle = fopen(_ROOT . $FilePath . '/' . $LogFileName . '.txt', 'a');
        if ($Handle) {
            if (flock($Handle, LOCK_EX)) {
                fwrite($Handle, $Content);
            }
            fclose($Handle);
        }
        return true;
    }

    //清空日志
    public static function clean(): void
    {
        $_SERVER['APIPHP']['Log'] = [];
    }

    //获取累积日志
    public static function get():array
    {
        return $_SERVER['APIPHP']['Log'];
    }

    public static function __callStatic($Method, $Parameters)
    {
        Common::unknownStaticMethod(__CLASS__, $Method);
    }
}