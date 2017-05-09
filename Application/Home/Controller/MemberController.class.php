<?php
namespace Home\Controller;

use Think\Controller;
use Common\Verify\TelVerify;
class MemberController extends CommonController
{
    //private platform_code = null; //平台代码, 即tb_platform表格中的code字段
    //private user_id = null; //用户ID, 即tb_pt_user表格里面的id字段
    
    function __construct()
    {
        parent::__construct();
        $this->oneCoin = "一元购优惠券发放";
        $this->checkTelList = array('LETIANBANG'); // 购买商品时，必须先输入手机号码才能购买的平台清单
    }
    
    // 用户登陆
    public function login()
    {
        $tel = I('param.tel', 0);
        // 如果是民生银行的用户登录
        if ($this->platform_code == 'MSYH') {
            $return = $this->msyhLogin($tel);
            exit($return);
        }
        if ($this->redis == null) {
            $this->redis = new \vendor\Redis\DefaultRedis();
        }
        $this->redis->databaseSelect('zwid');
        
        if ($tel) {
            if (preg_match('/^1[34578][0-9]{9}$/', $tel)) {
                //进行短信验证码验证
                //需要验证的平台和 号码使用次数超过限制的，（次数包括验证过的和没有验证过的）
                $platform_id = $this->platform_id;
                $isNeedCheckTel = D('PtUser')->checkTelNum($platform_id, $tel, false);
                if ($this->platform_config['isTel'] || !$isNeedCheckTel) {

                    $verifyCode = I('param.code');
                    $telVerify = new TelVerify(array('tel'=>$tel));
                    $verifyRes = $telVerify->checkNew($verifyCode);
                    if($verifyRes['state'] != 1) {
                        
                        if (!$isNeedCheckTel) {

                            $verifyRes['status'] = 1;  //需要进行登录验证
                        }
                        exit(json_encode($verifyRes));
                    }
                    //判断当前号码是否绑定过多
                    $isTelOk = D('PtUser')->checkTelNum($platform_id, $tel);
                    if (!$isTelOk) {

                        $dataJson = array(
                            'state' => 0,
                            'msg' => L('_TEL_USED_EXCESSIVE_'),
                        );

                        if (!$isNeedCheckTel) {

                            $dataJson['status'] = 1;  //需要进行登录验证
                        }
                        exit(json_encode($dataJson));
                    }
                    D('PtUser')->updateTelCheckStatus($this->user_id);
                    //M('pt_user_extra')->execute("insert into tb_pt_user_extra (`uid`, `is_check_tel`) values (".$this->user_id.",1) ON DUPLICATE KEY UPDATE is_check_tel = 1");

                }
                $session = $_SESSION[$_SESSION['PLATFORM_CODE']];
                $UserModel = D('pt_user');
                $platform = M('platform');
                $sql['a.code'] = $this->platform_code;
                $platform_code = $platform->alias('a')
                    ->field('b.code,b.id')
                    ->join('left join tb_platform b on a.customer_id = b.customer_id')
                    ->where("a.code='%s'", $sql['a.code'])
                    ->select();
                $where = '(';
                foreach ($platform_code as $k => $v) {
                    if ($session['zwcmopenid']) {
                        // 若存在zwcmopenid则使用zwcmopenid作为条件
                        $openid = $UserModel->field('openid')
                            ->where("zwcmopenid='%s' and ptid=%d", array(
                            $session['zwcmopenid'],
                            $v['id']
                        ))
                            ->find();
                    } else {
                        // 使用openid作为搜索条件
                        $openid = $UserModel->field('openid')
                            ->where("openid='%s' and ptid=%d", array(
                            $session['openid'],
                            $v['id']
                        ))
                            ->find();
                    }
                    if (online_redis_server == true) {
                        $this->redis->hset('openid_platcode:' . $openid['openid'] . '_' . $v['id'], $tel, 'telephone');
                        $this->redis->expire('openid_platcode:' . $openid['openid'] . '_' . $v['id'], 24 * 3600);
                    }
                    // 将发送给招行的手机信息加密后拼装成url存入redis缓存中 2016-12-12 YYQ
                    if (in_array($this->platform_code,C('LOGIN_SEND_TOZH_SPECIAL_PLATFORM_CODE_ARR'))) {
                        if (DEBUG_ON_LOCALHOST_OR_201TESTINGSERVER == FALSE) {
                            $telSendToZHUrl = "https://pointbonus.cmbchina.com/IMSPActivities/checkIn/savePhone"; // 正式服务器手机号发送地址(招行)
                        } else {
                            $telSendToZHUrl = C($this->platform_code.'_SEND_TEL_TESTURL'); // 测试服务器手机号发送地址(招行)
                        }
                        if ( strcasecmp($this->platform_code, "ZHCJ") == 0 ){ //&& isset($_SESSION['ZHCJ']['ZhcjOpenid'] 
                            $telSendToZH['openid'] = base64_encode($_SESSION[$this->platform_code][C('CHANNEL_OPENID_KEY')]);
                            $tmp_openid = $_SESSION[$this->platform_code][C('CHANNEL_OPENID_KEY')];
                        }
                        else{
                            $telSendToZH['openid'] = base64_encode($session['openid']);
                            $tmp_openid = $session['openid'];
                        }
                        $telSendToZH['phone'] = base64_encode($tel);
                        $telSendToZHUrl .= '?' . http_build_query($telSendToZH);
                        if ( !empty($telSendToZH['openid']) ){  //防止招行传不正确的openid过来，然后$_SESSION['ZHCJ']['ZhcjOpenid']为空的情况
                            $this->redisLog->set('telSendToZHUrl_' . $session['openid'] . '_' . $this->platform_code, $telSendToZHUrl, 24 * 60 * 60);
                            doLog('Member/cmbPhoneOpenidPair', $tmp_openid, '', $tel, $this->redisLog); //因为招行经常跟我们要这个数据，所以在日志中记录, 2017-02-21
                            unset($tmp_openid);
                        }
                    }
                    $_SESSION[$v['code']]['tel'] = $tel;
                    $where .= "ptid = '" . $v['id'] . "' OR ";
                }
                $data = array(
                    'telephone' => $tel
                );
                $where = substr($where, 0, - 4);
                if ($session['zwcmopenid']) {
                    $where .= "  ) AND zwcmopenid = '%s'";
                    $UserModel->where($where, $session['zwcmopenid'])
                        ->data($data)
                        ->save();
                } else {
                    $where .= ") AND openid = '%s'";
                    $UserModel->where($where, $session['openid'])
                        ->data($data)
                        ->save();
                }
                
                doLog('Member/login', "会员登录-成功", '', 'verifytelno=' . $this->platform_config['isTel'] . ', tel=' . $tel, $this->redisLog);
                $dataJson = array(
                    'state' => 1,
                    'msg' => '恭喜您，登录成功。'
                );
                exit(json_encode($dataJson));
            } else {
            	doLog('Member/login/failed', "会员登录-失败(手机号码格式错误)", '', 'verifytelno=' . $this->platform_config['isTel'], $this->redisLog);
                $dataJson = array(
                    'state' => 0,
                    'msg' => '对不起，您的手机号码格式不对！'
                );
                exit(json_encode($dataJson));
            }
        }
        doLog('Member/login/failed', "会员登录-失败(手机号码为空)", '', 'verifytelno=' . $this->platform_config['isTel'], $this->redisLog);
        $dataJson = array(
            'state' => 0,
            'msg' => '对不起，登录失败，请稍候再试。'
        );
        exit(json_encode($dataJson));
    }
    
