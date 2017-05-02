<?php

/*
 * 首页推荐类别
 */
function product_post($start = 0, $num = 3)
{
    $product_model = D('PlatformProduct');
    $post_list = $product_model->getPostProduct($start, $num);
    return $post_list;
}

/*
 * 首页推荐分类商品
 */
function type_product_post($type_id, $num = 6)
{
    $product_model = D('PlatformProduct');
    //$city_id = $_SESSION[$_SESSION['PLATFORM_CODE']]['city_id'];
    //$product_list = $product_model->getProductList($type_id, $city_id, 0, $num);
    $product_list = $product_model->getProductList($type_id, 0, $num);
    return $product_list;
}

/*
 * 根据条件搜索商品
 * @param $where查询条件如："id=1 and sort=0"
 */
function product_search($where, $start = 0, $num = 6)
{
    $product_model = D('PlatformProduct');
    $product_list = $product_model->searchProductLists($where, '37', $start, $num);
    return $product_list;
}

/*
 * @param $pid 父级id
 */
/* 2017-03-29 注释
function getAreaList($pid = 0, $keyword = '')
{
    $city_model = D('city_class');
    $where = array();
    if ($pid) {
        $where['keyid'] = $pid;
    } else {
        $where['keyid'] = array(
            'in',
            '1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21,22,23,24,25,26,27,28,29,30,31,32,34,35'
        );
    }
    if ($keyword) {
        $where['name'] = array(
            'like',
            '%' . $keyword . '%'
        );
    }
    $area_list = $city_model->where($where)
        ->order('sort asc')
        ->cache(true, 300)
        ->select();
    // TODO!!!
    
    return $area_list;
}*/
/* 2017-03-29 注释
function getAreaInfo($where)
{
    $city_model = D('city_class');
    $area_info = $city_model->where($where)
        ->cache(true, 300)
        ->find();
    return $area_info;
}*/

/*
 * 查找商品信息
 */
function getProductInfo($id)
{
    $ProductModel = D('platform_product');
    $product_info = $ProductModel->getProductInfo($id);
    return $product_info;
}

/*
 * @param $site_type 0省级，1市级，2区级
 * @param $pid 父级id
 */
/* 2017-03-29注释
function getAreaByParentName($p_name = '')
{
    $city_model = D('city_class');
    // $subQuery = $city_model->field('id')->where(array('sitetype' => 1, 'name' => $p_name))->buildSql();
    $subQuery = $city_model->field('id')
        ->where("sitetype=1 and name='%s'", $p_name)
        ->buildSql();
    // $area_list = $city_model->where('keyid = ' . $subQuery)->cache(true, 300)->select();
    $area_list = $city_model->where('keyid="%s" ', $subQuery)
        ->cache(true, 300)
        ->select();
    return $area_list;
}*/

/*
 *
 */
// function getProductList($where = '', $start = 0, $num = 10)
// {
// $product_model = D('PlatformProduct');
// $product_list = $product_model->getProductLists($where, $start, $num);
// return $product_list;
// }

/*
 * 返回平台信息
 */
function getPlatformInfo($where)
{
    $PlatformModel = D('Platform');
    $platform_info = $PlatformModel->where($where)
        ->cache(3600)
        ->find(); // 加cache, 一小时有效
    return $platform_info;
}

/*
 *
 */
function getProductByCollect($where, $start = 0, $num = 6)
{
    $CollectModel = D('UserCollection');
    $tablePrefix = C('DB_PREFIX');
    $product_list = $CollectModel->join($tablePrefix . 'product on ' . $tablePrefix . 'user_collection.productid=' . $tablePrefix . 'product.id')
        ->field($tablePrefix . 'product.*,' . $tablePrefix . 'user_collection.collectiontime')
        ->where($tablePrefix . 'product.status=1 and ' . $where)
        ->
    // ->where($tablePrefix . 'product.status=1 and "%s"', $where) //这样的写法有问题，地址取不到收藏数据
    order($tablePrefix . 'user_collection.collectiontime desc')
        ->limit($start, $num)
        ->
    // ->cache(true, 300) //不能加cache，否则用户点击收藏后要300秒后才能看到
    select();
    return $product_list;
}

// 根据订单查询商品
function getProductByOrder($user_id, $start = 0, $num = 6)
{
    $OrderProductModel = D('OrderProduct');
    $tel = $_SESSION[$_SESSION['PLATFORM_CODE']]['tel'];
    $tablePrefix = C('DB_PREFIX');
    $OrderModer = D('Order');
    $subQuery = $OrderModer->join($tablePrefix . 'order_product on ' . $tablePrefix . 'order_product.orderid=' . $tablePrefix . 'order.id')
        ->field($tablePrefix . 'order.shipping_cost,' . $tablePrefix . 'order.pay_price,' . $tablePrefix . 'order_product.quality,' . $tablePrefix . 'order_product.productid,' . $tablePrefix . 'order_product.createtime,' . $tablePrefix . 'order_product.productcode,' . $tablePrefix . 'order.ordenum,' . $tablePrefix . 'order.pay_status,' . $tablePrefix . 'order.id as orderid')
        ->where('ptuserid="%s"', $user_id)
        ->buildSql();
    // ->where('ptuserid=' . $user_id)->buildSql();
    
    $product_list = $OrderProductModel->table($subQuery . ' o')
        ->join($tablePrefix . 'product on ' . 'o.productid=' . $tablePrefix . 'product.id')
        ->field($tablePrefix . 'product.*,' . 'o.createtime,o.productcode,o.ordenum,o.pay_status,o.orderid,o.quality,o.pay_price,o.shipping_cost')
        ->where($tablePrefix . 'product.status=1 and ' . $tablePrefix . 'product.id=o.productid')
        ->order('o.createtime desc')
        ->limit($start, $num)
        ->
    // ->cache(true, 300) //不能加cache，否则用户购买后要300秒后才能看到
    select();
    
    return $product_list;
}

////不再使用，邵晓凌：2017-03-17
/*function checkPoint($product_id)
{
    $UserPointModel = D('UserPoint');
    // $state = $UserPointModel->where(array('user_id' => $_SESSION[$_SESSION['PLATFORM_CODE']]['id'], 'product_id' => $product_id, 'platform_id' => $_SESSION[$_SESSION['PLATFORM_CODE']]['platform_id']))->find();
    $state = $UserPointModel->where("user_id=%d and product_id=%d and platform_id=%d", array(
        $_SESSION[$_SESSION['PLATFORM_CODE']]['id'],
        $product_id,
        $_SESSION[$_SESSION['PLATFORM_CODE']]['platform_id']
    ))->find();
    return $state;
}
*/
function checkCollect($product_id)
{
    $CollectModel = D('UserCollection');
    // $state = $CollectModel->where(array('ptuserid' => $_SESSION[$_SESSION['PLATFORM_CODE']]['id'], 'productid' => $product_id, 'platformid' => $_SESSION[$_SESSION['PLATFORM_CODE']]['platform_id']))->find();
    $state = $CollectModel->where("ptuserid=%d and productid=%d and platformid=%d", array(
        $_SESSION[$_SESSION['PLATFORM_CODE']]['id'],
        $product_id,
        $_SESSION[$_SESSION['PLATFORM_CODE']]['platform_id']
    ))->find();
    return $state;
}

