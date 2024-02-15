<?php

namespace core;

/*
  APIPHP开源框架

  ©2024 APIPHP.com

  框架版本号：1.0.0
*/

class Api
{
    public static function respond($UnionData): void
    {
        $Content = Common::quickParameter($UnionData, 'content', '内容', false, []);
        $Log = Common::quickParameter($UnionData, 'log', '日志', false, false);
        $HttpCode = Common::quickParameter($UnionData, 'http', '响应码', false, 200);

        $Style = $_SERVER['APIPHP']['Config']['core\Api']['template'];

        foreach ($Content as $K => $V) {
            $Style[$K] = $V;
        }

        $Respond = json_encode($Style);

        if ($Log) {
            Log::add(['level' => 'info', 'info' => '[API Respond] ' . $Respond]);
        }

        ob_clean();
        http_response_code(intval($HttpCode));
        header('content-type:application/json');

        echo $Respond;
    }

    public static function wrong($UnionData)
    {
        $Detail = Common::quickParameter($UnionData, 'detail', '详情', true, null, true);
        $Code = Common::quickParameter($UnionData, 'code', '状态码', false, 0);
        $Stack = Common::quickParameter($UnionData, 'stack', '堆栈', false, false);
        $Log = Common::quickParameter($UnionData, 'log', '日志', false, true);
        $HttpCode = Common::quickParameter($UnionData, 'http', '响应码', false, 200);
        $Level = strtoupper(Common::quickParameter($UnionData, 'level', '级别', false, 'A'));

        $Config = $_SERVER['APIPHP']['Config']['core\Api'];

        foreach ($Config['wrong']['ignore'] as $V) {
            if (strstr($Detail, $V)) {
                return true;
            }
        }

        if (isset($Config['wrong']['replace'][$Code])) {
            $Code = $Config['wrong']['replace'][$Code];
        }

        $WrongInfo = [
            'level' => 'unknown',
            'detail' => str_replace('\\', '/', $Detail),
            'stack' => [],
            'time' => microtime(true)
        ];

        if (strtoupper($Level) == 'S') {
            $WrongInfo['level'] = 'script';
        } elseif (strtoupper($Level) == 'F') {
            $WrongInfo['level'] = 'framework';
        } elseif (strtoupper($Level) == 'A') {
            $WrongInfo['level'] = 'application';
        } else {
            $WrongInfo['level'] = 'user';
        }

        if ($Stack || $WrongInfo['level'] == 'script' || $WrongInfo['level'] == 'framework') {
            $StackArray = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
            foreach ($StackArray as $K => $V) {
                $StackInfo = ' ';
                if (isset($V['class'])) {
                    $StackInfo .= $V['class'] . $V['type'];
                }
                if (isset($V['function'])) {
                    if ($V['function'] == '{closure}') {
                        $StackInfo .= '{closure}';
                    } else {
                        $StackInfo .= $V['function'] . '()';
                    }
                }
                if (isset($V['file']) && isset($V['line'])) {
                    $StackInfo .= ' at [' . str_replace('\\', '/', $V['file']) . ':' . $V['line'] . '].';
                }
                $WrongInfo['stack']['#' . $K] = $StackInfo;
            }
        }


        if (_DEBUG || stristr($Config['wrong']['respond'], $Level) !== false) {
            foreach ($Config['wrong']['style'] as $K => $V) {
                $Config['wrong']['style'][$K] = str_replace(['{code}', '{info}', '{time}'],
                    [$Code, $WrongInfo['detail'], $WrongInfo['time']],
                    $V);
                if ($V == '{stack}') {
                    $Config['wrong']['style'][$K] = $WrongInfo['stack'];
                }
            }
        } else {
            foreach ($Config['wrong']['style'] as $K => $V) {
                $Config['wrong']['style'][$K] = str_replace(['{code}', '{info}', '{time}'],
                    ['M.13.0', 'Error#M.13.0', $WrongInfo['time']],
                    $V);
            }
        }
        self::respond(['content' => $Config['wrong']['style'], 'http' => $HttpCode]);

        if (stristr($Config['wrong']['log'], $Level) !== false && $Log) {
            $WrongLog = '[' . $WrongInfo['level'] . '] ' . $WrongInfo['detail'];

            foreach ($WrongInfo['stack'] as $K => $V) {
                $WrongLog .= "\r\n    " . $K . ' ' . $V;
            }

            $WrongLog .= "\r\n    " . 'Occurred on ' . $WrongInfo['time'];

            Log::add(['level' => 'error', 'info' => $WrongLog]);

            Log::output();
        }
        exit;
    }

    public static function __callStatic($Method, $Parameters)
    {
        Common::unknownStaticMethod(__CLASS__, $Method);
    }
}