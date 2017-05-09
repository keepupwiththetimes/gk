<?php
namespace Home\Controller;

use Think\Controller;
use Home\Model\PlatformProductModel;

class ProductController extends CommonController
{

    public function __construct()
    {
        // 获取城市列表
        parent::__construct();
    }
    
    // 商品列表
    public function lists()
    {
        $type = I('param.type', 1);
        //$three_cityid = I('param.three_cityid', '');
        $ProductModel = D('PlatformProduct');
        //$three_city_list = getAreaByParentName($_SESSION[$_SESSION['PLATFORM_CODE']]['city']);
        $product_list = $ProductModel->getProductList($this->platform_id, $type, 0, 6);
        $product_list_id = array();
        foreach ($product_list as $key => $items) {
            $product_list_id = $items['id'];
        }
        $data['goods'] = $product_list_id;
        $this->assign('type', $type);
        $this->assign('product_list', $product_list);
        //$this->assign('three_city_list', $three_city_list);
        doLog('Product/lists', "商品列表", '', json_encode($data), $this->redisLog);
        $this->display('lists');
    }
    
    // 商品详情
    public function view()
    {
        $id = I('param.id', 0, 'intval');
        $ajax = I('param.ajax', 0);
        $click_num = I('param.click_num', - 1);
        $source = I('param.source', '' );
        $orderId = I('param.orderId', '' ,'intval');

        // 获取商品信息
        $product_info = getProductInfo($id, $this->platform_id);
        if (empty($product_info) || $product_info['type_id'] == 4) {
            
            // 一元购
            $res = M('one_coin_product')->field('stage')
                ->where('one_coin_pid=%d', array(
                $id
            ))
                ->find();
            if ($res['stage'] > 0)
                redirect(ONE_COIN_URL . "index.php/Home/OneCoin/index/sid/" . session_id() . "/id/" . $res['stage']);
            $this->redirect('Index/index', array(
                'id' => $res['stage']
            ));
        }
        // ///////判断是否是招行的置顶项 2017-07-01 shenzihao
        if ($product_info['type_id'] == '6') {
            // $where['id'] = $id;
            // $ProductModel = D('product');
            // $url = $ProductModel->where($where)->limit(1)->getField('description');
            redirect($product_info['description']);
        }
        
        //$is_point = checkPoint($id);  ////不再使用，邵晓凌：2017-03-17
        $is_collect = checkCollect($this->user_id, $this->platform_id, $id);
        $PlatformProductModel = D('PlatformProduct');
        $product_info['product_exist'] = $PlatformProductModel->checkProductExist($id);
        $product_info['detail_picture'] = RESOURCE_PATH . $product_info['detail_picture'];
        // 解析description中的XML数据
        $product_info['introduction'] = xmlToStr($product_info['description'], 'introduction');
        $product_info['rules'] = xmlToStr($product_info['description'], 'rules');
        $product_info['time'] = xmlToStr($product_info['description'], 'time');
        // 解析商品参数
        $product_info['specification'] = $product_info['specification'] ? json_decode($product_info['specification'], true) : '';
        //如果是民生银行要将网站域名下的连接中的https换成http
       if($this->platform_code == 'MSYHC') {

            $product_info['remark'] = changeHttpsToHttp($product_info['remark']);
            $product_info['interfaceaddr'] = changeHttpsToHttp($product_info['interfaceaddr']);
            $product_info['introduction'] = changeHttpsToHttp(xmlToStr($product_info['description'], 'introduction'));
            $product_info['rules'] = changeHttpsToHttp(xmlToStr($product_info['description'], 'rules'));
        }
        // // 分享参数
        // $share['title'] = $product_info['name'];
        // $share['desc'] = '天天发福利，快来占便宜！';
        // //$share['link'] = 'http://uat.zwmedia.com.cn/wxzspfse/?platcode='.$_SESSION['PLATFORM_CODE'].'&shareid='.$id;
        // $share['link'] = HOST_URL.U('Product/view',array('id'=>$id,'platcode'=>$_SESSION['PLATFORM_CODE']));
        // $share['imgUrl'] = $product_info['detail_picture'];
        // $share['product_id'] = $id;
        // $this->assign('SHARE', $share);
        //$this->assign('is_point', $is_point); //不再使用，邵晓凌：2017-03-17
        $this->assign('is_collect', $is_collect);
        $this->assign('info', $product_info);
        $this->assign('platform_id', $this->platform_id);
        $cutomerServiceTel = getCustomerServiceTel($id, $this->redis);
        if (empty($cutomerServiceTel))
            $cutomerServiceTel = "021-62457922"; // 如果商户没有客服电话，则把乐天邦的客服电话展示给用户
        $this->assign('cutomer_service_tel', $cutomerServiceTel);
        $info['id'] = $id;
        $info['click_num'] = $click_num;
        doLog('Product/view/loganalysis', "商品详情", $id, json_encode($info), $this->redisLog);
        //用户点击该商品则商品当前分类分数+1分
        $category_id = $product_info['category_id'];
        //将用户id 和 分类id 和 对应分数转为json字符串
        //$zwcmopenid = $_SESSION[$this->platform_code]['zwcmopenid'];

        //记录
        log_user_category_score($this->zwid, $category_id, C('CLICK_ACTION'),$this->redisLog);
        //判断是否是从 订单页面过来的
        if ( $source == 'user' || $product_info['num_per_span']==1 ) {

            $rData = array(
                  
                  0 => $id,
                  1 => $this->user_id,
                  2 => $this->platform_id,
                  3 => $product_info['repeat_span'],
                  4 => $product_info['num_per_span'],
            );
            $isGet = R('Member/checkOrder', $rData);
            if($isGet['status'] == 2){    //不能再领取了
                
                if($isGet['orderid'] && empty($orderId)){

                    $orderId = $isGet['orderid'];
                }
                $checkInfo = array(
                    
                    'show' => 1,
                    'orderId' => $orderId,
                    'showType' => $product_info['verification'],
                );
                //核销方式三
                if($product_info['verification'] == 3) {
                    
                    if($product_info['remark']){

                        $checkInfo['msg'] = '优惠活动链接：'.$product_info['remark']." (3秒后跳转)";
                    }else{

                        $checkInfo['msg'] = '亲爱的客户，您已经领取过一张了。如果要了解使用规则请查看产品介绍.';
                    }

                } elseif ($product_info['verification'] == 1) {

                   $checkInfo['smstpl'] = $product_info['smstpl'];
                }
                $this->assign('checkInfo', $checkInfo);
            }
            
        }

        //按钮信息生成
        $buttonInfo = $this->getButtonInfo($product_info['product_exist'], $product_info['verification'], $checkInfo['show']);
        $this->assign('buttonInfo', $buttonInfo);
        setcookie($product_info['id'] . 'status', '', time()-3600);
        setcookie($product_info['id'] . 'orderid', '', time()-3600);
        setcookie($product_info['id'] . 'buttonid', '', time()-3600);
        setcookie('collected' . $product_info['id'] , '', time()-3600);
        if ($ajax == 1) {
            if (empty($product_info['product_exist'])) {
                echo json_encode(array(
                    'state' => 0,
                    'data' => $product_info
                ));
            } else {
                echo json_encode(array(
                    'state' => 1,
                    'data' => $product_info
                ));
            }
        } else {
            
            //page_title
            if(empty($this->page_title)) {

                $this->assign('page_title', '乐天邦 - 产品详情');
                if ( strcasecmp($this->platform_code, 'MSYHC') == 0 )
                    $this->assign('page_title', '商品详情');
            }

            //适用门店
            if(!$info['ifsore']) {

                $shop_id = $this->getMerchantInfo($id);
                $this->assign('shopList', $shop_id);
            }
            // 若为招行或者需要购买的商品
            if (($this->platform_code == 'ZHWZ' || $this->platform_code == 'NYYH') && $product_info['price'] > 0 && $product_info['ifpay'] == 1) {
                $page = I('page', 1);
                if ($page == '1') {

                    $this->assign('isFakeBuy', 1);
                    $this->display('view');
                } else {
                    $this->assign('templet', '4');
                    $this->assign('page_title', '官网商城');
                    $this->display('viewbuy');
                }
            }  else if ($product_info['price'] > 0 && $product_info['ifpay'] == 1) { // 需要购买
                    
                    $this->display('viewbuy'); // 购买页面
            } else {  // 领取页面
                    
                    $this->assign('isreceive', '1');
                    $this->display('view');
            }
        }
    }