// 日志记录
function doLog($action = '', $content = '', $product_id = '', $details = '', $redis = '')
{
    $data = array();
    $PtLogModel = M('PtLog');
    $PtUser = M('PtUser');
    $session = $_SESSION[$_SESSION['PLATFORM_CODE']];
    $pt_info = getPlatformInfo(array(
        'id' => $session['platform_id']
    ));
    // $pt_info = getPlatformInfo("id=%d", $session['platform_id']);
    $user_info = $PtUser->where(array(
        'id' => $session['id']
    ))
        ->cache(true, 300)
        ->find();
    // $user_info = $PtUser->where("id=%d", $session['id'])->cache(true,300)->find();
    $data['uid'] = $session['id'];
    $data['username'] = $user_info['username'];
    $data['ptid'] = $session['platform_id'];
    $data['ptname'] = $pt_info['name'];
    $data['productid'] = $product_id;
    // $data['devicename'] = checkOS();
    $data['action'] = $action;
    $data['content'] = $content;
    $data['ip'] = get_client_ip(0, true);
    $data['longitude'] = '';
    $data['latitude'] = '';
    $data['sex'] = $user_info['sex'];
    $data['openid'] = $session['openid'];
    $data['zwcmopenid'] = $session['zwcmopenid'];
    $data['createtime'] = date('Y-m-d H:i:s');
    $data['details'] = $details;
    $data['useragent'] = $_SERVER['HTTP_USER_AGENT'];
    if (online_redis_server) {
        
        if (empty($redis))
            $redis = new \Vendor\Redis\DefaultRedis(C('REDIS_HOST_LOG'), C('REDIS_PORT_LOG'), C('REDIS_AUTH_LOG'));
            // $a= $redis->databaseSelect('pt_log');
        $logid = $redis->get('logid');
        if (! $logid) {
            
            $redis->set('logid', 1);
            $logid = 1;
        }
        $res = $redis->lset($logid . 'pt_log_id', 0, 'R');
        $redis->hset($logid . 'pt_log_detail:' . $res[0], $data);
    } else {
        
        $PtLogModel->data($data)->add();
    }
}

/**
 * [doLogNoSession 没有继承CommonController的控制器写日志的函数]
 * @ckhero
 * @DateTime 2016-08-25
 * 
 * @param string $action
 *            [description]
 * @param string $content
 *            [description]
 * @param string $product_id
 *            [description]
 * @param string $details
 *            [description]
 * @param string $userid
 *            [description]
 * @param string $ptid
 *            [description]
 * @param string $ptname
 *            [description]
 * @return [type] [description]
 */
function doLogNoSession($action = '', $content = '', $product_id = '', $details = '', $userid = '', $ptid = '', $ptname = '', $redis = '')
{
    $data = array();
    $PtLogModel = M('PtLog');
    $PtUser = M('PtUser');
    
    // $user_info = $PtUser->where(array('id' => $userid))->cache(true,300)->find();
    $user_info = $PtUser->where("id=%d", $userid)
        ->cache(true, 300)
        ->find();
    $data['uid'] = $userid;
    $data['username'] = $user_info['username'];
    $data['ptid'] = $ptid;
    $data['ptname'] = $ptname;
    $data['productid'] = $product_id;
    // $data['devicename'] = checkOS();
    $data['action'] = $action;
    $data['content'] = $content;
    $data['ip'] = get_client_ip(0, true);
    $data['longitude'] = '';
    $data['latitude'] = '';
    $data['sex'] = $user_info['sex'];
    $data['openid'] = $user_info['openid'];
    $data['zwcmopenid'] = $user_info['zwcmopenid'];
    $data['createtime'] = date('Y-m-d H:i:s');
    $data['details'] = $details;
    $data['useragent'] = $_SERVER['HTTP_USER_AGENT'];
    
    if (online_redis_server) {
        
        if (empty($redis))
            $redis = new \Vendor\Redis\DefaultRedis(C('REDIS_HOST_LOG'), C('REDIS_PORT_LOG'), C('REDIS_AUTH_LOG'));
            // $a= $redis->databaseSelect('pt_log');
        $logid = $redis->get('logid');
        if (! $logid) {
            
            $redis->set('logid', 1);
            $logid = 1;
        }
        $res = $redis->lset($logid . 'pt_log_id', 0, 'R');
        $redis->hset($logid . 'pt_log_detail:' . $res[0], $data);
    } else {
        
        $PtLogModel->data($data)->add();
    }
}

function checkOS()
{
    vendor("Mobile.Mobile_Detect");
    $detect = new Mobile_Detect();
    if ($detect->isMobile()) {
        if ($detect->isiOS()) {
            return 'iOS:' . $detect->version('iOS');
        } elseif ($detect->isAndroidOS()) {
            return 'Android:' . $detect->version('Android');
        } else {
            return 'Other';
        }
    } else {
        return 'Other';
    }
}

// 奖项列表中加入 谢谢参与
function insertStrToArr($arr, $str = '谢谢参与', $num = 3)
{
    $count = count($arr);
    if ($count == 1) {
        $arr[2] = $str;
    } elseif ($count == 2) {
        $arr[3] = $arr[2];
        $arr[2] = $str;
    } else {
        $standard = intval($count / $num);
        for ($i = 1; $i <= $num; $i ++) {
            $count = count($arr);
            for ($j = $count; $j >= $standard * $i + $i; $j --) {
                $arr[$j + 1] = $arr[$j];
            }
            $arr[$standard * $i + $i] = $str;
        }
    }
    return array_filter($arr);
}

// //获取流量接口
// function get_flow($tel,$orderCode='20141219161253501883737')
// {
// //$url = "http://zshdl.gy/index.php/Home/Interface/index.html";
// $url = 'http://112.64.17.131/order';
// $customer = "20150316142239699506873";
// $app = "20150316142301424049917";
// $timestamp = get_millisecond();
// $account = 'shzwcmadmin';
// $token = '20150316125124121';
// $passWord = 'Shzwcm@qaz123';
// $sign = md5($customer.$app.$token.$account.strtoupper(md5($passWord)).$timestamp.$tel);
// $post_data = array(
// "customer" => $customer,
// "app" => $app,
// "timestamp" => $timestamp,
// "account" => base64_encode($account),
// "phone" => base64_encode($tel),
// "phoneType" => "0",
// "sign" => $sign,
// "orderCode" => base64_encode($orderCode),
// "reserveType" => "0"
// );
// $return_result = curl_post($url,json_encode($post_data),'POST');
// return json_decode($return_result);
// }
//
// 返回毫秒数
function get_millisecond()
{
    $time = explode(" ", microtime());
    $time = $time[1] . ($time[0] * 1000);
    $time2 = explode(".", $time);
    $time = $time2[0];
    return $time;
}

function get_flow($mobile, $orderid)
{
    $area = get_area($mobile);
    $Operator = json_decode($area, true);
    if ($Operator['OperatorType'] == '联通') {
        $data['package'] = '20';
    } else {
        $data['package'] = '10';
    }
    $data['account'] = 'D187';
    $data['mobile'] = $mobile;
    $data['timestamp'] = date('YmdHis');
    $data['orderno'] = $orderid;
    $data['sign'] = get_sign($data);
    $param = http_build_query($data);
    $url = 'http://bcp.pro-group.cn/api/CallApi/Index?v=1.2&action=charge&' . $param;
    $result = send_curl($url);
    return $result;
}

function get_sign($array)
{
    ksort($array);
    $array['key'] = 'e74ce4bbb666e44166cccc8e6200ed32';
    return md5(http_build_query($array));
}

