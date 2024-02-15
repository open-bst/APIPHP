<?php

namespace core;

/*
  APIPHP开源框架

  ©2024 APIPHP.com

  框架版本号：1.0.0
*/

class Ip
{
    private static $BlackListFile;
    private static $WhiteListFile;
    private static $BlackList;
    private static $WhiteList;


    private static function initial(): bool
    {
        if (!empty($_SERVER['APIPHP']['Runtime']['core\Ip']['initial'])) {
            return true;
        }

        self::$BlackListFile = _ROOT . '/temp/ip-blacklist.php';
        self::$WhiteListFile = _ROOT . '/temp/ip-whitelist.php';
        if (!file_exists(self::$BlackListFile)) {
            if (!file_put_contents(self::$BlackListFile, '<?php exit; ?>')) {
                Api::wrong(['level' => 'F', 'detail' => 'Error#M.3.0', 'code' => 'M.3.0']);
            }
        }
        if (!file_exists(self::$WhiteListFile)) {
            if (!file_put_contents(self::$WhiteListFile, '<?php exit; ?>')) {
                Api::wrong(['level' => 'F', 'detail' => 'Error#M.3.0', 'code' => 'M.3.0']);
            }
        }
        $BlackListText = file_get_contents(self::$BlackListFile);
        $WhiteListText = file_get_contents(self::$WhiteListFile);
        if ($BlackListText === false || $WhiteListText === false) {
            Api::wrong(['level' => 'F', 'detail' => 'Error#M.3.1', 'code' => 'M.3.1']);
        }

        self::$BlackList = self::textToArray($BlackListText);
        self::$WhiteList = self::textToArray($WhiteListText);

        $_SERVER['APIPHP']['Runtime']['core\Ip']['initial'] = 1;
        return true;
    }

    //转换
    private static function transform($Str, $Start = true)
    {
        if (ctype_digit($Str)) {
            return long2ip($Str);
        }
        if ($Start) {
            $Str = str_replace('*', '0', $Str);
        } else {
            $Str = str_replace('*', '255', $Str);
        }
        $IntIP = ip2long($Str);
        if ($IntIP === false) {
            return false;
        }
        return sprintf('%u', $IntIP);
    }

    //文本转数组
    private static function textToArray($Str): array
    {
        $Str = preg_replace('/[^\d.\-*,&]/', '', $Str);
        $FirstStep = explode('&', $Str);
        $SecondStep = [];
        foreach ($FirstStep as $K => $V) {
            if (!empty($V)) {
                $SecondStep[$K] = explode(',', $V);
                if (isset($SecondStep[$K][1])) {
                    $SecondStep[$K][2] = $SecondStep[$K][1];
                    $TempArray = explode('-', $SecondStep[$K][0]);
                    if (isset($TempArray[1])) {
                        $SecondStep[$K][0] = $TempArray[0];
                        $SecondStep[$K][1] = $TempArray[1];
                    }
                }
            }
        }
        return $SecondStep;
    }

    //数组转文本
    private static function arrayToText($Array): string
    {
        $Return = '';
        foreach ($Array as $V) {
            if (isset($V[0])) {
                if (!isset($V[1])) {
                    $V[1] = $V[0];
                }
                if (!isset($V[2])) {
                    $V[2] = '';
                }
                if ($V[0] > $V[1]) {
                    $Return .= $V[1] . '-' . $V[0] . ',' . $V[2] . '&';
                } else {
                    $Return .= $V[0] . '-' . $V[1] . ',' . $V[2] . '&';
                }
            }
        }
        return $Return;
    }

    //移除
    private static function remove($Type, $StartIPNumber, $EndIPNumber): bool
    {
        if (strtolower($Type) == 'b') {
            $ListArray = self::$BlackList;
        } else {
            $ListArray = self::$WhiteList;
        }
        foreach ($ListArray as $K => $V) {
            if ($StartIPNumber == $V[0] && $EndIPNumber == $V[1]) {
                if (strtolower($Type) == 'b') {
                    unset(self::$BlackList[$K]);
                } else {
                    unset(self::$WhiteList[$K]);
                }
            }
        }
        return true;
    }

    //写入文件
    private static function save($UnionData = [])
    {
        $Type = Common::quickParameter($UnionData, 'type', '类型', false, 'b');
        if (strtolower($Type) == 'b') {
            $ListText = self::arrayToText(self::$BlackList);
            $Handle = @fopen(self::$BlackListFile, 'w');
        } else {
            $ListText = self::arrayToText(self::$WhiteList);
            $Handle = @fopen(self::$WhiteListFile, 'w');
        }
        if (!$Handle) {
            Api::wrong(['level' => 'F', 'detail' => 'Error#M.3.2', 'code' => 'M.3.2']);
        }
        fwrite($Handle, '<?php exit; ?>' . $ListText);
        fclose($Handle);
    }