    //2017/03/23 商品领取后点击返回对应的返回页面的商品状态改变和弹框隐藏
    //商品状态的查询
    public function checkProductStatus()
    {

        $id = I('param.id', 0, 'intval');
        $ajax = I('param.ajax', 0);
        $click_num = I('param.click_num', - 1);
        $source = I('param.source', '' );
        $orderId = I('param.orderId', '' ,'intval');

        // 获取商品信息
        $product_info = getProductInfo($id, $this->platform_id);

        $is_collect = checkCollect($this->user_id, $this->platform_id, $id);

        $PlatformProductModel = D('PlatformProduct');
        $product_info['product_exist'] = $PlatformProductModel->checkProductExist($id);

        //判断是否是从 订单页面过来的
        if ( $source == 'user' || $product_info['num_per_span']==1 ) {

            $rData = array(
                  
                  0 => $id,
                  1 => $this->user_id,
                  2 => $this->platform_id,
                  3 => $product_info['repeat_span'],
                  4 => $product_info['num_per_span'],
            );

            $isGet = R('Member/checkOrder', $rData);

            if($isGet['status'] == 2){    //不能再领取了
                
                if($isGet['orderid'] && empty($orderId)){

                    $orderId = $isGet['orderid'];
                }
                $checkInfo = array(
                    
                    'show' => 1,
                    'orderId' => $orderId,
                    'showType' => $product_info['verification'],
                );
                //核销方式三
                if($product_info['verification'] == 3) {
                    
                    if($product_info['remark']){

                        $checkInfo['msg'] = '优惠活动链接：'.$product_info['remark']." (3秒后跳转)";
                    }else{

                        $checkInfo['msg'] = '亲爱的客户，您已经领取过一张了。如果要了解使用规则请查看产品介绍.';
                    }

                } elseif ($product_info['verification'] == 1) {

                   $checkInfo['smstpl'] = $product_info['smstpl'];
                }
                //$this->assign('checkInfo', $checkInfo);
            }
            
        }
        
        $buttonInfo = $this->getButtonInfo($product_info['product_exist'], $product_info['verification'],$checkInfo['show']);
        
        exit(json_encode($buttonInfo));

    }