function get_area($mobile)
{
    $data['account'] = 'D187';
    $data['mobile'] = $mobile;
    $data['timestamp'] = date('YmdHis');
    $data['sign'] = get_sign($data);
    $param = http_build_query($data);
    $url = 'http://bcp.pro-group.cn/api/CallApi/Index?v=1.2&action=getArea&' . $param;
    $result = send_curl($url);
    return $result;
}
// 宣榛-积分扣除接口
/*
function pointsDeduct($token, $proid, $point)
{
    $timestamp = get_millisecond();
    $data_str = "point=" . $point . "&proid=" . $proid . "&timestamp=" . $timestamp . "&token=" . $token;
    $signature = md5($data_str . "&secretkey=zgc5p2yk");
    $post_data_str = "?" . $data_str . "&sign=" . $signature;
    $url = "http://shop.happymath.org/OpenAPI/DuctPoint.aspx" . $post_data_str;
    $result = send_curl($url);
    if (APP_DEBUG) {
        file_put_contents('data.log', "访问链接:" . $url . "\n" . "积分扣除接口:" . $result . "\n", FILE_APPEND);
    }
    $obj = simplexml_load_string($result, 'SimpleXMLElement', LIBXML_NOCDATA);
    if (is_object($obj)) {
        $obj = get_object_vars($obj);
    }
    return $obj;
}
*/
// 宣榛-发货通知接口
/*
function deliverNotice($orderno = '', $dstate = 'SMSOK')
{
    $timestamp = get_millisecond();
    $data_str = "dstate=" . $dstate . "&orderno=" . $orderno . "&timestamp=" . $timestamp;
    $signature = md5($data_str . "&secretkey=zgc5p2yk");
    $post_data_str = "?" . $data_str . "&sign=" . $signature;
    $url = "http://shop.happymath.org/OpenAPI/Deliver.aspx" . $post_data_str;
    $result = send_curl($url);
    if (APP_DEBUG) {
        file_put_contents('data.log', "访问链接:" . $url . "\n" . "发货通知接口:" . $result . "\n", FILE_APPEND);
    }
    $obj = simplexml_load_string($result, 'SimpleXMLElement', LIBXML_NOCDATA);
    if (is_object($obj)) {
        $obj = get_object_vars($obj);
    }
    return $obj;
}
*/
// 宣榛-调用接口
/*
function pointsNotice($proid = 'T99124', $point = 88)
{
    if ($_SESSION[$_SESSION['PLATFORM_CODE']]['openid']) {
        $result_obj = pointsDeduct($_SESSION[$_SESSION['PLATFORM_CODE']]['openid'], $proid, $point);
        if (APP_DEBUG) {
            file_put_contents('data.log', "订单:" . $result_obj['result'] . "状态值:" . $result_obj['isSucc'] . "\n", FILE_APPEND);
        }
        if ($result_obj['result'] && intval($result_obj['isSucc']) == 1) {
            return deliverNotice($result_obj['result']);
        }
    }
    return false;
}
*/
/*
 * 解析最简单的xml格式数据
 * 输入<test>1</test><test2>2</test2>,'test1'
 * 输出 1
 * 2016-06-18 ------shenzihao
 */
function xmlToStr($str, $key)
{
    preg_match_all("/\<" . $key . "\>(.*)\<\/" . $key . "\>/s", $str, $a);
    return $a[1][0];
}

// 下面代码从招行项目拷贝过来 --2016-06-03 add --shenzihao
/*
 * 返回连续签到次数 邵晓凌注:实际上这个函数返回的是签到次数,即不再要求是连续的; 在数据库中的signday也表示的是签到次数(抽奖后清零)
 */
function getContinueSign()
{
    $SignDay = D('sign_day');
    $where['userid'] = $_SESSION[$_SESSION['PLATFORM_CODE']]['id']; // $_SESSION['_USER']['id'];
    $where['ptid'] = $_SESSION[$_SESSION['PLATFORM_CODE']]['platform_id']; // $_SESSION['_USER']['platform_id'];
                                                                           // $sign_day_info = $SignDay->where($where)->field('signday')->find();
    $sign_day_info = $SignDay->where("userid=%d and ptid=%d", array(
        $where['userid'],
        $where['ptid']
    ))
        ->field('signday')
        ->find();
    return $sign_day_info['signday'] ? $sign_day_info['signday'] : 0;
}

/*
 * 判断是否连续签到
 * 连续返回true
 * 不连续返回false
 * 邵晓凌注: 现在没有合作伙伴要求是连续签到的,所以此函数没有任何作用,可以删除
 */
function isContinueSign()
{
    return 1;
    $SignRecord = D('sign_record');
    $where = array();
    $where['userid'] = $_SESSION[$_SESSION['PLATFORM_CODE']]['id']; // $_SESSION['_USER']['id'];
    $where['ptid'] = $_SESSION[$_SESSION['PLATFORM_CODE']]['platform_id']; // $_SESSION['_USER']['platform_id'];
    $where['_string'] = "signtime between '" . date('Y-m-d 00:00:01', strtotime('last day')) . "' and '" . date('Y-m-d 00:00:00') . "'";
    if ($SignRecord->where($where)
        ->field('signtime')
        ->find()) {
        return 1;
    } else {
        return 0;
    }
}

/*
 * 是否已经签到
 */
function isSign()
{
    $SignRecord = D('sign_record');
    // $where = array();
    // $where['userid'] = $_SESSION[$_SESSION['PLATFORM_CODE']]['id']; //$_SESSION['_USER']['id'];
    // $where['ptid'] = $_SESSION[$_SESSION['PLATFORM_CODE']]['platform_id']; //$_SESSION['_USER']['platform_id'];
    // $where['_string'] = "signtime between '" . date('Y-m-d 00:00:01') . "' and '" . date('Y-m-d 00:00:00', strtotime('next day')) . "'";
    // if ($SignRecord->where($where)->field('signtime')->find()) {
    if ($SignRecord->where("userid=%d and ptid=%d and (signtime between '%s' and '%s')", array(
        $_SESSION[$_SESSION['PLATFORM_CODE']]['id'],
        $_SESSION[$_SESSION['PLATFORM_CODE']]['platform_id'],
        date('Y-m-d 00:00:01'),
        date('Y-m-d 00:00:00', strtotime('next day'))
    ))
        ->field('signtime')
        ->find()) {
        return 1;
    } else {
        return 0;
    }
}

/*
 * 签到功能
 * @return
 * 1签到成功
 * -1已签到
 * 0签到失败
 */
