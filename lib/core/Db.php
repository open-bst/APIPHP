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
    public static function choose($UnionData)
    {
        $DbName = Common::quickParameter($UnionData, 'db_name', '数据库', true, null, true);
        if (!empty($DbName)) {
            $_SERVER['APIPHP']['Config']['core\Db']['default'] = $DbName;
        }
    }

    //连接数据库
    private static function connect($DbName)
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
            'condition' => ['condition', '条件', false, '='],
            'order' => ['order', '顺序', false, null],
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

        foreach ($Extra as $Val) {
            $Parameters[$Val] = $ExtraParameters[$Val];
        }

        foreach ($Parameters as $Key => $Val) {
            $Result[$Key] = Common::quickParameter($UnionData, $Val[0], $Val[1], $Val[2], $Val[3], $Default == $Key);
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
    private static function bindData($StmtKey, $Field, $Data, $Tag = '', $Mix = false)
    {
        if (!$Mix) {
            foreach ($Field as $Key => $Val) {
                if (!isset($Data[$Key])) {
                    Api::wrong(['level' => 'F', 'detail' => 'Error#M.8.5' . "\r\n\r\n @ " . $Val, 'code' => 'M.8.5']);
                }
                if (is_array($Data[$Key])) {
                    $BindData = json_encode($Data[$Key], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                } else {
                    $BindData = $Data[$Key];
                }
                $BindTag = $Tag;
                if ($Tag == '_Where_') {
                    $BindTag .= $Key . '_';
                }
                self::$Stmts[$StmtKey]->bindValue(':' . $BindTag . str_replace('.','',$Val), $BindData);
            }
        } else {
            foreach ($Data as $Key => $Val) {
                self::$Stmts[$StmtKey]->bindValue(':' . $Tag . $Key, $Val);
            }
        }
    }

    //执行预处理
    private static function execBind($StmtKey, $PreSql, $Action, $Debug)
    {
        self::sqlLog($PreSql);
        if ($Debug) {
            return $PreSql;
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
    private static function sqlLog($Sql)
    {
        if ($_SERVER['APIPHP']['Config']['core\Db']['log']) {
            Log::add(['level' => 'debug', 'info' => '[SQL] ' . $Sql]);
        }
    }

    //获取表列表
    private static function getTableList($TableData)
    {
        $TableList = '';
        if (is_array($TableData)) {
            foreach ($TableData as $Val) {
                $TableList .= ' ' . $Val . ' ,';
            }
            return substr($TableList, 0, -1);
        } else {
            return ' ' . $TableData;
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
                foreach ($FieldData as $Val) {
                    $FieldList .= ' ' . $Val . ' ,';
                }
                return substr($FieldList, 0, -1);
            }
        }
        return $Default;
    }

    //查询条件转SQL语句
    private static function queryToSql($Para, $Sql = ''): string
    {
        if (empty($Para['condition'])) {
            $Para['condition'] = '=';
        }

        $WhereSql = '';
        foreach ($Para['field'] as $Key => $Val) {
            if ($WhereSql == '') {
                $WhereSql = ' WHERE';
            }

            if (!is_array($Para['condition']) || empty($Para['condition'][$Key])) {
                $TempCo = ['=', 'AND'];
                $WhereSql .= ' ' . $Val . ' ' . $TempCo[0] . ' :_Where_' . $Key . '_' . str_replace('.','',$Val);
            } elseif (!is_array($Para['condition'][$Key])) {
                if (strpos($Para['condition'][$Key], ',') === false) {
                    $Para['condition'][$Key] = str_replace(' ', '', $Para['condition'][$Key]);
                    $TempCo = [$Para['condition'][$Key], 'AND'];
                } else {
                    $Para['condition'][$Key] = str_replace(' ', '', $Para['condition'][$Key]);
                    $TempCo = explode(',', $Para['condition'][$Key]);
                    if (empty($TempCo[1])) {
                        $TempCo[1] = 'AND';
                    }
                }
                $WhereSql .= ' ' . $Val . ' ' . $TempCo[0] . ' :_Where_' . $Key . '_' . str_replace('.','',$Val);
            } else {
                if (empty($Para['condition'][$Key][0])) {
                    $TempCo = ['=', 'AND'];
                } elseif (strpos($Para['condition'][$Key][0], ',') === false) {
                    $Para['condition'][$Key][0] = str_replace(' ', '', $Para['condition'][$Key][0]);
                    $TempCo = [$Para['condition'][$Key][0], 'AND'];
                } else {
                    $Para['condition'][$Key][0] = str_replace(' ', '', $Para['condition'][$Key][0]);
                    $TempCo = explode(',', $Para['condition'][$Key][0]);
                    if (empty($TempCo[1])) {
                        $TempCo[1] = 'AND';
                    }
                }
                $TempBeforeTag = '';
                $TempAfterTag = '';
                if (!empty($Para['condition'][$Key][1])) {
                    if (strpos($Para['condition'][$Key][1], ',') === false) {
                        $Para['condition'][$Key][1] = str_replace(' ', '', $Para['condition'][$Key][1]);
                        $TempBeforeTag = $Para['condition'][$Key][1];
                    } else {
                        $Para['condition'][$Key][1] = str_replace(' ', '', $Para['condition'][$Key][1]);
                        $TempTag = explode(',', $Para['condition'][$Key][1]);
                        $TempBeforeTag = $TempTag[0];
                        $TempAfterTag = $TempTag[1];
                    }
                }

                $WhereSql .= ' ' . $TempBeforeTag . $Val . ' ' . $TempCo[0] . ' :_Where_' . $Key . '_' . str_replace('.','',$Val) . ' ' . $TempAfterTag;
            }
            if ($Key < (count($Para['field']) - 1)) {
                $WhereSql .= ' ' . $TempCo[1];
            }
        }
        if (is_string($Para['order'])) {
            $OrderSql = ' ORDER BY ' . $Para['order'];
            if ($Para['desc']) {
                $OrderSql .= ' DESC';
            }
        } elseif (is_array($Para['order'])) {
            $OrderSql = ' ORDER BY ';
            foreach ($Para['order'] as $Key => $Val) {
                if (!empty($Val)) {
                    $OrderSql .= $Val;
                    if ($Para['desc'] || (isset($Para['desc'][$Key]) && $Para['desc'][$Key])) {
                        $OrderSql .= ' DESC';
                    }
                    $OrderSql .= ',';
                }
            }
            $OrderSql = substr($OrderSql, 0, -1);
        } else {
            $OrderSql = '';
        }
        if (!empty($Para['index'])) {
            $IndexSql = ' FORCE INDEX(' . $Para['index'] . ')';
        } else {
            $IndexSql = '';
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

        return $WhereSql . ' ' . $Para['sql'] . $OrderSql . $LimitSql . $IndexSql . $GroupBySql;
    }

    //查询一条数据
    public static function select($UnionData = [])
    {
        $Para = self::parameterCheck($UnionData, ['fieldLimit'], 'table');

        $Para['limit'] = [1];
        $Para['groupBy'] = null;

        $QueryString = 'SELECT ' . self::getFieldList($Para['fieldLimit'], '*') . ' FROM' . self::getTableList(
                $Para['table']
            ) . self::queryToSql($Para);

        $StmtKey = self::createBind($QueryString, $Para['dbName']);
        self::bindData($StmtKey, $Para['field'], $Para['value'], '_Where_');
        self::bindData($StmtKey, [], $Para['bind'], '', true);

        return self::execBind($StmtKey, $QueryString, 'Fetch', $Para['debug']);
    }

    //查询多条数据
    public static function selectMore($UnionData = [])
    {
        $Para = self::parameterCheck($UnionData, ['fieldLimit', 'groupBy'], 'table');

        $QueryString = 'SELECT ' . self::getFieldList($Para['fieldLimit'], '*') . ' FROM' . self::getTableList(
                $Para['table']
            ) . self::queryToSql($Para);

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

        $QueryString = 'SELECT ' . $Para['fieldLimit'] . ' COUNT(*) AS Total FROM' . self::getTableList(
                $Para['table']
            ) . self::queryToSql($Para);

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
        foreach ($Para['sumField'] as $Key => $Val) {
            $SumSql .= ' SUM(' . $Key . ')' . ' AS ' . $Val . ',';
        }
        $SumSql = substr($SumSql, 0, -1);

        $Para['groupBy'] = null;
        $QueryString = 'SELECT' . $SumSql . ' FROM' . self::getTableList($Para['table']) . self::queryToSql($Para);

        $StmtKey = self::createBind($QueryString, $Para['dbName']);
        self::bindData($StmtKey, $Para['field'], $Para['value'], '_Where_');
        self::bindData($StmtKey, [], $Para['bind'], '', true);

        $Return = self::execBind($StmtKey, $QueryString, 'Fetch', $Para['debug']);
        foreach ($Return as $Key => $Val) {
            if (empty($Val)) {
                $Return[$Key] = 0;
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

        foreach ($Para['data'] as $Key => $Val) {
            $InsertField .= $Key . ',';
            $InsertValue .= ':_Insert_' . $Key . ',';
        }
        $InsertField = substr($InsertField, 0, -1);
        $InsertValue = substr($InsertValue, 0, -1);

        $QueryString = 'INSERT INTO' . self::getTableList(
                $Para['table']
            ) . ' ( ' . $InsertField . ' ) VALUES ( ' . $InsertValue . ' )' . ' ' . $Para['sql'];

        $StmtKey = self::createBind($QueryString, $Para['dbName']);
        self::bindData($StmtKey, [], $Para['data'], '_Insert_', true);
        self::bindData($StmtKey, [], $Para['bind'], '', true);

        return self::execBind($StmtKey, $QueryString, 'InsertId', $Para['debug']);
    }

    //全表误操作防护
    private static function tableChange($Unlock, $Field)
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
        $QueryString = 'DELETE FROM' . self::getTableList($Para['table']) . self::queryToSql($Para);

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

        foreach ($Para['data'] as $Key => $Val) {
            if (!empty($Para['autoOp'][$AutoOpNumber])) {
                $DataSql .= $Key . ' = ' . $Key . ' ' . $Para['autoOp'][$AutoOpNumber];
            } else {
                $DataSql .= $Key . ' = :_Update_' . $Key;
            }
            $DataSql .= ',';
            $AutoOpNumber++;
        }
        $DataSql = substr($DataSql, 0, -1);

        $Para['groupBy'] = null;
        $QueryString = 'UPDATE' . self::getTableList($Para['table']) . ' SET ' . $DataSql . self::queryToSql($Para);

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