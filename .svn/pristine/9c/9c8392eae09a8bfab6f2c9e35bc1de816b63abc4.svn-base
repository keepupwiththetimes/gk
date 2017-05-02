<?php
namespace Home\Controller;

use Think\Controller;

class UserController extends CommonController
{

    public function index()
    {
        $is_login = $this->checkLogin();
        $page_size = 7;
        //$user_id = $this->user_id; //$_SESSION[$_SESSION['PLATFORM_CODE']]['id'];
        $where = "ptuserid = " . $this->user_id; //$_SESSION[$_SESSION['PLATFORM_CODE']]['id'];
        $order_list = getProductByOrder($this->user_id, 0, $page_size);
        $collect_list = getProductByCollect($where, 0, $page_size);
        //判断是否要显示查看更多按钮
        if(count($order_list) == 7) {

            $this->assign('moreOrder', 1);
            unset($order_list[6]);
        } else {

            $this->assign('moreOrder', 0);
        }

        if(count($collect_list) == 7) {

            $this->assign('moreCollect', 1);
            unset($collect_list[6]);
        } else {

            $this->assign('moreCollect', 0);
        }

        $PlatformProductModel = D('PlatformProduct');
        $order_list_id = array();
        // 处理订单商品数据
        foreach ($order_list as $key => $items) {
            // if ($items['pay_status']==2 || $items['pay_status']==0) {
            // $order_list[$key]['url'] = U('Home/Product/view/id/' . $items['id']);
            // } else {
            
            // $order_list[$key]['url'] = U('Home/Order/pay/id/' . $items['orderid']);
            // }
            
            // if ($items['pay_status']==1) {
            
            // $order_list[$key]['url'] = U('Home/Order/pay/id/' . $items['orderid']);
            // $pay_status_desc = '（待付款）';
            // }elseif($items['pay_status']==2 || $items['pay_status']==0){
            
            // $order_list[$key]['url'] = U('Home/Product/view/id/' . $items['id']);
            // $pay_status_desc = '';
            // } else {
            
            // $order_list[$key]['url'] = U('Home/Product/view/id/' . $items['id']);
            // $pay_status_desc = '（未支付）';
            // }
            
            if ($items['pay_status'] == 1) {
                
                $id = $items['orderid'];
            } else {
                
                $id = $items['id'];
            }
            $payStatus = $this->payStatus($id, $items['pay_status'], $items['type_id'] ,$items['orderid'], $items['createtime']);
            
            // $order_list[$key]['pay_status_desc'] = $items['pay_status']==2 ? "" : "（未付款）";
            $order_list[$key]['pay_status_desc'] = $payStatus['status'];
            $order_list[$key]['url'] = $payStatus['url'];
            // $order_list[$key]['pay_status_desc'] = $pay_status_desc;
            $order_list[$key]['start_str'] = date('Y.m.d', strtotime($items['start']));
            $order_list[$key]['end_str'] = date('Y.m.d', strtotime($items['end']));
            $order_list[$key]['createtime'] = date('Y.m.d H:i', strtotime($items['createtime']));
            $order_list[$key]['product_exist'] = $PlatformProductModel->checkProductExist($items['id']);
            $order_list[$key]['id'] = $items['id'];
            $order_list[$key]['price'] = $items['pay_price'] + $items['shipping_cost'];
            $order_list[$key]['home_picture'] = RESOURCE_PATH . $items['home_picture'];
            $order_list[$key]['name'] = strip_tags($order_list[$key]['name']); // 一元购的商品名有html tag,去掉html tag以便一行显示
            $order_list[$key]['orderDetail'] = U('Home/Order/orderDetail/id/'.$items['orderid'].'/from/user'); // 订单详情链接
        }
        // 处理收藏商品数据
        foreach ($collect_list as $key => $items) {
            $collect_list[$key]['url'] = U('Home/Product/view/id/' . $items['id'].'/from/user');
            $collect_list[$key]['product_exist'] = $PlatformProductModel->checkProductExist($items['id']);
            $collect_list[$key]['createtime'] = date('Y.m.d', strtotime($items['collectiontime']));
            $collect_list[$key]['start_str'] = date('Y.m.d', strtotime($items['start']));
            $collect_list[$key]['end_str'] = date('Y.m.d', strtotime($items['end']));
            $collect_list[$key]['home_picture'] = RESOURCE_PATH . $items['home_picture'];
        }
        if (! $is_login) {
            $order_list = null;
        }
        doLog('User/order', "订单列表", '', '', $this->redisLog);
        $this->assign('is_login', $is_login);
        $this->assign('order_list', $order_list);
        $this->assign('collect_list', $collect_list);

        //page_title
        if(empty($this->page_title)) {

            $this->assign('page_title', '乐天邦 - 个人中心');
            if ( strcasecmp($this->platform_code, 'MSYHC') == 0 )
                $this->assign('page_title', '个人中心');
        }
        $masked_phone_number = empty($_SESSION[$_SESSION['PLATFORM_CODE']]['tel']) ? '点击此处登陆' : substr_replace($_SESSION[$_SESSION['PLATFORM_CODE']]['tel'], '****', 3,4);
        $this->assign('masked_phone_number',$masked_phone_number);
        $this->display('index');
    }
    
