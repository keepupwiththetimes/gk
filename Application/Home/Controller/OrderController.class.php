<?php
namespace Home\Controller;

use Common\Api\WxApi;
use Common\Api\Wxpay\UnifiedOrder_pub;

/*
 * ****************************
 *
 * 订单控制器
 * @since 2016-06-08
 *
 * ****************************
 */
class OrderController extends CommonController
{

    public function __construct()
    {
        parent::__construct();
        // $this->noTelList = array('CMBPAY','ZHWZ','BJDLQC','ZSYWZ','FLNMZWZ','XYXJYPX','SXYZYHG','LOVECLUB','XZZSH','JJLXWZ','NYYH','TESTACCOUNT','CXH','JJLXAPP','YX','MSYH', 'NYYHD', 'ZQX', 'HBCX');
        $this->checkTelList = array(
            'LETIANBANG'
        ); // 购买商品时，必须先输入手机号码才能购买的平台清单
        $this->timeOutStart = "2017-03-17 00:00:00"; //订单超时起算时间
        $this->timeOutLimit = date('Y-m-d H:i:s', strtotime("-10 day")); //十天前的时间
    }

    public function pay()
    {
        dolog('Order/paybuttonclicked', '点击购买按钮', '', '', $this->redisLog);
        // 一元购进入需要重定向、
        $sid = I('get.sid');
        if (! empty($sid))
            // if(!empty(I('get.sid')))
            redirect(ONE_COIN_URL . "index.php/Home/Order/pay/id/" . I('get.id'));
            
            // if(in_array($_SESSION['PLATFORM_CODE'],$this->noTelList)){ //下单的时候无需验证是否登录
        if (! in_array($this->platform_code, $this->checkTelList)) { // 下单的时候无需验证是否登录
            
            $is_login = true;
        } else {
            
            $is_login = $this->checkLogin();
        }
        if ($is_login) {
            $order_id = I('param.id', 0);
            $OrderModel = D('OrderProduct');
            $order_detail = $OrderModel->getOrderDetail($order_id);
            $total_product_price = 0;
            $total_shipping_cost = 0;
            $title = "";
            if (preg_match('/orderDetail/', $_SERVER['HTTP_REFERER'])) {
                
                $this->judgeWait($order_id);
            }
            // if(!empty($type)) $wait = $this->judgeWait($type,$order_id); //判断是否还能支付
            foreach ($order_detail['data'] as $key => $store) {
                for ($i = 0; $i < count($store['list']); $i ++) {
                    $product = $store['list'][$i];
                    $total_product_price += (float) ($product['p_price']) * (int) ($product['o_quality']);
                    // $title = $product['p_name'];
                    $product['p_name'] = strip_tags($product['p_name']); // 一元购项目里面的产品标题有html tag，用来页面上分行显示用
                    $title = $product['p_name'];
                    $pid = $product['p_id'];
                    $product['p_pic'] = RESOURCE_PATH . $product['p_pic'];
                    
                    if (! empty($product['o_specification']))
                        $product['o_specification'] = implode(' ', json_decode($product['o_specification'], true));
                    
                    $order_detail['data'][$key]['list'][$i] = $product; // 用处理好的商品信息替换原来数组中的商品性能西
                                                                        
                    // 判断是否是一元购--针对只有一个商品的情况
                    if ($product['type_id'] == 4) {
                        
                        $stage = M('one_coin_product')->where("one_coin_pid=%d", $product['p_id'])
                            ->cache(true, 300)
                            ->find();
                        $forward['url'] = U('Home/OneCoin/index', array(
                            'id' => $stage['stage']
                        ));
                        $forward['one_coin_enable'] = true;
                        $timeOut = strtotime($product['end']) - time();
                        if ($timeOut <= 0) {
                            
                            $this->error('活动已结束', '', 5);
                        } elseif ($timeOut < (15 * 60)) {
                            
                            $timeOut = $timeOut * 1000;
                        } else {
                            
                            $timeOut = 15 * 60 * 1000;
                        }
                    } else {
                        
                        $forward['url'] = U('Home/Order/orderDetail', array('id' => $order_id));
                        if ($this->platform_code == 'CMBPAY') {
                            $forward['url'] = U('Home/Product/view', array(
                                'id' => $product['p_id']
                            ));
                        } elseif ($this->platform_code == 'LETIANBANG') {
                                
                                $forward['url'] = U('LeTianBang/Index/index');
                            }
                        $forward['one_coin_enable'] = false;
                        $timeOut = 15 * 60 * 1000; // 页面超时时间
                    }
                }
                $total_shipping_cost += (float) ($store['shipping_cost']);
            }
            $total_price = $total_product_price + $total_shipping_cost;
            //商品id
            $this->assign('pid', $pid);
            // 由于某些历史遗留的问题(多个公众号合并成一个)，可能导致zwcmopenid不对，这样会致使支付失败。
            // 解决的办法是： 判断在较短的时间内用户是否会连续按2次购买按钮。如果是，则强制取用户的zwcmopenid
            
            //
            // 微信浏览器中获取openid begin--
            //
            if ($is_wx = is_wx()) {
                
                // 微信支付签名
                $wxApi = new WxApi(C('APP_ID'), C('APP_SECRET'));
                // $unifiedOrder = new UnifiedOrder_pub();
                
                $toRenewZwcmopenid = false;
                $UserModel = D('pt_user');
                if ($this->platform_code == 'LETIANBANG') { // 乐天邦重新获取openid
                                                              // $thezwopenid['zwcmopenid'] = $wxApi->getOpenid();
                    $toRenewZwcmopenid = true;
                } else {
                    // 先查询redis, redis没有再查询数据库
                    $this->redis->databaseSelect('zwid');
                    $session = $_SESSION[$this->platform_code];
                    $thezwopenid = $this->redis->hget('openid_platcode:' . $session['openid'] . '_' . $session['platform_id']);
                    if (empty($thezwopenid['telephone']))
                    	$thezwopenid = $UserModel->where('id=%d', $_SESSION[$this->platform_code]['id'])->find();
                    if (empty($thezwopenid['zwcmopenid'])) // 由于某些原因，有些用户的zwcmopenid在数据库里面没有记录。 邵晓凌， 2016-11-17
                                                           // $thezwopenid['zwcmopenid'] = $wxApi->getOpenid();
                        $toRenewZwcmopenid = true;
                }
                $redis_key_purchase = 'user_clicked_purchase_button_' . $this->platform_code . '_' . $this->user_id;
                if ($toRenewZwcmopenid || $this->redisLog->existsRedis($redis_key_purchase)) {
                    $old_zwcmopenid = $thezwopenid['zwcmopenid']; // 只是为了调试用，今后可以删掉。2016-12-05
                    $thezwopenid['zwcmopenid'] = $wxApi->getOpenid();
                    
                    // 要更新tb_pt_user表,LETIANBANG除外!
                    if (strcasecmp($this->platform_code, 'LETIANBANG') != 0 && ! empty($thezwopenid['zwcmopenid'])) {
                        $update_data = array();
                        $update_data['zwcmopenid'] = $thezwopenid['zwcmopenid'];
                        $UserModel->where('id=%d', $this->user_id)
                            ->limit(1)
                            ->save($update_data);
                    }
                    $this->redisLog->delete($redis_key_purchase);
                    doLog('Order/getZwcmopenid', '强制取zwcmopenid', '', 'old zwcmopenid=' . $old_zwcmopenid . ', ' . $UserModel->getLastSql(), $this->redisLog);
                } 
                $this->redisLog->set($redis_key_purchase, 1, 3 * 60); // 3分钟
                $_SESSION[$this->platform_code]['zwcmopenid'] = $thezwopenid['zwcmopenid'];
                
                $this->assign('is_wx', $is_wx);
            }
            //
            // 微信浏览器中获取openid end--
            //
            $_SESSION[$this->platform_code]['trade_sn'][$order_detail['detail']['ordenum']] = array( // 将订单的信息存在session里。。getprepareData的时候用到
                
                'out_trade_no' => $order_detail['detail']['ordenum'],
                'title' => $title,
                'total_fee' => $total_price
            );
            
            if ($this->platform_code == 'ZHWZ') {
                $this->assign('templet', '4');
                $this->assign('page_title', '官网商城');
            }
            // 完成支付后跳转地址
            $this->assign('forward', $forward);

            $this->assign('order_detail', $order_detail['detail']);
            $this->assign('total_price', $total_price);
            $this->assign('total_product_price', $total_product_price);
            $this->assign('total_shipping_cost', $total_shipping_cost);
            $this->assign('stores', $order_detail['data']);
            $this->assign('address', $order_detail['address']);
            $this->assign('orderNum', $order_detail['detail']['ordenum']);
            // $this->assign('paySign', $paySign);
            $reload = I('get.reload', 1);
            $this->assign('reload', $reload);
            $this->assign('timeOut', $timeOut);

            //支付类型
            $payTypeList = pay_type($this->payment_type);
            $this->assign('payTypeList',$payTypeList);

            //page_title
            if(empty($this->page_title)) {
                
                $this->assign('page_title', '乐天邦 - 确认订单');             
                if ( strcasecmp($this->platform_code, 'MSYHC') == 0 )
                    $this->assign('page_title', '确认订单');
            }
            
            return $this->display("pay");
        } else {}
    }

