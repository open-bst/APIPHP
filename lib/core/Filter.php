<?php

namespace core;

/*
  APIPHP开源框架

  ©2024 APIPHP.com

  框架版本号：1.0.0
*/

class Filter
{

    private static array $LastCheck;
    
    //长度检查
    private static function lengthCheck($Min,$Max, $Value): bool
    {
        $Value = strval($Value);
        $Len = mb_strlen($Value);
        if (($Min!='*' && $Len < intval($Min)) ||
            ($Max!='*' && $Len > intval($Max))) {
            return false;
        }
        return true;
    }

    //数值检查
    private static function valueCheck($Min,$Max,$Value):bool
    {
        if (($Min!='*' && $Value < $Min) ||
            ($Max!='*' && $Value > $Max)) {
            return false;
        }
        return true;
    }

    //类型转换
    public static function convertType($Val,$Type):mixed
    {
        if($Type===''){
            return $Val;
        }
        $ArrayMode=is_array($Val);
        if(!$ArrayMode){
            $Val=[$Val];
        }
        $Result=[];
        foreach($Val as $K=>$V){
            if($Type=='bool'){
                $V=strtolower(strval($V));
                $V=$V == '1' || $V == 'true' || $V == 'yes';
            }
            else if($Type=='float'){
                $V=floatval($V);
            }
            else if($Type=='string'){
                $V=strval($V);
            }
            else if($Type=='int'){
                $V=intval($V);
            }
            else if(intval(str_replace('float','',$Type))>0){
                $V=round(floatval($V),intval($Type));
            }
            $Result[$K]=$V;
        }
        if($ArrayMode){
            return $Result;
        }
        else{
            return $Result[0];
        }
    }

    //对象转数组
    private static function o2a($Value):mixed
    {
        if(is_object($Value)) {
            $Value = (array)$Value;
        }
        if(is_array($Value)) {
            foreach($Value as $K=>$V) {
                $Value[$K] = self::o2a($V);
            }
        }
        return $Value;
    }

    //指定规则检查
    private static function ruleCheck($Value,$Rule):bool
    {
        if (empty($Rule)) {
            return true;
        }
        $Value=strval($Value);
        if (str_contains($Rule, '|')) {
            $ValueList = explode('|', $Rule);
            return in_array($Value, $ValueList);
        } elseif ($Rule == 'email') {
            return filter_var($Value, FILTER_VALIDATE_EMAIL);
        } elseif ($Rule == 'ip') {
            return filter_var($Value, FILTER_VALIDATE_IP);
        } elseif ($Rule == 'mac') {
            return filter_var($Value, FILTER_VALIDATE_MAC)!==false;
        }
        if (!empty($_SERVER['APIPHP']['Config']['core\Filter']['rule'][$Rule])&&preg_match($_SERVER['APIPHP']['Config']['core\Filter']['rule'][$Rule], $Value)) {
                return true;
        }
        return false;
    }

    //转换数据类型名称
    private static function convertName($Name):string
    {
        if ($Name=='int') {
            return 'integer';
        }
        if($Name=='bool'){
            return 'boolean';
        }
        if($Name=='float'||str_contains($Name,'float')){
            return 'double';
        }
        return $Name;
    }

