<?php
namespace Home\Controller;

use Think\Controller;
use Common\Api\WxApi;

class InterfaceController extends Controller
{

    private $wx_api, $platform_config, $platform_code;

    function __construct()
    {
        parent::__construct();
        $this->platform_code = I('get.platcode', 0);
        $this->platform_config = getPlatformConfig($this->platform_code);
        $this->wx_api = new WxApi($this->platform_config['APP_ID'], $this->platform_config['APP_SECRET']);
    }

    public function index()
    {
        $this->display('index');
        exit();
    }
    
    // 根据openid创建加密uId
    public function getUid()
    {
        if ($this->platform_code) {
            if (empty($this->platform_config)) {
                redirect(C('WEB_URL') . C('ERROR_PAGE'));
            }
            $openid = $this->wx_api->getOpenid();
            $en_str = mcrypt_encrypt(MCRYPT_DES, $this->platform_config['DES_KEY'], $openid, MCRYPT_MODE_ECB);
            $base64_data = urlencode(base64_encode($en_str));
            $query_data = http_build_query(array(
                'platcode' => $this->platform_code,
                'uId' => $base64_data,
                'timestamp' => time()
            ));
            redirect(C('WEB_URL') . '?' . $query_data);
        } else {
            redirect(C('WEB_URL') . C('ERROR_PAGE'));
        }
    }

    public function isBind()
    {
        $post_string = file_get_contents("php://input");
        if ($this->platform_code && $post_string) {
            $post_data = json_decode($post_string, true);
            $md5_data['merchantId'] = $post_data['merchantId'];
            $md5_data['nonce'] = $post_data['nonce'];
            $md5_data['timestamp'] = $post_data['timestamp'];
            $md5_data['token'] = $this->platform_config['TOKEN'];
            $md5s = http_build_query($md5_data);
            
            if ($post_data['signature'] == md5($md5s)) {
                $result = $this->wx_api->getSubscribeUserInfo($post_data['openId']);
                if (empty($result) || intval($result['subscribe']) == 0) {
                    $this->returnXMl('0000', 0, '未关注');
                } else {
                    $this->returnXMl('0000', 1, '已关注');
                }
            } else {
                $this->returnXMl('3001', 0, '签名不正确');
            }
        } else {
            $this->returnXMl('1001', 0, '缺少参数');
        }
    }

    function returnXMl($code, $status, $msg)
    {
        echo "<xml>" . "<returnCode><![CDATA[" . $code . "]]></returnCode>" . "<status><![CDATA[" . $status . "]]></status>" . "<returnMessage><![CDATA[" . $msg . "]]></returnMessage>" . "</xml>";
    }
    
    // 绑定公众号
    function checkSignature()
    {
        $signature = $_GET["signature"];
        $timestamp = $_GET["timestamp"];
        $nonce = $_GET["nonce"];
        
        $token = 'test';
        $tmpArr = array(
            $token,
            $timestamp,
            $nonce
        );
        sort($tmpArr, SORT_STRING);
        $tmpStr = implode($tmpArr);
        $tmpStr = sha1($tmpStr);
        if ($tmpStr == $signature) {
            echo $_GET['echostr'];
        } else {
            echo 0;
        }
    }
    
    // 更新菜单
    // function updateMenu(){
    // $menu = '{
    // "button":[
    // {
    // "type":"view",
    // "name":"我要领券",
    // "url":"http://zsh.ejiazheng.cn/value/weixin/index.php?platcode=LOVECLUB"
    // }
    // ]}';
    // $result = $this->wx_api->updateMenu($menu);
    // echo $result;
    // }
}
