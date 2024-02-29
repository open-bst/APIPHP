<?php

namespace core;

/*
  APIPHP开源框架

  ©2024 APIPHP.com

  框架版本号：1.0.0
*/

class Load
{

    private static function upCall(
        $FileError,
        $FileName,
        $FileSize,
        $FileTmpName,
        $SaveNameInfo,
        $PathInfo,
        $SizeInfo,
        $TypeInfo,
        $IgnoreErrorInfo
    ): ?string {
        if ($FileError > 0) {
            $ModuleError = match ($FileError) {
                1 => '3',
                2 => '4',
                3 => '5',
                4 => '6',
                default => '7',
            };
            if (!$IgnoreErrorInfo) {
                Api::wrong(['level' => 'F', 'detail' => 'Error#M.4.' . $ModuleError, 'code' => 'M.4.' . $ModuleError]);
            } else {
                return null;
            }
        }
        $Exp = explode('.', $FileName);
        $Suffix = strtolower(end($Exp));

        $TypeInfo = explode(',', $TypeInfo);
        foreach ($TypeInfo as $K => $V) {
            $TypeInfo[$K] = strtoupper($V);
        }
        if (!in_array(strtoupper($Suffix), $TypeInfo)) {
            if (!$IgnoreErrorInfo) {
                Api::wrong(['level' => 'F', 'detail' => 'Error#M.4.8', 'code' => 'M.4.8']);
            } else {
                return null;
            }
        }

        if ($FileSize > $SizeInfo) {
            if (!$IgnoreErrorInfo) {
                Api::wrong(['level' => 'F', 'detail' => 'Error#M.4.9', 'code' => 'M.4.9']);
            } else {
                return null;
            }
        }
        if (empty($SaveNameInfo)) {
            $FileName = Tool::uuid(['type'=>'string']). '.' . $Suffix;
        } else {
            $FileName = $SaveNameInfo . '.' . $Suffix;
        }
        if (!file_exists($PathInfo)) {
            mkdir($PathInfo, 0777, true);
        }
        if (!move_uploaded_file($FileTmpName, $PathInfo . '/' . $FileName)) {
            if (!$IgnoreErrorInfo) {
                Api::wrong(['level' => 'F', 'detail' => 'Error#M.4.10', 'code' => 'M.4.10']);
            } else {
                return null;
            }
        }
        return $FileName;
    }

    //上传
    public static function up($UnionData = []): array
    {
        $FieldCheck = Common::quickParameter($UnionData, 'field', '字段');
        $Path = Common::quickParameter($UnionData, 'path', '路径');
        $Type = Common::quickParameter($UnionData, 'type', '类型');
        $SaveName = Common::quickParameter($UnionData, 'save_name', '保存名称', false);
        $Size = Common::quickParameter($UnionData, 'size', '大小', false);
        $Number = Common::quickParameter($UnionData, 'number', '数量', false,1);
        $IgnoreError = Common::quickParameter($UnionData, 'ignore_error', '忽略错误', false, false);

        $Return = [];
        if (!empty($FieldCheck) && is_array($FieldCheck)) {
            foreach ($FieldCheck as $V) {
                $TempOp = explode(',', $V);
                $TempField = str_replace('[]', '', $TempOp[0]);
                if ((!isset($_FILES[$TempField])) || (isset($TempOp[1]) && strtoupper(
                            $TempOp[1]
                        ) == 'true' && empty($_FILES[$TempField]['tmp_name']))) {
                    Api::wrong(
                        ['level' => 'F', 'detail' => 'Error#M.4.0' . "\r\n\r\n @ " . $TempField, 'code' => 'M.4.0']
                    );
                }
                if (is_array($Path)) {
                    if (empty($Path[$TempField])) {
                        Api::wrong(
                            ['level' => 'F', 'detail' => 'Error#M.4.1' . "\r\n\r\n @ " . $TempField, 'code' => 'M.4.1']
                        );
                    } else {
                        $TempPath = Common::diskPath($Path[$TempField]);
                    }
                }
                else{
                    $TempPath = Common::diskPath($Path);
                }
                $TempType = $Type;
                if (is_array($Type)) {
                    if (!isset($Type[$TempField])) {
                        Api::wrong(
                            ['level' => 'F', 'detail' => 'Error#M.4.2' . "\r\n\r\n @ " . $TempField, 'code' => 'M.4.2']
                        );
                    } else {
                        $TempType = $Type[$TempField];
                    }
                }

                if (empty($SaveName[$TempField])) {
                    $TempSaveName = null;
                } else {
                    $TempSaveName = $SaveName[$TempField];
                }

                if (empty($Size[$TempField]) || intval($Size[$TempField]) < 0) {
                    if (is_int($Size)) {
                        $TempSize = $Size * 1024;
                    } else {
                        $TempSize = 10485760;
                    }
                } else {
                    $TempSize = intval($Size[$TempField]) * 1024;
                }

                if (is_string($_FILES[$TempField]['tmp_name'])) {
                    $Return[$TempField][0] = [
                        $_FILES[$TempField]['name'],
                        self::upCall(
                            $_FILES[$TempField]['error'],
                            $_FILES[$TempField]['name'],
                            $_FILES[$TempField]['size'],
                            $_FILES[$TempField]['tmp_name'],
                            $TempSaveName,
                            $TempPath,
                            $TempSize,
                            $TempType,
                            $IgnoreError
                        )
                    ];
                } elseif (is_array($_FILES[$TempField]['tmp_name'])) {
                    if (empty($Number[$TempField])) {
                        $TempNumber = intval($Number);
                    } else {
                        $TempNumber = intval($Number[$TempField]);
                    }
                    if (count($_FILES[$TempField]['tmp_name']) < $TempNumber) {
                        $TempNumber = count($_FILES[$TempField]['tmp_name']);
                    }
                    for ($i = 0; $i < $TempNumber; $i++) {
                        $Return[$TempField][$i] = [
                            $_FILES[$TempField]['name'][$i],
                            self::upCall(
                                $_FILES[$TempField]['error'][$i],
                                $_FILES[$TempField]['name'][$i],
                                $_FILES[$TempField]['size'][$i],
                                $_FILES[$TempField]['tmp_name'][$i],
                                null,
                                $TempPath,
                                $TempSize,
                                $TempType,
                                $IgnoreError
                            )
                        ];
                    }
                }
                else{
                    $Return[$TempField] = [];
                }
            }
        }
        return $Return;
    }

