<?php 
if (DEBUG_ON_LOCALHOST_OR_201TESTINGSERVER == FALSE) { 
	
      //正式
      return array(
          
          //支付宝支付
        'AliPay' => array(
              
              //应用ID,您的APPID。
			  'app_id' => "2016092101937820",

			  //商户私钥，您的原始格式RSA私钥
			  'merchant_private_key' => "/var/www/html/pay_conf/alipay/app_private_key.pem",
			  
			  'rsaPrivateKeyFilePath' => "/var/www/html/pay_conf/alipay/app_private_key.pem",
			
			  //异步通知地址
			 'notify_url' => WEB_URL . 'index.php/Home/Notify/notify/payType/ali',
			
			  //同步跳转
			 'return_url' => "https://m.zwmedia.com.cn/wxzspfse/index.php",
			 //'return_url' => WEB_URL . 'index.php/Home/Notify/notify/payType/ali',

			  //编码格式
			  'charset' => "UTF-8",

			  //签名方式
			  'sign_type'=>"RSA",

			  //支付宝网关
			 'gatewayUrl' => "https://openapi.alipay.com/gateway.do",

			  //支付宝公钥,查看地址：https://openhome.alipay.com/platform/keyManage.htm 对应APPID下的支付宝公钥。
			 'alipay_public_key' => "MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQDDI6d306Q8fIfCOaTXyiUeJHkrIvYISRcc73s3vF1ZT7XN8RNPwJxo8pWaJMmvyTn9N4HQ632qJBVHf8sxHi/fEsraprwCtzvzQETrNRwVxLO5jVmRGi60j8Ue1efIlzPXV9je9mkjzOmdssymZkh2QhUrCmZYI/FCEa3/cNMW0QIDAQAB"
        	),
         
          //民生支付
         'cmbcPay' => array(
            
            'privatePath' => '/var/www/html/php-demo/lajp-10.05/testData/sm2/09025.sm2',
            'privatePassword' => '123123',
            'publicPath' => '/var/www/html/php-demo/lajp-10.05/testData/sm2/bank.cer',
            'ip' => 'localhost',
            'port' => '21230',
            'pError' => '101',
            'sError' => '102',
            'jE' => '104',
            'version' => '1.0.0',
            'corpID' => '09025',
            'corpName' => '上海掌握广告传播有限公司',
            'NotifyUrl' => WEB_URL . 'cmbc.php',
            'JumpUrl' => WEB_URL . 'index.php?platcode=MSYHC'
        ),

        //微信支付
        'weixinPay' => array(
            
            'NotifyUrl' => WEB_URL . 'index.php/Home/Notify/notify/payType/weixin',
            'TradeType' => 'JSAPI'
        )

      );

} else {
  
  return array(

        //支付宝支付
        'AliPay' => array(
              
              //应用ID,您的APPID。
			  'app_id' => "2016092101937820",

			  //商户私钥，您的原始格式RSA私钥
			  'merchant_private_key' => "/var/www/html/pay_conf/alipay/app_private_key.pem",
			  
			  'rsaPrivateKeyFilePath' => "/var/www/html/pay_conf/alipay/app_private_key.pem",
			
			  //异步通知地址
			 'notify_url' => WEB_URL . 'index.php/Home/Notify/notify/payType/ali',
			
			  //同步跳转
			 'return_url' => WEB_URL . 'index.php/Home/Notify/payReturn/payType/ali',
			 //'return_url' => WEB_URL . 'index.php/Home/Notify/notify/payType/ali',

			  //编码格式
			  'charset' => "UTF-8",

			  //签名方式
			  'sign_type'=>"RSA",

			  //支付宝网关
			 'gatewayUrl' => "https://openapi.alipay.com/gateway.do",

			  //支付宝公钥,查看地址：https://openhome.alipay.com/platform/keyManage.htm 对应APPID下的支付宝公钥。
			 'alipay_public_key' => "MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQDDI6d306Q8fIfCOaTXyiUeJHkrIvYISRcc73s3vF1ZT7XN8RNPwJxo8pWaJMmvyTn9N4HQ632qJBVHf8sxHi/fEsraprwCtzvzQETrNRwVxLO5jVmRGi60j8Ue1efIlzPXV9je9mkjzOmdssymZkh2QhUrCmZYI/FCEa3/cNMW0QIDAQAB"
        	),
         
         //民生支付
        'cmbcPay' => array(
            
            'privatePath' => '/var/www/html/php-demo/lajp-10.05/testData/sm2/09025.sm2',
            'privatePassword' => '123123',
            'publicPath' => '/var/www/html/php-demo/lajp-10.05/testData/sm2/bank.cer',
            'ip' => 'localhost',
            'port' => '21230',
            'pError' => '101',
            'sError' => '102',
            'jE' => '104',
            'version' => '1.0.0',
            'corpID' => '09025',
            'corpName' => '上海掌握广告传播有限公司',
            'NotifyUrl' => WEB_URL . 'cmbc.php',
            'JumpUrl' => WEB_URL . 'cmbc.php?type=return'
        ),
        
        //微信支付
        'weixinPay' => array(
            
            'NotifyUrl' => WEB_URL . 'index.php/Home/Notify/notify/payType/weixin',
            'TradeType' => 'JSAPI'
        )
  	);
}
 ?>
