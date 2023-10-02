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
                self::$DbHandle = @new PDO(
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
            'order' => ['order', '排序', false, null],
            'desc' => ['desc', '降序', false, false],
            'limit' => ['limit', '限制', false, null],
            'index' => ['index', '索引', false, null],
            'sql' => ['sql', 'sql', false, null],
            'bind' => ['bind', '绑定', false, []],
            'dbName' => ['db_name', '数据库', false, ''],
            'debug' => ['debug', '调试', false, false]
        ];

        $ExtraParameters = [
            'data' => ['data', '数据', true, null],
            'sumField' => ['sum', '合计', true, null],
            'fieldLimit' => ['field_limit', '字段限制', false, null],
            'rowCount' => ['row_count', '行数统计', false, false],
            'autoOp' => ['auto_operate', '自动操作', false, null],
            'groupBy' => ['group_by', '分组', false, null],
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
    private static function bindData($StmtKey, $Field, $Data, $Tag = '', $Mix = false): void
    {
        if (!$Mix) {
            foreach ($Field as $K => $V) {
                if (!isset($Data[$K])) {
                    Api::wrong(['level' => 'F', 'detail' => 'Error#M.8.5' . "\r\n\r\n @ " . $V, 'code' => 'M.8.5']);
                }
                if (is_array($Data[$K])) {
                    $BindData = json_encode($Data[$K], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                } else {
                    $BindData = $Data[$K];
                }
                $BindTag = $Tag;
                if ($Tag == '_Where_') {
                    $BindTag .= $K . '_';
                }
                self::$Stmts[$StmtKey]->bindValue(':' . $BindTag . md5($V) , $BindData);
            }
        } else {
            foreach ($Data as $K => $V) {
                self::$Stmts[$StmtKey]->bindValue(':' . $Tag . $K, $V);
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
    private static function getTableList($TableData,$IndexRule): string
    {
        if (is_array($TableData)) {
            $TableList = '';
            foreach ($TableData as $K => $V) {
                $TableList .= ' `' . self::getTableAlias($V) . '`';
                if(!empty($IndexRule[$K])){
                    $TableList .=' '.$IndexRule[$K];
                }
                $TableList .=' ,';
            }
            return substr($TableList, 0, -2);
        } else {
            $Return=' `' . self::getTableAlias($TableData).'`';
            if(!empty($IndexRule)){
                $Return.=' '.$IndexRule[0];
            }
            return $Return;
        }
    }

    //获取字段列表
    private static function getFieldList($FieldData, $Default)
    {
        $FieldList = '';
        if (!empty($FieldData)) {
            if (is_string($FieldData)) {
                return ' ' . $FieldData;
            } elseif (is_array($FieldData)) {
                foreach ($FieldData as $V) {
                    $FieldList .= ' ' . self::splitField($V) . ' ,';
                }
                return substr($FieldList, 0, -1);
            }
        }
        return $Default;
    }
    //拆分字段名
    private static function splitField($Name): string
    {
        $Name=str_replace('\.','#',$Name);
        $NameArr=explode('.', $Name);
        $Return='`'.self::getTableAlias(str_replace('#','.',$NameArr[0])).'`';
        if(!empty($NameArr[1])){
            if(str_replace(' ','',$NameArr[1])!='*'){
                $NameArr[1]='`'.str_replace('#','.',$NameArr[1]).'`';
            }

            $Return.='.'.$NameArr[1];
        }
        return $Return;
    }

    //查询条件转SQL语句
    private static function queryToSql($Para): string
    {
        $WhereSql = ' WHERE';
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
            $WhereSql .= ' ' . $FieldCo[0] . self::splitField($V) . ' ' . $FieldCo[1] . ' :_Where_' . $K . '_' . md5($V) . ' ' . $FieldCo[3];
            if ($K < (count($Para['field']) - 1)) {
                $WhereSql .= ' ' . $FieldCo[2];
            }
        }
        if (is_string($Para['order'])) {
            $OrderSql = ' ORDER BY ' . self::splitField($Para['order']);
            if ($Para['desc']) {
                $OrderSql .= ' DESC';
            }
        } elseif (is_array($Para['order'])) {
            $OrderSql = ' ORDER BY ';
            foreach ($Para['order'] as $K => $V) {
                if (!empty($V)) {
                    $OrderSql .= self::splitField($V);
                    if ($Para['desc'] || (isset($Para['desc'][$K]) && $Para['desc'][$K])) {
                        $OrderSql .= ' DESC';
                    }
                    $OrderSql .= ',';
                }
            }
            $OrderSql = substr($OrderSql, 0, -1);
        } else {
            $OrderSql = '';
        }

        $LimitSql = '';
        if (is_array($Para['limit'])) {
            if (!empty($Para['limit'][1])) {
                $LimitSql = ' LIMIT ' . intval($Para['limit'][0]) . ',' . intval($Para['limit'][1]);
            } elseif (isset($Para['limit'][0])) {
                $LimitSql = ' LIMIT 0,' . intval($Para['limit'][0]);
            }
        }

        if (!empty($Para['groupBy'])) {
            $GroupBySql = 'GROUP BY ' . self::getFieldList($Para['groupBy'], '');
        } else {
            $GroupBySql = '';
        }

        return $WhereSql . ' ' . $Para['sql'] . $OrderSql . $LimitSql . $GroupBySql;
    }

    //查询一条数据
    public static function select($UnionData = [])
    {
        $Para = self::parameterCheck($UnionData, ['fieldLimit'], 'table');

        $Para['limit'] = [1];
        $Para['groupBy'] = null;

        $QueryString = 'SELECT ' . self::getFieldList($Para['fieldLimit'], '*') . ' FROM' . self::getTableList($Para['table'],$Para['index']) . self::queryToSql($Para);

        $StmtKey = self::createBind($QueryString, $Para['dbName']);
        self::bindData($StmtKey, $Para['field'], $Para['value'], '_Where_');
        self::bindData($StmtKey, [], $Para['bind'], '', true);

        return self::execBind($StmtKey, $QueryString, 'Fetch', $Para['debug']);
    }

    //查询多条数据
    public static function selectMore($UnionData = [])
    {
        $Para = self::parameterCheck($UnionData, ['fieldLimit', 'groupBy'], 'table');

        $QueryString = 'SELECT ' . self::getFieldList($Para['fieldLimit'], '*') . ' FROM' . self::getTableList($Para['table'],$Para['index']) . self::queryToSql($Para);

        $StmtKey = self::createBind($QueryString, $Para['dbName']);
        self::bindData($StmtKey, $Para['field'], $Para['value'], '_Where_');
        self::bindData($StmtKey, [], $Para['bind'], '', true);

        return self::execBind($StmtKey, $QueryString, 'FetchAll', $Para['debug']);
    }

    //记录总数
    public static function total($UnionData = [])
    {
        $Para = self::parameterCheck($UnionData, ['fieldLimit', 'groupBy'], 'table');

        $Para['fieldLimit'] = '';
        if (!empty($Para['groupBy'])) {
            $Para['fieldLimit'] .= self::getFieldList($Para['groupBy'], '') . ',';
        }

        $QueryString = 'SELECT ' . $Para['fieldLimit'] . ' COUNT(*) AS Total FROM' . self::getTableList($Para['table'],$Para['index']) . self::queryToSql($Para);

        $StmtKey = self::createBind($QueryString, $Para['dbName']);
        self::bindData($StmtKey, $Para['field'], $Para['value'], '_Where_');
        self::bindData($StmtKey, [], $Para['bind'], '', true);

        $Return = self::execBind($StmtKey, $QueryString, 'FetchAll', $Para['debug']);

        if (!empty($Para['groupBy'])) {
            return $Return;
        } else {
            return $Return[0]['Total'];
        }
    }

    //求和
    public static function sum($UnionData = []): array
    {
        $Para = self::parameterCheck($UnionData, ['sumField'], 'table');

        $SumSql = '';
        foreach ($Para['sumField'] as $K => $V) {
            $SumSql .= ' SUM(' . $K . ')' . ' AS ' . $V . ',';
        }
        $SumSql = substr($SumSql, 0, -1);

        $Para['groupBy'] = null;
        $QueryString = 'SELECT' . $SumSql . ' FROM' . self::getTableList($Para['table'],$Para['index']) . self::queryToSql($Para);

        $StmtKey = self::createBind($QueryString, $Para['dbName']);
        self::bindData($StmtKey, $Para['field'], $Para['value'], '_Where_');
        self::bindData($StmtKey, [], $Para['bind'], '', true);

        $Return = self::execBind($StmtKey, $QueryString, 'Fetch', $Para['debug']);
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
            $InsertField .= $K . ',';
            $InsertValue .= ':_Insert_' . $K . ',';
        }
        $InsertField = substr($InsertField, 0, -1);
        $InsertValue = substr($InsertValue, 0, -1);

        $QueryString = 'INSERT INTO' . self::getTableList($Para['table'],$Para['index']) . ' ( ' . $InsertField . ' ) VALUES ( ' . $InsertValue . ' )' . ' ' . $Para['sql'];

        $StmtKey = self::createBind($QueryString, $Para['dbName']);
        self::bindData($StmtKey, [], $Para['data'], '_Insert_', true);
        self::bindData($StmtKey, [], $Para['bind'], '', true);

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
        self::bindData($StmtKey, [], $Para['bind'], '', true);

        return self::execBind($StmtKey, $QueryString, $Para['rowCount'] ? 'RowCount' : '', $Para['debug']);
    }

    //更新数据
    public static function update($UnionData = [])
    {
        $Para = self::parameterCheck($UnionData, ['data', 'rowCount', 'autoOp', 'unlock'], 'table');
        self::tableChange($Para['unlock'], $Para['field']);

        $DataSql = null;
        $AutoOpNumber = 0;

        foreach ($Para['data'] as $K => $V) {
            if (!empty($Para['autoOp'][$AutoOpNumber])) {
                $DataSql .= $K . ' = ' . $K . ' ' . $Para['autoOp'][$AutoOpNumber];
            } else {
                $DataSql .= $K . ' = :_Update_' . $K;
            }
            $DataSql .= ',';
            $AutoOpNumber++;
        }
        $DataSql = substr($DataSql, 0, -1);

        $Para['groupBy'] = null;
        $QueryString = 'UPDATE' . self::getTableList($Para['table'],$Para['index']) . ' SET ' . $DataSql . self::queryToSql($Para);

        $StmtKey = self::createBind($QueryString, $Para['dbName']);
        self::bindData($StmtKey, $Para['field'], $Para['value'], '_Where_');
        self::bindData($StmtKey, [], $Para['data'], '_Update_', true);
        self::bindData($StmtKey, [], $Para['bind'], '', true);

        return self::execBind($StmtKey, $QueryString, $Para['rowCount'] ? 'RowCount' : '', $Para['debug']);
    }

    //查询自定义语句
    public static function other($UnionData = [])
    {
        $Sql = Common::quickParameter($UnionData, 'sql', 'sql', true, null, true);
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