function doSign($redis, $redisLog)
{
    if ($_SESSION['PLATFORM_CODE']) { // modified by shao xiaoling
                                      // if ($_SESSION['_USER']) {
        $SignRecord = D('sign_record');
        $where = array();
        $session = $_SESSION[$_SESSION['PLATFORM_CODE']];
        // $where['userid'] = $session['id'] ;//$_SESSION['_USER']['id'];
        // $where['ptid'] = $session['platform_id']; //$_SESSION['_USER']['platform_id'];
        // $where['_string'] = "signtime between '" . date('Y-m-d H:i:s', strtotime(date('Y-m-d'))) . "' and '" . date('Y-m-d H:i:s', (strtotime(date('Y-m-d')) + 24 * 3600)) . "'";
        // $sign_record_data = $SignRecord->where($where)->find(); //不加数据库查询的缓存
        $sign_record_data = $SignRecord->where("userid=%d and ptid=%d and (signtime between '%s' and '%s') ", array(
            $session['id'],
            $session['platform_id'],
            date('Y-m-d H:i:s', strtotime(date('Y-m-d'))),
            date('Y-m-d H:i:s', (strtotime(date('Y-m-d')) + 24 * 3600))
        ))->find(); // 不加数据库查询的缓存
        if (empty($sign_record_data)) {
            
            $data = array();
            $data['userid'] = $session['id']; // $_SESSION['_USER']['id'];
            $data['channelid'] = $session['customer_id']; // $_SESSION['_USER']['customer_id'];
            $data['ptid'] = $session['platform_id']; // $_SESSION['_USER']['platform_id'];
//            $data['ptname'] = $session['platform_name']; // $_SESSION['_USER']['platform_name'];
            $data['signtime'] = date('Y-m-d H:i:s');
//            var_dump($data);die;
            if ($SignRecord->add($data)) {
                // ///////////以下语句是为招行抽奖平台设计的：当用户当天没有签到过的话，当天签到用户数加1.
                if ((online_redis_server == true) && ($_SESSION['PLATFORM_CODE'] == 'ZHCJ')) {
                    if (empty($redis))
                        $redis = new \Vendor\Redis\DefaultRedis();
                    $redis->databaseSelect();
                    $dateStr = date('Ymd');
                    $cmb_luckydraw_today_user_total = $redis->get('cmb_luckydraw_today_user_total' . '_' . $dateStr);
                    if ($cmb_luckydraw_today_user_total) {
                        $redis->set('cmb_luckydraw_today_user_total' . '_' . $dateStr, $cmb_luckydraw_today_user_total + 1, 24 * 60 * 60);
                    }
                    
                    // /////同步招行签到记录
                    $checkInTime = date('Y-m-d%20H:i:s', strtotime($data['signtime']));
                    $checkInTimeEncoded = urlencode(base64_encode($checkInTime));
                    $openidEncoded = urlencode(base64_encode($_SESSION['ZHCJ']['ZhcjOpenid']));
                    if (DEBUG_ON_LOCALHOST_OR_201TESTINGSERVER == FALSE) {
                        $url = 'https://pointbonus.cmbchina.com/IMSPActivities/checkIn/index?openid=' . $openidEncoded . '&checkInTime=' . $checkInTimeEncoded;
                    } else {
                        $url = 'http://pointbonustest.dev.cmbchina.com/IMSPActivities/checkIn/index?openid=' . $openidEncoded . '&checkInTime=' . $checkInTimeEncoded;
                    }
                    if ($_SESSION['ZHCJ']['ZhcjOpenid']) {
                        $redis->set('cmd_send_url:' . $_SESSION['ZHCJ']['ZhcjOpenid'], $url, 24 * 60 * 60);
                    }
                }
                // ///////////end 招行抽奖平台语句
                $SignDay = M('sign_day');
                $map = array();
                $map['userid'] = $session['id']; // $_SESSION['_USER']['id'];
                $map['ptid'] = $session['platform_id']; // $_SESSION['_USER']['platform_id'];
                                                        // $info = $SignDay->where($map)->find(); //不加数据库查询的缓存
                $info = $SignDay->where("userid=%d and ptid=%d", array(
                    $map['userid'],
                    $map['ptid']
                ))->find(); // 不加数据库查询的缓存
                if ($info) {
                    // $SignDay->where("userid=%d and ptid=%d", array($map['userid'], $map['ptid']))->setInc('signtotal', 1);
                    $SignDay->where("userid=%d and ptid=%d", array(
                        $map['userid'],
                        $map['ptid']
                    ))->save(array(
                        'signtotal' => $info['signtotal'] + 1,
                        'updatetime' => date('Y-m-d H:i:s')
                    )); // 更新updatetime： 2016-12-20
                    //连续签到
                    if (isContinueSign()) {
                        $Platform = D('platform');
                        // $platform_data = $Platform->where(array('id' => $session['platform_id']))->cache(true, 3600)->find();
                        $platform_data = $Platform->where("id=%d", $session['platform_id'])
                            ->cache(true, 3600)
                            ->find();
                        if ($platform_data['checkcycle'] > 0) {

                            //连续签到时间小于签到时间上限
                            if ($info['signday'] < $platform_data['checkcycle']) {
                                // $SignDay->where($map)->setInc('signday', 1);
                                $SignDay->where("userid=%d and ptid=%d", array(
                                    $map['userid'],
                                    $map['ptid']
                                ))->setInc('signday', 1);
                            } else {
                                // $SignDay->where($map)->save(array('signday' => 1));
                                $SignDay->where("userid=%d and ptid=%d", array(
                                    $map['userid'],
                                    $map['ptid']
                                ))->save(array(
                                    'signday' => 1
                                ));
                            }
                            return 1;
                        } else {
                            return 0;
                        }
                    //非连续签到
                    } else {
                        // $SignDay->where($map)->save(array('signday' => 1));
                        $SignDay->where("userid=%d and ptid=%d", array(
                            $map['userid'],
                            $map['ptid']
                        ))->save(array(
                            'signday' => 1
                        ));
                        return 1;
                    }
                } else {
                    $sign_day_data = array();
                    $sign_day_data['userid'] = $session['id']; // $_SESSION['_USER']['id'];
                    $sign_day_data['channelid'] = $session['customer_id']; // $_SESSION['_USER']['customer_id'];
                    $sign_day_data['ptid'] = $session['platform_id']; // $_SESSION['_USER']['platform_id'];
                    $sign_day_data['signtotal'] = 1;
                    $sign_day_data['signday'] = 1;
                    $sign_day_data['createtime'] = date('Y-m-d H:i:s');
                    $sign_day_data['updatetime'] = date('Y-m-d H:i:s');
                    $SignDay->add($sign_day_data);
                    // ///////////以下语句是为招行抽奖平台设计的：当用户从来没有签到过的话，历史用户数加1.
                    if ((online_redis_server == true) && ($_SESSION['PLATFORM_CODE'] == 'ZHCJ')) {
                        // $dateStr = date('Ymd');
                        if (empty($redis))
                            $redis = new \Vendor\Redis\DefaultRedis();
                        $redis->databaseSelect();
                        $cmb_luckydraw_user_total = $redis->get('cmb_luckydraw_user_total'); // 'cmb_luckydraw_user_total' . '_' .$dateStr
                        if ($cmb_luckydraw_user_total) {
                            $redis->set('cmb_luckydraw_user_total', $cmb_luckydraw_user_total + 1);
                        } // else {
                              // $redis->set('cmb_luckydraw_user_total' . '_' .$dateStr, 1);
                              // }
                    }
                    return 1;
                }
            } else {
                // 签到失败
                return 0;
            }
        } else {
            // 今日已签到
            return - 1;
        }
    } else {
        // 不存在平台code
        return 0;
    }
}

// 招行代码结束

/**
 * [lottery 抽奖数组]
 * @ckhero
 * @DateTime 2016-06-15
 * 
 * @param [type] $arr
 *            [权重数组]
 * @param [type] $k
 *            [为0 表示传进来的数组的键值已经为整数，不为0将K作为基值 将数组中的键值转为整数]
 * @param [type] $k2
 *            [几个谢谢惠顾]
 * @return [type] [中奖id]
 */
function lottery($arr, $k = 0, $k2 = 0)
{
    if ($k2 > 0) {

        $v = (1 - (array_sum($arr) + 2 * $k2)) * $k / $k2;
    
        if ($v < 0) { // 谢谢参与的概率小0的时候写成0
            
            $v = 0;
        }
    } else {

        $v = 0;
    }
      
    if ($k != 0) { // K不为0 说明传进来的数组的键值为浮点数
        
        foreach ($arr as $key => $val) {
            
            if ($val == - 2) {
                
                $arr[$key] = $v;
            } else {
                
                $arr[$key] = $val * $k;
            }
        }
    }
    
    $total = array_sum($arr);
    // doLog('Wheel/chance', "转化后的概率",'',json_encode($arr)."->total:".$total);
    foreach ($arr as $key => $val) {
        
        $seed = mt_rand(1, $total);
        if ($seed <= $val) {
            
            $result = $key;
            break;
        } else {
            
            $total -= $val;
        }
    }
    
    return $result;
}
// function lottery($sort, $k = 0, $k2 = 0)
// {

//     //生成两个数组 只包含写死百分比的商品的数组originalPercentArr 和未写死百分比的商品的数组percentArr
//     foreach($sort as $key => $val) {

//         if ($val['originalPercent'] > 0) {
            
//             $originalPercentSum += $val['originalPercent'];
//         } else {

//             $percentSum += $val['percent'];
//         }

//         $arr[$key] = $val['percent'];
//         $originalPercent[$key] = isset($val['originalPercent']) ? $val['originalPercent'] : 0;
//     }
//     if ($originalPercentSum >= 100) {

