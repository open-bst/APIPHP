<?php

namespace core;

/*
  APIPHP开源框架

  ©2023 APIPHP.com

  框架版本号：1.0.0
*/

class Initial
{
    //获取配置项
    public static function getConfig($Key)
    {
        return $_SERVER['APIPHP']['Config']['core\Initial'][$Key];
    }

    //自动加载
    public static function autoload($ClassName)
    {
        if (!file_exists(_ROOT . '/lib/' . str_replace(['\\', '//'], '/', $ClassName) . '.php')) {
            Api::wrong(['level' => 'F', 'detail' => 'Error#C.0.5' . "\r\n\r\n @ " . $ClassName, 'code' => 'C.0.5']);
        } else {
            if (file_exists(_ROOT . '/config/' . str_replace(['\\', '//'], '/', $ClassName) . '.php')) {
                require(_ROOT . '/config/' . str_replace(['\\', '//'], '/', $ClassName) . '.php');
            }
            require(_ROOT . '/lib/' . str_replace(['\\', '//'], '/', $ClassName) . '.php');
        }
    }

    // 错误处理
    public static function fatalErr()
    {
        $Err = error_get_last();
        if ($Err && ($Err["type"] === ($Err["type"] & E_FATAL))) {
            self::sysErr($Err["type"], $Err["message"], $Err["file"], $Err["line"]);
        }
    }

    public static function sysErr($ErrNo, $ErrMsg, $ErrFile, $ErrLine): bool
    {
        if (error_reporting() == 0) {
            return true;
        }
        switch ($ErrNo) {
            case E_WARNING:
                $PSE = 'PHP Warning: ';
                break;
            case E_NOTICE:
                $PSE = 'PHP Notice: ';
                break;
            case E_DEPRECATED:
                $PSE = 'PHP Deprecated: ';
                break;
            case E_USER_ERROR:
                $PSE = 'User Error: ';
                break;
            case E_USER_WARNING:
                $PSE = 'User Warning: ';
                break;
            case E_USER_NOTICE:
                $PSE = 'User Notice: ';
                break;
            case E_USER_DEPRECATED:
                $PSE = 'User Deprecated: ';
                break;
            case E_STRICT:
                $PSE = 'PHP Strict: ';
                break;
            default:
                $PSE = 'Unknown error: ';
                break;
        }

        $PSE .= $ErrMsg . ' in ' . str_replace('\\', '/', $ErrFile) . ' on ' . $ErrLine;
        Api::wrong(['level' => 'S', 'detail' => 'Error#C.0.2 @ ' . $PSE, 'code' => 'C.0.2']);
        return true;
    }

    //Debug模式
    public static function debug()
    {
        if (!_DEBUG) {
            error_reporting(0);
        } else {
            header('Cache-Control: no-cache,must-revalidate');
            header('Pragma: no-cache');
            header("Expires: -1");
            header('Last-Modified: Thu, 01 Jan 1970 00:00:00 GMT');
        }
    }

    //路由
    public static function route()
    {
        $_SERVER['APIPHP']['Option'] = getopt('', ['path:']);
        if (empty($_SERVER['APIPHP']['Option']['path'])) {
            if (!isset($_GET['p_a_t_h'])) {
                $_GET['p_a_t_h'] = '';
            }
            $_SERVER['APIPHP']['URI'] = $_GET['p_a_t_h'];
        } else {
            $_SERVER['APIPHP']['URI'] = $_SERVER['APIPHP']['Option']['path'];
        }
        define('_URI', $_SERVER['APIPHP']['URI']);
        Cache::compile(['path' => _URI]);
        if (file_exists(_ROOT . '/temp/cache' . _URI . '.php')) {
            require(_ROOT . '/temp/cache' . _URI . '.php');
        } elseif (file_exists(_ROOT . '/asset' . _URI . '/index.html')) {
            $Content = file_get_contents(_ROOT . '/asset' . _URI . '/index.html');
            echo($Content);
        } elseif (file_exists(_ROOT . '/asset' . _URI)) {
            header('Location: /asset' . _URI);
        } elseif (!empty($_SERVER['APIPHP']['Config']['core\Base']['pageNotFound'])) {
            header('Location: ' . $_SERVER['APIPHP']['Config']['core\Base']['pageNotFound']);
        } else {
            Api::wrong(['level' => 'U', 'detail' => 'Error#C.0.0', 'code' => 'C.0.0', 'http' => 404]);
        }
    }
}