    //添加
    public static function add($UnionData = []): bool
    {
        $Type = Common::quickParameter($UnionData, 'type', '类型');
        $StartIP = Common::quickParameter($UnionData, 'ip_start', '起始ip');
        $EndIP = Common::quickParameter($UnionData, 'ip_end', '结束ip', false);
        $ExpTime = Common::quickParameter($UnionData, 'exp_time', '过期时间', false);

        self::initial();

        if (empty($StartIP)) {
            return false;
        }
        if (ip2long($StartIP) === false) {
            return false;
        }
        if (empty($EndIP)) {
            $EndIP = $StartIP;
        }
        if (ip2long($EndIP) === false) {
            return false;
        }
        if (!empty($ExpTime) && intval($ExpTime) < _TIME) {
            return false;
        }
        $StartIPNumber = self::transform($StartIP);
        $EndIPNumber = self::transform($EndIP, false);
        self::remove($Type, $StartIPNumber, $EndIPNumber);
        if (strtolower($Type) == 'b') {
            self::$BlackList[] = [$StartIPNumber, $EndIPNumber, $ExpTime];
        } else {
            self::$WhiteList[] = [$StartIPNumber, $EndIPNumber, $ExpTime];
        }
        self::save($Type);
        return true;
    }

    //移除
    public static function delete($UnionData = []): bool
    {
        $StartIP = Common::quickParameter($UnionData, 'ip_start', '起始ip');
        $EndIP = Common::quickParameter($UnionData, 'ip_end', '结束ip', false);
        $Type = Common::quickParameter($UnionData, 'type', '类型');

        self::initial();

        if (empty($StartIP)) {
            return false;
        }
        if (ip2long($StartIP) === false) {
            return false;
        }
        if (empty($EndIP)) {
            $EndIP = $StartIP;
        }
        if (ip2long($EndIP) === false) {
            return false;
        }
        $StartIPNumber = self::transform($StartIP);
        $EndIPNumber = self::transform($EndIP, false);
        self::remove($Type, $StartIPNumber, $EndIPNumber);
        self::save($Type);
        return true;
    }

    //IP名单检测
    public static function check($UnionData = []): bool
    {
        $IP = Common::quickParameter($UnionData, 'ip', 'ip', false);
        self::initial();

        if (ip2long($IP) === false) {
            return false;
        }
        if (empty($IP)) {
            $IP = $_SERVER['REMOTE_ADDR'];
        }

        if (!self::find(['type' => 'w', 'ip' => $IP]) && self::find(['type' => 'b', 'ip' => $IP])) {
            return false;
        }
        return false;
    }

    //导出全部记录
    public static function getAll($UnionData = []): array
    {
        $Type = Common::quickParameter($UnionData, 'type', '类型', true, null, true);

        self::initial();

        $Return = [];
        if (strtolower($Type) == 'b') {
            $ListArray = self::$BlackList;
        } else {
            $ListArray = self::$WhiteList;
        }
        foreach ($ListArray as $V) {
            $Return[] = [
                self::transform($V[0]),
                self::transform($V[1]),
                $V[2]
            ];
        }
        return $Return;
    }

    //查找
    public static function find($UnionData = []): bool
    {
        $Type = Common::quickParameter($UnionData, 'type', '类型');
        $IP = Common::quickParameter($UnionData, 'ip', 'ip地址');

        self::initial();

        if (empty($IP) || ip2long($IP) === false) {
            return false;
        }
        $IPNumber = self::transform($IP);
        if (strtolower($Type) == 'b') {
            $ListArray = self::$BlackList;
        } else {
            $ListArray = self::$WhiteList;
        }
        foreach ($ListArray as $V) {
            if (($IPNumber == $V[0] || ($IPNumber > $V[0] && $IPNumber < $V[1])) && (_TIME <= $V[2] || empty($V[2]))) {
                return true;
            }
        }
        return false;
    }

    //清理
    public static function clean($UnionData = [])
    {
        $Type = Common::quickParameter($UnionData, 'type', '类型', true, null, true);
        $Reset = Common::quickParameter($UnionData, 'reset', '重置', false, false);

        self::initial();

        if ($Reset) {
            if (strtolower($Type) == 'b' || empty($Type)) {
                self::$BlackList = [];
            }
            if (strtolower($Type) == 'w' || empty($Type)) {
                self::$WhiteList = [];
            }
        } else {
            if (strtolower($Type) == 'b' || empty($Type)) {
                foreach (self::$BlackList as $K => $V) {
                    if (!empty($V[2]) && intval($V[2]) < _TIME) {
                        unset(self::$BlackList[$K]);
                    }
                }
            }
            if (strtolower($Type) == 'w' || empty($Type)) {
                foreach (self::$WhiteList as $K => $V) {
                    if (!empty($V[2]) && intval($V[2]) < _TIME) {
                        unset(self::$WhiteList[$K]);
                    }
                }
            }
        }
        if (strtolower($Type) == 'b' || empty($Type)) {
            self::save('b');
        }
        if (strtolower($Type) == 'w' || empty($Type)) {
            self::save('w');
        }
    }

    public static function __callStatic($Method, $Parameters)
    {
        Common::unknownStaticMethod(__CLASS__, $Method);
    }
}