<?php

namespace core;

use Exception;
use PDO;
use PDOException;


/*
  APIPHP开源框架

  ©2023 APIPHP.com

  框架版本号：1.0.0
*/

class Db
{
    private static $DbHandle;
    private static $NowDb;
    private static $Stmts;

    //选择数据库
    public static function choose($UnionData): void
    {
        $DbName = Common::quickParameter($UnionData, 'db_name', '数据库', true, null, true);
        if (!empty($DbName)) {
            $_SERVER['APIPHP']['Config']['core\Db']['default'] = $DbName;
        }
    }

    //连接数据库
    private static function connect($DbName): void
    {
        if (empty($DbName)) {
            $DbName = $_SERVER['APIPHP']['Config']['core\Db']['default'];
        }
        if (self::$NowDb != $DbName) {
            self::$DbHandle = null;
            self::$NowDb = $DbName;
        }

        if (empty(self::$DbHandle)) {
            self::$Stmts = [];

            if (empty($_SERVER['APIPHP']['Config']['core\Db']['dbInfo'][self::$NowDb])) {
                Api::wrong(['level' => 'F', 'detail' => 'Error#M.8.0', 'code' => 'M.8.0']);
            }
            $Dsn = $_SERVER['APIPHP']['Config']['core\Db']['dbInfo'][self::$NowDb]['type'] .
                ':host=' . $_SERVER['APIPHP']['Config']['core\Db']['dbInfo'][self::$NowDb]['address'] .
                ';port=' . $_SERVER['APIPHP']['Config']['core\Db']['dbInfo'][self::$NowDb]['port'] .
                ';dbname=' . $_SERVER['APIPHP']['Config']['core\Db']['dbInfo'][self::$NowDb]['dbname'] .
                ';charset=' . $_SERVER['APIPHP']['Config']['core\Db']['dbInfo'][self::$NowDb]['charset'];
            try {
                self::$DbHandle = new PDO(
                    $Dsn,
                    $_SERVER['APIPHP']['Config']['core\Db']['dbInfo'][self::$NowDb]['username'],
                    $_SERVER['APIPHP']['Config']['core\Db']['dbInfo'][self::$NowDb]['password']
                );
                self::$DbHandle->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            } catch (PDOException $Err) {
                Api::wrong(
                    [
                        'level' => 'F',
                        'detail' => 'Error#M.8.1' . "\r\n\r\n @ " . 'ErrorInfo: (' . $Err->getCode(
                            ) . ') ' . $Err->getMessage(),
                        'code' => 'M.8.1'
                    ]
                );
            }
        }
    }

    //参数检查
    private static function parameterCheck($UnionData, $Extra = [], $Default = ''): array
    {
        $Result = [];
        $Parameters = [
            'table' => ['table', '表', true, null],
            'field' => ['field', '字段', false, []],
            'value' => ['value', '值', false, []],
            'condition' => ['condition', '条件', false, []],
            'order' => ['order', '排序', false, []],
            'desc' => ['desc', '降序', false, []],
            'limit' => ['limit', '限制', false, []],
            'index' => ['index', '索引', false, []],
            'dbName' => ['db_name', '数据库', false, ''],
            'debug' => ['debug', '调试', false, false]
        ];

        $ExtraParameters = [
            'data' => ['data', '数据', true, null],
            'sumField' => ['sum', '合计', true, null],
            'json' => ['json', 'json', false, []],
            'fieldLimit' => ['field_limit', '字段限制', false, []],
            'rowCount' => ['row_count', '行数统计', false, []],
            'groupBy' => ['group_by', '分组', false, []],
            'unlock' => ['unlock', '解锁', false, false]
        ];

        foreach ($Extra as $V) {
            $Parameters[$V] = $ExtraParameters[$V];
        }

        foreach ($Parameters as $K => $V) {
            $Result[$K] = Common::quickParameter($UnionData, $V[0], $V[1], $V[2], $V[3], $Default == $K);
        }

        return $Result;
    }

    //创建绑定
    private static function createBind($PreSql, $DbName): string
    {
        self::connect($DbName);
        $StmtKey = md5($PreSql);
        if (empty(self::$Stmts[$StmtKey])) {
            self::$Stmts[$StmtKey] = self::$DbHandle->prepare($PreSql);
        }
        return $StmtKey;
    }