//         $arr = $originalPercent;
//     } else {

//         //定死的百分比除外还剩多少百分比（定死的概率总和不能大于100）；
//         $exceptOriginalPercent = (100 - $originalPercentSum) / 100;
//         $v = (1 - (array_sum($percentSum) + 2 * $k2)) * $k / $k2 * $exceptOriginalPercent;
        
//         if ($v < 0) { // 谢谢参与的概率小0的时候写成0
            
//             $v = 0;
//         }
//         if ($k != 0) { // K不为0 说明传进来的数组的键值为浮点数
//             //未写死百分比的商品的总量
//             $totalExceptOriginal = 0;
//             foreach ($arr as $key => $val) {
                
//                 if($originalPercent[$key] > 0) {

//                     //$arr2[$key] = $originalPercent[$key] / 100 * $k;
//                 } else {

//                     if ($val == - 2) {
                    
//                         $arr[$key] = $v;
//                     } else {
                        
//                         $arr[$key] = $val * $k;
//                     }

//                     $arr[$key] = $arr[$key];
//                     $totalExceptOriginal += $arr[$key]; 
//                 }
//             }
//         }
//         //$totalExceptOriginal / $exceptOriginalPercent 计算得出总量 * 写死的百分比得出该商品的量
//         foreach($arr as $key => $val) {

//             if($originalPercent[$key] > 0) {

//                 $arr[$key] = ($totalExceptOriginal / $exceptOriginalPercent) * ($originalPercent[$key] / 100);
//             }
//         }
//     }
//     $total = array_sum($arr);
//     // doLog('Wheel/chance', "转化后的概率",'',json_encode($arr)."->total:".$total);
//     foreach ($arr as $key => $val) {
        
//         $seed = mt_rand(1, $total);
//         if ($seed <= $val) {
            
//             $result = $key;
//             break;
//         } else {
            
//             $total -= $val;
//         }
//     }
    
//     return $result;
// }
/**
 * [get_sort 计算概率]
 * @ckhero
 * @DateTime 2016-08-15
 * 
 * @param [type] $time
 *            [持续时间]
 * @param [type] $past
 *            [已经过去的时间]
 * @param [type] $num
 *            [持续时间内的商品数量]
 * @param [type] $percent
 *            [概率]
 * @param [type] $date
 *            [总的活动的时间]
 * @param [type] $left
 *            [总的活动剩余时间]
 * @return [type] [description]
 */
function get_sort($time, $past, $num, $percent, $date, $left)
{
    $one_cycle = ceil($time / $num); // 计算一个周期是几个小时或者几天，每个周期派发一个奖品
    $remaining = $one_cycle - ($past % $one_cycle) + 1;
    $res = round(($one_cycle / $remaining) * 2 * $percent * ceil($past / $one_cycle), 8); // ?乘以第几个周期待讨论 2 为默认设定的值
    $res = round($res * ($date / $left), 8);
    return $res;
}

/**
 * [day 计算天数间隔]
 * @ckhero
 * @DateTime 2016-06-13
 * 
 * @param [type] $start
 *            [开始时间]
 * @param [type] $end
 *            [结束时间]
 * @param integer $k
 *            [是否需要转成时间戳 默认0 不需要 1 需要]
 * @return [type] [时间间隔/天]
 */
function day($start, $end, $k = 0)
{
    if ($k == 1) {
        
        $start = strtotime($start);
        $end = strtotime($end);
    }
    $res = ceil(($end - $start) / 86400);
    return $res;
}

// 流量接口,1中国移动,2中国联通,3中国电信
function checkTelArea($tel)
{
    // 手机号码段检验
    $url = "http://123.56.101.230/Sapi/checkAreaByMobile?phone=" . $tel;
    // 流量接口地址
    $result = send_curl($url);
    $result_data = json_decode($result, true);
    if (APP_DEBUG) {
        file_put_contents('data.log', "手机号码:" . $tel . "检查运营商接口返回数据:" . $result_data['result']['company'] . "\n", FILE_APPEND);
    }
    if ($result_data) {
        if ($result_data['result']['company']) {
            return $result_data['result'];
        } else {
            $result_data['result']['company'] = "未知运营商";
            return $result_data['result'];
        }
    }
    return false;
}

// //用户赠送流量接口
// function putFlowToUser($tel, $order, $product) {
// $flew_url = "http://123.56.101.230/Sapi/putFlowToUser/";
// $cf_mobile = $tel;
// $cf_user = 'CFKJ0100';
// $cf_key = '%TGH*UYHGVSED*UJOK';
// $cf_order = $order;
// $cf_product = $product;
// $cf_sign = md5($cf_mobile . "&" . $cf_key . "&" . $cf_user . "&" . $cf_order);
// $get_flew_url = $flew_url . "?" . "cf_user=" . $cf_user . "&cf_order=" . $cf_order . "&cf_mobile=" . $cf_mobile . "&cf_product=" . $cf_product . "&cf_key=" . $cf_key . "&cf_sign=" . $cf_sign;
// $result = send_curl($get_flew_url);
// if (APP_DEBUG) {
// file_put_contents('data.log', "赠送流量接口地址:" . $get_flew_url . "\n" . "手机号码:" . $tel . "赠送流量接口返回数据:" . $result . "\n", FILE_APPEND);
// }
// return json_decode($result, true);
// }

// 获取平台配置
function getPlatformConfig($platform_code) //请传入参数
{
    $cachetime = 3600; // 缓存过期时间
    if (empty($platform_code))
        $platform_code = $_SESSION['PLATFORM_CODE'];
    
    if (online_redis_server == true) {
        
        $redis = new \Vendor\Redis\DefaultRedis(); // 加载缓存类
        $redis->databaseSelect();
        $config = $redis->getArr('platform_config:' . $platform_code);
        if ($config)
            return $config;
    }
    
    // if($config){
    // return $config;
    // }else{
    $sql['PLATFORM_CODE'] = $platform_code;
    // $data = D('platform_config')->where($sql)->find();
    $data = D('platform_config')->where("PLATFORM_CODE='%s'", $sql['PLATFORM_CODE'])->find();
    // 获取平台模板
    $map['code'] = $platform_code;
    // $templet = D('platform')->field('templet')->where($map)->find();
    $templet = D('platform')->field('templet')
        ->where("code='%s'", $map['code'])
        ->find();
    $data['templet'] = $templet['templet'];
    if (isset($data['SHARE'])) {
        $data['SHARE'] = json_decode($data['SHARE'], TRUE);
        $data['SHARE']['imgUrl'] = RESOURCE_PATH . $data['SHARE']['imgUrl'];
        if (! checkUrl($data['SHARE']['link'])) {
            $data['SHARE']['link'] = WEB_URL . $data['SHARE']['link'];
        }
    }
    if (isset($data['BANNER'])) {
        $banner = json_decode($data['BANNER'], TRUE);
        $data['BANNER'] = '';
        foreach ($banner as $k => $v) {
            $data['BANNER'][$k]['title'] = $v[0];
            $data['BANNER'][$k]['img'] = RESOURCE_PATH . $v[1];
            if (! checkUrl($v[2])) {
                $data['BANNER'][$k]['url'] = WEB_URL . $v[2];
            } else {
                $data['BANNER'][$k]['url'] = $v[2];
            }
        }
    }
    if (isset($data['RIGHT_SIGN'])) {
        $right_sign = implode(';', $data['RIGHT_SIGN']);
        $data['RIGHT_SIGN'] = '';
        $data['RIGHT_SIGN']['state'] = $right_sign[0];
        $data['RIGHT_SIGN']['img_name'] = $right_sign[1];
    }
    if (isset($data['LEFT_SIGN'])) {
        $left_sign = implode(';', $data['LEFT_SIGN']);
        $data['LEFT_SIGN'] = '';
        $data['LEFT_SIGN']['state'] = $right_sign[0];
        $data['LEFT_SIGN']['img_name'] = $right_sign[1];
    }
    if (isset($data['LEFT_BOTTOM_SIGN'])) {
        $left_sign = implode(';', $data['LEFT_BOTTOM_SIGN']);
        $data['LEFT_BOTTOM_SIGN'] = '';
        $data['LEFT_BOTTOM_SIGN']['state'] = $right_sign[0];
        $data['LEFT_BOTTOM_SIGN']['img_name'] = $right_sign[1];
    }
    if (isset($data['WORD_ENABLE'])) {
        $left_sign = implode(';', $data['WORD_ENABLE']);
        $data['WORD_ENABLE'] = '';
        $data['WORD_ENABLE']['state'] = $right_sign[0];
        $data['WORD_ENABLE']['img_name'] = $right_sign[1];
    }
    if (isset($data['URL_VIDEO'])) {
        if (! checkUrl($data['URL_VIDEO'])) {
            $data['URL_VIDEO'] = WEB_URL . $data['URL_VIDEO'];
        }
    }
    if (isset($data['URL_GAME'])) {
        if (! checkUrl($data['URL_GAME'])) {
            $data['URL_GAME'] = WEB_URL . $data['URL_GAME'];
        }
    }
    if (isset($data['URL_LUCKYDRAW'])) {
        if (! checkUrl($data['URL_LUCKYDRAW'])) {
            $data['URL_LUCKYDRAW'] = WEB_URL . $data['URL_LUCKYDRAW'];
        }
    }
    if (online_redis_server == true) {
        $redis->setArr('platform_config:' . $platform_code, $data);
        $redis->expire('platform_config:' . $platform_code, $cachetime); // 设置3600秒后过期
    }
    
    return $data;
    // }
}
// 判断是否是完整url
function checkUrl($str)
{
    if (preg_match("/^(http:\/\/|https:\/\/).*$/", $str))
        return TRUE;
    else
        return FALSE;
}

