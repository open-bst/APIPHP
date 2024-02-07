<?php

namespace core;

/*
  APIPHP开源框架

  ©2023 APIPHP.com

  框架版本号：1.0.0
*/
class Doc
{



    public static function __callStatic($Method, $Parameters)
    {
        Common::unknownStaticMethod(__CLASS__, $Method);
    }
}