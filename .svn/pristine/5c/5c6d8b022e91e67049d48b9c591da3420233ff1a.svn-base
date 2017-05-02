<?php

namespace Common\Api;

class WxApi {

    private $AppId;
    private $AppSecret;
    private $AccessToken;
    public  $data;
    private $returnParameters;

    public function __construct($AppId, $AppSecret) {
        $this->AppId = $AppId;
        $this->AppSecret = $AppSecret;
        $this->getAccessToken();
    }

    //页面JSSDK签名包
    public function getSignPackage() {
        $js_api_ticket = $this->getJsApiTicket();
        $url = REQUEST_SCHEME."://" . $_SERVER[HTTP_HOST] . $_SERVER[REQUEST_URI];
        $timestamp = time();
        $nonceStr = $this->createNonceStr();
        // 这里参数的顺序要按照 key 值 ASCII 码升序排序
        $string = "jsapi_ticket={$js_api_ticket}&noncestr={$nonceStr}&timestamp={$timestamp}&url={$url}";
        $signature = sha1($string);
        $signPackage = array(
            "appId" => $this->AppId,
            "nonceStr" => $nonceStr,
            "timestamp" => $timestamp,
            "url" => $url,
            "signature" => $signature
        );
        return $signPackage;
    }
    /**
     *  作用：生成签名
     */
    public function getSign($Obj)
    {
        foreach ($Obj as $k => $v)
        {
            $Parameters[$k] = $v;
        }
        //签名步骤一：按字典序排序参数
        ksort($Parameters);
        $String = $this->formatBizQueryParaMap($Parameters, false);
        //echo '【string1】'.$String.'</br>';
        //签名步骤二：在string后加入KEY
        $String = $String."&key=".C('WXPAY_PARTNER_KEY');
        //echo "【string2】".$String."</br>";
        //签名步骤三：MD5加密
        $String = md5($String);
        //echo "【string3】 ".$String."</br>";
        //签名步骤四：所有字符转为大写
        $result_ = strtoupper($String);
        //echo "【result】 ".$result_."</br>";
        return $result_;
    }

    //生成JSSDK支付签名
    public function getPaySign($packageString) {
        $jsApiObj["appId"] = $this->AppId;
        $timeStamp = time();
        $jsApiObj["timeStamp"] = "$timeStamp";
        $jsApiObj["nonceStr"] = $this->createNoncestr();
        $jsApiObj["package"] = "prepay_id=$packageString";
        $jsApiObj["signType"] = "MD5";
        $jsApiObj["paySign"] = $this->getSign($jsApiObj);
        
        return json_encode($jsApiObj);
    }

    /**
     *  作用：格式化参数，签名过程需要使用
     */
    function formatBizQueryParaMap($paraMap, $urlencode)
    {
        $buff = "";
        ksort($paraMap);
        foreach ($paraMap as $k => $v)
        {
            if($urlencode)
            {
               $v = urlencode($v);
            }
            //$buff .= strtolower($k) . "=" . $v . "&";
            $buff .= $k . "=" . $v . "&";
        }
        $reqPar;
        if (strlen($buff) > 0) 
        {
            $reqPar = substr($buff, 0, strlen($buff)-1);
        }
        return $reqPar;
    }

    /**
     *  作用：产生随机字符串，不长于32位
     */
    public function createNoncestr( $length = 32 ) 
    {
        $chars = "abcdefghijklmnopqrstuvwxyz0123456789";  
        $str ="";
        for ( $i = 0; $i < $length; $i++ )  {  
            $str.= substr($chars, mt_rand(0, strlen($chars)-1), 1);  
        }  
        return $str;
    }

    //获取jsapi票据
    private function getJsApiTicket() {
        //jsapi_ticket 应该全局存储与更新，以下代码以写入到文件中做示例
        $ticket = S('ticket_' . $this->AppId);
        if (empty($ticket)) {
            $url = "https://api.weixin.qq.com/cgi-bin/ticket/getticket?type=jsapi&access_token={$this->AccessToken}";
            $res = json_decode(send_curl($url));
            $ticket = $res->ticket;
            if ($ticket) {
                S('ticket_' . $this->AppId, $ticket, 6000);
            }
        }
        return $ticket;
    }

    //获取微信信息
    private function getCode($redirect_uri = '', $scope = 'snsapi_base', $state = '1') {
       // $redirect_uri = empty($redirect_uri) ? 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'] . '?' . $_SERVER['QUERY_STRING'] : $redirect_uri;
       // 正式服务器做了负载均衡之后，如果访问url中有文件没已斜杠结束的话nginx在加斜杠的过程中会在url中加 上负载均衡后的端口号，导致微信回调出问题 ，去掉url
       // 中8080即可
        $redirect_uri = empty($redirect_uri) ? REQUEST_SCHEME.'://' . preg_replace('/[(:8082)|(:8080)]/', '', $_SERVER['HTTP_HOST']) . $_SERVER['PHP_SELF'] . '?' . $_SERVER['QUERY_STRING'] : $redirect_uri;
        $redirect_uri = urlencode($redirect_uri);
        $url = "https://open.weixin.qq.com/connect/oauth2/authorize?appid={$this->AppId}&redirect_uri={$redirect_uri}&response_type=code&scope={$scope}&state={$state}#wechat_redirect";
        header("Location:{$url}");
        exit;
    }