    public function paySucess()
    {
        $dataJson = array(
            'state' => 1,
            'msg' => '操作成功'
        );
        exit(json_encode($dataJson));
        // if(in_array($_SESSION['PLATFORM_CODE'],$this->noTelList)){ //下单的时候无需验证是否登录
        if (! in_array($this->platform_code, $this->checkTelList)) { // 下单的时候无需验证是否登录
            
            $is_login = true;
        } else {
            
            $is_login = $this->checkLogin();
        }
        if ($is_login) {
            $order_id = I('param.id', 0);
            $pay_type = I('param.pay_type', 0);
            $pay_price = I('param.pay_price', 0);
            $shipping_cost = I('param.shipping_cost', 0);
            $pay_account = I('param.pay_account', 0);
            $remark = I('param.remark', 0);
            $now = date('Y-m-d H:i:s');
            $OrderModel = D('order');
            $order_info = $OrderModel->where('id=%d', $order_id)->select();
            if (isset($order_info[0]) && ! empty($order_info[0])) {
                if ($order_info[0]['pay_status'] != 0 && $order_info[0]['pay_status'] != 1) {
                    $dataJson = array(
                        'state' => 0,
                        'msg' => '订单状态不正确。'
                    );
                    exit(json_encode($dataJson));
                }
            } else {
                $dataJson = array(
                    'state' => 0,
                    'msg' => '订单不存在。'
                );
                exit(json_encode($dataJson));
            }
            $result = $OrderModel->where('id=%d', $order_id)
                ->lock(true)
                ->data(array(
                'pay_status' => 1,
                'pay_type' => $pay_type,
                'pay_price' => $pay_price,
                'shipping_cost' => $shipping_cost,
                'remark' => $remark,
                'pay_account' => $pay_account,
                'pay_time' => $now,
                'updatetime' => $now
            ))
                ->save();
            if (empty($result)) {
                $dataJson = array(
                    'state' => 0,
                    'msg' => '操作失败。'
                );
                exit(json_encode($dataJson));
            } else {
                $dataJson = array(
                    'state' => 1,
                    'msg' => '操作成功'
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
    
    // 支付成功页
    public function payOk()
    {
        //page_title
        if(empty($this->page_title)) {
        
            $this->assign('page_title', '乐天邦 - 支付结果');
            if ( strcasecmp($this->platform_code, 'MSYHC') == 0 )
                $this->assign('page_title', '支付结果');
        }
        $this->display('payok');
    }
    
    // 支付失败成功页
    public function payFail()
    {
        //page_title
        if(empty($this->page_title)) {
        var_dump('支付结果');
            $this->assign('page_title', '乐天邦 - 支付结果');
            if ( strcasecmp($this->platform_code, 'MSYHC') == 0 )
                $this->assign('page_title', '支付结果');
        }
        
        $errorMsg = I('param.errmsg', "未知异常");
        $this->assign("error", $errorMsg);
        $this->display('payfail');
    }

    public function judgeWait($order_id)
    {
        $order = M('order')->table('tb_order o,tb_order_product p ')
            ->field('o.ptid,0.ptuserid,o.pay_status,p.productid,o.ordenum')
            ->where('o.id = p.orderid and o.id = "%s"', array(
            $order_id
        ))
            ->find();
        $productidInfo = M('product')->where('id=%d', $order['productid'])->find();
        if (empty($order)) {
            
            return false;
        }
        
        if ($order['pay_status'] == 2)
            $this->error('该订单已完成,刷新页面更新支付状态', '', 5);
        if ($order['pay_status'] == 4)
            $this->error('已超过可支付时间(15分钟),请重新下单哦！', '', 5);
        
        if ($productidInfo['type_id'] == 4) {
            
            $key = 'waitPayProductid' . $order['productid'] . 'Tradesn:' . $order['ordenum'];
        } else {
            
            $key = 'waitPayPtid' . $order['ptid'] . 'Productid' . $order['productid'] . 'Tradesn:' . $order['ordenum'];
        }
        
        if (online_redis_server) {
            if (empty($this->redis)) {
                
                $this->redis = new \Vendor\Redis\DefaultRedis(C('REDIS_HOST_DEFAULT'), C('REDIS_PORT_DEFAULT'), C('REDIS_AUTH_DEFAULT'));
            }
            $this->redis->databaseSelect('order');
            if (strtotime($productidInfo['end']) < time() && $productidInfo['type_id'] == 4) {
                M('order')->where('id=%d', $order_id)
                    ->lock(true)
                    ->save(array(
                    'pay_status' => 4
                ));
                ;
                $this->error('本期活动已结束,下次早点哦！', '', 5);
            }
            if ($this->redis->exists($key)) {
                
                return true;
            } else {
                
                M('order')->where('id=%d', $order_id)
                    ->lock(true)
                    ->save(array(
                    'pay_status' => 4
                ));
                $this->error('已超过可支付时间(15分钟),请重新下单！', '', 5);
            }
        }
    }

    public function getPrepareData()
    {
        $payType = I('post.payType', 'weixin'); // 支付类型 默认为 微信支付
        $orderid = I('post.id', 0); // 订单id
        $remark = I('post.remark', 0);
        $res = M('order')->where("id=%d", $orderid)->find(); // 针对一个订单号对应一条记录
        
        if (empty($res))
            $this->ajaxReturn(array(
                'status' => 0,
                'msg' => '订单异常'
            )); // 订单不存在
        
        if ($res['pay_status'] == 2)
            $this->ajaxReturn(array(
                'status' => 0,
                'msg' => '订单已经完成'
            ));
        
        $orderNum = $res['ordenum'];
        $orderDetail = $_SESSION[$this->platform_code]['trade_sn'][$orderNum]; // c从session 中读取订单信息
        
        if ($orderNum != $orderDetail['out_trade_no'] || empty($orderDetail))
            $this->ajaxReturn(array(
                'status' => 0,
                'msg' => '订单有误'
            )); // 数据库中订单编号和session中的订单编号不一致的话报错
        
        $saveData = array();
        
        if ($payType == 'weixin') {
            
            if (! is_wx())
                $this->ajaxReturn(array(
                    'satus' => 0,
                    'msg' => '当前不是微信浏览器，请选择其他支付方式'
                )); // 选择微信支付的时候判断是否是微信支付
            
            $saveData['pay_type'] = 1;
        } else if ($payType == 'cmbc') {
                
                $saveData['pay_type'] = 3;
        } else if ($payType == 'ali') {
                
                $saveData['pay_type'] = 2;
        }
        
        $now = date('Y-m-d H:i:s');
        $saveData['pay_status'] = 1;
        $saveData['remark'] = $remark;
        $saveData['pay_time'] = $now;
        $saveData['updatetime'] = $now;
        $orderInfo = M('order')->where("id=%d", $orderid)
            ->limit(1)
            ->find(); // 更新订单的支付类型以及支付状态
        if($orderInfo['pay_type'] == $saveData['pay_type'] && $orderInfo['remark'] == $remark){

            $res = true;
        }else{

            $res = M('order')->where("id=%d", $orderid)
                             ->limit(1)
                             ->save($saveData); // 更新订单的支付类型以及支付状态
        }
        
        
        if ($res) { // 订单状态生成以后 生成密文
            
            $payment = new \Common\Pay\PayFactory($payType, C($payType . 'Pay'));
            $payment->setOrderInfo($orderDetail);
            $getPrepareData = $payment->getPrepareData(); // 获取支付的信息
                                                          // $return = array('status'=>1,'msg'=>'success','data'=>$getPrepareData['data']);
            $this->ajaxReturn($getPrepareData);
        } else {
            
            $this->ajaxReturn(array(
                'status' => 0,
                'msg' => '支付类型修改失败'
            ));
        }
    }

    /**
     * [address 地址设置页面]
     * @ckhero
     * @DateTime 2016-12-22
     * 
     * @return [type] [description]
     */
    public function address()
    {
        $id = I('get.id', 0);
        $orderid = I('get.orderid', 0);
        $addrModel = D('user_mailing_addr');
        $ptuserid = $this->user_id; //$_SESSION[$_SESSION['PLATFORM_CODE']]['id'];
        $result = $addrModel->query("select * from tb_user_mailing_addr where ptuserid = '{$ptuserid}' order by updatetime desc");
        if ($id == 0 && $result[0]['id'] > 0) {
            
            $id = $result[0]['id'];
        }
        $this->assign('addressList', $result);
        $this->assign('id', $id);
        $this->assign('orderid', $orderid);
            
        $this->display();
    }

    /**
     * [orderDetail 订单详情页]
     * @ckhero
     * @DateTime 2017-01-22
     * @return   [type]     [description]
     */
    public function orderDetail()
    {
        $orderid = I('param.id',0,'intval');
        if($orderid == 0) {
            
            $this->error('订单有误，刷新后重试，或者联系工作人员！');
        } else {
            $tablePrefix = C('DB_PREFIX');
            $arr = array(
                $tablePrefix."order_product"=>'op',
                $tablePrefix."order"=>'o',
            );
            $detail = M('order_product')->alias("op")
                                        ->field("op.quality, op.productcode, op.specification, op.productid, o.pay_status, o.tel, o.pay_type, o.pay_price, o.createtime, o.ordenum, o.remark as message, o.addr_id,p.name, p.code, p.remark, p.interfaceaddr, p.verification, p.ifpay, p.extersysdock, p.qrcode_url, p.description, p.brand_id")
                                        ->join($tablePrefix."order as o on op.orderid = o.id")
                                        ->join($tablePrefix."product as p on op.productid = p.id")
                                        //->join($tablePrefix."merchant as m on p.code = m.code")
                                        ->where("op.orderid=%d", $orderid)
                                        ->find();
            $merchant = M('merchant')->alias('m')
                                     ->field('m.merchantimage, m.merchantshort')
                                     ->join("tb_brand as b on m.id=b.merchant_id")
                                     ->where("b.id = '%d'", $detail['brand_id'])
                                     ->cache(3600)
                                     ->find();
            //地址单独取 避免 有的没地址的情况影响数据
            if($detail['addr_id'] > 0) {

                //支付成功的情况下从tb_order_extra里面取
                if ($detail['pay_status'] == 2) {

                    $addrInfoStr = M('order_extra')->field('addr_detail')
                                                   //->cache(3600)
                                                   ->where("order_id = %d", $orderid)
                                                   ->find();
                    $addrInfo = json_decode($addrInfoStr['addr_detail'], true);
                } else {

                    $addrInfo = M('user_mailing_addr')->getById($detail['addr_id']);
                }

                if (isset($addrInfo) && !empty($addrInfo)) {

                    $this->assign('addrInfo', $addrInfo);
                }
            }
            if(!empty($merchant)) {

                $detail = array_merge($detail, $merchant);
            }
            //商户logo处理
            if(empty($detail['merchantimage'])){ 

                $detail['merchantimage'] =  RESOURCE_PATH.'/Public/Home/Resource/Letianbang/images/header.jpg';
               // unset($detail['merchantshort']);
            } else {

                $detail['merchantimage'] = RESOURCE_PATH.$detail['merchantimage'];
            }
            
            //超过支付时间的订单状态修改--begin
            $leftPayTime = time() - strtotime($detail['createtime']);

            if($leftPayTime > 897 && $detail['ifpay'] == 1 && $detail['pay_status'] != 2){

                if($leftPayTime >= 900){  //超过支付时间

                    $detail['pay_status'] = 4;
                    M('order')->where('id = %d', $orderid)->save(array('pay_status' => 4));
                }
                $leftPayTime = 897;
            }
            $leftPayTime = 900-$leftPayTime;
            $this->assign('leftPayTime', $leftPayTime);
            //超过支付时间的订单状态修改--end
            
            $detail['createtime2'] = $detail['createtime'];
            $detail['specification'] = json_decode($detail['specification'], true);
            $detail['createtime'] = date('Y-m-d', strtotime($detail['createtime']));
            $detail['introduction'] = xmlToStr($detail['description'], 'introduction');
            $detail['rules'] = xmlToStr($detail['description'], 'rules');
            $detail['time'] = xmlToStr($detail['description'], 'time');
            if(mb_strlen(trim($detail['time'])) > 30) {

                $detail['times'] = $detail['time'];
                $detail['time'] = '请至详情查看';
            }
            $orderStatus = array();

            if ($detail['ifpay'] == 1) {

                
                if ($detail['pay_status'] == 2) {

                    $detail['pay_status_msg'] = L('_PAY_OK_');
                    $orderStatus['status'] = 1;
                } elseif($detail['pay_status'] == 0 || $detail['pay_status'] == 1) {

                    $detail['pay_status_msg'] = L('_UNPAY_');
                    $orderStatus = array(
                           
                           'status' => 2,
                           'url' => U('Order/pay', array('id' =>$orderid, 'from' => 'user')),
                        );  
                    if(is_wx()) {

                        $orderStatus['url'] = U('Order/pay', array('id' =>$orderid));
                    }             
                } elseif ($detail['pay_status'] == 4) {

                    $detail['pay_status_msg'] = L('_ORDER_CANCEL_');
                    $orderStatus['status'] = 3;
                }

            } else {

                $platformCodeList = D('Platform')->getPlatformCode();
                //抽奖平台处理
                if (in_array($this->platform_code, $platformCodeList)) {
                     //下面这段目的是防止抽奖用户直接进入订单详情
                    //状态为6 的或者创建时间大于截止时间并且距现在为10天前的并且状态为0的为过期订单
                    if ($detail['pay_status']==6 || ($detail['createtime2'] < $this->timeOutLimit && $detail['createtime2'] > $this->timeOutStart && $detail['pay_status'] ==0 )) {

                        $detail['pay_status_msg'] = L('_ORDER_EXPIRED_');
                        $orderStatus['status'] = 3;
                    //领取成功
                    } elseif (($detail['pay_status'] == 2 && $detail['createtime2'] >$this->timeOutStart) || ($detail['tel'] && $detail['createtime2'] <$this->timeOutStart)) {

                        $detail['pay_status_msg'] = L('_RECEIVE_OK_');
                        $orderStatus['status'] = 1;
                    //尚未领取
                    } else {

                        $detail['pay_status_msg'] = L('_UN_RECEIVE_');
                        $orderStatus['status'] = 3;
                    }
                } else {

                    $detail['pay_status_msg'] = L('_RECEIVE_OK_');
                    $orderStatus['status'] = 1;
                }
            }
            
            $this->assign('orderStatus', $orderStatus);
            //详情链接生成，以interfaceaddr的为准
            if( is_url($detail['remark'] )) {
                           
                //interfaceaddr和remark都是连接的情况下  使用remark为详情链接
                $detail['url'] = $detail['remark'];
                
                if (strpos($detail['url'], '{phone}') !== false) {     //如果商家要求url中传手机号码。
                	$session = $_SESSION[$this->platform_code];
                    $detail['url'] = str_replace('{phone}', urlencode(base64_encode($session['tel'])), $detail['url']);
                }

            }
            
            if($_SESSION['PLATFORM_CODE'] == 'MSYHC' && preg_match("/zwmedia\.com\.cn/", $detail['url'])) {

                $detail['url'] = changeHttpsToHttp($detail['url']);
            }
            if($detail['url'] && preg_match('/.*\.(liulishuo|taobao)\.com.*/', $detail['url']) || $this->platform_code == 'MSYHC') {

                $this->assign('isTaobao', true);
            }
            //使用规则
            if(!is_url($detail['remark']) && !empty($detail['remark'])){
                
                //什么都没有的情况下 remak里存着使用规则
                $detail['rule'] = $detail['remark'];
            }
            
            $this->assign('detail', $detail);
            if(empty($this->page_title)){
                $this->assign('page_title', '乐天邦 - 订单详情');
                if ( strcasecmp($this->platform_code, 'MSYHC') == 0 )
                    $this->assign('page_title', '订单详情');
            }
            //首页按钮连接,不管抽奖还是不是抽奖都返回乐天邦
            if ($this->platform_config['PLATFORM_URL']){

                $this->assign('homeUrl', $this->platform_config['PLATFORM_URL']);
            } else {

                $this->assign('homeUrl', U('Home/Index/index', array('platcode', $this->platform_code)));
            }
            $this->display();
        }       
    }
    
    /**
     * [orderCancel 取消订单]
     * @ckhero
     * @DateTime 2017-02-17
     * @return   [type]     [description]
     */
    public function orderCancel()
    {

        $orderid = I('param.orderid', 0, 'intval');
        $orderInfo = M('order')->field('pay_status')
                               ->where('id=%d', $orderid)
                               ->find();
        //订单不存在
        if(empty($orderInfo)) {

            $data = array(
                  
                    'status' => 0,
                    'msg' => L('_ORDER_NO_')
                );
        } else {
            
            //订单状态未支付 可以取消订单
            if($orderInfo['pay_status'] == 1) {

                $res = M('order')->where('id=%d', $orderid)
                                 ->save(array('pay_status'=>4));
                //订单取消成功
                if($res) {

                    $data = array(
                  
                        'status' => 1,
                        'msg' => L('_ORDER_CANCEL_OK_')
                    );
                } else {

                    $data = array(
                  
                        'status' => 0,
                        'msg' => L('_ORDER_CANCEL_FAIL_')
                    );
                }
            //订单状态为支付成功  不能取消订单
            } elseif ($orderInfo['pay_status'] == 2) {

                $data = array(
                  
                        'status' => 0,
                        'msg' => L('_ORDER_CANCEL_FAIL_')
                    );
            //订单状态为已经取消订单
            } elseif ($orderInfo['pay_status'] == 4) {

                $data = array(
                  
                        'status' => 1,
                        'msg' => L('_ORDER_CANCEL_OK_')
                    );

            //订单状态异常
            } else {

                $data = array(
                  
                    'status' => 0,
                    'msg' => L('_ORDER_ABNORMAL_')
                );
            } 
       }

       $this->ajaxReturn($data);
    }
}