/**
 * [setZwid 设置掌握id]
 * @ckhero
 * @DateTime 2016-07-04
 */
function setZwid($session, $createtime, $redis, $redisLog)
{
    if (empty($session['openid'])) { // openid不存在
        
        doLog('Common/error', "setZwid - openid不存在", '', '', $redisLog);
        return 0;
    }
    $zwcmopenid = empty($session['zwcmopenid']) ? $_SESSION[$_SESSION['PLATFORM_CODE']]['id'] : $session['zwcmopenid'];
    if (empty($zwcmopenid))
    	return 0;
    $res = M('zwid')->where("zwcmopenid='%s'", $zwcmopenid)->find();
    if (empty($res)) {
        
        $new_zwid = array(
            'zwcmopenid' => $zwcmopenid,
            'openid_platcode' => $session['openid'] . '_' . $session['platform_id'],
            'addtime' => date('Y-m-d H:i:s'),
            'createtime' => $createtime
        );
        $zwid = M('zwid')->data($new_zwid)->add();
        cookie('zwid', $zwid, 300);
        $new_zwid2['id'] = $zwid;
        $redis->hset('zwid:' . $zwid, array_merge($new_zwid2, $new_zwid));
        $redis->expire('zwid:' . $zwid, 24 * 3600);
        $redis->set('zwcmopenid:' . $zwcmopenid, $zwid);
        $redis->expire('zwcmopenid:' . $zwcmopenid, 24 * 3600);
    } else {
    	$zwid = $res['id'];
        $openid_platcode = explode(',', $res['openid_platcode']);
        
        if (! in_array($session['openid'] . '_' . $session['platform_id'], $openid_platcode)) {
            
            $openid_platcode[] = $session['openid'] . '_' . $session['platform_id'];
            $openid_platcode_new = implode($openid_platcode, ',');
            // M('zwid')->where("id=".$res['id'])->save(array('openid_platcode'=>$openid_platcode_new));
            M('zwid')->where("id=%d", $res['id'])->save(array(
                'openid_platcode' => $openid_platcode_new
            ));
            $res['openid_platcode'] = $openid_platcode_new;
            $redis->hset('zwid:' . $res['id'], $res);
            $redis->expire('zwid:' . $res['id'], 24 * 3600);
        } else {
            $zwidAtRedis = $redis->hget('zwid:' . $res['id']);
            $zwcmopenid2 = $redis->get('zwcmopenid:' . $zwcmopenid);
            if (empty($zwidAtRedis))
                $redis->hset('zwid:' . $res['id'], $res);
            $redis->expire('zwid:' . $res['id'], 24 * 3600);
            if (empty($zwcmopenid2))
                $redis->set('zwcmopenid:' . $zwcmopenid, $res['id']);
            $redis->expire('zwcmopenid:' . $zwcmopenid, 24 * 3600);
        }
        return $zwid;
    }
}

/**
 * [getSelfProductList 获取我的优惠券]
 * @ckhero
 * @DateTime 2016-10-09
 * 
 * @param [type] $tel
 *            [description]
 * @param integer $start
 *            [description]
 * @param integer $end
 *            [description]
 * @return [type] [description]
 */
// function getSelfProductList($tel,$start =0,$end =5){

// $exceptPlatform = M('platform')->field('id')->where('code in (\'TESTACCOUNT\',\'CMBPAY\')')->cache(true,3600)->select();
// foreach($exceptPlatform as $key=>$val){

// $str .= $val['id'].',';
// }
// $str = trim($str,',');
// $subSql = M('pt_user')->field('id')->where("telephone = %d and ptid not in(".$str.")",$tel)->buildSql();
// $tablePrefix = C('DB_PREFIX');
// $res = M('order_product')
// ->field('p.* ,'.$tablePrefix.'order_product.productcode,'.$tablePrefix.'order_product.ptid')
// ->join($tablePrefix.'order o on o.id= orderid')
// ->join($tablePrefix.'product p on p.id= productid')
// // ->where($tablePrefix.'order_product.tel=%d and o.pay_status in (0,2) and o.ptuserid in ('.$subSql .')',$tel) 这样子需要订单号码也是登陆号码
// ->where('o.pay_status in (0,2) and o.ptuserid in ('.$subSql .')',$tel)
// ->order($tablePrefix.'order_product.createtime desc')
// ->limit($start,$end)
// ->select();
// return $res;
// }

/**
 * [resetProductList 重置商品列表]
 * @ckhero
 * @DateTime 2016-10-12
 * 
 * @param [type] $list
 *            [description]
 * @param [type] $url
 *            [description]
 * @return [type] [description]
 */