    //下载
    public static function down($UnionData = []): string
    {
        $Url = Common::quickParameter($UnionData, 'url', '地址');
        $Path = Common::quickParameter($UnionData, 'path', '路径');
        $FileName = Common::quickParameter($UnionData, 'filename', '文件名', false, '');
        $Headers = Common::quickParameter($UnionData, 'header', 'header', false, []);
        $Timeout = Common::quickParameter($UnionData, 'timeout', '超时时间', false, 86400);
        $Ssl = Common::quickParameter($UnionData, 'ssl', 'ssl', false, false);

        $Path = Common::diskPath($Path);

        set_time_limit($Timeout);
        if (!function_exists('curl_init')) {
            Api::wrong(['level' => 'F', 'detail' => 'Error#M.4.11', 'code' => 'M.4.11']);
        }

        if (!file_exists($Path)) {
            mkdir($Path, 0777, true);
        }

        if (!empty($FileName)) {
            $NewName = $Path . '/' . $FileName;
        } else {
            $NewName = $Path . '/' . intval(_TIME) . mt_rand(111, 999) . '-' . basename($Url);
        }

        $Handle = curl_init();
        $FileHandle = @fopen($NewName, 'wb');
        if (!$FileHandle) {
            Api::wrong(['level' => 'F', 'detail' => 'Error#M.4.12', 'code' => 'M.4.12']);
        }
        curl_setopt($Handle, CURLOPT_URL, $Url);
        curl_setopt($Handle, CURLOPT_CONNECTTIMEOUT, 0);
        curl_setopt($Handle, CURLOPT_TIMEOUT, $Timeout);
        curl_setopt($Handle, CURLOPT_HEADER, false);
        curl_setopt($Handle, CURLOPT_HTTPHEADER, $Headers);

        curl_setopt($Handle, CURLOPT_FILE, $FileHandle);
        curl_setopt($Handle, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($Handle, CURLOPT_MAXREDIRS, 20);

        curl_setopt($Handle, CURLOPT_SSL_VERIFYPEER, $Ssl);
        curl_setopt($Handle, CURLOPT_SSL_VERIFYHOST, $Ssl);

        $Response = curl_exec($Handle);
        $CurlErrno = curl_errno($Handle);
        fclose($FileHandle);
        curl_close($Handle);
        if ($Response === false && $CurlErrno > 0) {
            Api::wrong(['level' => 'F', 'detail' => 'Error#M.4.13' . "\r\n\r\n @ " . $CurlErrno, 'code' => 'M.4.13']);
        }

        return $NewName;
    }

    public static function __callStatic($Method, $Parameters)
    {
        Common::unknownStaticMethod(__CLASS__, $Method);
    }
}