    // 用户退出
    public function logout()
    {
        doLog('Member/logout', "会员退出", '', '', $this->redisLog);
        unset($_SESSION[$_SESSION['PLATFORM_CODE']]['tel']);
        $dataJson = array(
            'state' => 1,
            'msg' => '您退出成功'
        );
        exit(json_encode($dataJson));
    }
    
    // 设置所在城市
    /* //邵晓凌： 2017-03-29 注释
    public function setCity()
    {
        $city_name = I('param.city_name', '上海');
        $city_id = I('param.city_id', '37');
        $_SESSION[$_SESSION['PLATFORM_CODE']]['city'] = $city_name;
        $_SESSION[$_SESSION['PLATFORM_CODE']]['city_id'] = $city_id;
        if (empty($_SESSION[$_SESSION['PLATFORM_CODE']]['city'])) {
            $dataJson = array(
                'state' => 0,
                'msg' => '设置失败'
            );
            exit(json_encode($dataJson));
        }
        doLog('Member/setCity', "设置城市", '', '', $this->redisLog);
        $dataJson = array(
            'state' => 1,
            'msg' => '设置成功'
        );
        exit(json_encode($dataJson));
    }*/
    
    // 收藏列表
    public function collect()
    {
        $method = I('param.method', '');
        $cur_page = I('param.page', 1, 'intval');
        $page_size = 6;
        $start = ($cur_page - 1) * $page_size;
        $is_login = $this->checkLogin();
        $PlatformProductModel = D('PlatformProduct');
        if (! $is_login) {
            $product_list = NULL;
            if ($method == 'ajax') {
                $json_data = array(
                    'state' => 0,
                    'msg' => '没有内容可加载了！'
                );
                exit(json_encode($json_data));
            }
        } else {
            $where = "ptuserid = " . $this->user_id;
            $product_list = getProductByCollect($where, $start, $page_size);
            $product_list_id = array();
            foreach ($product_list as $key => $items) {
                $product_list[$key]['url'] = U('Home/Product/view/id/' . $items['id']);
                $product_list[$key]['product_exist'] = $PlatformProductModel->checkProductExist($items['id']);
                $product_list_id[$key] = $items['id'];
            }
            if ($method == 'ajax') {
                if ($product_list) {
                    doLog('Member/collectmore', "收藏加载更多", '', json_encode($product_list_id), $this->redisLog);
                    $json_data = array(
                        'state' => 1,
                        'msg' => '加载完成',
                        'list_data' => $product_list
                    );
                    exit(json_encode($json_data));
                } else {
                    doLog('Member/collectall', "收藏加载更多-全部加载", '', '', $this->redisLog);
                    $json_data = array(
                        'state' => 0,
                        'msg' => '没有内容可加载了！'
                    );
                    exit(json_encode($json_data));
                }
            }
        }
        doLog('Member/collect', "收藏列表", '', '', $this->redisLog);
        $this->assign('product_list', $product_list);
        $this->display();
    }
    
    // 商品加入收藏
    public function doCollect()
    {        
        $is_login = $this->checkLogin();
        if(!$is_login && $this->check_mode == 4) {
            
            $dataJson = array(
                    'state' => 4,
                    'msg' => '对不起，请先登录.'
                );
            $this->ajaxReturn($dataJson);
        }
        $platform_id = I('param.platform_id', 0, 'intval');
        if ($platform_id == 0)
            $platform_id = $this->platform_id; //$_SESSION[$_SESSION['PLATFORM_CODE']]['platform_id'];
        $product_id = I('param.product_id', 0, 'intval');
        $CollectModel = D('UserCollection');
        $collect_info = $CollectModel->where("ptuserid=%d and productid=%d", array($this->user_id, $product_id))->find();
        if ($collect_info) { // 假若已经存在，则表明是取消收藏操作
            $result = $CollectModel->where("ptuserid=%d and productid=%d", array($this->user_id, $product_id))
                ->limit(1)
                ->delete();
            if ($result >= 1) {
                $dataJson = array(
                    'state' => 3,
                    'msg' => '您已经取消收藏了！'
                );
                $product_point_key = "all_product_point";
                if($this->redisLog->existsRedis($product_point_key)){
                    $productallpoint = $this->redisLog->getArr($product_point_key);
                    $point = $productallpoint[$product_id];
                    if($point > 0 ){
                        $productallpoint[$product_id] = $point-1;
                        $this->redisLog->setArr($product_point_key, $productallpoint);
                    }         
                }
                $product_is_collect_key = "user_product_is_collect" . $this->user_id . intval($this->platform_id);
                if($this->redisLog->existsRedis($product_is_collect_key)){
                    $productcollect = $this->redisLog->getArr($product_is_collect_key);
                    $productcollect[$product_id] = 0;
                    $this->redisLog->setArr($product_is_collect_key, $productcollect);
                }

                exit(json_encode($dataJson));
            } else {
                $dataJson = array(
                    'state' => 2,
                    'msg' => '对不起，取消收藏失败！请稍后再试.'
                );
                exit(json_encode($dataJson));
            }
        }
        $platform_info = getPlatformInfo(array(
            'id' => $platform_id
        ));
        $product_info = getProductInfo($product_id, $this->platform_id);
        $data = array();
        $data['ptuserid'] = $this->user_id;
        $data['customerid'] = $platform_info['customer_id'];
        $data['platformid'] = $platform_info['id'];
        $data['productid'] = $product_info['id'];
        $data['template'] = $platform_info['templet'];
        $data['productname'] = $product_info['name'];
        $data['productstatus'] = $product_info['status'];
        $data['collectiontime'] = date('Y-m-d H:i:s');
        $data['tel'] = $_SESSION[$this->platform_code]['tel'];
        $state = $CollectModel->data($data)->add();
        if ($state) {
            doLog('Member/doCollect/loganalysis', "商品加入收藏-成功", $product_id, '', $this->redisLog);
            log_user_category_score($this->zwid,$product_info['category_id'],C('COLLECT_ACTION'),$this->redisLog);
            $dataJson = array(
                'state' => 1,
                'msg' => '恭喜您，收藏成功！'
            );
            $product_point_key = "all_product_point";
            if($this->redisLog->existsRedis($product_point_key)){
                $productallpoint = $this->redisLog->getArr($product_point_key);
                if ( array_key_exists($product_id, $productallpoint) ){
                    $productallpoint[$product_id] += 1;
                }else{
                    $productallpoint[$product_id] = 1;
                }  
                $this->redisLog->setArr($product_point_key, $productallpoint);              
                
            }
            $product_is_collect_key = "user_product_is_collect" . $this->user_id . intval($this->platform_id);
            if($this->redisLog->existsRedis($product_is_collect_key)){
                $productcollect = $this->redisLog->getArr($product_is_collect_key);
                $productcollect[$product_id] = 1;
                $this->redisLog->setArr($product_is_collect_key, $productcollect);
            }
                
            exit(json_encode($dataJson));
        } else {
            doLog('Member/collectfailed', "商品加入收藏-失败", $product_id, '', $this->redisLog);
            $dataJson = array(
                'state' => 0,
                'msg' => '对不起，收藏失败！'
            );
            exit(json_encode($dataJson));
        }
    }
    
