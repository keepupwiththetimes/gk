<?php
namespace Home\Controller;

use Think\Controller;
use Common\Api\WxApi;
use Common\Api\Aes;
use Common\Api\CmbcApi\decryptAndCheck;

class CommonController extends Controller
{
    private $wxApi;             //微信接口api分支
    protected $platform_config, $platform_code, $user_id, $platform_id, $platform_info;
    protected $check_mode = 1;    //验证用户是否通过uid进行接口验证
    private $zwcmopenidFetchedFromWeixin = false;    //在这里是否去微信取zwcmopenid， 如果取过的话，需要修改tb_pt_user表格
    private $zsyhBindBankCardVerified = false; //是否已经检查招行用户的绑卡状态， 可以用来减少写tb_pt_log表格的次数。
    public  $page_title = '';
    protected  $zwid = 0;

    function __construct()
    {
        parent::__construct();

        $this->redis = new \Vendor\Redis\DefaultRedis(C('REDIS_HOST_DEFAULT'), C('REDIS_PORT_DEFAULT'), C('REDIS_AUTH_DEFAULT'));   //默认redis实例
        $this->redisLog = new \Vendor\Redis\DefaultRedis(C('REDIS_HOST_LOG'), C('REDIS_PORT_LOG'), C('REDIS_AUTH_LOG'));            //记录log用的redis实例
               
        //platfomr_code 的获取与写入
        $this->platform_code = $this->getPlatcode();

        if ((!preg_match("/" . $_SERVER["HTTP_HOST"] . "/", ONE_COIN_URL) && (strcasecmp($this->platform_code, 'LETIANBANG')) != 0) || (!preg_match("/" . $_SERVER["HTTP_HOST"] . "/", ONE_COIN_URL) && !empty($this->platform_code))) { //不是一元购。老版本

            if (APP_DEBUG) {
                file_put_contents("debug.log", "入口连接:" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] . "\n" . 'session信息:' . json_encode($_SESSION) . "\n", FILE_APPEND);
            }

            //$session_platform_code = $this->platform_code; //将session 存入 变量
            //$this->platform_code = $session_platform_code;
            
            //平台信息--begin
            //
            $this->platform_info = M('platform')->where('code="%s"', $this->platform_code)->cache(3600)->find();  
            //获取流量入口code
            if (!isset($_SESSION[$this->platform_code]['traffic_code'])) {
                $_SESSION[$this->platform_code]['traffic_code'] = I('param.traffic_code', $this->platform_code);
            }
            if ($this->platform_info['id']) {
                $_SESSION[$this->platform_code]['platform_id'] = $this->platform_info['id'];
                $_SESSION[$this->platform_code]['customer_id'] = $this->platform_info['customer_id'];
                $_SESSION[$this->platform_code]['platform_name'] = $this->platform_info['name'];
                $this->platform_id = $this->platform_info['id'];
            } else {
                doLog('Common/error', '平台不存在', '', json_encode($_SERVER['REQUEST_URI']), $this->redisLog);
                $this->error('您查看的平台不存在', C('WEB_URL') . C('ERROR_PAGE'));
            }
            
            //平台信息--end
            
            //获取平台配置--begin
            $this->platform_config = getPlatformConfig($this->platform_code);//C('PLATFORM.' . $session_platform_code);   
            if (empty($this->platform_config)) {
                doLog('Common/error', '平台还未配置', '', json_encode($_SERVER['REQUEST_URI']), $this->redisLog);
                $this->error('您查看的平台还未配置，请稍后再打开', C('WEB_URL') . C('ERROR_PAGE'));
            }        
            $this->payment_type = $this->platform_config['payment_type']; //支付类型
            $this->check_mode = intval($this->platform_config['APP_MODE']);//应用模式
            $this->page_title = $this->platform_config['WEB_PAGE_TITLE'];//page_title
            $this->assign('templet', $this->platform_config['templet']);//样式模板
            if(true == DEBUG_ON_LOCALHOST_OR_201TESTINGSERVER && $this->platform_code !='NYYHD'){ //在测试环境强制短信认证
            	$this->platform_config['isTel'] = 1;
            }
            $this->assign('isTel', $this->platform_config['isTel']); //是否需要短信验证  
            $this->assign('banner', $this->platform_config['BANNER']);//banner配置         

            $isWx = is_wx();
            if ($isWx){
                $this->wxApi = new WxApi(C('APP_ID'), C('APP_SECRET'));
                /*微信分享签名-----start*/
                $sign_package = $this->wxApi->getSignPackage();
                $this->assign('sign_package', $sign_package);
                /*微信分享签名-----end*/

                $this->assign('SHARE', $this->platform_config['SHARE']);
            }

            //获取openid和zwcmopenid
            $openidArr = $this->getOpenidArr();
            //招行转盘花集网转盘，需要验证用户是否关注，把他们的openid以platcode_openid的形式存在session里，验证的时候用这个值进行验证
            if (isset($_SESSION[$this->platform_code][$this->platform_code.'_openid']) && !empty($_SESSION[$this->platform_code][$this->platform_code.'_openid'])) {

                $checkOpenid = $_SESSION[$this->platform_code][$this->platform_code.'_openid'];
            } else {

                $checkOpenid = $openidArr['openid'];
            }
           
            $this->checkBindCode($checkOpenid);
            //验证痛过之后，设置openid和zwcmopenid的session
            foreach($openidArr as $key=>$val) {

                if (!empty($val)) {

                    $_SESSION[$this->platform_code][$key] = $val;
                }
            }
            if (empty($_SESSION[$this->platform_code]['openid'])) { //在某些情况下，如urldecode出错，会取不到openid。要求用户重新刷新网页
                doLog('Common/error', 'openid为空', '', json_encode($_SERVER['REQUEST_URI']), $this->redisLog);
                $this->error('亲，出错了。请刷新网页。', C('WEB_URL') . C('ERROR_PAGE'));
            }
            
            //获取城市列表
            //$city_list = getAreaList();
            //需要这些吗？
            //if (empty($_SESSION[$this->platform_code]['city']) || empty($_SESSION[$this->platform_code]['city_id'])) {
            //    $_SESSION[$this->platform_code]['city_id'] = 0;
            //}

  
            $web_url = C('WEB_URL');
            $this->assign('web_url', $web_url);
            $this->assign('isWx', $isWx);
            $this->assign('city_name', $_SESSION[$this->platform_code]['city']);

           
            /*验证并记录用户信息----start*/
            $this->checkUserInfoRedis();
            /*验证并记录用户信息----end*/


        } else if (strcasecmp($this->platform_code, 'LETIANBANG') == 0) {
            $this->wxApi = new WxApi(C('APP_ID'), C('APP_SECRET'));

            /*微信分享签名-----start*/
            $sign_package = $this->wxApi->getSignPackage();
            $this->assign('sign_package', $sign_package);
            $this->assign('templet', 1);
        } else {   //一元购进行处理

            $sessionId = I('param.sid');
            $cSessionId = $_COOKIE['sessionId'];
            if (empty($sessionId) && empty($cSessionId)) $this->error('你进了不该进的页面', WEB_URL, 5);
            if ($sessionId && $cSessionId != $sessionId) {

                session_destroy();
                session_id($sessionId);
                cookie('sessionId', $sessionId);
                session_start();

            }
            $this->platform_code = $_SESSION['PLATFORM_CODE'];
            $this->platform_config = getPlatformConfig($this->platform_code);//C('PLATFORM.' . $session_platform_code);
            $this->assign('templet', $this->platform_config['templet']);
            $this->page_title = '一元夺宝';
        }