function resetProductList($list, $url, $type)
{
    foreach ($list as $key => $val) {
        // $status = D('Product')->checkProductExist($val['id']);
        $res[$key]['url'] = U(str_replace('id/', 'id/' . $val['id'], $url));
        $res[$key]['status'] = D('Home/PlatformProduct')->checkProductExist($val['id'], false, $val['ptid']);
        // $res[$key]['status'] = $status['state'];
        $res[$key]['pic'] = RESOURCE_PATH . $val['detail_picture'];
        $res[$key]['start'] = date('Y.m.d', strtotime($val['start']));
        $res[$key]['end'] = date('Y.m.d', strtotime($val['end']));
        $res[$key]['name'] = $val['name'];
        $res[$key]['verification'] = $val['verification'];
        $res[$key]['productCode'] = trim($val['productcode'], '\n ');
        $res[$key]['price'] = $val['price'];
        $res[$key]['ifpay'] = $val['ifpay'];
        $res[$key]['id'] = $val['id'];
        $res[$key]['ptid'] = $val['ptid'];
        $res[$key]['orderid'] = $val['orderid'] ? $val['orderid'] : 0;
        if (empty($val['platformCode'])) {
            
            $res[$key]['platformCode'] = getPlatformCodeById($val['ptid']);
        } else {
            
            $res[$key]['platformCode'] = $val['platformCode'];
        }
        
        if ($type == 'self') {
            
            if ($val['ifpay'] == 1 && $val['price'] > 0) {
                
                $res[$key]['buyUrl'] = platformWeixinGuanzhu($res[$key]['platformCode']);
                
                // $res[$key]['buyUrl'] = "javascript:buy(".$val['id'].",".$val['price'].")";
                $res[$key]['buyType'] = '立即购买';
            } else {
                
                if (empty($val['remark']) && empty($val['mremark'])) {
                    
                    $res[$key]['buyUrl'] = $res[$key]['url'];
                } else {
                    
                    if ($val['ifstore'] == 1) {
                        
                        $res[$key]['buyUrl'] = U('Home/Map/lists/product_id/' . $val['id']);
                    } else {
                        
                        if (preg_match('/^https?/', $val['remark'])) {
                            
                            $res[$key]['buyUrl'] = $val['remark'];
                        } else {
                            
                            preg_match("/([\'\"]).*(\\1)/", $val['mremark'], $url2);
                            
                            if (! empty($url2[0])) {
                                
                                $url2[0] = preg_replace('/[\'\"]/', '', $url2[0]);
                                $res[$key]['buyUrl'] = $url2[0];
                            } else {
                                
                                $res[$key]['buyUrl'] = $res[$key]['url'];
                            }
                        }
                    }
                }
                $res[$key]['buyType'] = '立即使用';
            }
        } else {
            
            $res[$key]['buyUrl'] = platformWeixinGuanzhu($res[$key]['platformCode']);
            if ($val['price'] > 0 && $val['ifpay'] == 1) {
                
                $res[$key]['buyType'] = '立即购买';
            } else {
                
                $res[$key]['buyType'] = '立即领取';
            }
        }
    }
    return $res;
}

/**
 * [getPlatformCodeById 根据ptid查询 平台名字]
 * @ckhero
 * @DateTime 2016-10-09
 * 
 * @param [type] $ptid
 *            [description]
 * @return [type] [description]
 */
function getPlatformCodeById($ptid)
{
    if ($ptid) {
        
        $res = M('platform')->where("id=%d", $ptid)
            ->cache(true, 3600)
            ->find();
        if (mb_strlen($res['displayname'], 'UTF8') > 7) {
            
            $res = mb_substr($res['displayname'], 0, 7, 'UTF8') . '...';
        } else {
            
            $res = $res['displayname'];
        }
        return $res;
    }
}

function getShopUrl($productid)
{
    $tablePrefix = C('DB_PREFIX');
    $res = M('product')->alias('p')
        ->join($tablePrefix . 'brand b on b.id=p.brand_id')
        ->join($tablePrefix . 'merchant m on b.merchant_id = m.id')
        ->
    // ->where('p.id='.$productid)
    where('p.id=%d', $productid)
        ->field('m.remark')
        ->find();
    if (empty($res['remark']))
        $res['remark'] = '敬请期待！';
    return $res['remark'];
}

function platformWeixinGuanzhu($platformCode)
{
    switch ($platformCode) {
        
        case '招商银行':
            $url = 'http://mp.weixin.qq.com/s?__biz=MzIwNTMzMDQyOQ==&mid=100000063&idx=1&sn=941277a079bcb3d6cb9d7ddc411e8a31&chksm=1733c3ad20444abbe681b1fffdac770f9fe1c03b8fb6c2962fbd10fd17f3e76c25f94c51b6a1#rd';
            break;
        
        case '锦江礼享':
            $url = 'http://mp.weixin.qq.com/s?__biz=MzIwNTMzMDQyOQ==&mid=100000074&idx=1&sn=722d29c3b4ddc1c9c3c96fb608d0e9e6&chksm=1733c3d820444ace9c32d106087b2304b573eb93234530979c7743c200aaaaed4fa987e0ea24#rd';
            break;
        
        case '农业银行':
            $url = 'http://mp.weixin.qq.com/s?__biz=MzIwNTMzMDQyOQ==&mid=100000072&idx=1&sn=4dbaf60af307aa62a284be0b11a6cdeb&chksm=1733c3da20444acceed70d380b4ad9b7245f831cb90bdc0e36d096ef888b1169c8290181774e#rd';
            break;
        
        case '山西邮政':
            $url = 'http://mp.weixin.qq.com/s?__biz=MzIwNTMzMDQyOQ==&mid=100000069&idx=1&sn=9fa8fe41a57d555381d0ab1331fa3eda&chksm=1733c3d720444ac13c6562a864effaefd3fbff53ecb610c4030982c8c828da2dc24bcaa87dcb#rd';
            break;
        
        case '大陆汽车':
            $url = 'http://mp.weixin.qq.com/s?__biz=MzIwNTMzMDQyOQ==&mid=100000067&idx=1&sn=415f5bf0c1421dad97c7f99e0ba7a6dd&chksm=1733c3d120444ac7d4138723c0762feecba08e55261e75e91bbde0ed2a3258d91ad521661e10#rd';
            break;
        
        case '一箱有货':
            $url = 'http://mp.weixin.qq.com/s?__biz=MzIwNTMzMDQyOQ==&mid=100000070&idx=1&sn=dc04d04531db46f3cf081476d8dc45af&chksm=1733c3d420444ac253c64957dc5ba7a27fa44daebb7e1f50d9ea4c4dc2caf0c6c14197056754#rd';
            break;
        
        case '中国石油':
            $url = 'http://mp.weixin.qq.com/s?__biz=MzIwNTMzMDQyOQ==&mid=100000065&idx=1&sn=b582835547e4e27ffa8e5945ded29276&chksm=1733c3d320444ac577ff0ee72fa8944c54549d004d5a472c6e714dd48e56cfb031bd0142fbce#rd';
            break;
        
        case '民生银行':
            $url = 'http://mp.weixin.qq.com/s?__biz=MzIwNTMzMDQyOQ==&mid=100000083&idx=1&sn=6e689f1e5d7c950658d0000d9ef74a1d&chksm=1733c3c120444ad7f80064788838041c4f86133a387aad8618a814feb48c93ff37eef5ca0517&scene=0#wechat_redirect';
            break;
        
        case '上海农商银行':
            $url = 'http://mp.weixin.qq.com/s?__biz=MzIwNTMzMDQyOQ==&mid=100000085&idx=1&sn=b5bf246b24c81b626251ae76d0cea457&chksm=1733c3c720444ad18a12eebb6562404250ab4a824c47fabb0560876c1ee30a95875f896e1bcc&scene=0#wechat_redirect';
            break;
        
        case '上海前线':
            $url = 'http://mp.weixin.qq.com/s?__biz=MzIwNTMzMDQyOQ==&mid=100000077&idx=1&sn=f4729911044c7f9c82663eeb38e7cc7f&chksm=1733c3df20444ac95b55d9c9042cc3c74ec5d38270fc4050f1bff28cf8b634d81f973f3c25a0&scene=0#wechat_redirect';
            break;
        
        case '麻辣情医':
            $url = 'http://mp.weixin.qq.com/s?__biz=MzIwNTMzMDQyOQ==&mid=100000076&idx=1&sn=5122f5340e2dbd3a7670111d2d0ff14b&chksm=1733c3de20444ac8f4e66df10b064dc0dc6734a07df06bfc8d072e31f5ee4b44eae4df0c1c7c&scene=0#wechat_redirect';
            break;
        
        default:
            $url = 'http://mp.weixin.qq.com/s?__biz=MzIwNTMzMDQyOQ==&mid=100000026&idx=1&sn=99ad0b0fc3315cf9b4e6de14d18eca49&scene=18#wechat_redirect';
            break;
    }
    return $url;
}

