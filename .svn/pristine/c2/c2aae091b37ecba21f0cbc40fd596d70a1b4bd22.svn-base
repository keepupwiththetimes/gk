<?php
namespace Common\Api\Wxpay;

use Exception;
class  SDKRuntimeException extends Exception {
	public function errorMessage()
	{
		echo("<script>javascript:history.back();</script>"); //为了避免支付成功后在浏览器上安回退键后出现错误的提示信息
        die();  

		return $this->getMessage();
	}

}

?>