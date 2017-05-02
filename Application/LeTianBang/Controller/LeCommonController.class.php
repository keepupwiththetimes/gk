<?php
namespace LeTianBang\Controller;

use Think\Controller;
use Common\Api\WxApi;
use Common\Api\Aes;
class LeCommonController extends Controller
{
    private $wxApi;             //微信接口api
    private $platform_config;
    private $check_mode = 1;    //验证用户是否通过uid进行接口验证

    function __construct()
    {
        parent::__construct();

            load('Home/function');
           if(online_redis_server){

              $this->redis = new \Vendor\Redis\DefaultRedis(C('REDIS_HOST_DEFAULT'),C('REDIS_PORT_DEFAULT'),C('REDIS_AUTH_DEFAULT'));   //默认redis实例
              $this->redisLog = new \Vendor\Redis\DefaultRedis(C('REDIS_HOST_LOG'),C('REDIS_PORT_LOG'),C('REDIS_AUTH_LOG'));            //记录log用的redis实例
           }else{

              $this->redis = "";
              $this->redisLog = "";    //dolog中会加实例化的redis 参数，在不需要redis 的时候设置为为空避免报错
           }
          if(APP_DEBUG){
              file_put_contents("debug.log","入口连接:".$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']."\n".'session信息:' . json_encode($_SESSION) . "\n",FILE_APPEND);
          }
          //获取平台code
          $platform_code = 'LETIANBANG';
          //if(empty($_SESSION['_PROJECT_NAME']) || $_SESSION['_PROJECT_NAME']!= ROOT_PATH){

           // //若为招行老版本过来则不清空session
          //  $_SESSION = NULL;
          //  $_SESSION['_PROJECT_NAME'] = ROOT_PATH;
          //}


          //验证平台code参数
          if (empty($platform_code) && empty($_SESSION['PLATFORM_CODE'])) {
              if(empty($_COOKIE['user_platform_code'])){

                  doLog('Common/error', '网页过期','',json_encode( $_SERVER['REQUEST_URI'] ,$this->redisLog));
                  $this->error('您查看网页已过期，请重新打开入口链接', C('WEB_URL') . C('ERROR_PAGE'));
              }else{

                  $_SESSION['PLATFORM_CODE'] = $_COOKIE['user_platform_code'];
              }
          }else{

              $_SESSION['PLATFORM_CODE'] = empty($platform_code) ? $_SESSION['PLATFORM_CODE'] : $platform_code;  //设置 platform_code 的 session
              if($_COOKIE['user_platform_code'] != $_SESSION['PLATFORM_CODE']){

                  cookie('user_platform_code',$_SESSION['PLATFORM_CODE'],3600*24);
              }
          }
          $this->platformCode = $_SESSION['PLATFORM_CODE']; //将session 存入 变量
          $platform_info = M('platform')->where(array('code' => $this->platformCode))->cache(true, 3600)->find();
          if ($platform_info['id']) {
              $_SESSION[$this->platformCode]['platform_id']   = $platform_info['id'];
              $_SESSION[$this->platformCode]['customer_id']   = $platform_info['customer_id'];
              $_SESSION[$this->platformCode]['platform_name'] = $platform_info['name'];
          } else {
            doLog('Common/error', '平台不存在','',json_encode( $_SERVER['REQUEST_URI'] ,$this->redisLog));
              $this->error('您查看的平台不存在', C('WEB_URL') . C('ERROR_PAGE'));
          }

          //获取平台配置
          $this->platform_config = getPlatformConfig($this->platformCode);//C('PLATFORM.' . $this->platformCode);
          if (empty($this->platform_config))
          {
              doLog('Common/error', '平台还未配置','',json_encode( $_SERVER['REQUEST_URI'] ),$this->redisLog);
              $this->error('您查看的平台还未配置，请稍后再打开', C('WEB_URL') . C('ERROR_PAGE'));
          }
          
          $this->check_mode = intval($this->platform_config['APP_MODE']);
          if(!$isWx  = is_wx()) $this->error('不在微信', C('WEB_URL') . C('ERROR_PAGE'));
          $this->wxApi = new WxApi(C('APP_ID_LETIANBANG'), C('APP_SECRET_LETIANBANG'));
           /*微信分享签名-----start*/
          $sign_package = $this->wxApi->getSignPackage();
          $this->assign('sign_package', $sign_package);
          /*
           * 获取应用模式
           * 0普通模式(接受传入商户uid,不验证商户uid,不获取乐天邦平台openid). 可能是从app发起的请求
           * 1验证模式(接受传入商户uid,并验证商户uid,不获取乐天邦平台openid)，
           * 2大转盘抽奖模式(只获取乐天邦平台openid)
           * 3获取平台openid(只获取乐天邦平台openid).来自自媒体的请求
           * 4无参模式(APP发起的请求,并且不传任何参数)
           * 5无为模式(不做任何操作)
           * */
          // $_SESSION[$this->platformCode]['openid']= 'o3InpjmFOHN8WF3hDVDd88K45h-s';
          //设置 openid
          /*微信中设置分享参数-----start*/

          if(empty($_SESSION[$this->platformCode]['openid'])){

            $wxUserInfo                                  = $this->wxApi->getUserInfoNew();
            $_SESSION[$this->platformCode]['openid']     = $wxUserInfo['openid'];
            $_SESSION[$this->platformCode]['zwcmopenid'] = $wxUserInfo['openid'];
            $_SESSION[$this->platformCode]['headimgurl'] = $wxUserInfo['headimgurl'];
            $_SESSION[$this->platformCode]['sex']        = $wxUserInfo['sex']-1;   //微信1为男  2为女  我们0为男 1为女
          } 
          
          /*配置页面头部----start*/
          $SEO['title'] = '乐天邦';

          /*配置页面头部----end*/

          //获取城市列表
          //$city_list = getAreaList();
          //if (empty($_SESSION[$this->platformCode]['city']) || empty($_SESSION[$this->platformCode]['city_id'])) {
          //    $_SESSION[$this->platformCode]['city_id'] = 0;
          //}
          /*验证并记录用户信息----start*/
          if(online_redis_server == true){
            $this->checkUserInfoRedis();
          }else{
            $this->checkUserInfo();
          }
          doLog('Common/construct', "初始化完成",'',json_encode($time),$this->redisLog);
          $this->assign('seo',$SEO);
    }

    //自动记录并加载用户信息
    //留着checkUserInfo是为了在没有redis上的机器上调试用，部分功能已经不正确了。在正式环境，请使用checkUserInfoRedis。 2016年9月10日
    function checkUserInfo()
    {
      $platform_code = $_SESSION['PLATFORM_CODE'];
      if (empty($_SESSION[$platform_code]['zwcmopenid'])) {
          if (intval($this->check_mode) == 1) {
              $_SESSION[$platform_code]['zwcmopenid'] = $this->wxApi->getOpenid();
          }
      }
      $session        = $_SESSION[$platform_code];
      $UserModel      = D('pt_user');
      $map = array();
      $map['openid']  = $session['openid'];
      $map['ptid']    = $session['platform_id'];
      $user_info      = $UserModel->where($map)->find();
      if (empty($user_info)) {
          $data                = array();
          $data['openid']      = $session['openid'];
          $data['telephone']   = $session['tel'];
          $data['zwcmopenid']  = $session['zwcmopenid'];
          $PlatformModel       = D('Platform');
          $platform_info       = $PlatformModel->where(array('id' => $session['platform_id']))->cache(true, 3600)->find();
          $CustomerModel       = D('Customer');
          $customer_info       = $CustomerModel->where(array('id' => $platform_info['customer_id']))->cache(true, 3600)->find();
          $data['browsetime']  = date('Y-m-d H:i:s');
          $data['channelid']   = $customer_info['id'];
          $data['channelname'] = $customer_info['name'];
          $data['ptid']        = $platform_info['id'];
          $data['ptname']      = $platform_info['name'];
          $data['template']    = $platform_info['templet'];
          $data['createtime']  = date('Y-m-d H:i:s');
          if ($data['openid']) {
              $_SESSION[$platform_code]['id'] = $UserModel->data($data)->add();
          }
      } else {
          $update_data = array();
          if (empty($user_info['zwcmopenid'])) {
              $update_data['zwcmopenid'] = $session['zwcmopenid'];
          } else {
              if ($user_info['zwcmopenid'] != $session['zwcmopenid']) {
                if ( DEBUG_ON_LOCALHOST_OR_201TESTINGSERVER == FALSE ){
                           if ($this->check_mode != 0){
                                  doLog('Common/error', 'zwcmopenid不匹配','',json_encode( $user_info['zwcmopenid'] ),$this->redisLog);
                          redirect($this->platform_config['REDIRECT_URL']);
                          exit;
                          }
                }
              }
          }
          $update_data['browsetime'] = date('Y-m-d H:i:s');

          //如果session中不存在手机号则将数据库中的手机号存到session中
          if(!isset($session['tel']))$_SESSION[$platform_code]['tel'] = $user_info['telephone'];
      }
  }
   //自动记录并加载用户信息
    function checkUserInfoRedis()
    {

     // $this->redis = new \Vendor\Redis\DefaultRedis(); //加载缓存类

      $platform_code = $_SESSION['PLATFORM_CODE'];
      if (empty($_SESSION[$platform_code]['zwcmopenid'])) {
          if (intval($this->check_mode) == 1) {
              $_SESSION[$platform_code]['zwcmopenid'] = $this->wxApi->getOpenid();
          }
      }
      $session = $_SESSION[$platform_code];
      $UserModel = D('pt_user');

      //读取用户信息

      $this->redis->databaseSelect('zwid');
      $user_info = $this->redis->hget('openid_platcode:'.$session['openid'].'_'.$session['platform_id']);
      $map = array();
      $map['openid'] = $session['openid'];
      $map['ptid'] = $session['platform_id'];

      if(!$user_info){   //缓存中不存在从数据中读取

         $user_info = $UserModel->where($map)->find();
         if(!empty($user_info)){   //更新缓存

          if(!empty($user_info['openid'])){

            //$this->redis->databaseSelect('zwid');
            $this->redis->hset('openid_platcode:'.$user_info['openid'].'_'.$session['platform_id'],$user_info);
            $this->redis->expire('openid_platcode:'.$user_info['openid'].'_'.$session['platform_id'],24*3600);
            $zwidUpdate = 1;
          }
         }
      }

      if (empty($user_info)) {
          $data                = array();
          $data['openid']      = $session['openid'];
          $data['telephone']   = $session['tel'];
          $data['zwcmopenid']  = $session['zwcmopenid'];
          $PlatformModel       = D('Platform');
          $platform_info       = $PlatformModel->where(array('id' => $session['platform_id']))->cache(true, 3600)->find();
          $CustomerModel       = D('Customer');
          $customer_info       = $CustomerModel->where(array('id' => $platform_info['customer_id']))->cache(true, 3600)->find();
          $data['browsetime']  = date('Y-m-d H:i:s');
          $data['channelid']   = $customer_info['id'];
          $data['channelname'] = $customer_info['name'];
          $data['ptid']        = $platform_info['id'];
          $data['ptname']      = $platform_info['name'];
          $data['template']    = $platform_info['templet'];
          $data['createtime']  = date('Y-m-d H:i:s');
          $data['sex']         = $session['sex'];
          if ($data['openid']) {
              $_SESSION[$platform_code]['id'] = $UserModel->data($data)->add();
              //添加新用户之后更新缓存
              if(!empty($data['openid'])){
                //$this->redis->databaseSelect('zwid');
                $this->redis->hset('openid_platcode:'.$data['openid'].'_'.$session['platform_id'],$user_info);
                $this->redis->expire('openid_platcode:'.$data['openid'].'_'.$session['platform_id'],24*3600);
              }
             setZwid($session,$data['createtime'],$this->redis,$this->redisLog);//设置掌握id的redis
          }
      } else {
          $update_data = array();

          /*   修改老用户的zwcmopenid    end       */
          $update_data['browsetime'] = date('Y-m-d H:i:s');
          if($map['openid']){
            $UserModel->where("openid='%s' and ptid=%d",array($map['openid'],$map['ptid']))->limit(1)->save($update_data);
          }

          if(!isset($user_info['id'])){  //缓存中id为空
            $user_info = $UserModel->where($map)->find();
            //$this->redis->databaseSelect('zwid');
            $this->redis->hset('openid_platcode:'.$user_info['openid'].'_'.$session['platform_id'],$user_info);
            $this->redis->expire('openid_platcode:'.$user_info['openid'].'_'.$session['platform_id'],24*3600);
          }
          $_SESSION[$platform_code]['id']  = $user_info['id'];
          //如果session中不存在手机号则将数据库中的手机号存到session中
          if(!isset($session['tel']))$_SESSION[$platform_code]['tel'] = $user_info['telephone'];
          //将zwid存入cookie
          $zwid = cookie('zwid');
          if(empty($zwid)){  //cookie中不存在查找zwid
             $zwcmopenid = empty($session['zwcmopenid']) ?  $_SESSION[$platform_code]['id']: $session['zwcmopenid'];
             if(!empty($zwcmopenid)){

                $zwid = M('zwid')->where(array('zwcmopenid'=>$zwcmopenid))->find();
                cookie('zwid',$zwid['id'],300);//五分钟
             }
          }

          if($zwidUpdate == 1) setZwid($session,$user_info['createtime'],$this->redis,$this->redisLog);
      }
  }

    //验证是否登录
    function checkLogin()
    {

        $session = $_SESSION[$_SESSION['PLATFORM_CODE']];

        //如果不存在session 则查询数据库是否有该用户的手机号存在，如存在说明不是第一次登陆
        if (empty($session['id']) || empty($session['tel'])) {
          $UserModel      = D('pt_user');
          $map            = array();
          $map['openid']  = $session['openid'];
          $map['ptid']    = $session['platform_id'];
          $user_info      = $UserModel->where($map)->find();
          if($user_info['telephone']){
            $_SESSION[$_SESSION['PLATFORM_CODE']]['tel'] = $user_info['telephone'];
            $_SESSION[$_SESSION['PLATFORM_CODE']]['id'] = $user_info['id'];
            return true;
          }else{
            return false;
          }
        }
        return true;
    }
    
}