    // 订单列表
    public function order()
    {
        $method = I('param.method', '');
        $cur_page = I('param.page', 1, 'intval');
        $page_size = 6;
        $is_login = $this->checkLogin();
        
        if (! $is_login) {
            $product_list = null;
            if ($method == 'ajax') {
                $json_data = array(
                    'state' => 0,
                    'msg' => '没有内容可加载了！'
                );
                exit(json_encode($json_data));
            }
        } else {
            $start = ($cur_page - 1) * $page_size;
            $user_id = $this->user_id;
            $product_list = getProductByOrder($user_id, $start, $page_size);
            $PlatformProductModel = D('PlatformProduct');
            $product_list_id = array();
            foreach ($product_list as $key => $items) {
                if ($items['pay_status'] == 2) {
                    $product_list[$key]['url'] = U('Home/Product/view/id/' . $items['id']);
                } else {
                    $product_list[$key]['url'] = U('Home/Order/pay/id/' . $items['orderid']);
                }
                $product_list[$key]['pay_status_desc'] = $items['pay_status'] == 2 ? "" : "（未付款）";
                $product_list[$key]['start_str'] = date('Y年m月d日', strtotime($items['start']));
                $product_list[$key]['end_str'] = date('Y年m月d日', strtotime($items['end']));
                $product_list[$key]['product_exist'] = $PlatformProductModel->checkProductExist($items['id']);
                $product_list_id[$key] = $items['id'];
            }
            if ($method == 'ajax') {
                if ($product_list) {
                    doLog('Member/orderlistmore', "订单加载更多", '', json_encode($product_list_id), $this->redisLog);
                    $json_data = array(
                        'state' => 1,
                        'msg' => '加载完成',
                        'list_data' => $product_list
                    );
                    exit(json_encode($json_data));
                } else {
                    doLog('Member/orderlistall', "订单加载更多-全部加载", '', '', $this->redisLog);
                    $json_data = array(
                        'state' => 0,
                        'msg' => '没有内容可加载了！'
                    );
                    exit(json_encode($json_data));
                }
            }
        }
        doLog('Member/orderlist', "订单列表", '', '', $this->redisLog);
        $this->assign('product_list', $product_list);
        $this->display();
    }
    
    // 获取地址
    public function getOneAddress()
    {
        // if(in_array($_SESSION['PLATFORM_CODE'],$this->noTelList)){ //下单的时候无需验证是否登录
        if (! in_array($this->platform_code, $this->checkTelList)) { // 下单的时候无需验证是否登录
            
            $is_login = true;
        } else {
            
            $is_login = $this->checkLogin();
        }
        if ($is_login) {
            $addrModel = D('user_mailing_addr');
            $ptuserid = $this->user_id;
            $id = I('post.id', 0, 'intval');
            $result = M('order')->table("tb_order o,tb_user_mailing_addr u")
                ->field("u.*")
                ->where("o.id=%d  and o.addr_id=u.id", $id)
                ->select();
            if (empty($result)) {
                
                $result = $addrModel->query("select * from tb_user_mailing_addr where ptuserid = '{$ptuserid}' order by updatetime desc limit 1");
            }
            if (isset($result[0]) && ! empty($result[0])) {
                
                // 更新地址begin
                $orderid = I('post.id', 0, 'intval');
                $orderInfo = M('order')->where("id=%d", $orderid)->find();
                if (! empty($orderInfo)) {
                    
                    // if(in_array($_SESSION['PLATFORM_CODE'], $this->noTelList)){
                    if (! in_array($this->platform_code, $this->checkTelList)) {
                        
                        M('order')->where("id=%d", $orderid)->save(array(
                            'addr_id' => $result[0]['id'],
                            'tel' => $result[0]['tel']
                        ));
                    } else {
                        
                        M('order')->where("id=%d", $orderid)->save(array(
                            'addr_id' => $result[0]['id']
                        ));
                    }
                }
                // 更新地址end
                $dataJson = array(
                    'state' => 1,
                    'msg' => '获取成功',
                    'data' => $result[0]
                );
                exit(json_encode($dataJson));
            } else {
                $dataJson = array(
                    'state' => 0,
                    'msg' => '没有可用地址'
                );
                exit(json_encode($dataJson));
            }
        } else {
            $dataJson = array(
                'state' => - 1,
                'msg' => '对不起，请您先登录！'
            );
            exit(json_encode($dataJson));
        }
    }
    
