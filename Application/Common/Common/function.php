<?php

function send_curl($url, $data = '', $method = 'GET', $charset = 'utf-8', $timeout = 15)
{
    // 初始化并执行curl请求
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_TIMEOUT, $timeout);
    // 设置抓取的url
    curl_setopt($curl, CURLOPT_URL, $url);
    // 设置头文件的信息作为数据流输出
    curl_setopt($curl, CURLOPT_HEADER, 0);
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
    // 设置获取的信息以文件流的形式返回，而不是直接输出。
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    
    if (strtoupper($method) == 'POST') {
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        if (is_string($data)) { // 发送JSON数据
            $http_header = array(
                'Content-Type: application/json; charset=' . $charset,
                'Content-Length: ' . strlen($data)
            );
            curl_setopt($curl, CURLOPT_HTTPHEADER, $http_header);
        }
    }
    $result = curl_exec($curl);
    $error = curl_error($curl);
    curl_close($curl);
    // 发生错误，抛出异常
    // if ($error) throw new \Exception('请求发生错误：' . $error);
    // if($error){readdir(C('WEB_URL').C('ERROR_PAGE'));}
    return $result;
}

function curl_post($url, $data)
{
    $curl = curl_init();
    $param = http_build_query($data);
    curl_setopt($curl, CURLOPT_URL, $url . '?' . $param);
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($curl, CURLOPT_AUTOREFERER, 1);
    curl_setopt($curl, CURLOPT_POST, 1);
    curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
    curl_setopt($curl, CURLOPT_TIMEOUT, 10);
    curl_setopt($curl, CURLOPT_HEADER, 0);
    curl_setopt($curl, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json; charset=utf-8',
        'Content-Length: ' . strlen($data)
    ));
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    $result = curl_exec($curl);
    if (curl_errno($curl)) {
        echo 'Error:' . curl_error($curl);
    }
    curl_close($curl);
    return $result;
}

function send_msg_aliyun($tel, $msg, $redisLog){
    vendor('Sms.aliyun-php-sdk-core.Config');
        
    $iClientProfile = \DefaultProfile::getProfile("cn-hangzhou", "LTAIE2hl7LxNZvH4", "p5dxoML3L4VXhCMZ8vwD7s2SMzuEe5");        
    $client = new \DefaultAcsClient($iClientProfile);    
    $request = new \Sms\Request\V20160927\SingleSendSmsRequest();
    $request->setSignName("乐天邦");//签名名称
    $request->setTemplateCode("SMS_54725082");//模板code
    $request->setRecNum($tel);//目标手机号
    $data = array(
            'code'=>$msg
        );
    $data = json_encode($data);
    $request->setParamString($data);//模板变量，数字一定要转换为字符串
    if (empty($redisLog)){
        $redisLog = new \Vendor\Redis\DefaultRedis(C('REDIS_HOST_LOG'), C('REDIS_PORT_LOG'), C('REDIS_AUTH_LOG'));
    }
    //doLog('function/del', '阿里云发短信' . $tel, '', 'tel='. $tel, $redisLog); //以后可以删除，只记录发送
    try {
        $response = $client->getAcsResponse($request);
        date_default_timezone_set("PRC"); //阿里云短信服务必须设置时区为GMT，否则会发送失败。所以只能在发送后再恢复成北京时区。
        doLog('function/del', '阿里云发短信结果' . $tel, '', json_encode($response), $redisLog); //以后可以删除，只记录发送
        return 'ok'. ':' . $tel;
    }
    catch (ClientException  $e) {
        date_default_timezone_set("PRC");//阿里云短信服务设置使用时区为GMT，否则会发送失败。所以只能在发送后再恢复成北京时区。
        doLog('function/error', '发短信失败' . $tel, '', $e->getErrorCode() . $e->getErrorMessage() .  ', msg=' . $msg, $redisLog);
    }
    return '';
}

/*
 * 短信发送内容
 * @param $tel，手机号码
 * @param $msg，短信内容
 */
