<?php
echo 1;
require_once "decryptAndCheck.class.php";

// 示例
// $keyStr：密钥，请向民生银行索取
// $chiperTxt: 密文
$keyStr = 'JiYqrz583wzVghMAnsFzbg==';
$chiperTxt = 'plxIaWGVLEwO9uWJRklDyDhWprbTb9rfaHsnGCs/jJ2YabAwvz99ZkBpoahObXxj';

// DecryptAndCheck::checkWithTimeStamp
// 函数功能：使用AES解密，得到以竖线分割的字符串，取出最后一段的时间戳，与当前时间相比。如果之差的绝对值小于$timeStamp，则返回AES解密的报文，否则返回null；异常时也返回null
// 入参： $encryptContent:密文 $keyStr:密钥 $timeStamp：允许的时间差（单位毫秒）
// 返回： $encryptContent对应的明文或者null
$plainTxt = DecryptAndCheck::checkWithTimeStamp($chiperTxt, $keyStr, 50000000000.1);
echo $plainTxt;
?>