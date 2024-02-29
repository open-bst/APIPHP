<?php

namespace core;

/*
  APIPHP开源框架

  ©2024 APIPHP.com

  框架版本号：1.0.0
*/

class Initial
{
    //自动加载
    public static function autoload($ClassName): void
    {
        if (!file_exists(_ROOT . '/lib/' . str_replace(['\\', '//'], '/', $ClassName) . '.php')) {
            if(!$_SERVER['APIPHP']['Config']['core\Initial']['composer']){
                Api::wrong(['level' => 'F', 'detail' => 'Error#C.0.5' . "\r\n\r\n @ " . $ClassName, 'code' => 'C.0.5']);
            }
        } else {
            if (file_exists(_ROOT . '/config/' . str_replace(['\\', '//'], '/', $ClassName) . '.php')) {
                require(_ROOT . '/config/' . str_replace(['\\', '//'], '/', $ClassName) . '.php');
            }
            require(_ROOT . '/lib/' . str_replace(['\\', '//'], '/', $ClassName) . '.php');
        }
    }

    // 错误处理
    public static function fatalErr(): void
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
        $PSE = match ($ErrNo) {
            E_WARNING => 'PHP Warning: ',
            E_NOTICE => 'PHP Notice: ',
            E_DEPRECATED => 'PHP Deprecated: ',
            E_USER_ERROR => 'User Error: ',
            E_USER_WARNING => 'User Warning: ',
            E_USER_NOTICE => 'User Notice: ',
            E_USER_DEPRECATED => 'User Deprecated: ',
            E_STRICT => 'PHP Strict: ',
            default => 'Unknown error: ',
        };

        $PSE .= $ErrMsg . ' in ' . str_replace('\\', '/', $ErrFile) . ' on ' . $ErrLine;
        Api::wrong(['level' => 'S', 'detail' => 'Error#C.0.2'. "\r\n\r\n @ " . $PSE, 'code' => 'C.0.2']);
        return true;
    }

    //调试模式
    public static function debug(): void
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
    public static function route(): void
    {
        $_SERVER['APIPHP']['Option'] = getopt('', ['path:']);
        if (empty($_SERVER['APIPHP']['Option']['path'])) {
            if (!isset($_GET['p_a_t_h'])) {
                $_GET['p_a_t_h'] = '';
            }
            $_SERVER['APIPHP']['URI'] = $_GET['p_a_t_h'];
        } else {
            $_SERVER['APIPHP']['URI'] = $_SERVER['APIPHP']['Option']['path'];
            $QueryOpt=getopt('', ['query:']);
            if(!empty($QueryOpt)){
                $Query=explode('&',$QueryOpt['query']);
                foreach ($Query as $V){
                    $V=explode('=',$V);
                    if(!empty($V[0])){
                        $_GET[urldecode($V[0])]=empty($V[1])?'':urldecode($V[1]);
                    }
                }
            }
        }
        $_SERVER['APIPHP']['URI']=Hook::call(
            [
                'name' => 'apiphp_initial_route',
                'parameter' => [
                    'uri' => $_SERVER['APIPHP']['URI'],
                ]
            ]
        )['uri'];
        define('_URI', $_SERVER['APIPHP']['URI']);
        Cache::compile(['path' => _URI]);

        if (file_exists(_ROOT . '/temp/cache' . _URI . '.php')) {
            require(_ROOT . '/temp/cache' . _URI . '.php');
        } elseif (file_exists(_ROOT . '/asset' . _URI . '/index.html')) {
            $Content = file_get_contents(_ROOT . '/asset' . _URI . '/index.html');
            echo($Content);
        } elseif (file_exists(_ROOT . '/asset' . _URI)) {
            header('Location: /asset' . _URI);
        } elseif (!empty($_SERVER['APIPHP']['Config']['core\Initial']['pageNotFound'])) {
            header('Location: ' . $_SERVER['APIPHP']['Config']['core\Initial']['pageNotFound']);
        } else {
            Api::wrong(['level' => 'U', 'detail' => 'Error#C.0.0', 'code' => 'C.0.0', 'http' => 404]);
        }
    }
}