function send_msg($tel, $msg)
{
    $platform_code = $_SESSION['PLATFORM_CODE'];
    $username = C('MSG_USER_NAME');
    $password = C('MSG_PASSWORD');
    if ($platform_code == 'NYYHD') {
        if (DEBUG_ON_LOCALHOST_OR_201TESTINGSERVER == TRUE){
            $send_msg = urlencode(iconv('UTF-8', 'GB2312', '【刷卡欢乐送测试】' . $msg  ));
        }else{
            $send_msg = urlencode(iconv('UTF-8', 'GB2312', '【刷卡欢乐送】' . $msg  ));
        }
    } else 
        if ($platform_code == 'NYYH' || $platform_code == 'NYYHCJ') {
            if (DEBUG_ON_LOCALHOST_OR_201TESTINGSERVER == TRUE){
                $send_msg = urlencode(iconv('UTF-8', 'GB2312', '【天天有礼测试】' . $msg));
            }else{
                $send_msg = urlencode(iconv('UTF-8', 'GB2312', '【天天有礼】' . $msg));
            }
        } else {
            if (DEBUG_ON_LOCALHOST_OR_201TESTINGSERVER == TRUE){             
                $send_msg = urlencode(iconv('UTF-8', 'GB2312', '【乐天邦测试】' . $msg)); //在测试环境的短信，都在短信中表明是测试，以免误发。
            }else{
                $send_msg = urlencode(iconv('UTF-8', 'GB2312', '【乐天邦】' . $msg));
            }
        }
    
    $url = 'http://58.83.147.92:8080/qxt/smssenderv2?user=' . $username . '&password=' . md5($password) . '&tele=' . $tel . '&msg=' . $send_msg;
    $result = send_curl($url);
    //发送成功的结果为：ok:99082191:13501650221。
    if (stristr($result , 'ok') == false){  //发送失败则重发一次
        //file_put_contents("debug.log", "send SMS failed, result=" . $result . "\n", FILE_APPEND);
        $result = send_curl($url, '', '', '' , 3);
        if (stristr($result , 'ok') == false){ //两次发送都失败，则记录到数据库日志中
            $redisLog = new \Vendor\Redis\DefaultRedis(C('REDIS_HOST_LOG'), C('REDIS_PORT_LOG'), C('REDIS_AUTH_LOG'));
            doLog('function/error', '连续发2次短信失败' . $tel, '', json_encode($result) . ', msg=' . $send_msg, $redisLog);
        }
    }
    //file_put_contents("debug.log", "send SMS result is:" . $result . "\n", FILE_APPEND);
    if(empty($result)){
    
        $result = 'error:unicode';
    }
    return $result;
}

/* 判断是否微信浏览器 */
function is_wx()
{
    $user_agent = $_SERVER['HTTP_USER_AGENT'];
    if (strpos($user_agent, 'MicroMessenger') === false) {
        return false;
    } else {
        return true;
    }
}

// 创建随机数
function createNonceStr($length = 6, $chars = '')
{
    if (! $chars) {
        $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
    }
    $str = "";
    for ($i = 0; $i < $length; $i ++) {
        $str .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
    }
    return $str;
}

// PHP与Java对接des解密
function decrypt($str, $key)
{
    $str = mcrypt_decrypt(MCRYPT_DES, $key, $str, MCRYPT_MODE_ECB);
    $pad = ord($str[($len = strlen($str)) - 1]);
    if ($pad > strlen($str)) {
        return false;
    }
    if (strspn($str, chr($pad), strlen($str) - $pad) != $pad) {
        return false;
    }
    if ($pad === 0) {
        return trim(substr($str, 0));
    }
    return trim(substr($str, 0, - 1 * $pad));
}

// des加密小红书
function encrypt($key, $data)
{
    $base64_data = base64_encode($data);
    $encrypt_data = base64_encode(mcrypt_encrypt(MCRYPT_DES, $key, $base64_data, MCRYPT_MODE_ECB));
    return $encrypt_data;
}

