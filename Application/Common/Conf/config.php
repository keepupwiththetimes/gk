<?php
$custom_config_path = dirname(__FILE__);
if (file_exists($custom_config_path . "/platform.config.php")) {
    $plate_configs = include $custom_config_path . "/platform.config.php";
} else {
    $plate_configs = array();
}
if (file_exists($custom_config_path . "/pay.config.php")) {
    $pay_configs = include $custom_config_path . "/pay.config.php";
} else {
    $pay_configs = array();
}
if (DEBUG_ON_LOCALHOST_OR_201TESTINGSERVER == FALSE) { // 在正式服务器上的配置信息
    $configs = array(
        
        'URL_CASE_INSENSITIVE' => false, // 默认false 表示URL区分大小写 true则表示不区分大小写
                                           // '配置项'=>'配置值'
        'ERROR_PAGE' => 'Public/Error/index.html',
        'Resource_THEME' => 'Resource' . EDITION_NUM,
        
        // 网站基本信息配置
        'WEB_URL' => WEB_URL, 
        'WEB_ROOT' => $_SERVER['DOCUMENT_ROOT'],
        'WEB_NAME' => '乐天邦',
        // 数据库配置项
        'DB_TYPE' => 'mysql',
        'DB_HOST' => 'rm-uf685d499m5uvq1o6.mysql.rds.aliyuncs.com',
        'DB_NAME' => 'zsmp', 
        'DB_USER' => 'zsmp', 
        'DB_PWD' => 'ZSwNYDnrZuFVhR9B',
        'DB_PORT' => '3306',
        'DB_PREFIX' => 'tb_',
        'DB_PARAMS' => array(
            \PDO::ATTR_CASE => \PDO::CASE_NATURAL
        ),
        // 模板标签替换
        TMPL_PARSE_STRING => array(
            '__PUBLIC__' => RESOURCE_PATH . '/Public',
            '__Home_PUBLIC__' => RESOURCE_PATH . '/Public/Home/Resource' . EDITION_NUM,
            '__Home_Bootstrap__' => RESOURCE_PATH . '/Public/Home/Default/bootstrap',
            '__Home_CSS__' => RESOURCE_PATH . '/Public/Home/Resource' . EDITION_NUM . '/Css',
            '__Home_CSS_STATIC__' => RESOURCE_PATH . '/Public/Home/Default/Css', // 第三方的css，不需要设置版本号
            '__Home_JS_STATIC__' => RESOURCE_PATH . '/Public/Home/Default/Js', // 第三方的js，不需要设置版本号
            '__Home_IMG__' => RESOURCE_PATH . '/Public/Home/Default/Image',
            '__Home_JS__' => RESOURCE_PATH . '/Public/Home/Resource' . EDITION_NUM . '/Js',
            '__Home_Plug__' => RESOURCE_PATH . '/Public/Home/Default/plugins',
            '__LETIANBANG__' => RESOURCE_PATH . '/Public/Home/Resource' . EDITION_NUM . '/Letianbang'
        ),
        
        // 短信账号配置
        'MSG_USER_NAME' => 'cs_zwgg',
        'MSG_PASSWORD' => '123zhang',
        
        // 乐天邦
        'APP_ID_LETIANBANG' => 'wx307bfefddcfa71a2',
        'APP_SECRET_LETIANBANG' => '2c1ab6b6c51c108e7fa8c72e5674762b',
        
        // 微信公众号配置
        'APP_ID' => 'wx3cb763d185e92ddf', 
        'APP_SECRET' => '4f89581b0b3d58cff92888e3f6ae0236', 
        
        'WXPAY_MCH_ID' => "1388768802", 
        'WXPAY_PARTNER_KEY' => "zwmediaweixinpay1234567890123456", 
        'SSLCERT_PATH' => "/services/www/m_zwmedia/Pay/wxpay/WxPayPubHelper/cacert/apiclient_cert.pem", // 此参数属于微信支付部分，现在没有使用
        'SSLKEY_PATH' => "/services/www/m_zwmedia/Pay/wxpay/WxPayPubHelper/cacert/apiclient_key.pem", // 此参数属于微信支付部分，现在没有使用
                                                                                                    
            
        // default 实例
        'REDIS_HOST_DEFAULT' => 'r-uf60d46cf5a620b4.redis.rds.aliyuncs.com',
        'REDIS_PORT_DEFAULT' => '6379',
        'REDIS_AUTH_DEFAULT' => 'yr9XjBb6k',
        
        // 记录log的实例
        'REDIS_HOST_LOG' => 'r-uf6343510a51f384.redis.rds.aliyuncs.com',
        'REDIS_PORT_LOG' => '6379',
        'REDIS_AUTH_LOG' => 'yr9XjBb6k',
        'DATA_CACHE_TYPE' => 'redis',
        'REDIS_DB_PREFIX' => 'TP:',
        
        
    )
    
    ;
} else { // 此处为测试服务器的配置信息
    $configs = array(
        // 资源地址
        
        // '配置项'=>'配置值'
        'ERROR_PAGE' => 'Public/Error/index.html',
        'DEFAULT_THEME' => 'Resource' . EDITION_NUM,
        
        // 网站基本信息配置
        'WEB_URL' => WEB_URL,
        'WEB_ROOT' => $_SERVER['DOCUMENT_ROOT'],
        'WEB_NAME' => '乐天邦微信版',
        // 数据库配置项
        'DB_TYPE' => 'mysql',
        'DB_HOST' => '139.224.0.198',
        // 'DB_HOST' => '127.0.0.1',
        // 'DB_HOST' => 'rm-uf685d499m5uvq1o6.mysql.rds.aliyuncs.com',
        'DB_NAME' => 'uatzsmp',
        'DB_USER' => 'zwcm',
        'DB_PWD' => 'zwcm2016',
        'DB_PORT' => '3306',
        'DB_PREFIX' => 'tb_',
        'DB_PARAMS' => array(
            \PDO::ATTR_CASE => \PDO::CASE_NATURAL
        ),
        // 模板标签替换
        TMPL_PARSE_STRING => array(
            '__PUBLIC__' => RESOURCE_PATH . '/Public',
            
            '__PUBLIC__' => RESOURCE_PATH . '/Public',
            '__Home_PUBLIC__' => RESOURCE_PATH . '/Public/Home/Resource' . EDITION_NUM,
            '__Home_Bootstrap__' => RESOURCE_PATH . '/Public/Home/Default/bootstrap',
            '__Home_CSS__' => RESOURCE_PATH . '/Public/Home/Resource' . EDITION_NUM . '/Css',
            '__Home_IMG__' => RESOURCE_PATH . '/Public/Home/Default/Image',
            '__Home_CSS_STATIC__' => RESOURCE_PATH . '/Public/Home/Default/Css', // 第三方的css，不需要设置版本号
            '__Home_JS_STATIC__' => RESOURCE_PATH . '/Public/Home/Default/Js', // 第三方的js，不需要设置版本号
            '__Home_JS__' => RESOURCE_PATH . '/Public/Home/Resource' . EDITION_NUM . '/Js',
            '__Home_Plug__' => RESOURCE_PATH . '/Public/Home/Default/plugins',
            '__LETIANBANG__' => RESOURCE_PATH . '/Public/Home/Resource' . EDITION_NUM . '/Letianbang'
        ),
        
        // 短信账号配置
        'MSG_USER_NAME' => 'cs_zwgg',
        'MSG_PASSWORD' => '123zhang',
        
        // 乐天邦
        'APP_ID_LETIANBANG' => 'wxf42302be9b7152f8',
        'APP_SECRET_LETIANBANG' => '6feaec4c62bc275a6edcbbab492dbaeb',
        
        // 微信公众号配置
        'APP_ID' => 'wxf42302be9b7152f8', // 使用测试服务器对应的微信公众号
        'APP_SECRET' => '6feaec4c62bc275a6edcbbab492dbaeb', // 使用测试服务器对应的微信公众号
        
        'WXPAY_MCH_ID' => "1247685701", // 使用测试服务器对应的微信公众号的商户平台
        'WXPAY_PARTNER_KEY' => "zwmedia1234567890123456789012345", 
        'SSLCERT_PATH' => "/services/www/m_zwmedia/Pay/wxpay/WxPayPubHelper/cacert/apiclient_cert.pem", // 此参数属于微信支付部分，现在没有使用
        'SSLKEY_PATH' => "/services/www/m_zwmedia/Pay/wxpay/WxPayPubHelper/cacert/apiclient_key.pem", // 此参数属于微信支付部分，现在没有使用
                                                                                                    
       
        'REDIS_HOST_DEFAULT' => '127.0.0.1',
        'REDIS_PORT_DEFAULT' => '3198',
        'REDIS_AUTH_DEFAULT' => '#yr9XjB%b6k',
        
        // 记录log的实例
        'REDIS_HOST_LOG' => '127.0.0.1',
        'REDIS_PORT_LOG' => '3199',
        'REDIS_AUTH_LOG' => '#yr9XjB%b6k',
        'DATA_CACHE_TYPE' => 'redis',
        'REDIS_DB_PREFIX' => 'TP:',
        
    );
    
}
return array_merge($configs, $plate_configs ,$pay_configs);