        $this->assign('page_title', $this->page_title);

    }
    
    private function getPlatcode(){
        
        $platform_code = I('param.platcode', null);
        if (isset($platform_code)) $platform_code = strtoupper($platform_code);
        
        //////若为空且不存在session(.....)则默认招行    2016-07-01 shenzihao(最好以后修改成需要传参)
        if ((!isset($platform_code) && I('param.openid')) || ((strcasecmp($_SESSION['PLATFORM_CODE'], 'ZHCJ') == 0) && CONTROLLER_NAME == 'Index' && !isset($platform_code))) {
            $platform_code = 'ZHWZ';
        }
        //宣榛已经不再是我们的合作伙伴， 邵晓凌 2017-03-07
        //宣榛ios设置session失效处理
        /*
        if (!isset($_SESSION[$_SESSION['PLATFORM_CODE']]) && (strcasecmp($platform_code, "XZZSH") == 0)) {
            $redirect = I('param.redirect', null);
            if (!$redirect) {
                $uId = I('param.uId', null, 'rawurlencode');
                $parm_data = array('uId' => $uId, 'platcode' => $platform_code, 'redirect' => 1);
                doLog('Common/error', '宣榛session失效', '', json_encode($parm_data), $this->redisLog);
                exit("<script>top.location = '" . U('Home/Session/index', $parm_data, true, true) . "';</script>");
            }
        }*/
        //验证平台code参数
        if (empty($platform_code) && empty($_SESSION['PLATFORM_CODE'])) {
            if (empty($_COOKIE['user_platform_code'])) {
        
                doLog('Common/error', '网页过期', '', json_encode($_SERVER['REQUEST_URI']), $this->redisLog);
                $this->error('您查看网页已过期，请重新打开入口链接', C('WEB_URL') . C('ERROR_PAGE'));
            } else {
        
                $_SESSION['PLATFORM_CODE'] = $_COOKIE['user_platform_code'];
            }
        } else {
        
            $_SESSION['PLATFORM_CODE'] = empty($platform_code) ? $_SESSION['PLATFORM_CODE'] : $platform_code;  //设置 platform_code 的 session
            if ($_COOKIE['user_platform_code'] != $_SESSION['PLATFORM_CODE']) {
        
                cookie('user_platform_code', $_SESSION['PLATFORM_CODE'], 3600 * 24);
            }
            return $_SESSION['PLATFORM_CODE'];
        }
    }

    //自动记录并加载用户信息
    private function checkUserInfoRedis()
    {
        $session = $_SESSION[$this->platform_code];
        $UserModel = D('pt_user');
        
        //调用获取地理位置的类
        if(preg_match('/zwmedia.com.cn/', $_SERVER['HTTP_HOST'])) {
        	$ip = get_client_ip(0, true);
        	$this->Ipipnet = new \Vendor\Ipipnet\Ipipnet();
        	//调用获取地理位置的类
        	$result = $this->Ipipnet->find($ip,$this->redis);
        	$this->locationInfo = array('province'=>$result[1],'city'=>$result[2]);
        	
        } else {  //防止本地调试出错
        	
        	$this->locationInfo = array();
        }
        
        //读取用户信息
        
        $this->redis->databaseSelect('zwid');
        $user_info = $this->redis->hget('openid_platcode:' . $session['openid'] . '_' . $session['platform_id']);
        $map = array();
        $map['openid'] = $session['openid'];
        $map['ptid'] = $session['platform_id'];

        if (!$user_info) {   //缓存中不存在从数据中读取
            $user_info = $UserModel->where("openid='%s' and ptid=%d ", array($map['openid'], $map['ptid']))->find();
            if (!empty($user_info)) {   //更新缓存

                if (!empty($user_info['openid'])) {
                    $this->redis->hset('openid_platcode:' . $user_info['openid'] . '_' . $session['platform_id'], $user_info);
                    $this->redis->expire('openid_platcode:' . $user_info['openid'] . '_' . $session['platform_id'], 24 * 3600);
                    $zwidUpdate = 1;
                }
            }
        }

        //取zwcmopenid
        $session_platform_code = $this->platform_code;
        $renew_zwcmopenid = false;    //判断是否要重新取zwcmopenid
        if (!isset($_SESSION[$session_platform_code]['zwcmopenid']) || empty($_SESSION[$session_platform_code]['zwcmopenid'])) {

            if ((intval($this->check_mode) == 1) || (intval($this->check_mode) == 3) || (intval($this->check_mode) == 5) || (intval($this->check_mode) == 6)) {
                if (empty($user_info)) $renew_zwcmopenid = true;  //用户第一次访问
                else {
                    if ((strtotime($user_info['browsetime']) < strtotime("2016-09-24"))) { //2016-9-24切换招行的zwcmopenid. 在这之后取得的zwcmopenid不用再更新
                        $renew_zwcmopenid = true;
                    } else {
                        if (!empty($user_info['zwcmopenid']))
                            $_SESSION[$session_platform_code]['zwcmopenid'] = $user_info['zwcmopenid'];
                        else $renew_zwcmopenid = true;
                    }
                }
            }
            if ($renew_zwcmopenid == true) {

                $time_beforeopenid = microtime(true);
                $newZwcmopenid = $this->wxApi->getOpenid();
                if (!empty($newZwcmopenid)) {
                    $_SESSION[$session_platform_code]['zwcmopenid'] = $newZwcmopenid;
                    $this->zwcmopenidFetchedFromWeixin = true;
                }
                $elapseTime = round(microtime(true) - $time_beforeopenid, 4);
                if ($elapseTime > 0.3) {
                    dolog('Common/debug', '获取zwopenid超过300ms', '', 'latency: ' . $elapseTime, $this->redisLog);
                }
            }
        }

        if (empty($user_info)) {    //  用户第一次访问乐天邦平
            $this->assign('display_instructions', 'yes'); //是否要显示覆层，以告诉用户左右滑动。
            
            $data = array();
            $data['openid'] = $session['openid'];
            if (preg_match('/^1[34578][0-9]{9}$/', $session['app_tel'])) { //这段代码是为了处理一些传手机号码到乐天邦平台，但是可能引起无法修改手机号码的问题

                $data['telephone'] = isset($session['app_tel']) ? $session['app_tel'] : '0' ;
                $_SESSION[$session_platform_code]['tel'] = isset($session['app_tel']) ? $session['app_tel'] : '0' ;
            } else {

                $data['telephone'] = isset($session['tel']) ? $session['tel'] : '0' ;
            }
            $data['zwcmopenid'] = $_SESSION[$session_platform_code]['zwcmopenid'];// $session['zwcmopenid']; 有些情况下$session['zwcmopenid']为空，但是$_SESSION[$session_platform_code]['zwcmopenid'];不为空
            $PlatformModel = D('Platform');
            //$this->platform_info = $PlatformModel->where("id=%d ", $session['platform_id'])->cache(true, 3600)->find();
            $CustomerModel = D('Customer');
            $customer_info = $CustomerModel->where("id=%d ", $this->platform_info['customer_id'])->cache(true, 3600)->find();
            $data['browsetime'] = date('Y-m-d H:i:s');
            $data['channelid'] = empty($customer_info['id']) ? 0 : $customer_info['id'] ;
            $data['channelname'] = $customer_info['name'];
            $data['ptid'] = $this->platform_info['id'];
            $data['ptname'] = $this->platform_info['name'];
            $data['template'] = $this->platform_info['templet'];
            $data['createtime'] = date('Y-m-d H:i:s');
            $data['province'] = $this->locationInfo['province'];
            $data['city'] = $this->locationInfo['city'];
            //2016.12.28 jiang 用户昵称和头像记录
            //start
            if (isset($_GET['nickname'])) {
                $data['username'] = json_encode(I('get.nickname'));
                //暴露昵称中图片的unicode
                //用户昵称处理完毕
//                $data['username'] = json_decode(preg_replace("/\\\ue[0-9a-f]{3}/", 'u002a', json_encode($data['username'])));
            } elseif (strcasecmp($this->platform_code, 'ZHCJ') == 0) {//($map['ptid'] == '12')  {
                //招行银行：招行乐天邦和签到抽奖都能直接获取昵称和头像
                //但是招行乐天邦首页的签到按钮无法传昵称和头像，所以当从签到按钮进签到抽奖页面的时候，直接从数据库取招行乐天邦的昵称和头像
            	$data['username'] = $this->getUserNameAndPicture($session['zwcmopenid'])['username'];
            }
            if (isset($_GET['headimgurl'])) {
                $data['picture'] = I('get.headimgurl');
            } elseif (strcasecmp($this->platform_code, 'ZHCJ') == 0)  { //($map['ptid'] == '12')
            	$data['picture'] = $this->getUserNameAndPicture($session['zwcmopenid'])['picture'];
            }
            //end
            if ($data['openid']) {
                $uid = $UserModel->data($data)->add();
                //新用户标 识
                $_SESSION[$this->platform_code]['isNew'] = 1 ;
                $_SESSION[$this->platform_code]['id'] = $uid;
                $this->user_id = $uid;
                //为了防止出现tb_pt_user表格里面有记录，但是在tb_pt_log表格里面没有用户的记录的情况（农业银行动账抽奖），在每次新用户产生的时候，往tb_pt_log表格记录一下
                dolog('Common/AddNewUser', '添加新用户', '', '' , $this->redisLog);
                
                //添加新用户之后更新缓存
                if (!empty($data['openid'])) {
                    $this->redis->hset('openid_platcode:' . $data['openid'] . '_' . $session['platform_id'], $data);
                    $this->redis->expire('openid_platcode:' . $data['openid'] . '_' . $session['platform_id'], 24 * 3600);
                }
                $session = $_SESSION[$this->platform_code];
                $this->zwid = setZwid($session, $data['createtime'], $this->redis, $this->redisLog);//设置掌握id的redis
            }
        } else {
            if (strtotime($user_info['browsetime']) < strtotime("2017-03-16 10:30:00")){
                $this->assign('display_instructions', 'yes'); //是否要显示覆层，以告诉用户左右滑动。2017-03-16日上线。
            }
            //处理招行抽奖看不到历史数据的问题，发生的原因是招行抽奖平台原来记录的openid为招行传过来的，
            //而我们对所有抽奖平台的处理为openid记录的是我们掌握传媒的zwcmopenid.
            //修改日期为2016-09-24。建议到一定期限如半年后去掉这段代码。
            if ($session['platform_id'] == 1) {

                if (strtotime($user_info['browsetime']) < strtotime("2016-09-24")) {
                    if (!empty($session['zwcmopenid'])) {
                        $UserModel->where("openid='%s' and ptid=%d ", array($session['openid'], 12))->limit(1)->save($modify_zwcmopenid_data);
                        doLog('Common/modifycmbzwcmopenid', '修改招行抽奖平台openid', '', ' zwcmopenid＝' . $session['zwcmopenid'] . ', old openid=' . $session['openid'], $this->redisLog);
                        //马上更新缓存，以免出现一天更新多次的现象
                        $user_info = array_merge($user_info,array('browsetime'=> date('Y-m-d H:i:s')));
                        $this->redis->hset('openid_platcode:' . $user_info['openid'] . '_' . $session['platform_id'], $user_info);
                        $this->redis->expire('openid_platcode:' . $user_info['openid'] . '_' . $session['platform_id'], 24 * 3600);
                    }
                }
            }
            // 需要判断新旧zwcmopenid是否一致，不一致的话，需要修改对应的抽奖平台的数据  //
            // 只需要判断目前3个抽奖平台： 中石油、山西邮政、锦江利享
            //1. 在tb_pt_user表格，利用乐天邦平台ptid和openid得到旧的zwcmopenid
            //2. 判断这个旧的zwcmopenid是否跟刚刚获取的zwcmopenid是否一
            //3。 如果不一样。则把tb_pt_user表格里面旧的zwcmopenid和抽奖ptid对应的列的zwcmopenid改成新的zwcmopenid， 并且在zwid表格中更新相关的字段。
            // 不修改自媒体渠道的tb_order的记录了，因为数量在50人以下
            if (($session['platform_id'] == 4) || ($session['platform_id'] == 7) || ($session['platform_id'] == 14)) {
                if (strtotime($user_info['browsetime']) < strtotime("2016-09-13")) { // 2016-09-13 修改了非招行项目使用的微信公众号，从而引起中石油、山西邮政、锦江利享三个渠道的抽奖平台看不到获奖记录，只能人为修改数据库的记录
                    $old_zwcmopenidArr = $UserModel->field('zwcmopenid')->where("openid='%s' and ptid=%d", array($map['openid'], $map['ptid']))->find();
                    $old_zwcmopenid = $old_zwcmopenidArr['zwcmopenid'];
                    if ((!empty($old_zwcmopenid)) && (!empty($session['zwcmopenid'])) && ($old_zwcmopenid != $session['zwcmopenid'])) {

                        $modify_zwcmopenid_data = array();
                        $modify_zwcmopenid_data['zwcmopenid'] = $session['zwcmopenid'];
                        $modify_zwcmopenid_data['openid'] = $session['zwcmopenid'];
                        if ($session['platform_id'] == 4) //中石油, 约有1588个有中奖记录
                            $luckydrawptid = 3;
                        //2015-12-19及之前, ptid=3为中石油的乐天邦平台
                        //2015-12-30及以后， ptid=3为中石油的抽奖平台；2015-12-19至 2015-12-30期间没有新用户
                        elseif ($session['platform_id'] == 7) //山西邮政, 约有4924个有中奖记录
                            $luckydrawptid = 9;
                        elseif ($session['platform_id'] == 14) //锦江利享, 约有181个有中奖记录
                            $luckydrawptid = 20;

                        //因为在中石油平台，抽奖平台中间切换过，所以需要判断zwcmopenid和openid一样才是抽奖平台的数据！！！
                        $UserModel->where("zwcmopenid='%s' and ptid=%d and zwcmopenid=openid", $old_zwcmopenid, $luckydrawptid)->limit(1)->save($modify_zwcmopenid_data);
                        doLog('Common/modifyzwcmopenid', '修改pt_user表的抽奖平台zwcmopenid', '', 'old zwcmopenid＝' . $old_zwcmopenid . ',ptid=' . $luckydrawptid . ', new zwcmopenid and openid=' . $session['zwcmopenid'], $this->redisLog);

                    }
                }
            }

            /*   修改老用户的zwcmopenid    start        */
            $update_data = array();
            $update_data['browsetime'] = date('Y-m-d H:i:s');

            //更新省份信息
            if($user_info['province'] != $this->locationInfo['province'] && !empty($this->locationInfo['province'])) {

                $update_data['province'] = $this->locationInfo['province'];
                $this->redis->hset('openid_platcode:' . $user_info['openid'] . '_' . $session['platform_id'], $this->locationInfo['province'], 'province');
            }

            //更新城市信息
            if($user_info['city'] != $this->locationInfo['city'] && !empty($this->locationInfo['city'])) {

                $update_data['city'] = $this->locationInfo['city'];
                $this->redis->hset('openid_platcode:' . $user_info['openid'] . '_' . $session['platform_id'], $this->locationInfo['city'], 'city');
            }

            //在很久以前，有些平台如招行的zwcmopenid为空，影响产品的购买和支付。
            /*   修改老用户的zwcmopenid    end       */

            if ($map['openid']) {
                $newZwcmOpenid = $_SESSION[$session_platform_code]['zwcmopenid']; //为什么此处$session['zwcmopenid']为空; 但是$_SESSION[$session_platform_code]['zwcmopenid'] 不为空？
                if (($this->zwcmopenidFetchedFromWeixin == true) && (!empty($newZwcmOpenid))) {
                    $update_data['zwcmopenid'] = $newZwcmOpenid;//$session['zwcmopenid']; //此代码添加日期：2016-11-17下午18:00
                    $this->redis->hset('openid_platcode:' . $user_info['openid'] . '_' . $session['platform_id'], $newZwcmOpenid, 'zwcmopenid');
                    $this->redis->expire('openid_platcode:' . $user_info['openid'] . '_' . $session['platform_id'], 24 * 3600);
                }


                if (isset($_SESSION[$_SESSION['PLATFORM_CODE']]['isbind']) && ($_SESSION[$_SESSION['PLATFORM_CODE']]['isbind'] == '1')) {
                    if ($this->zsyhBindBankCardVerified == true)    //只有当用户第一次去招行查询的时候，才需要记录到数据库
                        $update_data['bindbankcard'] = 1;
                }
                //招行用户头像和昵称 不存在的老用户，再存一次库和redis jiang
                //start
                if (isset($_GET['nickname'])&& ( !$user_info['username'] || ($user_info['username'] != json_encode($_GET['nickname'])))){
                    $username = json_encode(I('get.nickname'));
                    $user_info = array_merge($user_info,array('username'=>$username));
                    $this->redis->hset('openid_platcode:' . $user_info['openid'] . '_' . $session['platform_id'], $user_info);
                    $this->redis->expire('openid_platcode:' . $user_info['openid'] . '_' . $session['platform_id'], 24 * 3600);
                    $update_data['username'] = $username;
                } elseif (strcasecmp($this->platform_code, 'ZHCJ') == 0)  { // ($map['ptid'] == '12')
                    $tmp_username = $this->getUserNameAndPicture($session['zwcmopenid'])['username'];
                    if (!empty($tmp_username)) {
            		    //$update_data['username']  = $tmp_username;
            		    $user_info = array_merge($user_info,array('username'=>$tmp_username));
            		    $this->redis->hset('openid_platcode:' . $user_info['openid'] . '_' . $session['platform_id'], $user_info);
                        $this->redis->expire('openid_platcode:' . $user_info['openid'] . '_' . $session['platform_id'], 24 * 3600);
                    }
                    unset($tmp_username);
            	}


                if (isset($_GET['headimgurl']) && ( !$user_info['picture'] || ($user_info['picture'] != $_GET['headimgurl']))) {
                    $user_info = array_merge($user_info,array('picture'=>I('get.headimgurl')));
                    $this->redis->hset('openid_platcode:' . $user_info['openid'] . '_' . $session['platform_id'], $user_info);
                    $this->redis->expire('openid_platcode:' . $user_info['openid'] . '_' . $session['platform_id'], 24 * 3600);
                    $update_data['picture'] = I('get.headimgurl');
                }  elseif (strcasecmp($this->platform_code, 'ZHCJ') == 0)  { // ($map['ptid'] == '12')
                    $tmp_picture = $this->getUserNameAndPicture($session['zwcmopenid'])['picture'];
                    if(!empty($tmp_picture)){
            		    //$update_data['picture'] = $tmp_picture;
            		    $user_info = array_merge($user_info,array('picture'=>$tmp_picture));
            		    $this->redis->hset('openid_platcode:' . $user_info['openid'] . '_' . $session['platform_id'], $user_info);
                        $this->redis->expire('openid_platcode:' . $user_info['openid'] . '_' . $session['platform_id'], 24 * 3600);
                    }
                    unset($tmp_picture);
            	}

                //end
            	if (!isset($user_info['id'])) {  //缓存中id为空
            	    $user_info = $UserModel->where("openid='%s' and ptid=%d ", array($map['openid'], $map['ptid']))->find();
            	    $this->redis->hset('openid_platcode:' . $user_info['openid'] . '_' . $session['platform_id'], $user_info);
            	    $this->redis->expire('openid_platcode:' . $user_info['openid'] . '_' . $session['platform_id'], 24 * 3600);
            	}
            	$_SESSION[$this->platform_code]['id'] = $user_info['id'];  //这样下面的dolog记录下列的时候uid就是正确的值了。
                $this->user_id = $user_info['id'];
            	
                $redisKeyBrowsetimeUpdated =  'browsetime_updated_recently_' . $session['platform_id'] . '_' . $session['openid'];
                $updateTimes = 0; //记录进入首页的次数，用在判断是否要记录首页加载时间到tb_pt_log
                //if ((count($update_data) != 1) || (!$this->redisLog->existsRedis($redisKeyBrowsetimeUpdated))) {
                if (!$this->redisLog->existsRedis($redisKeyBrowsetimeUpdated)) {
                    $UserModel->where("openid='%s' and ptid=%d", array($map['openid'], $map['ptid']))->limit(1)->save($update_data);
                    //更新redis里的browstime
                    $this->redis->hset('openid_platcode:' . $user_info['openid'] . '_' . $session['platform_id'], $update_data['browsetime'], 'browsetime');                 
                    //为了防止出现tb_pt_user表格里面有记录，但是在tb_pt_log表格里面没有用户的记录的情况（农业银行动账抽奖），在每次用户更新浏览时间的时候，往tb_pt_log表格记录一下
                    dolog('Common/UpdateUser', '修改用户浏览时间', '', $UserModel->getLastSql() , $this->redisLog);
                    
                }else{
                    $updateTimes = $this->redisLog->get($redisKeyBrowsetimeUpdated);
                }
                
                $this->redisLog->set($redisKeyBrowsetimeUpdated, $updateTimes + 1, 10 * 60); //10分钟之内不再更新browsetime，以节省数据库的开销
            }

            
            //如果session中不存在手机号则将数据库中的手机号存到session中
            if (!isset($session['tel'])) $_SESSION[$this->platform_code]['tel'] = $user_info['telephone'];
            
            $session = $_SESSION[$this->platform_code]; //重新对$session赋值，因为招行平台的zwcmopenid在上一次对$session赋值的时候还没有
            //将zwid存入cookie
            $this->zwid= cookie('zwid');
            
            if (empty($this->zwid)) {  //cookie中不存在查找zwid
                $zwcmopenid = empty($session['zwcmopenid']) ? $this->user_id : $session['zwcmopenid'];
                if (!empty($zwcmopenid)) {

                    $zwid = M('zwid')->where("zwcmopenid='%s'", $zwcmopenid)->find();
                    cookie('zwid', $zwid['id'], 300);//五分钟
                    $this->zwid = $zwid['id'];
                }
            }
            
            if ($zwidUpdate == 1) {
                
                
                setZwid($session, $user_info['createtime'], $this->redis, $this->redisLog);
            }
        }
        if( empty($this->zwid)){
        	$this->zwid = intval(cookie('zwid'));
        }
    }
    private function getUserNameAndPicture($zwcmopenid){
        //招行银行：由于招行乐天邦首页的签到按钮无法传昵称和头像，所以当从签到按钮进签到抽奖页面的时候，直接从数据库取招行乐天邦的昵称和头像
    	return  M('pt_user')->field('username,picture')->where("zwcmopenid='%s' and ptid=%d ", array($zwcmopenid, 1))->cache(600)->find();
    }
    //验证是否登录
    function checkLogin()
    {
        if (strcasecmp($this->platform_code, 'CMBPAY') == 0) return true;//招商银行推送支付页面不需要登录，在输地址的地方有手机号码

        $session = $_SESSION[$this->platform_code];

        //如果不存在session 则查询数据库是否有该用户的手机号存在，如存在说明不是第一次登陆
        if (empty($session['id']) || empty($session['tel'])) {
            $this->redis->databaseSelect('zwid');
            $user_info = $this->redis->hget('openid_platcode:' . $session['openid'] . '_' . $session['platform_id']);
            if (empty($user_info['telephone'])) {    //if(!$user_info)
                $UserModel = D('pt_user');
                $user_info = $UserModel->where("openid='%s' and ptid=%d ", array($session['openid'], $session['platform_id']))->find();
            }
            if ($user_info['telephone']) {
            	$_SESSION[$this->platform_code]['tel'] = $user_info['telephone'];
            	$_SESSION[$this->platform_code]['id'] = $user_info['id'];
                $this->user_id = $user_info['id'];
                return true;
            } else {
                return false;
            }
        }
        return true;
    }

    //验证参数t_uid是否关注商户
    private function checkBindCode($openid = '')
    { 
        //不需要验证的直接返回
        if ($this->platform_config['IS_CHECK_BIND'] != 1) {

            return true;
        }
        //如果openid验证过了直接返回
        if ($_SESSION[$this->platform_code][$openid."_ischecked"] == 1) {

            return true;
        }
        ///////判断是否为招行。若为招行另外处理  2016-07-04    shenzihao
        if (strcasecmp($this->platform_code, 'ZHWZ') == 0 || strcasecmp($this->platform_code, 'ZHCJ') == 0) {

            // $aes = new Aes();
            // if (DEBUG_ON_LOCALHOST_OR_201TESTINGSERVER == FALSE)
            //     ////正式环境的KEY
            //     $key = base64_decode(utf8_encode('BCIYxaR6PQM5Z1DeMxUgAg=='));
            // else
            //     ////测试环境
            //     $key = base64_decode(utf8_encode('7/jZgyyvZdwO06bbuZ5cwQ=='));

            // $aes->set_key($key);
            // $aes->require_pkcs();
            // $openid = $aes->decrypt(utf8_encode($t_uid));
            //if (strcasecmp($this->platform_code, 'ZHWZ') == 0 && $openid) $_SESSION['ZHWZ']['openid'] = $openid;
            //若请求url中存在时间戳,则验证  2016-11-14    shenzihao
            $timestamp = I('timestamp', null);
            $signature = I('signature', null);
            if (!empty($timestamp)) {
                if (DEBUG_ON_LOCALHOST_OR_201TESTINGSERVER == FALSE) {
                    ////正式环境的KEY
                    $oauthid = '75cade02';
                } else {
                    ////测试环境
                    $oauthid = 'qep3eg6';
                }
                //组装请求url
                $param = array(
                    'oauthId' => $oauthid,
                    'openId' => $openid,
                    'signature' => $signature,
                    'timestamp' => $timestamp,
                    'channel' => 'WeiXin'
                );
                $http_query = http_build_query($param);
                if (DEBUG_ON_LOCALHOST_OR_201TESTINGSERVER == FALSE) {
                    ////正式环境的KEY
                    $url = 'http://xyk.cmbchina.com/Activity/customer/check-customer-status.json?' . $http_query;
                } else {
                    ////测试环境
                    $url = 'http://uat.cc.cmbchina.com/Activity/customer/check-customer-status.json?' . $http_query;
                }
                //验证
                $time_beforeopenid = microtime(true);
                $result = json_decode(send_curl($url), true);
                
                $elapseTime = round(microtime(true) - $time_beforeopenid, 4);
                if ($elapseTime > 0.3) {
                    dolog('Common/zhchecktime', '验证超过300ms', '', 'latency: ' . $elapseTime, $this->redisLog);
                }
                //判断是否关注
                $this->zsyhBindBankCardVerified = true;    // 取招行查询过用户的绑卡状态了。

                if ($result['respCode'] == '1000') {
                    //判断是否绑卡
                    if ($result['data'] == 'BIND') {
                        $_SESSION[$_SESSION['PLATFORM_CODE']]['isbind'] = '1';
                    } else if ($result['data'] == 'FOLLOW') {
                        $_SESSION[$_SESSION['PLATFORM_CODE']]['isbind'] = '0';
                    } else {

                        //绑定关系
                        $this->bindInviter();
                        redirect('http://xyk.cmbchina.com/Latte/wx/20151022wx');
                        exit;
                    }
                } else {

                    //绑定关系
                    $this->bindInviter();
                    redirect('http://xyk.cmbchina.com/Latte/wx/20151022wx');
                    exit;
                }
            }
        } else {
             $t1 = microtime(true);
            // $_uid = decrypt(base64_decode($t_uid), $this->platform_config['DES_KEY']);

            $post_data = create_bind_data($openid, $this->platform_config['TOKEN'], $this->platform_config['MERCHANT_ID']);

            $bind_result = is_bind($post_data, $this->platform_config['INTERFACE_URL']);
            $bind_code = intval($bind_result['status']);
            $data['openid'] = $openid; //记录openid到tb_pt_log表格, 邵晓凌 2017-03-20
            $data['time'] = round(microtime(true) - $t1, 3); //统计时间
            $data['result'] = $bind_result;
            doLog('Common/checkBindCode', '验证是否关注', '', json_encode($data), $this->redisLog);
            if ($bind_code != 1) {
                if (DEBUG_ON_LOCALHOST_OR_201TESTINGSERVER == FALSE) {
                    redirect($this->platform_config['REDIRECT_URL']);
                    unset($_SESSION[$_SESSION['PLATFORM_CODE']]['openid']);
                    exit;
                }
            }

            //设置商户传递的openid
            //$_SESSION[$_SESSION['PLATFORM_CODE']]['openid'] = $_uid;
            //$openid = $_uid;
        }
        
        $_SESSION[$this->platform_code][$openid."_ischecked"] = 1;
        return true;
    }

    //获取渠道商传递的tuid参数
    private function getTUid()
    {
        //$platform_code = $this->platform_code;
        ////////判断是否为招行  2016-07-04 shenzihao
        if (strcasecmp($this->platform_code, 'ZHWZ') == 0) {
            $t_uid = I('param.openid', '');
        } else {
            $t_uid = I('param.uId', '', 'rawurldecode');
        }
        if (empty($_SESSION[$this->platform_code]['tuid']) && empty($t_uid)) {
            if (DEBUG_ON_LOCALHOST_OR_201TESTINGSERVER == FALSE) {
                doLog('Common/error', 'tuid为空', '', json_encode($_SERVER['REQUEST_URI']), $this->redisLog);
                redirect($this->platform_config['REDIRECT_URL']);
                exit();
            }
        } else {
            if (empty($t_uid)) {
                return $_SESSION[$this->platform_code]['tuid'];
            } else {
                $_SESSION[$this->platform_code]['tuid'] = $t_uid;
                return $_SESSION[$this->platform_code]['tuid'];
            }
        }
    }


    //  /*
    //   * [appHandle 给浏览器登录的用户设置openid]
    //   * @shenzihao
    //   *
    //   * @DateTime 2016-09-20
    //   * */

    private function appHandle($session_platform_code)
    {
        //北京民生APP
        if ($session_platform_code == 'MSYHC') {
            $this->getMsyhcParam($session_platform_code);
        }
        //将session 存入 变量
        if ($cookie = cookie($session_platform_code . '_openid')) {
           // $_SESSION[$session_platform_code]['openid'] = $cookie;
            //$_SESSION[$session_platform_code]['zwcmopenid'] = $cookie;
        } else {
            $cookie = $session_platform_code . time() . rand(1000, 9999);
            //$_SESSION[$session_platform_code]['openid'] = $cookie;
            //$_SESSION[$session_platform_code]['zwcmopenid'] = $cookie;
            cookie($session_platform_code . '_openid', $cookie, 60 * 60 * 24 * 100);
        }

        if (strcasecmp($session_platform_code, 'msyh') == 0 && CONTROLLER_NAME == 'Index') {
            $platform = D('platform');
            $ptid = $platform->where('code="%s"', $session_platform_code)->find();
            $platform_product = D('platform_product');
            $productid = $platform_product->where("platform_id=%d and status=%d ", array($ptid['id'], 1))->find();
            redirect(U('product/view?', 'id=' . $productid['product_id'] . '&platcode=MSYH'));
        }

        $openidArr['openid'] = $cookie;
        $openidArr['zwcmopenid'] = $cookie;

        return $openidArr;
    }
    //  /*
    //   * [getZhcjOpenid 招商银行转盘获取openid]
    //   * @shenzihao
    //   *
    //   * @DateTime 2016-10-09
    //   * */
    /*
    function getZhcjOpenid($t_uid)
    {
        $aes = new Aes();
        if (DEBUG_ON_LOCALHOST_OR_201TESTINGSERVER == FALSE)
            ////正式环境的KEY
            $key = base64_decode(utf8_encode('BCIYxaR6PQM5Z1DeMxUgAg=='));
        else
            ////测试环境
            $key = base64_decode(utf8_encode('7/jZgyyvZdwO06bbuZ5cwQ=='));
        //$t_uid= I('param.openid',null);
        $aes->set_key($key);
        $aes->require_pkcs();
        $openid = $aes->decrypt(utf8_encode($t_uid));
        $_SESSION['ZHCJ']['ZhcjOpenid'] = $openid;
        $_SESSION[$this->platform_code]['ZHCJ_openid'] = $openid;
    }*/

    private function getMsyhcParam($session_platform_code)
    {
        $chiperTxt = I('param.param', '');
        if ($chiperTxt) {
            $keyStr = 'JiYqrz583wzVghMAnsFzbg==';
            $chiperTxt = $chiperTxt;//'plxIaWGVLEwO9uWJRklDyDhWprbTb9rfaHsnGCs/jJ2YabAwvz99ZkBpoahObXxj';
            $plainTxt = DecryptAndCheck::checkWithTimeStamp($chiperTxt, $keyStr, 50000000000.1);
            $result = explode('|', $plainTxt);
            if ($result[0]) {
                $_SESSION[$session_platform_code]['tel'] = $result[0];
                cookie($session_platform_code . '_openid', $result[1], 60 * 60 * 24 * 100);
            }
        }
    }
    
    /**
     * [bindInviter 绑定邀请人和被邀请人的关系]
     * @ckhero
     * @DateTime 2017-03-22
     * @return   [type]     [description]
     */
    public function bindInviter()
    {

        if (ACTION_NAME=='zhWelcome' && !empty($_SESSION['ZHCJ']['ZhcjOpenid']) && $_GET['uid'] > 0) {
                      
            $this->redis->databaseSelect('wheel');
            $this->redis->set('Invitee:'.$_SESSION['ZHCJ']['ZhcjOpenid'], $_GET['uid'], strtotime(date('Y-m-d 00:00:00', strtotime('+1 day'))) - time());
        }
    }
    
    /**
     * 
     * 获取应用模式
     * 0普通模式(接受传入商户uid,不验证商户uid,不获取乐天邦平台openid). 可能是从app发起的请求
     * 1验证模式(接受传入商户uid,并验证商户uid,获取乐天邦平台openid)，
     * 2大转盘抽奖模式(只获取乐天邦平台openid, 并且把openid设成乐天邦平台openid的值)
     * 3获取平台openid(只获取乐天邦平台openid, 并且把openid设成乐天邦平台openid的值).来自自媒体的请求
     * 4无参模式(APP发起的请求,并且不传任何参数)， 不获取乐天邦平台openid
     * 5无为模式(不做任何操作)，获取乐天邦平台openid
     * 6获取商户openid不验证，获取乐天邦平台openid
     * 
     * [getOpenidArr 获取openid和zwcmopenid]
     * @ckhero
     * @DateTime 2017-03-24
     */
    private function getOpenidArr(){

        //本地测试写死openid
        //$_SESSION[$session_platform_code]['openid']= 'o3InpjmFOHN8WF3hDVDd88K45h-s';
        
        //获取openid和zwcmopenid--begin 
        $openidArr = array();  
        if ($this->check_mode == 0) {
            $t_uid = $this->getTUid();
            $t_uid_openid = decrypt(base64_decode($t_uid), $this->platform_config['DES_KEY']);
            if (is_tel($t_uid_openid)) {
                //$_SESSION[$platform_code]['tel'] = $t_uid_openid;
                $_SESSION[$platform_code]['app_tel'] = $t_uid_openid;
            }

            if (empty($t_uid_openid)) {
                if (DEBUG_ON_LOCALHOST_OR_201TESTINGSERVER == FALSE) {

                    doLog('Common/error', 'openid为空', '', json_encode($_SERVER['REQUEST_URI']), $this->redisLog);
                    redirect($this->platform_config['REDIRECT_URL']);
                    exit();
                }
            }
            // $_SESSION[$this->platform_code]['openid'] = $t_uid_openid;
            // $_SESSION[$this->platform_code]['zwcmopenid'] = $this->platform_code . $t_uid_openid;
            $openidArr['openid'] = $t_uid_openid;
            $openidArr['zwcmopenid'] = $this->platform_code . $t_uid_openid;

        } elseif ($this->check_mode == 1) {
            //if ($_SESSION[$this->platform_code]['ischecked'] != true) {
                $t_uid = $this->getTUid();
                //$openid = $this->checkBindCode($t_uid);
                $openid = $this->getOpenidByDecrypt($t_uid);
                $_SESSION[$this->platform_code]['ischecked'] = true;
                $openidArr['openid'] = $openid;
            //}
             
        } elseif (($this->check_mode == 2)) {
            /**
             * 单独处理的平台列表
             * MSYHCCJ会返回openid和zwcmopenid,其他不会
             * ZHCJ招行抽奖
             * MSYHCCJ民生 银行抽奖
             */
            if (method_exists($this, $this->platform_code)) {

                $openidArr = call_user_func(array($this, $this->platform_code));
            }
            if (empty($_SESSION[$this->platform_code]['openid']) && empty($openidArr['openid'])) {
            //if (empty($openidArr['openid'])) {
                $time_beforeopenid = microtime(true);
                $newOpenid = $this->wxApi->getOpenid();
                //$_SESSION[$this->platform_code]['openid'] = $newOpenid;
                //会出现在很短的时间内首页会被自动连续刷新2次，而第2次页面加载的时候去微信取openid的时候验证码失效，会取不到openid,所以需要判断取到的openid是否为空
                if (!empty($newOpenid)) {
                    $this->zwcmopenidFetchedFromWeixin = true;
                    //$_SESSION[$this->platform_code]['zwcmopenid'] = $newOpenid;
                }
                $elapseTime = round(microtime(true) - $time_beforeopenid, 4);
                if ($elapseTime > 0.3) {
                    dolog('Common/debug', '获取openid超过300ms', '', 'latency(appmode=2): ' . $elapseTime, $this->redisLog);
                }

                $openidArr['openid'] = $newOpenid;
                $openidArr['zwcmopenid'] = $newOpenid;
            }

            if ((CONTROLLER_NAME !== 'Wheel') && (CONTROLLER_NAME !== 'Member') && (CONTROLLER_NAME !== 'Map') && (CONTROLLER_NAME.ACTION_NAME !== 'OrderorderDetail') && (CONTROLLER_NAME.ACTION_NAME !== 'AjaxshareLog') && (CONTROLLER_NAME.ACTION_NAME != 'Orderaddress')) {  //设置抽奖平台可以进的页面
                redirect(U('Home/Wheel/index'));
            }

        } elseif ($this->check_mode == 3) {

            if (strcasecmp($this->platform_code, 'NYYH') == 0) {

                $this->NYYH();
            } else {
                if (empty($_SESSION[$this->platform_code]['openid'])) {
                    $time_beforeopenid = microtime(true);
                    $newOpenid = $this->wxApi->getOpenid();
                    //$_SESSION[$this->platform_code]['openid'] = $newOpenid;
                    $openidArr['openid'] = $newOpenid;
                    if (!empty($newOpenid)) {
                        $this->zwcmopenidFetchedFromWeixin = true;
                        //$_SESSION[$this->platform_code]['zwcmopenid'] = $newOpenid;
                        $openidArr['zwcmopenid'] = $newOpenid;
                    }
                    $elapseTime = round(microtime(true) - $time_beforeopenid, 4);
                    if ($elapseTime > 0.3) {
                        dolog('Common/debug', '取zwopenid超过300ms', '', 'latency(appmode=3): ' . $elapseTime, $this->redisLog);
                    }
                }
            }
        } elseif ($this->check_mode == 4) {
           // if (CONTROLLER_NAME == 'Index') {
           // 注释待定
            $openidArr = $this->appHandle($this->platform_code);
            //}

            $this->getMsyhcParam($this->platform_code);
        } elseif ($this->check_mode == 5) {
            //不做任何操作,农行动账
        } elseif ($this->check_mode == 6) {

            if (method_exists($this, $this->platform_code)) {

                $openidArr['openid'] = call_user_func(array($this, $this->platform_code));
            }
            // if (!isset($_SESSION[$this->platform_code]['zwcmopenid'])) {

            //     $openidArr['zwcmopenid'] = $this->wxApi->getOpenid();
            // }

        } else {
            doLog('Common/error', '模式设置不正确', '', json_encode($_SERVER['REQUEST_URI']), $this->redisLog);
            $this->error('模式设置不正确', C('WEB_URL') . C('ERROR_PAGE'));
        }
        
        return $openidArr;
    }
    
    /**
     * [ZHCJ 招行抽奖特殊 ]
     * @ckhero
     * @DateTime 2017-03-24
     */
    private function ZHCJ()
    {
        
        if (!isset($_SESSION['ZHCJ']['ZhcjOpenid']) || empty($_SESSION['ZHCJ']['ZhcjOpenid']) || !isset($_SESSION['ZHCJ']['ZHCJ_openid']) || empty($_SESSION['ZHCJ']['ZHCJ_openid'])) {
            $t_uid = I('param.openid', '');
            if ($t_uid == '') { //从乐天邦过来的，从session里面取
                $zh_openid = $_SESSION['ZHWZ']['openid'];
                $_SESSION['ZHCJ']['ZhcjOpenid'] = $zh_openid;
            } else {

                //$this->getZhcjOpenid($t_uid);
                $openid = $this->getOpenidByDecrypt($t_uid);
                $_SESSION['ZHCJ']['ZhcjOpenid'] = $openid;
                $_SESSION[$this->platform_code]['ZHCJ_openid'] = $openid;
            }
        }

        // if ($_SESSION[$this->platform_code]['ischecked'] != true) {
        //     //$openid = $this->checkBindCode($t_uid);
        //     $openid = $this->getOpenidByDecrypt($t_uid);
        //     $_SESSION[$this->platform_code]['ZHCJ_openid'] = $openid;
        //     $_SESSION[$this->platform_code]['ischecked'] = true;
        // } 
    }
    /**
     * [MSYHCCJ 民生银行抽奖特殊处理]
     * @ckhero
     * @DateTime 2017-03-24
     */
    private function MSYHCCJ()
    {

        if (isset($_SESSION['MSYHC']['tel'])) {
            $_SESSION[$this->platform_code]['tel'] = $_SESSION['MSYHC']['tel'];
            $MSYHCCJ_openid = $_SESSION['MSYHC']['openid'];
        } else {
            $this->getMsyhcParam($this->platform_code);
            $MSYHCCJ_openid = cookie('MSYHCCJ_openid');
        }
       // $_SESSION[$this->platform_code]['openid'] = $MSYHCCJ_openid;
        //$_SESSION[$this->platform_code]['zwcmopenid'] = $MSYHCCJ_openid;
        $openidArr['openid'] = $MSYHCCJ_openid;
        $openidArr['zwcmopenid'] = $MSYHCCJ_openid;
        return $openidArr;
    }
    
    /**
     * [NYYH 农业银行]
     * @ckhero
     * @DateTime 2017-03-24
     */
    private function NYYH()
    {

        $time_0 = microtime(true);
        $result = json_decode(send_curl($this->platform_config['INTERFACE_URL'] . $_SESSION[$this->platform_code]['openid']), true);
        $latency = round(microtime(true) - $time_0, 4);
        if ($latency > 0.3) {
            dolog('Common/debug', '记录农行时延大于300ms', '', 'latency: ' . $latency, $this->redisLog);
        }

        if (intval($result['code']) != 1) {
            redirect($this->platform_config['REDIRECT_URL']);
        } else {

        }
    }

    /**
     * [GFZQ 广发证券通过ticket获取openid]
     * @ckhero
     * @DateTime 2017-03-27
     */
    public function GFZQ()
    {

        $ticket = I('get.ticket');
        if ($ticket) {

           $url = DEBUG_ON_LOCALHOST_OR_201TESTINGSERVER ? 'http://twx.gf.com.cn': 'http://wechat.gf.com.cn';
           $url .= '/v1/api/ticket/user?ticket='.$ticket; 
           $res = send_curl($url);
           $res = json_decode($res, true);
           if ($res['openid']) {
            
            if ($res['subscribe'] != 1) {

                redirect($this->platform_config['REDIRECT_URL']);
            }
            doLog('Common/GFZQ_ok', '广丰openid获取成功', '', json_encode($res), $this->redisLog);
            return $res['openid'];
           } else {
            
            return false;
            doLog('Common/GFZQ_fail', '广丰openid获取失败', '', json_encode($res), $this->redisLog);
           }
        }
    }
    
    /**
     * [getOpenidByDecrypt 通过解密方式获得商户传过来的openid]
     * @ckhero
     * @DateTime 2017-03-28
     * @return   [type]     [description]
     */
    private function getOpenidByDecrypt($t_uid)
    {

        if (strcasecmp($this->platform_code, 'ZHWZ') == 0 || strcasecmp($this->platform_code, 'ZHCJ') == 0) {

            $aes = new Aes();
            $key = DEBUG_ON_LOCALHOST_OR_201TESTINGSERVER? '7/jZgyyvZdwO06bbuZ5cwQ==': 'BCIYxaR6PQM5Z1DeMxUgAg==';
            $key = base64_decode(utf8_encode($key));
            // if (DEBUG_ON_LOCALHOST_OR_201TESTINGSERVER == FALSE)
            //     ////正式环境的KEY
            //     $key = base64_decode(utf8_encode('BCIYxaR6PQM5Z1DeMxUgAg=='));
            // else
            //     ////测试环境
            //     $key = base64_decode(utf8_encode('7/jZgyyvZdwO06bbuZ5cwQ=='));

            $aes->set_key($key);
            $aes->require_pkcs();
            $openid = $aes->decrypt(utf8_encode($t_uid));
        } else {

            $openid = decrypt(base64_decode($t_uid), $this->platform_config['DES_KEY']);

        }

        return $openid;
    }
    
    /**
     * [HJSHCJ 获取花集生活openid用户验证]
     * @ckhero
     * @DateTime 2017-03-30
     */
    private function HJSHCJ()
    {
        
        $t_uid = $this->getTUid();
        $_SESSION[$this->platform_code]['HJSHCJ_openid'] = $this->getOpenidByDecrypt($t_uid);
    }
}