    //获取微信accesstoken
    private function getAccessToken() {
        $access_token = S('access_token_' . $this->AppId);
        if (empty($access_token)) {
            $this->AccessToken = $this->refreshAccessToken();
        } else {
            $this->AccessToken = $access_token;
        }
    }

    //获取微信accesstoken
    private function refreshAccessToken() {
        $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid={$this->AppId}&secret={$this->AppSecret}";
        $result_data = send_curl($url);
        $result = json_decode($result_data, true);
        $access_token = $result['access_token'];
        S('access_token_' . $this->AppId, $access_token, 6000);
        return $access_token;
    }

    public function getOpenid($redirect_uri = '') {
        $code = I('param.code', '');
        if (!empty($code)) {
            $url = "https://api.weixin.qq.com/sns/oauth2/access_token?appid={$this->AppId}&secret={$this->AppSecret}&code={$code}&grant_type=authorization_code";
            $result = send_curl($url);
            $result_data = json_decode($result, true);
            return $result_data['openid'];
        }
        $this->getCode($redirect_uri);
    }

    public function getUserInfo($redirect_uri = '') {
        $code = $_GET['code'];
        if (!empty($code)) {
            $url = "https://api.weixin.qq.com/sns/oauth2/access_token?appid={$this->AppId}&secret={$this->AppSecret}&code={$code}&grant_type=authorization_code";
            $result = send_curl($url);
            $result = json_decode($result, true);
            return $result;
        }
        $this->getCode($redirect_uri, 'snsapi_userinfo');
    }
    

     public function getUserInfoNew($redirect_uri = '') {
        $code = $_GET['code'];
        if (!empty($code)) {
            $url = "https://api.weixin.qq.com/sns/oauth2/access_token?appid={$this->AppId}&secret={$this->AppSecret}&code={$code}&grant_type=authorization_code";
            $result = send_curl($url);
            $result = json_decode($result, true);
            $res = send_curl('https://api.weixin.qq.com/sns/userinfo?access_token='.$result['access_token'].'&openid='.$result['openid'].'&lang=zh_CN ');
            $res = json_decode($res,true);
            return $res;
        }
        $this->getCode($redirect_uri, 'snsapi_userinfo');
    }
    public function getSubscribeUserInfo($openid, $lang = 'zh_CN') {
        $url = "https://api.weixin.qq.com/cgi-bin/user/info?access_token={$this->AccessToken}&openid={$openid}&lang={$lang}";
        $result_data = send_curl($url);
        $result = json_decode($result_data, true);
        if (APP_DEBUG) {
            file_put_contents('debug.log', "getSubscribeUserInfo 访问时间：" . date('Y-m-d H:i:s') . "\n" . "用户信息：" . $result_data . "\n", FILE_APPEND);
        }
        if (intval($result['errcode'])==42001) {
            $this->AccessToken = $this->refreshAccessToken();
            $new_url = "https://api.weixin.qq.com/cgi-bin/user/info?access_token={$this->AccessToken}&openid={$openid}&lang={$lang}";
            $return_data = send_curl($new_url);
            return json_decode($return_data, true);
        }
        return $result;
    }

    public function updateMenu($menu) {
        $url = "https://api.weixin.qq.com/cgi-bin/menu/create?access_token={$this->AccessToken}";
        $obj = $this->https_post($url, $menu);
        $obj = json_decode($obj);
        return $obj->errmsg;
    }

    //* 向远程网址发送POST信息*

    public function https_post($url, $data) {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $result = curl_exec($curl);
        if (curl_errno($curl)) {
            return 'Errno' . curl_error($curl);
        }
        curl_close($curl);
        return $result;
    }

    function checkSign()
    {
        $tmpData = $this->data;
        unset($tmpData['sign']);
        unset($tmpData['platcode']);
        $sign = $this->getSign($tmpData);//本地签名
        if ($this->data['sign'] == $sign) {
            return TRUE;
        }
        return FALSE;
    }
    
    /**
     * 将微信的请求xml转换成关联数组，以方便数据处理
     */
    function saveData($xml)
    {
        $this->data = $this->xmlToArray($xml);
    }
    
    /**
     * 	作用：将xml转为array
     */
    public function xmlToArray($xml)
    {
        //将XML转为array
        $array_data = json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
        return $array_data;
    }
    
    
    
    /**
     * 设置返回微信的xml数据
     */
    function setReturnParameter($parameter, $parameterValue)
    {
        $this->returnParameters[$this->trimString($parameter)] = $this->trimString($parameterValue);
    }
    
    function trimString($value)
    {
        $ret = null;
        if (null != $value)
        {
            $ret = $value;
            if (strlen($ret) == 0)
            {
                $ret = null;
            }
        }
        return $ret;
    }
    
    /**
     * 将xml数据返回微信
     */
    function returnXml()
    {
        $returnXml = $this->createXml();
        return $returnXml;
    }
    
    /**
     * 生成接口参数xml
     */
    function createXml()
    {
        return $this->arrayToXml($this->returnParameters);
    }
    
    /**
     * 	作用：array转xml
     */
    function arrayToXml($arr)
    {
        $xml = "<xml>";
        foreach ($arr as $key=>$val)
        {
            if (is_numeric($val))
            {
                $xml.="<".$key.">".$val."</".$key.">";
    
            }
            else
                $xml.="<".$key."><![CDATA[".$val."]]></".$key.">";
        }
        $xml.="</xml>";
        return $xml;
    }
    
}