/**
 * [getRecentPrize 转盘获取最近中奖的20条信息]
 * @zeo
 * @DateTime 2016-10-26
 *
 * @return [type] [description]
 */
function getRecentPrize($pt_id, $redis)
{
    //$pt_id = $_SESSION[$_SESSION['PLATFORM_CODE']]['platform_id'];  //$this->platform_id = $_SESSION[$this->platform_code]['platform_id'];
    if (online_redis_server == true) {
        //$redis = new \Vendor\Redis\DefaultRedis(); // 加载缓存类
        $redis->databaseSelect();
        $recentprize = $redis->hget('RecentPrize:' . $pt_id);
        if ($recentprize)
            return $recentprize;
    }
    
    $date = date('Y-m-d H:i:s%', strtotime("-60 day"));
    $offlinetime = date('Y-m-d%');
    $product_list = D('platform_product')->field('product_id,total,offlinetime')
        ->where("platform_id=%d", $pt_id)
        -> // offlinetime没有索引
select();
    foreach ($product_list as $k => $v) {
        if ($v['total'] < 10)
            $limit = 1;
        else 
            if ($v['total'] < 1000 && $v['total'] > 10)
                $limit = 5;
            else
                $limit = 10;
        if ($limit == 1 || (strtotime($v['offlinetime']) > time())) {
            $product_arr[] = D('order_product')->field('productname,tel')
                ->where("createtime >='%s' and ptid='%d' and productid='%d' ", $date, $pt_id, $v['product_id'])
                ->limit($limit)
                ->order('id desc')
                ->select();
        }
    }
    foreach ($product_arr as $k => $v) {
        foreach ($v as $k2 => $v2) {
            if (! empty($v2['tel'])) {
                $v2['productname'] = preg_replace('/\|\|/', '', $v2['productname']);
                $result[] = '用户' . substr($v2['tel'], 0, 3) . '****' . substr($v2['tel'], 7, 4) . '抽中' . $v2['productname'];
            }
        }
    }
    shuffle($result);
    if (online_redis_server == true) {
        $redis->hset('RecentPrize:' . $pt_id, $result);
        $redis->expire('RecentPrize:' . $pt_id, 24 * 3600);
    }
    return $result;
}

/**
 * [getUserByTel 根据电话号查询用户列表]
 * @ckhero
 * @DateTime 2016-10-25
 * 
 * @param [type] $tel
 *            [description]
 * @param boolean $cache
 *            [description]
 * @param string $type
 *            [description]
 * @return [type] [description]
 */
function getUserByTel($tel, $cache = false, $type = 'arr')
{
    $tablePrefix = C('DB_PREFIX');
    $res = M('pt_user')->table($tablePrefix . 'pt_user as u')
        ->field('u.id')
        ->join($tablePrefix . 'platform p on p.id=u.ptid')
        ->where('p.code not in (\'TESTACCOUNT\',\'CMBPAY\') and telephone = \'%s\' ', $tel)
        ->cache($cache, 300)
        ->select();
    if ($type == 'arr') {
        
        return $res;
    } else {
        
        $str = '';
        foreach ($res as $key => $val) {
            
            $str .= $val['id'] . $type;
        }
        
        return trim($str, $type);
    }
}

function getCustomerServiceTel($productId, $redis)
{
    // check if there's cache
    $redisKey = 'customer_service_tel_' . $productId;
    $redis->databaseSelect();
    $tel = $redis->get($redisKey);
    if ($tel != false)
        return $tel;
    
    $telArray = M('merchant')->table(array(
        C('DB_PREFIX') . 'merchant' => 'merchant',
        C('DB_PREFIX') . 'brand' => 'brand',
        C('DB_PREFIX') . 'product' => 'product'
    ))
        ->where('product.id=%d and product.brand_id=brand.id and brand.merchant_id=merchant.id', $productId)
        ->field('merchant.ophone as ophone, merchant.fphone as fphone')
        ->find();
    $tel = "";
    if (! empty($telArray['ophone']))
        $tel = $telArray['ophone'];
    elseif (! empty($telArray['fphone']))
        $tel = $telArray['fphone'];
    $redis->set($redisKey, $tel, 3600 * 24); // 电话号码缓存24小时
    
    return $tel;
}

/**
 * [reset_sms 根据短信模板生成短信内容]
 * @ckhero
 * @DateTime 2017-01-13
 * @param    [type]     $smstpl       [description]
 * @param    [type]     $product_code [description]
 * @return   [type]                   [description]
 */
/*
function reset_sms($smstpl, $product_code = '')
{
    $tablePrefix = C('DB_PREFIX');
    $platform_id = $_SESSION[$_SESSION['PLATFORM_CODE']]['platform_id'];
    $customer_info = M('customer')->table($tablePrefix."platform p, ".$tablePrefix."customer c")
                                  ->field('c.name')
                                  ->where("p.customer_id = c.id and p.id = %d", $platform_id)
                                  ->cache(3600)
                                  ->find();
    $msg = str_replace('{channel}', $customer_info['name'], $smstpl);
    $msg = str_replace('{couponcode}', $product_code, $msg);
    return $msg;
}*/
/**
 * [changeHttpsToHttp 将自己域名下的https连接换成http连接]
 * @ckhero
 * @DateTime 2017-01-22
 * @param    [type]     $str [description]
 * @return   [type]          [description]
 */
function changeHttpsToHttp($str)
{
    $res = str_replace("https://".$_SERVER['HTTP_HOST'], "http://".$_SERVER['HTTP_HOST'], $str);
    return $res;
}

/**
 * [is_url 判断是否是url]
 * @ckhero
 * @DateTime 2017-01-22
 * @param    [type]     $str [description]
 * @return   boolean         [description]
 */
function is_url($str){

    return preg_match("/^http(s)?:\/\/[A-Za-z0-9]+\.[A-Za-z0-9]+[\/=\?%\-&_~`@[\]\’:+!]*([^<>\"])*$/", $str);
}

if (!function_exists('log_user_category_score')){
    /**
     * @Description:记录用户分类的得分
     * @User:jysdhr
     * @param $zwid 用户的zwid
     * @param $category_id 分类id
     * @param $score 得分（1.点击一次+2分 2。收藏一次+3分 3.领取一次+5分）
     * @param $redisLog
     */
    function log_user_category_score($zwid, $category_id, $score, $redisLog)
    {
        if (empty($category_id)) return;
        $category_id_arr = explode(',',$category_id);
        if (empty($zwid)){
        	$zwid = intval(cookie('zwid'));
        	if( empty($zwid)) return;
        }
        foreach ($category_id_arr as $k => $v){
            $json_arr = ['zwid' => $zwid, 'category_id' => $v, 'score' => $score];
            //选择队列序号
            $category_queue_index = 1;//默认选择队列1
            if ($redisLog->existsRedis('Category_queue_index'))
                $category_queue_index = $redisLog->get('Category_queue_index');
            else
                $redisLog->set('Category_queue_index',$category_queue_index);
            //存redis链表
            $redisLog->lset('Category_queue' . $category_queue_index, json_encode($json_arr), 'R');
        }
    }
    
    //如果产品有被下载，或者被购买，则在redis里面更新all_product_sale
    function setProductDownloadOrPurchaseNuminRedis($product_id, $redisLog){
    	$product_sale_key = "all_product_sale";
    	if($redisLog->existsRedis($product_sale_key)){
    		$productallbuy = $redisLog->getArr($product_sale_key);
    		if ( array_key_exists($product_id, $productallbuy) ){
    			$productallbuy[$product_id] += 1;
    			$redisLog->setArr($product_sale_key, $productallbuy);
    		}
    		
    	}
    }
}