    // 登录登出 弃用
    // 沈子皓 2016-07-26
    // public function log(){
    // $is_login = $this->checkLogin();
    // if($is_login) $this->display('logout');
    // else $this->display('login');
    // }
    // public function logout(){
    // doLog('User/logout', "会员退出");
    // unset($_SESSION[$_SESSION['PLATFORM_CODE']]['tel']);
    // return 'logout';
    // }
    
    /*
     * 加载订单商品信息
     * 沈子皓
     * @param page 分页页号
     * @return 商品信息
     */
    public function order()
    {
        $cur_page = I('param.page', 1, 'intval');
        $page_size = 6;
        $is_login = $this->checkLogin();
        $start = ($cur_page - 1) * $page_size;
        $this->user_id = $this->user_id; //$_SESSION[$_SESSION['PLATFORM_CODE']]['id'];
        $product_list = getProductByOrder($this->user_id, $start, $page_size);
        $PlatformProductModel = D('PlatformProduct');
        if (! $is_login) {
            $json_data = array(
                'state' => 0,
                'msg' => '请先登录！'
            );
            exit(json_encode($json_data));
        } else {
            $product_list_id = array();
            // 处理商品信息
            foreach ($product_list as $key => $items) {
                // if ($items['pay_status']==2 || $items['pay_status']==0) {
                // $product_list[$key]['url'] = U('Home/Product/view/id/' . $items['id']);
                // } else {
                // $product_list[$key]['url'] = U('Home/Order/pay/id/' . $items['orderid']);
                // }
                
                if ($items['pay_status'] == 1) {
                    
                    $id = $items['orderid'];
                } else {
                    
                    $id = $items['id'];
                }
                $payStatus = $this->payStatus($id, $items['pay_status'], $items['type_id'] ,$items['orderid'], $items['createtime']);
                // $product_list[$key]['pay_status_desc'] = $items['pay_status']==2 ? "" : "（未付款）";
                $product_list[$key]['pay_status_desc'] = $payStatus['status'];
                $product_list[$key]['url'] = $payStatus['url'];
                $product_list[$key]['start_str'] = date('Y.m.d', strtotime($items['start']));
                $product_list[$key]['end_str'] = date('Y.m.d', strtotime($items['end']));
                $product_list[$key]['createtime'] = date('Y.m.d H:i', strtotime($items['createtime']));
                $product_list[$key]['product_exist'] = $PlatformProductModel->checkProductExist($items['id']);
                $product_list[$key]['id'] = $items['id'];
                $product_list[$key]['price'] = $items['pay_price'] + $items['shipping_cost'];
                $product_list[$key]['home_picture'] = RESOURCE_PATH . $items['home_picture'];
                $product_list[$key]['name'] = strip_tags($product_list[$key]['name']); // 一元购的商品名有html tag,去掉html tag以便一行显示
                $product_list[$key]['orderDetail'] = U('Home/Order/orderDetail/id/'.$items['orderid']); // 订单详情链接
            }
            if ($product_list) {
                doLog('Member/collect', "订单加载更多", '', json_encode($product_list_id), $this->redisLog);
                $json_data = array(
                    'state' => 1,
                    'msg' => '加载完成',
                    'list_data' => $product_list
                );
                // var_dump($product_list);
                exit(json_encode($json_data));
            } else {
                doLog('Member/collect', "订单加载更多-全部加载", '', '', $this->redisLog);
                $json_data = array(
                    'state' => 0,
                    'msg' => '没有内容可加载了！'
                );
                exit(json_encode($json_data));
            }
        }
    }