    //校验
    public static function check($UnionData = []): false|array
    {
        $Field = Common::quickParameter($UnionData, 'field', '字段');
        $Optional = Common::quickParameter($UnionData, 'optional', '可选', false, []);
        $Mode = Common::quickParameter($UnionData, 'mode', '模式',false,'');
        $Data = Common::quickParameter($UnionData, 'data', '数据', false, []);
        $ReturnObj=Common::quickParameter($UnionData, 'return_object', '返回对象', false, false);
        $Template=Common::quickParameter($UnionData, 'template', '模板', false, []);
        $Mode = strtolower($Mode);
        $Return=[];


        if (!in_array($Mode,['get','post','header','cookie',''])) {
            Api::wrong(['level' => 'F', 'detail' => 'Error#M.7.0', 'code' => 'M.7.0']);
        }
        self::$LastCheck = ['result' => [], 'optional' => [],'mode'=>$Mode];
        foreach ($Field as $K => $V) {
            $TempOp = explode(',', $V);
            $TempData = null;
            if ($Mode == 'post' && isset($_POST[$K])) {
                $TempData = $_POST[$K];
            } elseif ($Mode == 'get' && isset($_GET[$K])) {
                $TempData = $_GET[$K];
            } elseif ($Mode == 'cookie' && isset($_COOKIE[$K])) {
                $TempData = $_COOKIE[$K];
            } elseif ($Mode == 'header') {
                $KeyName = 'HTTP_' . str_replace('-', '_', strtoupper($K));
                if (isset($_SERVER[$KeyName])) {
                    $TempData = $_SERVER[$KeyName];
                }
            }
            elseif (isset($Data[$K])){
                $TempData = $Data[$K];
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
            $Check=self::$LastCheck['result'][$K]['exist']=$TempData !== null;
            $OpType=self::convertName($TempOp[0]);
            if($Check){
                if(!in_array($OpType,['string','double','integer','boolean','json','object','array'])){
                    Api::wrong(['level' => 'F', 'detail' => 'Error#M.7.1' . "\r\n\r\n @ " . $OpType, 'code' => 'M.7.0']);
                }
                if($OpType=='json'||$OpType=='object'||$OpType=='array'){
                    $Obj=$TempData;
                    if($OpType=='json'){
                        $Obj=json_decode($Obj);
                        self::$LastCheck['result'][$K]['json']= (bool)$Obj;
                    }
                    if(gettype($Obj)=='object'||gettype($Obj)=='array'){
                        $Return[$K]=self::objCheck(
                            $Obj,
                            json_decode(empty($Template[$K])?'':$Template[$K]),
                            !empty($TempOp[3])&&$TempOp[3]=='convert_type'
                        );
                        if(is_object($Return[$K])&&!$ReturnObj){
                            $Return[$K]=self::o2a($Return[$K]);
                        }
                    }
                    else{
                        $Return[$K]=false;
                    }
                    $Check=self::$LastCheck['result'][$K]['object']= $Return[$K]!==false;
                    if(!empty(self::$LastCheck['object_key'])){
                        self::$LastCheck['result'][$K]['object_key']=self::$LastCheck['object_key'];
                    }
                }
                else{
                    $Return[$K]=$TempData;
                    $Type=gettype($Return[$K]);
                    if($OpType!=$Type){
                        $Return[$K]=self::convertType($Return[$K],$TempOp[0]);
                        $Type=gettype($Return[$K]);
                    }
                    if(isset($TempOp[1])){
                        if($Type=='string'){
                            $Check=self::$LastCheck['result'][$K]['length']=self::lengthCheck($TempOp[1],$TempOp[2] ?? '*',$TempData);
                        }
                        elseif ($Type=='integer'||$Type=='double'){
                            $Check=self::$LastCheck['result'][$K]['value']=self::valueCheck(self::convertType($TempOp[1],$Type), isset($TempOp[2])?self::convertType($TempOp[2],$Type):'*',$TempData);
                        }
                    }
                }
                if($Check&&!empty($TempOp[3])&&$OpType!='json'&&$OpType!='object'&&$OpType!='array'){
                    $Check=self::$LastCheck['result'][$K]['rule']= self::ruleCheck($TempData,$TempOp[3]);
                }
            }
            unset(self::$LastCheck['object_key']);
            if (!$Check) {
                Hook::call(
                    [
                        'name' => 'apiphp_filter_check-failed',
                        'parameter' => ['field' => $K, 'result' => self::$LastCheck['result'][$K]]
                    ]
                );
                return false;
            }
        }
        return $Return;
    }

    private static function objCheck($Obj,$Template,$Convert):object|false
    {
        if(!is_object($Template)||!isset($Template->__self)||!is_string($Template->__self)){
            self::$LastCheck['object_key']='__self';
            return false;
        }
        $Self = explode(',', $Template->__self);
        $Optional=isset($Template->__optional)&&is_array($Template->__optional)?$Template->__optional:[];
        $Key=isset($Template->__key)&&is_string($Template->__key)?$Template->__key:'';
        if(gettype($Obj)!=$Self[0]||!in_array($Self[0],['object','array'])){
            self::$LastCheck['object_key']='__self';
            return false;
        }

        $Strict=empty($Template->__default);
        if(!$Strict&&isset($Self[1])){
            $Count=count(is_array($Obj)?$Obj:get_object_vars($Obj));
            $Self[2]=$Self[2]??'*';
            if($Count<intval($Self[1])||($Self[2]!='*'&&$Count>intval($Self[2]))){
                self::$LastCheck['object_key']='__default';
                return false;
            }
        }


        foreach ($Obj as $K => $V){
            self::$LastCheck['object_key']=$K;
            $T=$Template->$K??null;
            if(empty($T)){
                if(!$Strict){
                    if(!empty($Key)){
                        $KeyOp=explode(',',$Key);
                        if(isset($KeyOp[1])){
                            $KeyCheck=false;
                            if($KeyOp[0]=='string'){
                                $KeyCheck=self::lengthCheck($KeyOp[1],$TempOp[2]??'*',$K);
                            }
                            else if(in_array(self::convertName($KeyOp[0]),['double','integer'])){
                                $KeyCheck=self::valueCheck(self::convertType($KeyOp[1],$KeyOp[0]),isset($TempOp[2])?self::convertType($KeyOp[2],$KeyOp[0]):'*',$K);
                            }
                            if($KeyCheck&&!empty($KeyOp[3])){
                                $KeyCheck=self::ruleCheck($K,$KeyOp[3]);
                            }
                            if(!$KeyCheck){
                                return false;
                            }
                        }
                    }
                    $T=$Template->__default;
                }
                else{
                    return false;
                }
            }

            if(!is_object($T)&&!is_string($T)){
                return false;
            }

            if(is_object($T)){
                $Obj->$K=self::objCheck($V,$T,$Convert);
                if(!$Obj->$K){
                    return false;
                }
            }
            elseif(is_string($T)){

                $TempOp=explode(',',$T);
                $OpType=self::convertName($TempOp[0]);
                if(!in_array($OpType,['string','double','integer','boolean','json','object'])){
                    Api::wrong(['level' => 'F', 'detail' => 'Error#M.7.1' . "\r\n\r\n @ " . $OpType, 'code' => 'M.7.0']);
                }
                $Type=gettype($V);
                if($Type!=$OpType){
                    if($Convert&&in_array($Type,['string','double','integer','boolean'])){
                        if(is_array($V)){
                            $V=$Obj[$K]=self::convertType($V,$TempOp[0]);
                        }
                        else{
                            $V=$Obj->$K=self::convertType($V,$TempOp[0]);
                        }
                        $Type=gettype($V);
                    }
                    else{
                        return false;
                    }
                }
                if(isset($TempOp[1])){
                    if($Type=='string'){
                        if(!self::lengthCheck($TempOp[1], $TempOp[2]??'*',$V)){
                            return false;
                        }
                    }
                    elseif ($Type=='integer'||$Type=='double'){
                        if(!self::valueCheck(self::convertType($TempOp[1],$Type),isset($TempOp[2])?self::convertType($TempOp[2],$Type):'*',$V)){
                            return false;
                        }
                    }
                    if(!empty($TempOp[3])&&!self::ruleCheck($V,$TempOp[3])){
                        return false;
                    }
                }
            }
            if(isset($Template->$K)){
                $Template->$K=null;
            }
        }
        foreach ($Template as $K=>$V){
            if(!empty($V)&&!in_array($K,$Optional)&&!in_array($K,['__self','__key','__optional','__default'])){
                self::$LastCheck['object_key']=$K;
                return false;
            }
        }
        return $Obj;
    }

    //返回最后一次运行Filter::check()的校验结果
    public static function lastCheck($UnionData = []): array
    {
        return self::$LastCheck;
    }

    public static function __callStatic($Method, $Parameters)
    {
        Common::unknownStaticMethod(__CLASS__, $Method);
    }
}