// 检测关系
function is_bind($platform_code, $data, $is_bind_url = '')
{
    //$platform_code = $_SESSION['PLATFORM_CODE'];
    $post_data['merchantId'] = strval($data['merchantId']);
    $post_data['nonce'] = strval($data['nonce']);
    $post_data['timestamp'] = strval($data['timestamp']);
    $post_data['token'] = strval($data['token']);
    $md5s = http_build_query($post_data);
    $signature = md5($md5s);
    unset($post_data['token']);
    $post_data['signature'] = strval($signature);
    $post_data['openId'] = strval($data['openid']);
    // /////招行验证开始 2016-07-01 shenzihao
    if ($platform_code == 'ZHWZ') {
        if (DEBUG_ON_LOCALHOST_OR_201TESTINGSERVER == FALSE)
            // 正式环境
            $is_bind_url = "https://pointbonus.cmbchina.com/IMSPActivities/up/isBind";
        else
            // 测试环境
            $is_bind_url = "https://pointbonustest.dev.cmbchina.com/IMSPActivities/up/isBind";
        $result = curl_post($is_bind_url, $post_data);
    } else {
        $result = send_curl($is_bind_url, json_encode($post_data), 'POST');
    }
    // /////招行验证结束
    if (APP_DEBUG) {
        file_put_contents('debug.log', "is_bind访问时间：" . date('Y-m-d H:i:s') . "\n" . "平台CODE：" . $platform_code . "\n" . "用户uId：" . $_SESSION[$_SESSION['PLATFORM_CODE']]['tuid'] . "\n" . "平台传递的json数据：" . json_encode($post_data) . "\n" . "返回的XML数据：" . $result . "\n", FILE_APPEND);
    }
    $pos = strpos($result, 'xml');
    if (! $pos) {
        // ///若为招行跳转回招行 2016-07-01 shenzihao
        if ($platform_code == 'ZHWZ'); // redirect('http://xyk.cmbchina.com/Latte/wx/20151022wx');
                                                // ///
        else
            redirect(C('WEB_URL') . C('ERROR_PAGE'));
    }
    $obj = simplexml_load_string($result, 'SimpleXMLElement', LIBXML_NOCDATA);
    if (is_object($obj)) {
        $obj = get_object_vars($obj);
    }
    return $obj;
}

// 创建绑定数据
function create_bind_data($openid = '', $token = '', $merchantId = '', $timestamp = '')
{
    if (empty($timestamp)) {
        $timestamp = time();
    }
    $nonce = createNonceStr(6);
    $data = array(
        'openid' => $openid,
        'token' => $token,
        'merchantId' => $merchantId,
        'timestamp' => $timestamp,
        'nonce' => $nonce
    );
    
    return $data;
}

// 获取浏览器IP
function get_browser_ip()
{
    if (isset($_SERVER)) {
        if (isset($_SERVER["HTTP_X_FORWARDED_FOR"])) {
            $realip = $_SERVER["HTTP_X_FORWARDED_FOR"];
        } else 
            if (isset($_SERVER["HTTP_CLIENT_IP"])) {
                $realip = $_SERVER["HTTP_CLIENT_IP"];
            } else {
                $realip = $_SERVER["REMOTE_ADDR"];
            }
    } else {
        if (getenv("HTTP_X_FORWARDED_FOR")) {
            $realip = getenv("HTTP_X_FORWARDED_FOR");
        } else 
            if (getenv("HTTP_CLIENT_IP")) {
                $realip = getenv("HTTP_CLIENT_IP");
            } else {
                $realip = getenv("REMOTE_ADDR");
            }
    }
    return $realip;
}

/**
 * [is_tel 判断是否是电话号码]
 * @ckhero
 * @DateTime 2016-10-08
 * 
 * @param [type] $tel
 *            [description]
 * @return boolean [description]
 */
function is_tel($tel)
{
    return preg_match('/^1[34578][0-9]{9}$/', $tel);
}

/**
 * [pay_type 支付类型生成]
 * @ckhero
 * @DateTime 2017-01-04
 * 
 * @param integer $paymeny_type
 *            [description]
 * @return [type] [description]
 */
function pay_type($paymeny_type = 0)
{
    $paymeny_type = decbin($paymeny_type);
    $paymeny_type = sprintf('%04d', $paymeny_type);
    $data = array();
    
    if ($paymeny_type['3'] == 1) { // 微信支付
        
        $data[] = array(
            'type' => 'weixin',
            'name' => '微信支付'
        );
    }
    
    if ($paymeny_type['2'] == 1) { // 支付宝支付
        
        $data[] = array(
            'type' => 'ali',
            'name' => '支付宝支付'
        );
    }
    
    if ($paymeny_type['1'] == 1) { // 民生银行支付
        
        $data[] = array(
            'type' => 'cmbc',
            'name' => '民生支付'
        );
    }
    
    if (empty($data))
        $data[] = array(
            'type' => 'weixin',
            'name' => '微信支付'
        );
    
    return $data;
}

/**
 * [is_ajax 判断是否ajax请求]
 * @ckhero
 * @DateTime 2017-02-27
 * @return   boolean    [true:是ajax请求;false:不是ajax请求]
 */
function is_ajax()
{
    
    return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest' && isset($_SERVER['HTTP_REFERER']) && !empty($_SERVER['HTTP_REFERER']) ? true : false;
}