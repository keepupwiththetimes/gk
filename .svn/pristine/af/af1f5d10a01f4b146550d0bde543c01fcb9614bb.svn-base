<?php
namespace Common\Api\CmbcApi;
use Common\Api\CmbcApi\CryptAES;

	//函数功能：使用AES解密，得到以竖线分割的字符串，取出最后一段的时间戳，与当前时间相比。如果之差的绝对值小于$timeStamp，则返回AES解密的报文，否则返回null；异常时也返回null
	//入参：  $encryptContent:密文    $keyStr:密钥    $timeStamp：允许的时间差（单位毫秒）
	//返回： $encryptContent对应的明文或者null
	class DecryptAndCheck
	{
		public  static function checkWithTimeStamp($encryptContent, $keyStr, $timeStamp)
		{
			try{
				//解密
				$aes = new CryptAES();
				$aes->set_key($keyStr);
				$aes->require_pkcs5();
				$plainContent = $aes->decrypt($encryptContent);
				//echo 'plainContent:'.$plainContent.'<br>';

				//获取时间戳
				$index=strrpos($plainContent,'|');
				$oldTime=(float)substr($plainContent,$index+1);
				//echo 'old Time:'.$oldTime.'<br>';
				//获取当前时间
				$currTime=microtime(true)*1000;
				//echo 'currTime:'.$currTime.'<br>';
				//计算时间差与5秒的比对
				if(abs($currTime-$oldTime)<=$timeStamp)
				{
					return $plainContent;
				}
				else return null;
			}
			catch(Exception $e)
			{
				return null;
			}


		}

		//函数功能：使用AES解密，得到以竖线分割的字符串，取出最后一段的时间戳，与当前时间相比。如果之差的绝对值小于5分钟，则返回AES解密的报文，否则返回null；异常时也返回null
		//入参：  $encryptContent:密文    $keyStr:密钥
		//返回： $encryptContent对应的明文或者null
		public  static function check($encryptContent, $keyStr)
		{
			return DecryptAndCheck::checkWithTimeStamp($encryptContent, $keyStr, 300000.1);
		}



	}

?>