    //绑定参数
    private static function bindData($StmtKey, $Field, $Data, $Tag = '', $Mix = false,$Md5=false): void
    {
        $PdoDataTypes= [
            'BOOL'=>PDO::PARAM_BOOL,
            'NULL'=>PDO::PARAM_NULL,
            'INT'=>PDO::PARAM_INT,
            'STR'=>PDO::PARAM_STR,
            'STR_NATL'=>PDO::PARAM_STR_NATL,
            'STR_CHAR'=>PDO::PARAM_STR_CHAR,
            'LOB'=>PDO::PARAM_LOB
        ];
        if (!$Mix) {
            foreach ($Field as $K => $V) {
                if (!isset($Data[$K])) {
                    Api::wrong(['level' => 'F', 'detail' => 'Error#M.8.5' . "\r\n\r\n @ " . $V, 'code' => 'M.8.5']);
                }
                $BindData = $Data[$K];
                $BindTag = $Tag;
                if ($Tag == '_Where_') {
                    $BindTag .= $K . '_';
                }
                $DataType=strtoupper((string)preg_filter('/^\((.*?)\)(.*)/','$1',$V));
                if(empty($DataType)||empty($PdoDataTypes[$DataType])){
                    $DataType='STR';
                }
                $V=preg_replace('/^\((.*?)\)/','',$V);
                if(!str_starts_with($V, '#')||str_contains($V,'?')){
                    self::$Stmts[$StmtKey]->bindValue(':' . $BindTag . md5($V) , $BindData,$PdoDataTypes[$DataType]);
                }
            }
        } else {
            foreach ($Data as $K => $V) {
                $DataType=strtoupper((string)preg_filter('/^\((.*?)\)(.*)/','$1',$K));
                if (is_array($V)) {
                    $V = json_encode($V, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                }
                if(empty($DataType)||empty($PdoDataTypes[$DataType])){
                    $DataType='STR';
                }
                $K=preg_replace('/^\((.*?)\)/','',$K);
                if($Md5){
                    $K=md5($K);
                }
                self::$Stmts[$StmtKey]->bindValue(':' . $Tag . $K, $V,$PdoDataTypes[$DataType]);
            }
        }
    }

    //执行预处理
    private static function execBind($StmtKey, $PreSql, $Action, $Debug)
    {
        self::sqlLog($PreSql);
        if ($Debug) {
            Api::wrong(['level' => 'F', 'detail' => 'Error#M.8.6' . "\r\n\r\n @ " . $PreSql, 'code' => 'M.8.6']);
        }

        try {
            self::$Stmts[$StmtKey]->execute();
        } catch (PDOException $Err) {
            $ModuleError = 'Detail: ' . $Err->getMessage() . ' | SQL String: ' . $PreSql . ' | errno:' . $Err->getCode(
                );
            Api::wrong(['level' => 'F', 'detail' => 'Error#M.8.2' . "\r\n\r\n @ " . $ModuleError, 'code' => 'M.8.2']);
        }

        if ($Action == 'Fetch') {
            return self::$Stmts[$StmtKey]->fetch(PDO::FETCH_ASSOC);
        } elseif ($Action == 'FetchAll') {
            return self::$Stmts[$StmtKey]->fetchAll(PDO::FETCH_ASSOC);
        } elseif ($Action == 'InsertId') {
            return self::$DbHandle->lastInsertId();
        } elseif ($Action == 'RowCount') {
            return self::$Stmts[$StmtKey]->rowCount();
        } else {
            return null;
        }
    }

    //写入日志
    private static function sqlLog($Sql): void
    {
        if ($_SERVER['APIPHP']['Config']['core\Db']['log']) {
            Log::add(['level' => 'debug', 'info' => '[SQL] ' . $Sql]);
        }
    }

    //获取表别名
    private static function getTableAlias($Name): string
    {
        if(!empty($_SERVER['APIPHP']['Config']['core\Db']['tableAlias'][$Name])){
            return $_SERVER['APIPHP']['Config']['core\Db']['tableAlias'][$Name];
        }
        else{
            return $Name;
        }
    }

    //获取表列表
    private static function getTableList($Table,$Index): string
    {
        $Return = '';
        foreach ($Table as $K => $V) {
            $V=preg_replace('/^\((.*?)\)/','',$V);
            if (str_starts_with($V, '#')) {
                $Return .=
                    str_replace(
                        '#',
                        '',
                        preg_replace_callback('/`(.*?)`/', function ($Matches) {
                            return self::splitName($Matches[1], 2);
                        }, $V)
                    );
            } else {
                $Return .= self::splitName($V, 2);
            }
            if (!empty($Index[$K])) {
                $Return .= ' ' . $Index[$K];
            }
            $Return .= ' ,';
        }
        return substr($Return, 0, -2);
    }

    //获取字段列表
    private static function getFieldList($FieldData, $Default, $RawCheck=false)
    {
        $FieldList = '';
        if (!empty($FieldData)) {
            foreach ($FieldData as $V) {
                $V=preg_replace('/^\((.*?)\)/','',$V);
                if ($RawCheck&&str_starts_with($V, '#')) {
                    $FieldList .=' ' .
                        str_replace(
                            '#',
                            '',
                            preg_replace_callback('/`(.*?)`/', function ($Matches) {
                                return self::splitName($Matches[1], 3);
                            }, $V)
                        );
                } else {
                    $FieldList .= ' ' . self::splitName($V, 3);
                }
                $FieldList .=' ,';
            }
            return substr($FieldList, 0, -2);
        }
        return $Default;
    }


    //拆分表名&字段名
    private static function splitName($Name,$Max): string
    {
        $NameArr=explode('.', str_replace(' ','',$Name));
        $Return='';
        $j=count($NameArr);
        for($i=0;$i<$j&&$i<$Max;$i++){
            if($i>0){
                $Return.='.';
            }
            if(($i==$j-2&&$Max==3)||($i==$j-1&&$Max==2)){
                $Return.='`'.self::getTableAlias($NameArr[$i]).'`';
            }
            else{
                if(str_contains($NameArr[$i], '*')&&$i==$j-1&&$Max==3){
                    $Return.='*';
                }
                else{
                    $Return.='`'.$NameArr[$i].'`';
                }
            }
        }
        return $Return;
    }

    //查询条件转SQL语句
    private static function queryToSql($Para): string
    {
        $WhereSql = '';
        if (!empty($Para['field'])){
            $WhereSql = ' WHERE';
        }
        $ArrLen=count($Para['field']);
        foreach ($Para['field'] as $K => $V) {
            $FieldCo = ['','=','AND',''];
            if (!empty($Para['condition'][$K])) {
                $TempCo = explode(',', $Para['condition'][$K]);
                foreach ($TempCo as $CKey => $CVal){
                    if ($FieldCo[0]==''&&str_contains($CVal, '(')) {
                        $FieldCo[0] = $CVal;
                        unset($TempCo[$CKey]);
                    }
                    if ($FieldCo[3]==''&&str_contains($CVal, ')')) {
                        $FieldCo[3] = $CVal;
                        unset($TempCo[$CKey]);
                    }
                    $TempCo=array_values($TempCo);
                    if(!empty($TempCo[0])){
                        $FieldCo[1] = $TempCo[0];
                    }
                    if(!empty($TempCo[1])){
                        $FieldCo[2] = $TempCo[1];
                    }
                }
            }
            if($Para['debug']){
                $FieldCo[1]=strtoupper($FieldCo[1]);
                $FieldCo[2]=strtoupper($FieldCo[2]);
            }
            $V=preg_replace('/^\((.*?)\)/','',$V);
            if (str_starts_with($V, '#')) {
                $WhereSql .= ' ' . $FieldCo[0] .
                    str_replace(['#', '?'],
                        ['', ':_Where_' . $K . '_' . md5($V)],
                        preg_replace_callback('/`(.*?)`/', function ($Matches) {
                            return self::splitName($Matches[1], 3);
                        }, $V)) . ' ' . $FieldCo[3];
            }
            else{
                $WhereSql .= ' ' . $FieldCo[0] . self::splitName($V,3) . ' ' . $FieldCo[1] . ' :_Where_' . $K . '_' . md5($V) . ' ' . $FieldCo[3];
            }
            if ($K < $ArrLen - 1) {
                $WhereSql .= ' ' . $FieldCo[2];
            }
        }
        $OrderSql = '';
        if (!empty($Para['order'])) {
            $OrderSql = ' ORDER BY ';
            foreach ($Para['order'] as $K => $V) {
                if (!empty($V)) {
                    $OrderSql .= self::splitName($V,3);
                    if (!empty($Para['desc'][$K])) {
                        $OrderSql .= ' DESC';
                    }
                    $OrderSql .= ',';
                }
            }
            $OrderSql = substr($OrderSql, 0, -1);
        }

        $LimitSql = '';
        if (!empty($Para['limit'][1])) {
            $LimitSql = ' LIMIT ' . intval($Para['limit'][0]) . ',' . intval($Para['limit'][1]);
        }

        $GroupBySql = '';
        if (!empty($Para['groupBy'])) {
            $GroupBySql = 'GROUP BY' . self::getFieldList($Para['groupBy'], '');
        }

        return $WhereSql . ' ' . $OrderSql . $LimitSql . $GroupBySql;
    }


    //查询方法调用
    private static function selectCall($Para,$FetchType){

        $QueryString = 'SELECT' . self::getFieldList($Para['fieldLimit'], '*',true) . ' FROM ' . self::getTableList($Para['table'],$Para['index']) . self::queryToSql($Para);

        $StmtKey = self::createBind($QueryString, $Para['dbName']);
        self::bindData($StmtKey, $Para['field'], $Para['value'], '_Where_');

        return self::execBind($StmtKey, $QueryString, $FetchType, $Para['debug']);

    }

    //查询一条数据
    public static function select($UnionData = [])
    {
        $Para = self::parameterCheck($UnionData, ['fieldLimit','json'], 'table');
        $Para['limit'] = [1];
        $Para['groupBy'] = [];
        $Result= self::selectCall($Para,'Fetch');
        foreach ($Para['json'] as $V){
            if(isset($Result[$V])){
                $Result[$V]=json_decode($Result[$V],true);
            }
        }
        return empty($Result)?false:$Result;
    }

    //查询多条数据
    public static function selectMore($UnionData = [])
    {
        $Para = self::parameterCheck($UnionData, ['fieldLimit', 'groupBy','json'], 'table');
        $Result= self::selectCall($Para,'FetchAll');
        foreach ($Result as $K => $Row){
            foreach ($Para['json'] as $V){
                if(isset($Row[$V])){
                    $Result[$K][$V]=json_decode($Row[$V],true);
                }
            }
        }
        return $Result;
    }


    //记录总数
    public static function total($UnionData = [])
    {
        $Para = self::parameterCheck($UnionData, ['groupBy'], 'table');

        $Para['fieldLimit'] = $Para['groupBy'];
        $Para['fieldLimit'][]='#COUNT(*) AS `__Total`';

        $Return=self::selectCall($Para,'FetchAll');

        if (!empty($Para['groupBy'])) {
            return $Return;
        } else {
            return $Return[0]['__Total'];
        }
    }

    //求和
    public static function sum($UnionData = []): array
    {
        $Para = self::parameterCheck($UnionData, ['sumField'], 'table');
        $Para['groupBy'] = [];
        $Para['fieldLimit'] = [];

        foreach ($Para['sumField'] as $K => $V) {
            $Para['fieldLimit'][]= '#SUM(' . self::splitName($K,3) . ')' . ' AS `' . $V . '`';
        }

        $Return=self::selectCall($Para,'Fetch');
        foreach ($Return as $K => $V) {
            if (empty($V)) {
                $Return[$K] = 0;
            }
        }
        return $Return;
    }

    //插入数据
    public static function insert($UnionData = [])
    {
        $Para = self::parameterCheck($UnionData, ['data']);

        $InsertField = null;
        $InsertValue = null;

        foreach ($Para['data'] as $K => $V) {
            $InsertField .= self::splitName($K,3) . ',';
            $InsertValue .= ':_Insert_' . md5($K) . ',';
        }
        $InsertField = substr($InsertField, 0, -1);
        $InsertValue = substr($InsertValue, 0, -1);

        $QueryString = 'INSERT INTO' . self::getTableList($Para['table'],$Para['index']) . ' ( ' . $InsertField . ' ) VALUES ( ' . $InsertValue . ' )' ;

        $StmtKey = self::createBind($QueryString, $Para['dbName']);
        self::bindData($StmtKey, [], $Para['data'], '_Insert_', true,true);

        return self::execBind($StmtKey, $QueryString, 'InsertId', $Para['debug']);
    }

    //全表误操作防护
    private static function tableChange($Unlock, $Field): void
    {
        if (!$Unlock && empty($Field)) {
            Api::wrong(['level' => 'F', 'detail' => 'Error#M.8.4', 'code' => 'M.8.4']);
        }
    }

    //删除数据
    public static function delete($UnionData = [])
    {
        $Para = self::parameterCheck($UnionData, ['rowCount', 'unlock'], 'table');
        self::tableChange($Para['unlock'], $Para['field']);

        $Para['groupBy'] = null;
        $QueryString = 'DELETE FROM' . self::getTableList($Para['table'],$Para['index']) . self::queryToSql($Para);

        $StmtKey = self::createBind($QueryString, $Para['dbName']);
        self::bindData($StmtKey, $Para['field'], $Para['value'], '_Where_');

        return self::execBind($StmtKey, $QueryString, $Para['rowCount'] ? 'RowCount' : '', $Para['debug']);
    }

    //更新数据
    public static function update($UnionData = [])
    {
        $Para = self::parameterCheck($UnionData, ['data', 'rowCount', 'unlock'], 'table');
        self::tableChange($Para['unlock'], $Para['field']);

        $DataSql = null;

        foreach ($Para['data'] as $K => $V) {
            $K=preg_replace('/^\((.*?)\)/','',$K);
            if (str_starts_with($K, '#')) {
                $DataSql .=
                    str_replace(['#', '?'],
                        ['', ':_Update_' . md5($K)],
                        preg_replace_callback('/`(.*?)`/', function ($Matches) {
                            return self::splitName($Matches[1], 3);
                        }, $K));
            }
            else{
                $DataSql .= self::splitName($K,3) . ' = :_Update_' . md5($K);
            }

            $DataSql .= ',';
        }
        $DataSql = substr($DataSql, 0, -1);

        $Para['groupBy'] = null;
        $QueryString = 'UPDATE' . self::getTableList($Para['table'],$Para['index']) . ' SET ' . $DataSql . self::queryToSql($Para);

        $StmtKey = self::createBind($QueryString, $Para['dbName']);
        self::bindData($StmtKey, $Para['field'], $Para['value'], '_Where_');
        self::bindData($StmtKey, [], $Para['data'], '_Update_', true,true);

        return self::execBind($StmtKey, $QueryString, $Para['rowCount'] ? 'RowCount' : '', $Para['debug']);
    }

    //查询自定义语句
    public static function raw($UnionData = [])
    {
        $Sql = Common::quickParameter($UnionData, 'sql', 'sql', true, '', true);
        $Bind = Common::quickParameter($UnionData, 'bind', '绑定', false, []);
        $Fetch = Common::quickParameter($UnionData, 'fetch_result', '取回结果', false, false);
        $DbName = Common::quickParameter($UnionData, 'db_name', '数据库', false, '');

        $StmtKey = self::createBind($Sql, $DbName);
        self::bindData($StmtKey, [], $Bind, '', true);

        return self::execBind($StmtKey, $Sql, $Fetch ? 'FetchAll' : '', false);
    }

    //事务
    public static function acid($UnionData = []): bool
    {
        $Option = Common::quickParameter($UnionData, 'option', '操作', true, null, true);
        $DbName = Common::quickParameter($UnionData, 'db_name', '数据库', false, '');
        self::connect($DbName);

        if ($Option == 'start') {
            try {
                self::$DbHandle->beginTransaction();
                return true;
            } catch (Exception $Err) {
                Api::wrong(
                    [
                        'level' => 'F',
                        'detail' => 'Error#M.8.3' . "\r\n\r\n @ " . 'Detail: ' . $Err->getMessage(),
                        'code' => 'M.8.3'
                    ]
                );
            }
        } elseif ($Option == 'commit') {
            if (!self::$DbHandle->commit()) {
                return false;
            } else {
                return true;
            }
        } elseif ($Option == 'cancel') {
            if (!self::$DbHandle->rollBack()) {
                return false;
            } else {
                return true;
            }
        }
        return false;
    }

    public static function __callStatic($Method, $Parameters)
    {
        Common::unknownStaticMethod(__CLASS__, $Method);
    }
}