    // 加入地址
    public function setAddress()
    {
        // if(in_array($_SESSION['PLATFORM_CODE'],$this->noTelList)){ //下单的时候无需验证是否登录
        if (! in_array($this->platform_code, $this->checkTelList)) { // 下单的时候无需验证是否登录
            $is_login = true;
        } else {
            
            $is_login = $this->checkLogin();
        }
        if ($is_login) {
            $addrModel = D('user_mailing_addr');
            $now = date('Y-m-d H:i:s');
            $userName = I('param.userName', '');
            $telNumber = I('param.telNumber', '');
            $nationalCode = I('param.nationalCode', '');
            $postalCode = I('param.postalCode', '');
            $provinceName = I('param.provinceName', '');
            $cityName = I('param.cityName', '');
            $countryName = I('param.countryName', '');
            $detailInfo = I('param.detailInfo', '');
            $order_id = I('param.orderid', 0, 'intval');
            //民生银行最多添加四个商品
            if(!is_wx()) {

                $addrList = M('user_mailing_addr')->where('ptuserid=%d', $this->user_id)->count();
                if($addrList >= 4) {

                    $dataJson = array(
                    'state' => 0,
                    'msg' => '亲爱的客户，最多能添加四条收货地址!'
                );

                exit(json_encode($dataJson));
                }
            }
            $res = $addrModel->execute("insert into tb_user_mailing_addr
                 (ptuserid,name,tel,national_code,post_code,province,city,area,address,createtime,updatetime)
                 values ('{$this->user_id}','{$userName}',
                         '{$telNumber}','{$nationalCode}',
                         '{$postalCode}','{$provinceName}',
                         '{$cityName}','{$countryName}',
                         '{$detailInfo}','{$now}','{$now}' ) ON DUPLICATE KEY UPDATE
                         national_code = '{$nationalCode}',post_code='{$postalCode}',
                         province = '{$provinceName}',city = '{$cityName}',
                         area = '{$countryName}',updatetime = '{$now}',id=LAST_INSERT_ID(id)");
            $id = $addrModel->getLastInsID();
            $addinfo = $addrModel->query("select * from tb_user_mailing_addr where id = '{$id}'");
            if (! empty($res) && ! empty($order_id)) {
                // if(in_array($_SESSION['PLATFORM_CODE'], $this->noTelList)){
                if (! in_array($this->platform_code, $this->checkTelList)) {
                    
                    $res1 = $addrModel->execute("update tb_order set addr_id = '{$id}', tel='{$telNumber}' where id = '{$order_id}'");
                } else {
                    
                    $res1 = $addrModel->execute("update tb_order set addr_id = '{$id}' where id = '{$order_id}'");
                }
            }
            if (empty($addinfo)) {
                $dataJson = array(
                    'state' => 0,
                    'msg' => '保存失败！'
                );
                exit(json_encode($dataJson));
            } else {
                $addinfo['orderid'] = $order_id;
                $dataJson = array(
                    'state' => 1,
                    'msg' => '保存成功',
                    'data' => $addinfo,
                    'id' => $id
                );
                exit(json_encode($dataJson));
            }
        } else {
            $dataJson = array(
                'state' => - 1,
                'msg' => '对不起，请您先登录！'
            );
            exit(json_encode($dataJson));
        }
    }
    
    // 加入订单
    public function doOrder()
    {
        $product_id = I('param.product_id', 0, 'intval');
        // 判断是否是中奖商品
        if ($this->platform_code == 'LETIANBANG') {
            
            $lotteryIdList = D('Product')->getProductIdLottery();
            $lotteryIdList = explode(',', $lotteryIdList);
            if (in_array($product_id, $lotteryIdList)) {
                
                $dataJson = array(
                    'state' => 0,
                    'msg' => '亲爱的客户，这是抽奖商品无法领取。'
                );
                exit(json_encode($dataJson));
            }
        }
        $ProductModel = M('Product');
        $product_info = $ProductModel->where("id=%d", $product_id)->find(); // 不能加cache
                                                                            
        // if(in_array($_SESSION['PLATFORM_CODE'],$this->noTelList) && $product_info['ifpay'] && $product_info['price'] >0 ){ //下单买的的时候无需验证是否登录
        if ((! in_array($this->platform_code, $this->checkTelList)) && $product_info['ifpay'] && $product_info['price'] > 0) { // 下单买的的时候无需验证是否登录
            $is_login = true;
        } else {
            $is_login = $this->checkLogin();
        }
        if ($this->platform_code == 'LETIANBANG' || $this->platform_code == 'MSYHC')
            $is_login = $this->checkLogin();
        $session = $_SESSION[$_SESSION['PLATFORM_CODE']];
        $quality = I('param.quality', 1, 'intval');
        $pay_price = I('param.pay_price', 0);
        $pay_type = I('param.pay_type', 0);
        $addr_id = I('param.addr_id', 0);
        $type_id = I('param.type_id', 1);
        $specification = htmlspecialchars_decode(I('param.info', null));
        if ($is_login) {
            // 检查用户是否在该平台领过该优惠券

            $is_get = $this->checkOrder($product_id, $session['id'], $session['platform_id'],$product_info['repeat_span'],$product_info['num_per_span']);

            if ($is_get['status'] == 2 && empty($pay_price)) {

                if($product_info['repeat_span'] !='-1' && $product_info['repeat_span'] <'10000') {

                    $msg = '亲爱的客户，您已经领取'.$product_info['num_per_span'].'张了，'.$product_info ['repeat_span'].'天内不能领取更多';
                }else{

                    $msg = '亲爱的客户，您已经领取'.$product_info['num_per_span'].'张了，不能领取更多';
                }
                    
                if ($this->platform_code == 'MSYH') {

                    $dataJson = array(
                        'state' => 0,
                        'msg' => $msg
                    );
                } else {
                    
                    $dataJson = array(
                        'state' => 0,
                        'msg' => $msg.'，可至【个人中心】查看。'
                    );
                }
                exit(json_encode($dataJson));
            }
            
            $PlatformProductModel = D('PlatformProduct');
            
            // 区分是否是一元购
            if ($type_id == 4) {
                
                $isExistArr = D('product')->checkProductExist($product_id, $quality);
                $isExist = $isExistArr['state'];
                if ($isExist == 2) {
                    
                    $msg = '亲爱的用户，你还能购买' . $isExistArr['quantity'] . '份，请重新选择购买数量！';
                } else {
                    
                    $msg = '曾经有个机会摆在你面前，你没抓紧！现在销售一空，你才后悔莫及~';
                }
            } else {
                // 自己的乐天邦
                if ($this->platform_code == 'LETIANBANG') {
                    
                    $isExistArr = D('product')->checkProductExist($product_id, $quality);
                } else {
                    
                    $isExistArr = $PlatformProductModel->checkProductExistNew($product_id, true, $quality); // no cache
                }
                $isExist = $isExistArr['state'];
                if ($isExist == 2) {
                    
                    $msg = '亲爱的用户，你还能购买' . $isExistArr['quantity'] . '份，请重新选择购买数量！';
                } else {
                    $msg = '亲爱的客户，此商品领取过于火爆，请稍后再试。';//亲爱的客户，库存不足，无法下单。';
                }
            }
            if ($isExist != 1) {
                doLog('Member/orderinsufficient', "用户下订单-库存不足", $product_id, '', $this->redisLog);
                $dataJson = array(
                    'state' => 0,
                    'msg' => $msg
                );
                exit(json_encode($dataJson));
            }
            
            $PlatformModel = M('Platform');
            $ProductCodeModel = M('ProductCode');
            $CustomerModel = M('Customer');
            $OrderModel = D('order');
            $platform_info = $PlatformModel->where("id=%d", $session['platform_id'])
                ->cache(true, 300)
                ->find();
            
            $start_time = strtotime($product_info['start']);
            $end_time = strtotime($product_info['end']);
            
            if ($start_time > time() || $end_time < time()) {
                doLog('Member/orderexpired', "用户下订单-优惠券已过期", $product_id, '', $this->redisLog);
                $dataJson = array(
                    'state' => 0,
                    'msg' => '亲爱的客户，该优惠券已过期，已无法领用。'
                );
                exit(json_encode($dataJson));
            }
            $customer_info = $CustomerModel->where("id=%d", $platform_info['customer_id'])
                ->cache(true, 300)
                ->find();
            
            // 宣臻扣除积分接口
            /*
            $point_dct_reslut = null;
            if ($this->platform_code == 'XZZSH') {
                $point_dct_reslut = pointsDeduct($session['openid'], 'T99124', 88);
                if (empty($point_dct_reslut['result']) || intval($point_dct_reslut['isSucc']) != 1) {
                    doLog('Member/error', "用户下订单-宣臻接口错误", $product_id, '', $this->redisLog);
                    $dataJson = array(
                        'state' => 0,
                        'msg' => '亲爱的客户，系统繁忙请重新领取。'
                    );
                    exit(json_encode($dataJson));
                }
            }*/
            $product_code = array();
            if ($product_info['verification'] == 1) {

                $product_code = $ProductCodeModel->where("productid=%d and status=0", $product_id)
                                                 ->lock(true)
                                                 ->find();
                if (empty($product_code)) {
                    $state5 = false;
                } else {
                    $state5 = $ProductCodeModel->where("id=%d and status=0", $product_code['id'])->lock(true)->data(array('status' => 1,'updatetime' => date('Y-m-d H:i:s')))->save();
                }
            } else {
                $state5 = true;
            }
            if ($state5) {
                // 订单表里生成订单记录
                $data = array();
                $data['ordenum'] = date('YmdHis') . createNonceStr(4, '1234567890');
                $data['tel'] = $session['tel'];
                $data['ptuserid'] = $session['id'];
                if ($product_info['price'] > 0 && $product_info['ifpay'] == 1) {
                    
                    $data['pay_status'] = 1;
                } else {
                    
                    $data['pay_status'] = 0;
                }
                
                $data['pay_type'] = $pay_type;
                $data['pay_price'] = $pay_price;
                $data['pay_account'] = '';
                $data['transaction_no'] = '';
                $data['pay_time'] = '1900-01-01 00:00:00';
                $data['addr_id'] = $addr_id;
                $data['ptid'] = $platform_info['id'];
                $data['ptname'] = $platform_info['name'];
                $data['remark'] = '';
                $data['updatetime'] = date('Y-m-d H:i:s');
                $data['ordertime'] = date('Y-m-d H:i:s');
                $data['createtime'] = date('Y-m-d H:i:s');
                $data['updatetime'] = date('Y-m-d H:i:s');
                $data['traffic_code'] = $session['traffic_code'];
                $id = $OrderModel->data($data)->add();
                
                // 订单商品表里生成订单记录
                $OrderProductModel = D('OrderProduct');
                $product_data = array();
                $product_data['orderid'] = $id;
                $product_data['productid'] = $product_id;
                $product_data['productname'] = $product_info['name'];
                $product_data['producttypeid'] = $product_info['type_id'];
                $product_data['ptid'] = $platform_info['id'];
                $product_data['ptname'] = $platform_info['name'];
                // $product_data['smsreturnvalue'] = '';
                $product_data['status'] = $product_info['ifpay'];
                $product_data['quality'] = $quality;
                $product_data['productcodeid'] = $product_code['id'];
                $product_data['productcode'] = $product_code['couponcode'];
                $product_data['createtime'] = date('Y-m-d H:i:s');
                $product_data['updatetime'] = date('Y-m-d H:i:s');
                $product_data['tel'] = $session['tel'];
                $product_data['specification'] = $specification;
                $od_id = $OrderProductModel->data($product_data)->add();
                
                // 检验订单是否正常生成否则撤销操作
                if ($id && $od_id) {
                    // 若是一元购无需生成订单
                    if ($type_id != 4) {
                        
                        if (online_redis_server && $product_info['price'] > 0 && $product_info['ifpay'] == 1) {
                            
                            $this->redis->databaseSelect('order');
                            $this->redis->set('waitPayPtid' . $session['platform_id'] . 'Productid' . $product_id . 'Tradesn:' . $data['ordenum'], $quality, 15 * 60); // 锁定购买份数
                        } else {
                            $PlatformProductModel->where("platform_id=%d and product_id=%d and total>0", array(
                                $session['platform_id'],
                                $product_id
                            ))->setInc('salequantity', $quality);
                        }
                    }
                    
                    if (online_redis_server && $product_info['price'] > 0 && $product_info['ifpay'] == 1) { // 需要支付的通过redis来锁定
                        
                        $this->redis->databaseSelect('order');
                        if ($type_id == 4) {
                            
                            $this->redis->set('waitPayProductid' . $product_id . 'Tradesn:' . $data['ordenum'], $quality, 15 * 60); // 锁定购买份数
                        }
                    } else {
                        $ProductModel->where("id=%d and total>0", $product_id)->setInc('saleallquantity', $quality);
                        setProductDownloadOrPurchaseNuminRedis($product_id, $this->redisLog);
                    }
                    
                    // 宣臻发货通知接口-成功
                    /*
                    if ($this->platform_code == 'XZZSH') {
                        if (! empty($point_dct_reslut)) {
                            deliverNotice($point_dct_reslut['result']);
                        }
                    }*/
                } else {
                    if ($id) {
                        $OrderModel->where("id=%d", $id)->delete();
                    }
                    if ($od_id) {
                        $OrderProductModel->where("id=%d", $od_id)->delete();
                    }
                    if ($product_info['verification'] == 1 && $state5) {
                        $ProductCodeModel->where("id=%d and status=1", $product_code['id'])
                            ->lock(true)
                            ->data(array(
                            'status' => 0,
                            'updatetime' => date('Y-m-d H:i:s')
                        ))
                            ->save();
                    }
                    // 宣臻发货通知接口-失败
                    /*
                    if ($this->platform_code == 'XZZSH') {
                        if (! empty($point_dct_reslut)) {
                            deliverNotice($point_dct_reslut['result'], "SMSFail");
                        }
                    }*/
                    doLog('Member/error', "用户下订单-系统繁忙", $product_id, '', $this->redisLog);
                    $dataJson = array(
                        'state' => 0,
                        'msg' => '对不起，系统繁忙，稍后请重试一次。'
                    );
                    exit(json_encode($dataJson));
                }
                
                if (! empty($pay_price)) { // 需要付钱的商品 在支付完成之后 发短信
                    $dataJson = array(
                        'state' => 2,
                        'msg' => '下单成功请及时支付。',
                        'order_id' => $id
                    );
                    exit(json_encode($dataJson));
                }
                
                //如果需要通知优惠券商家， 则把手机号码传给他们
                //暂时没有商家需要此功能，等到有商家需要的时候再加回去。邵晓凌：2017-02-17
                /*
                if ($product_info['notify_merchant'] == 1){
                    $this->sendPhoneNumToMerchant($product_info['notify_merchant_url'], $session['tel'], $product_id);
                }
                */
                
                // 发送短信，并将返回值保存到订单表
                $return_sms = NULL;
                if ($product_info['issms'] == 1) {
                    $msg = str_replace('{channel}', $customer_info['name'], $product_info['smstpl']);
                    $msg = str_replace('{couponcode}', $product_code['couponcode'], $msg);
                    if (strpos($msg, '{phone}') !== false) {     //如果商家要求传手机号码
                        $msg = str_replace('{phone}', urlencode(base64_encode($session['tel'])), $msg);
                    }
                    $return_sms = send_msg($session['tel'], $msg);
                    // $return_sms = null;
                    $return['msg'] = $return_sms;
                    $coupon['time'] = round(microtime(true) - $t1, 3); // 统计时间;
                    $coupon['code'] = $product_data['productcode'];
                    
                    $coupon['time'] = round(microtime(true) - $t1, 3);
                    
                    doLog('Member/ordersuccessful', "用户下订单-成功", $product_id, json_encode($coupon), $this->redisLog);
                    
                    doLog('Member/doOrder/loganalysis', "用户下订单-短信返回结果", $product_id, json_encode($return), $this->redisLog);
                    //领取时计算用户和分类分数
                    log_user_category_score($this->zwid,$product_info['category_id'],C('DOWNLOAD_ACTION'),$this->redisLog);
                    // 动账抽奖不需要日志分析
                    //if ($_SESSION['PLATFORM_CODE'] != 'NYYHD') {
                    //    doLog('Member/doOrder/loganalysis', "用户下订单-短信返回结果", $product_id, json_encode($return), $this->redisLog);
                    //} else {
                    //    doLog('Member/doOrder', "用户下订单-短信返回结果", $product_id, json_encode($return), $this->redisLog);
                    //}
                    if ($this->platform_code == 'NYYHD') {
                        // 农行动账抽奖,特殊处理
                        $nh_url = U('Nyyh/returnResult');
                        $dataJson = array(
                            'state' => 1,
                            'msg' => '亲爱的客户，您已成功领取奖品，5秒后自动跳转',
                            'return_url' => $nh_url
                        );
                    } elseif ($this->platform_code == 'MSYH') {
                        $dataJson = array(
                            'state' => 1,
                            'msg' => '您已领取成功啦，稍后会有短信通知。'
                        );
                    } elseif ($this->platform_code == 'NYYH') {
                        $dataJson = array(
                            'state' => 1,
                            'msg' => '您已领取成功啦，稍后会有短信通知，也可至【粉丝福利】查看详情。'
                        );
                    } else {
                        $dataJson = array(
                            'state' => 1,
                            'msg' => '您已领取成功啦，稍后会有短信通知，也可至【个人中心】查看详情。'
                        );
                    }

                } else {
                    // 是否调用第三方接口，默认正常提示，1发送短信，2跳转链接
                    if ($product_info['extersysdock'] == 1) {
                        $url = $product_info['interfaceaddr'];
                        $post_data = array();
                        $key = 'zcnmedia';
                        $post_data['uId'] = encrypt($key, $session['tel']);
                        $post_data['merchantId'] = 'XHS';
                        $post_data['timestamp'] = time();
                        $param = http_build_query($post_data);
                        $url = $url . "?" . $param;
                        
                        //if (strpos($url, '{PHONE}') !== false) {
                        //    $url = str_replace('{phone}', urlencode(base64_encode($session['tel'])), $url);
                        //}
                        
                        $return_data = send_curl($url);
                        $return['msg'] = $return_data;
                        doLog('Member/externallink', "用户下订单-第三方接口", $product_id, json_encode($return), $this->redisLog);
                        $json_data = json_decode($return_data, true);
                        $return_sms = $json_data['status'] . ':' . $json_data['returnMessage'] . ':' . $json_data['returnCode'];
                        
                        // 针对第三方接口返回值，进行提示
                        if ($json_data['status'] != 1 || $json_data['returnCode'] != '0000') {
                            if ($id) {
                                $OrderModel->where("id=%d", $id)->delete();
                            }
                            if ($od_id) {
                                $OrderProductModel->where("id=%d", $od_id)->delete();
                            }
                            if ($product_info['verification'] == 1 && $state5) {
                                $ProductCodeModel->where("id=%d and status=1", $product_code['id'])
                                    ->lock(true)
                                    ->data(array(
                                    'status' => 0,
                                    'updatetime' => date('Y-m-d H:i:s')
                                ))
                                    ->save();
                            }
                            doLog('Member/orderfailed', "用户下订单-失败", $product_id, '', $this->redisLog);
                            $dataJson = array(
                                'state' => 0,
                                'msg' => '' . $json_data['returnMessage']
                            );
                        } else {
                            doLog('Member/doOrder/loganalysis', "用户下订单-成功", $product_id, '', $this->redisLog);
                            //领取时计算用户和分类分数
                            log_user_category_score($this->zwid,$product_info['category_id'],C('DOWNLOAD_ACTION'),$this->redisLog);
                            if ($this->platform_code == 'MSYH') {
                                $dataJson = array(
                                    'state' => 1,
                                    'msg' => '您已领取成功啦，稍后会有短信通知。'
                                );
                            } elseif ($this->platform_code == 'NYYH') {
                                $dataJson = array(
                                    'state' => 1,
                                    'msg' => '您已领取成功啦，稍后会有短信通知，也可至【粉丝福利】查看详情。'
                                );
                            } else {
                                $dataJson = array(
                                    'state' => 1,
                                    'msg' => '您已领取成功啦，稍后会有短信通知。也可至【我的订单】处查看详情。'
                                );
                            }
                        }
                    } elseif ($product_info['extersysdock'] == 2) {
                        
                        doLog('Member/doOrder/loganalysis', "用户下订单-成功", $product_id, '', $this->redisLog);
                        //领取时计算用户和分类分数
                        log_user_category_score($this->zwid,$product_info['category_id'],C('DOWNLOAD_ACTION'),$this->redisLog);
                        $dataJson = array(
                            'state' => 1,
                            'msg' => '尊敬的客户您好，请在弹出的页面上，填写预约试驾信息并提交，试驾成功后才能领取礼品',
                            'return_url' => $product_info['interfaceaddr']
                        );
                    } else {
                        doLog('Member/doOrder/loganalysis', "用户下订单-成功", $product_id, '', $this->redisLog);
                        //领取时计算用户和分类分数
                        log_user_category_score($this->zwid,$product_info['category_id'],C('DOWNLOAD_ACTION'),$this->redisLog);
                        if ($this->platform_code == 'MSYH') {
                            $dataJson = array(
                                'state' => 1,
                                'msg' => '您已领取成功啦，稍后会有短信通知。'
                            );
                        } elseif ($this->platform_code == 'NYYH') {
                            $dataJson = array(
                                'state' => 1,
                                'msg' => '您已领取成功啦，稍后会有短信通知，也可至【粉丝福利】查看详情。'
                            );
                        } else {
                            $dataJson = array(
                                'state' => 1,
                                'msg' => '您已领取成功啦，稍后会有短信通知。也可至【我的订单】处查看详情。'
                            );
                        }
                    }
                }
                

                if ($product_info['verification']==4) {   //返回二维码

                    $dataJson['state'] = 2;
                    $dataJson['qrcode_url'] = $product_info['qrcode_url'];
                } elseif ($product_info['verification']==5) {
                    
                    $dataJson['return_url'] = $product_info['interfaceaddr']; 
                    $dataJson['msg'] = '正在进入购买页面~'; 
                } 

                $dataJson['orderId'] = $id;
                $OrderProductModel->where("id=%d", $od_id)
                    ->data(array(
                    'smsreturnvalue' => $return_sms
                ))
                    ->save();

                if($this->platform_code == 'MSYHC' && $dataJson['return_url']) {

                    $dataJson['return_url'] = changeHttpsToHttp($dataJson['return_url']);
                }
                exit(json_encode($dataJson));
            } else {
                doLog('Member/error', "用户下订单-系统繁忙", $product_id, '', $this->redisLog);
                $dataJson = array(
                    'state' => 0,
                    'msg' => '对不起，系统繁忙，稍后请重试一次。'
                );
                exit(json_encode($dataJson));
            }
        } else {
            $dataJson = array(
                'state' => - 1,
                'msg' => '对不起，请您先登录！'
            );
            exit(json_encode($dataJson));
        }
    }

    //把用户手机号码传给商家，以便商家为此用户产生一个账号。目前还没有商家使用。邵晓凌：2017-02-20
    //1. 在数据库对于优惠券的triggerevent字段中，格式为 www.whateverdomain.com/... &phone={PHONE}&...
    //手机号码在base64加密后传给商家
    //2. 如果在数据库对于优惠券的triggerevent字段中没有{PHONE}字符串，则把手机号码的明码以POST数据传过去
    /*private function sendPhoneNumToMerchant($url, $phoneNum, $product_id){

        if (strpos($url, '{PHONE}') !== false) {
            $url = str_replace('{PHONE}', urlencode(base64_encode($phoneNum)),  $url);
            $result = send_curl($url, '', 'GET', 'utf-8', 2); // 2秒超时
            if ($result == false){
                $result = send_curl($url, '', 'GET', 'utf-8', 1); 
            }
        } else{
            $data['phone'] =  $phoneNum;
            $result = send_curl($url, json_encode($data), 'POST', 'utf-8', 2); // 2秒超时
            if ($result == false){
                $result = send_curl($url, json_encode($data), 'POST', 'utf-8', 1); 
            }
        }
        doLog('Memeber/sendPhoneNumToMerchant', '商家生成账户的返回结果', $product_id, 'phone='. $phoneNum . ', result=' . substr($result, 0, 32) . ', url=' . $url, $this->redisLog);
        //如果失败了，怎么办？
        return $result;    
    }*/
    /**
     * [checkOrder 检查商品是否用此号码领取过]
     * @ckhero
     * @DateTime 2017-01-16
     * @param    [type]     $product_id   [商品id]
     * @param    [type]     $uid          [用户id]
     * @param    [type]     $pid          [平台id]
     * @param    integer    $repeat_span  [领取间隔]
     * @param    integer    $num_per_span [领取间隔内可以领取的次数]
     * @return   [type]                   [status为1是可以领取 2 为不能领取]
     */
    function checkOrder($product_id, $uid, $pid ,$repeat_span = 10000 ,$num_per_span =1) {     //默认为终生只能领取一次
        
        $resFail = array(
            'status' => 2,
            'msg' => '无法领取'
        );
        $resSucc = array(
            'status' => 1,
            'msg' => '可以领取'
        );

         //可以无限领取
        if($repeat_span == -1) { 

            return $resSucc;
        }
        
        if($num_per_span<1 || $repeat_span <1){ //传进来的值有问题不允许领取

            return $resFail;
        }
        $OrderModel = D('Order');
        //搜索是否存在该用户在该平台有该商品的订单
        $createtime = date('Y-m-d H:i:s',strtotime("-$repeat_span day"));
        $sql = "SELECT o.* FROM  __ORDER__ AS o JOIN __ORDER_PRODUCT__  AS p ON p.orderid = o.id AND p.productid= '".$product_id."' AND p.ptid='".$pid."' WHERE o.ptuserid='".$uid."' and o.createtime>='$createtime'"; //邵晓凌：是否加 limit 1?     获取单位时间内领取的数量
        $product_info = $OrderModel->query($sql) ;
        if ($product_info) {

            if( count( $product_info ) < $num_per_span){
                
                return $resSucc;
            }else{

                if($num_per_span == 1){

                    $resFail['orderid'] = $product_info[0]['id'];
                }
                return $resFail;
            }

        } else {

            return $resSucc;
        }
    }

    /**
     * [doOrderOneCoin 一元购发奖品]
     * @ckhero
     * @DateTime 2016-07-14
     * 
     * @param string $product_id
     *            [优惠券id]
     * @param string $list
     *            [未中奖用户列表]
     * @return [type] [description]
     */
    public function doOrderOneCoin($product_id = '', $list = '')
    {
        $quality = 1;
        $pay_price = 0;
        $pay_type = 0;
        $addr_id = 0;
        $type_id = 1;
        $ProductModel = M('Product');
        $PlatformModel = M('Platform');
        $ProductCodeModel = M('ProductCode');
        $CustomerModel = M('Customer');
        $OrderModel = D('order');
        $product_info = $ProductModel->where("id=%d", $product_id)
            ->cache(true, 300)
            ->find();
        $start_time = strtotime($product_info['start']);
        $end_time = strtotime($product_info['end']);
        // 判断是否过期
        
        if ($start_time > time() || $end_time < time()) {
            doLog('Member/doOrderOneCoin', $this->oneCoin . "-优惠券已过期", $product_id, '', $this->redisLog);
            return false;
        }
        
        // 判断是否是优惠券
        if ($product_info['verification'] != 1) {
            
            doLog('Member/doOrderOneCoin', $this->oneCoin . "-该商品不是优惠券", $product_id, '', $this->redisLog);
            return false;
        }
        foreach ($list as $key => $val) {
            
            // 判断是否已发放
            $is_get = $this->checkOrder($product_id, $val['ptuserid'], $val['ptid']);
            if ($is_get['status'] == 2) { // 已发放;
                
                continue;
            }
            
            // 判断商品库存
            if ($product_info['total'] - $product_info['saleallquantity'] < 1) {
                doLog('Member/doOrderOneCoin', $this->oneCoin . "-库存不足", $product_id, '', $this->redisLog);
                return false;
            }
            
            $platform_info = $PlatformModel->where("id=%d", $val['ptid'])
                ->cache(true, 3600)
                ->find();
            $product_code = $ProductCodeModel->where("productid=%d and status=0", $product_id)
                ->lock(true)
                ->find();
            
            $customer_info = $CustomerModel->where("id=%d", $val['ptid'])
                ->cache(true, 3600)
                ->find();
            
            if (empty($product_code)) {
                
                doLog('Member/doOrderOneCoin', $this->oneCoin . "-优惠券不足", $product_id, '', $this->redisLog);
                continue;
            }
            
            $state5 = $ProductCodeModel->where("id=%d and status=0", $product_code['id'])
                ->lock(true)
                ->data(array(
                'status' => 1,
                'updatetime' => date('Y-m-d H:i:s')
            ))
                ->save();
            
            if ($state5) {
                
                // 订单表里生成订单记录
                $data = array();
                $data['ordenum'] = date('YmdHis') . createNonceStr(4, '1234567890');
                $data['tel'] = $val['tel'];
                $data['ptuserid'] = $val['ptuserid'];
                $data['pay_status'] = 0;
                $data['pay_type'] = $pay_type;
                $data['pay_price'] = $pay_price;
                $data['pay_account'] = '';
                $data['transaction_no'] = '';
                $data['pay_time'] = '1900-01-01 00:00:00';
                $data['addr_id'] = $addr_id;
                $data['ptid'] = $platform_info['id'];
                $data['ptname'] = $platform_info['name'];
                $data['remark'] = '';
                $data['updatetime'] = date('Y-m-d H:i:s');
                $data['ordertime'] = date('Y-m-d H:i:s');
                $data['createtime'] = date('Y-m-d H:i:s');
                $data['updatetime'] = date('Y-m-d H:i:s');
                $id = $OrderModel->data($data)->add();
                // 订单商品表里生成订单记录
                $OrderProductModel = D('OrderProduct');
                $product_data = array();
                $product_data['orderid'] = $id;
                $product_data['productid'] = $product_id;
                $product_data['productname'] = $product_info['name'];
                $product_data['producttypeid'] = $product_info['type_id'];
                $product_data['ptid'] = $platform_info['id'];
                $product_data['ptname'] = $platform_info['name'];
                // $product_data['smsreturnvalue'] = '';
                $product_data['status'] = $product_info['ifpay'];
                $product_data['quality'] = $quality;
                $product_data['productcodeid'] = $product_code['id'];
                $product_data['productcode'] = $product_code['couponcode'];
                $product_data['createtime'] = date('Y-m-d H:i:s');
                $product_data['updatetime'] = date('Y-m-d H:i:s');
                $product_data['tel'] = $val['tel'];
                $od_id = $OrderProductModel->data($product_data)->add();
                // 检验订单是否正常生成否则撤销操作
                if (! $id || ! $od_id) {
                    
                    if ($id) {
                        $OrderModel->where("id=%d", $id)->delete();
                    }
                    if ($od_id) {
                        $OrderProductModel->where("id=%d", $od_id)->delete();
                    }
                    if ($product_info['verification'] == 1 && $state5) {
                        $ProductCodeModel->where("id=%d and status=1", $product_code['id'])
                            ->lock(true)
                            ->data(array(
                            'status' => 0,
                            'updatetime' => date('Y-m-d H:i:s')
                        ))
                            ->save();
                    }
                    doLog('Member/doOrderOneCoin', $this->oneCoin . "-订单失败-userid:" . $val['ptuserid'], $product_id, '', $this->redisLog);
                    continue;
                }
                
                $ProductModel->where("id=%d and total>0", $product_id)->setInc('saleallquantity', $quality);
                
                // 发送短信，并将返回值保存到订单表
                $return_sms = NULL;
                if ($product_info['issms'] == 1) {
                    $msg = str_replace('{channel}', $customer_info['name'], $product_info['smstpl']);
                    $msg = str_replace('{couponcode}', $product_code['couponcode'], $msg);
                    $return_sms = send_msg($val['tel'], $msg);
                    // $return_sms = null;
                    $return['msg'] = $return_sms;
                    $coupon['code'] = $product_data['productcode'];
                    doLog('Member/SMS', $this->oneCoin . "-短信返回结果", $product_id, json_encode($return), $this->redisLog);
                    doLog('Member/doOrderOneCoin', $this->oneCoin . "-成功", $product_id, json_encode($coupon), $this->redisLog);
                }
                
                $OrderProductModel->where("id=%d", $od_id)
                    ->data(array(
                    'smsreturnvalue' => $return_sms
                ))
                    ->save();
            }
        }
        
        return true;
    }
    
    // /**
    // * [msyhLogin 民生银行登录验证]
    // * @shenzihao
    // * @DateTime 2016-09-19
    // * @return result json
    // */
    public function msyhLogin($tel = '')
    {
        $card = I('param.card', null);
        if ($tel && $card) {
            $userModel = D('pt_user');
            $platcode = $this->platform_code;
            // $u_info['telephone'] = $tel;
            $u_info['cardno'] = $card;
            $info = $userModel->where("cardno='%s'", $u_info['cardno'])->find();
            if ($info) {
                $_SESSION[$platcode]['tel'] = $info['telephone'];
                $_SESSION[$platcode]['openid'] = $info['openid'];
                $_SESSION[$platcode]['zwcmopenid'] = $info['zwcmopenid'];
                cookie($platcode . '_openid', $info['openid'], 60 * 60 * 24 * 100);
                doLog('Member/login', "会员登录-成功", '', 'tel=' . $tel, $this->redisLog);
                $dataJson = array(
                    'state' => 1,
                    'msg' => '恭喜您，登录成功。'
                );
            } else {
                // 民生银行验证
                $url = 'https://www.msjyw.com.cn/api/index.php?flow=check&ac=index&cardnum=' . $card . '&phone=' . $tel;
                $result = json_decode(send_curl($url), true);
                if ($result['result'] == '0') {
                    $data['telephone'] = $tel;
                    $data['cardno'] = $card;
                    if ($_SESSION[$platcode]['openid'])
                        $userModel->where("openid='%s'", $_SESSION[$platcode]['openid'])->save($data);
                    $_SESSION[$platcode]['tel'] = $tel;
                    $_SESSION[$platcode]['cardno'] = $card;
                    doLog('Member/login', "会员登录-成功", '', 'tel=' . $tel, $this->redisLog);
                    $dataJson = array(
                        'state' => 1,
                        'msg' => '恭喜您，登录成功。'
                    );
                } else {
                    doLog('Member/login/failed', "会员登录-失败", '', '', $this->redisLog);
                    $dataJson = array(
                        'state' => 2,
                        'msg' => '对不起，您输入的卡号有误，如还未开通民生直销银行电子账户，请点击开通。'
                    );
                    // 引导用户去开通民生直销银行电子账户的链接：https://mkt.cmbc.com.cn/wxbank/WxRD.do?SI=0200&TYPE=VLK&BaNo=02050&ACTYPE=OPACT
                }
            }
        } else {
            doLog('Member/login/failed', "会员登录-失败", '', '', $this->redisLog);
            $dataJson = array(
                'state' => 0,
                'msg' => '对不起，您的卡号为空。请输入卡号！'
            );
        }
        return json_encode($dataJson);
    }

    /**
     * [modify 修改地址]
     * @ckhero
     * @DateTime 2016-12-26
     * 
     * @return [type] [description]
     */
    public function modify()
    {
        $id = I('param.id', 0, 'intval');
        
        $info['updatetime'] = date('Y-m-d H:i:s');
        $info['name'] = I('param.userName', '');
        $info['tel'] = I('param.telNumber', '');
        $info['national_code'] = I('param.nationalCode', '');
        $info['post_code'] = I('param.postalCode', '');
        $info['province'] = I('param.provinceName', '');
        $info['city'] = I('param.cityName', '');
        $info['area'] = I('param.countryName', '');
        $info['address'] = I('param.detailInfo', '');
        $addInfo = M('user_mailing_addr')->where("id=%d", $id)->find();
        $data = array(
            'status' => 0,
            'msg' => '地址不存在'
        );
        if (! empty($addInfo)) {
            
            $res = M('user_mailing_addr')->where("id=%d", $id)->save($info);
            if ($res) {
                
                $data['status'] = 1;
                $data['msg'] = '修改成功';
            }
        }
        
        $this->ajaxReturn($data);
    }

    /**
     * [modifyOrderAddr 更新订单中的地址id]
     * @ckhero
     * @DateTime 2016-12-26
     * 
     * @return [type] [description]
     */
    function modifyOrderAddr()
    {
        $addrId = I('get.id', 0, 'intval');
        $orderId = I('get.orderid', 0, 'intval');
        $data = array(
            
            'status' => 0,
            'msg' => '未知错误'
        );
        $addInfo = M('user_mailing_addr')->where('id=%d', $addrId)->find();
        $orderInfo = M('order')->where('id=%d', $orderId)->find();
        if (! empty($addInfo) && ! empty($orderInfo)) {
            
            if ($orderInfo['addr_id'] == $addrId) {
                
                $data['status'] = 1;
                $data['msg'] = '无需修改';
            } else {
                
                $updateData = array(
                    'addr_id' => $addrId,
                    'tel' => $addInfo['tel']
                );
                $res = M('order')->where('id=%d', $orderId)->save($updateData);
                if ($res) {
                    
                    $data['status'] = 1;
                    $data['msg'] = '更新成功';
                }
            }
        }
        
        $this->ajaxReturn($data);
    }

    /**
     * [delAddr 删除地址]
     * @ckhero
     * @DateTime 2016-12-26
     * 
     * @return [type] [description]
     */
    public function delAddr()
    {
        $id = I('get.id', 0, 'intval');
        $order_id = I('get.order_id', 0, 'intval');
        $data = array(
            
            'status' => 0,
            'msg' => '地址不存在'
        );
        //地址被正在进行中的订单使用的时候不能删除
        //$isUsed = M('order')->field('name, ')->where("addr_id = %d and pay_status in (1,2) and id != %d", $id, $order_id)->count();
        $isUsed = 0;
        if ($isUsed > 0){

            $data['msg'] = '该地址正在被使用，无法删除';
        } else {

            $addInfo = M('user_mailing_addr')->where('id=%d', $id)->find();
        
            if (! empty($addInfo)) {
                
                $res = M('user_mailing_addr')->where('id=%d', $id)->delete();
                if ($res) {
                    
                    $data = array(
                        
                        'status' => 1,
                        'msg' => '删除成功'
                    );
                } else {
                    
                    $data = array(
                        
                        'status' => 0,
                        'msg' => '删除失败'
                    );
                }
            }
        } 
        $this->ajaxReturn($data);
    }
    
    /**
     * [telVerify 获取手机验证码+
     * ]
     * @ckhero
     * @DateTime 2017-02-24
     * @return   [type]     [description]
     */
    public function telVerify()
    {
        //判断是ajax发起的
        if (!is_ajax()) {

            exit;
        }
        $tel = I('param.tel');

        //电话号码不正确
        if (!is_tel($tel)) {

            $data = array(
                    'status' => 0,
                    'msg' => L('_TEL_ILLEGAL_'),
                );
        } else {

            $telVerify = new TelVerify(array('tel'=>$tel));
            $verifyCode = $telVerify->entry();
            $send_res = send_msg_aliyun($tel, $verifyCode, $this->redisLog);
            doLog('LeTianBang/verify', '验证码发送结果通知:'.$send_res,'','验证码为：'.$verifyCode,$this->redisLog);
            if (preg_match('/^ok.*/', $send_res)) {

                $data = array(
                    'status' => 1,
                    'msg' => L('_TEL_VERIFY_CODE_SEND_OK_'),
                );
            } else {

                $data = array(
                    'status' => 0,
                    'msg' => L('_SEND_FAIL_TRY_LATER_'),
                );
            }
        }

        $this->ajaxReturn($data);
    }
}