    public function search()
    {
        $keywords = I('param.keywords', '');
        $ProductModel = D('PlatformProduct');
        
        $product_list_1 = $ProductModel->searchProductLists($keywords, 1, 0, 12);
        $product_list_2 = $ProductModel->searchProductLists($keywords, 2, 0, 12);
        
        $this->assign('product_list_1', $product_list_1);
        $this->assign('product_list_2', $product_list_2);
        doLog('Product/search', "商品搜索-" . $keywords, '', '', $this->redisLog);
        $this->display('search');
    }

    
    /**
     * [queryOrderDetail 查看订单信息]
     * @ckhero
     * @DateTime 2017-01-11
     * @return   [type]     [description]
     */
    public function queryOrderDetail ()
    {

        $orderId = I('get.orderId', 0, 'intval');
        // $smstpl = I('get.smstpl', '');
        $info = M('order_product')->where('orderid = %d', $orderId)->find();
        
        if($info['productcode']){
            
            $data = array(
                    
                'status' => 1,
                'msg' => '获取成功',
                'data' => "您的券码为: ".$info['productcode'],
            );
        } else {

            $data = array(
                    
                'status' => 2,
                'msg' => '订单有误,可联系管理员',
            );
        }

        $this->ajaxReturn($data);
    }

    /**
     * [getButtonInfo 获取详情页按钮信息]
     * @ckhero
     * @DateTime 2017-01-17
     * @param    integer    $product_exist [商品状态判断]
     * @param    integer    $verification  [商品核销方式]
     * @param    integer    $show          [从个人中心进来的，领取次数只有一次且领取过的商品]
     * @return   [type]                    [description]
     */
    public function getButtonInfo($product_exist = 1, $verification = 0, $show = 0)
    {
        //商品活动已结束
        if ($product_exist == 0 || $product_exist == -2) {

            $buttonInfo =  array(
                
                'id' =>'',
                'name' => '已结束',
            );

        //商品已售完
        } elseif ($product_exist == -1){

            $buttonInfo =  array(
                
                'id' =>'',
                'name' => '已售完',
            );
        //商品未售完 且动未结束
        } else {
            
            //跳到合作方购买的。(类似一号店)
            if ($verification == 5) {

                $buttonInfo =  array(
                
                    'id' =>'receive',
                    'name' => '立即购买',
                );

            //个人中心过来领取只有一次并且已经领取的
            } elseif ($show == 1) {

                $buttonInfo =  array(
                
                    'id' =>'check',
                    'name' => '查 看',
                );
            //正常情况
            } else {

                $buttonInfo =  array(
                
                    'id' =>'receive',
                    'name' => '领 取',
                );
            }
        }

        return $buttonInfo;
    }

    private function getMerchantInfo($product_id)
    {
        
        $ProductModel = M('product');
        $shop_list = $ProductModel->alias('p')
                                ->join('__BRAND__ b on p.brand_id = b.id')
                                ->join('__MERCHANT__ m on m.id=b.merchant_id')
                                ->where("p.id=%d", $product_id)
                                ->limit($limit_start, $page_size)
                                ->field('p.name,m.*')
                                ->cache(true, 300)
                                ->select();
            return $shop_list;
    }
}