    /*
     * 加载收藏商品信息
     * 沈子皓
     * @param page 分页页号
     * @return 商品信息
     */
    public function collect()
    {
        $cur_page = I('param.page', 1, 'intval');
        // 获取page
        $page_size = 6;
        $start = ($cur_page - 1) * $page_size;
        $is_login = $this->checkLogin();
        $PlatformProductModel = D('PlatformProduct');
        $where = "ptuserid = " . $this->user_id; //$_SESSION[$_SESSION['PLATFORM_CODE']]['id'];
        // 获取收藏商品信息
        $product_list = getProductByCollect($where, $start, $page_size);
        $product_list_id = array();
        // 处理商品信息
        foreach ($product_list as $key => $items) {
            $product_list[$key]['url'] = U('Home/Product/view/id/' . $items['id'].'/from/user');
            $product_list[$key]['product_exist'] = $PlatformProductModel->checkProductExist($items['id']);
            $product_list[$key]['createtime'] = date('Y.m.d', strtotime($items['collectiontime']));
            $product_list[$key]['start_str'] = date('Y.m.d', strtotime($items['start']));
            $product_list[$key]['end_str'] = date('Y.m.d', strtotime($items['end']));
            $product_list[$key]['home_picture'] = RESOURCE_PATH . $items['home_picture'];
        }
        if ($product_list) {
            doLog('Member/collect', "收藏加载更多", '', json_encode($product_list_id), $this->redisLog);
            $json_data = array(
                'state' => 1,
                'msg' => '加载完成',
                'list_data' => $product_list
            );
            exit(json_encode($json_data));
        } else {
            doLog('Member/collect', "收藏加载更多-全部加载", '', '', $this->redisLog);
            $json_data = array(
                'state' => 0,
                'msg' => '没有内容可加载了！'
            );
            exit(json_encode($json_data));
        }
    }

    public function payStatus($id, $status, $type_id = 1, $orderId = 0, $createtime = 0)
    {   
        //订单超时
        if((time() - strtotime($createtime)) > 900 && $status !=2) { 

            $status = 4;
        }
        if ($status == 1) {
            
            if ($type_id == 1)
                $data['url'] = U('Home/Order/pay/id/' . $id);
            else
                $data['url'] = U('Home/Order/pay/', array(
                    'id' => $id,
                    'sid' => session_id()
                )); // 为一元购的订单的时候还需传入session_id
            $data['status'] = '（待付款）';
        } elseif ($status == 2 || $status == 0) {
            
            if($status == 0 ){

                $data['url'] = U('Home/Product/view/id/' . $id.'/source/user/orderId/'.$orderId);
                $data['status'] = '';
            }else{

                $data['url'] = U('Home/Product/view/id/' . $id);
                $data['status'] = "(".L('_PAY_OK_').")";
            }
            
        } elseif ($status == 4 ) {
                
                $data['url'] = U('Home/Product/view/id/' . $id);
                $data['status'] = "(".L('_ORDER_CANCEL_').")";
        } elseif ($status == 5) {
                    
            $data['url'] = U('Home/Product/view/id/' . $id);
            $msg = '在您支付完成前活动已结束,本次购买视为无效,申请退款号码为021-62457922';
            $data['status'] = "<a onclick=showMsg('" . $msg . "')>申请退款</a>";
        }
        return $data;
    }
}
