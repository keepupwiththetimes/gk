<?php 
namespace LeTianBang\Controller;

use Think\Controller;
use Common\Verify\TelVerify;
class IndexController extends LeCommonController{
    
    public function __construct(){

        parent::__construct();
        $this->cache = false;
        $this->resetUrl = "LeTianBang/Index/detail/id/";
    }
    //首页
	public function index(){
        
        $productModel =  D('Product');
        $tel = $_SESSION[$this->platformCode]['tel'];
        if($tel){//登录状态下

           $selfProductList = $this->getSelfProductListWithShop($tel);
           if($selfProductList[0]['id']) $this->assign('selfBefore',true);
        }

        $PlatformIdLottery = $productModel->getPlatformIdLottery($this->redis);

        $allList = $productModel->getProductWithPlatformName(' and fp.platform_id not in ('.$PlatformIdLottery.')',0,5,$this->cache);
        $allList = resetProductList($allList,$this->resetUrl,'all');
        $this->assign('selfProductList',$selfProductList);
        $this->assign('allList',$allList);
        foreach($allList as $key=>$val){

            $goodsList[] = $val['id'];
        }
        doLog('LeTianBang/indexAnalysis', '访问乐天邦首页','',json_encode($goodsList),$this->redisLog);
		$this->display();
	}
/**
 * [login 登录页面]
 * @ckhero
 * @DateTime 2016-09-30
 * @return   [type]     [description]
 */
	public function login(){

	    if(I('get.submit') != 'submit'){
            
            $forward = I('get.forward');
            $forward = !empty($forward)?$forward:$_SERVER['HTTP_REFERER'];
            $this->assign('forward',$forward);
	    	$this->display();
	    }else{
            
            $code = I('get.code','',intval);
            $tel = I('get.tel','',intval);
            $type = 'error';
            $state = 1;
            if(!is_tel($tel)){

                $info = '手机号码格式不对';
            }else{

                $TelVerify = new TelVerify(array('tel'=>$tel));
                $verify = $TelVerify->check($code);  
                if($verify==0){
                    
                    

                    $data = array(
                           'id' => $_SESSION[$this->platformCode]['id'],
                           'openid' => $_SESSION[$this->platformCode]['openid'],
                           'tel' => $tel,
                           'platformId' => $_SESSION[$this->platformCode]['platform_id'],
                        );
                    $res = $this->update_tel($data,$this->redis);
                    if($res == 0 ){
                        
                        doLog('LeTianBang/login', '登录成功','','',$this->redisLog);
                        $_SESSION[$this->platformCode]['tel'] = $tel;
                        $info = '登录成功';
                        $type = 'success';
                    }else{
                        
                        doLog('LeTianBang/login', '登录失败原因：号码更新失败','','',$this->redisLog);
                        $info = '登录失败';
                    }
                }else if($verify == 1){
                    
                    $info = '验证码不正确，请重新输入';
                }else if($verify == 2){

                    $info = '验证码已过期，请重新获取';
                }
            }

            $this->ajaxReturn(array('info'=>$info,'type'=>$type));
	    }
	}

    public function verify(){
        
        $tel = I('get.tel');
        $TelVerify = new TelVerify(array('tel'=>$tel));
        $code = $TelVerify->entry();
        $send_res = send_msg($tel,'本次登录的验证码为：'.$code.',有效期为15分钟。');
        doLog('LeTianBang/verify', '验证码发送结果通知:'.$send_res,'','验证码为：'.$code,$this->redisLog);
        $this->ajaxReturn(array('code'=>$code));
        
    }
/**
 * [detail 详情页]
 * @ckhero
 * @DateTime 2016-09-30
 * @return   [type]     [description]
 */
	public function detail(){
        
        $id = I('param.id','',intval);
        //$productInfo = M('product')->where(array('id'=>$id))->cache(300)->find();
        $productInfo = M('product')->where("id=%d", $id)->cache(300)->find();
        if(empty($productInfo)) $this->error('商品不存在');
        $productInfo['status']  = D('Home/Product')->checkProductExist($id);
        $productInfo['pic'] = RESOURCE_PATH.$productInfo['detail_picture'];
        if($productInfo['ifstore']==0){
            
            //获取商户网站地址
            $productInfo['shopUrl'] = getShopUrl($productInfo['id']);
        }
        //解析description中的XML数据
        $productInfo['introduction']  = xmlToStr($productInfo['description'],'introduction');
        $productInfo['rules']         = xmlToStr($productInfo['description'],'rules') ;
        $productInfo['time']          = xmlToStr($productInfo['description'],'time') ;
        //解析商品参数
        $productInfo['specification'] = $productInfo['specification']?json_decode($productInfo['specification'],true):'';
        $this->assign('productInfo' ,$productInfo);
        doLog('LeTianBang/detail', '访问乐天邦公众号的详情页',$id,'',$this->redisLog);
	    $this->display();
	}


    public function update_tel($data,$redis){

        $userInfo = M('pt_user')->where("id=%d",$data['id'])->find();
        $msg = 1; //默认登录失败
        if($userInfo['openid'] == $data['openid'] && !empty($data['openid'])){
            
            if($userInfo['telephone'] == $data['tel']){

                $msg = 0;
            }else{   //号码相同无需更新

                $res = M('pt_user')->where("id=%d",$data['id'])->limit(1)->save(array('telephone'=>$data['tel']));
                doLog('LeTianBang/update_tel', '手机验证之后号码更新','',M('pt_user')->getLastSql(),$this->redisLog);
                $userInfo['telephone'] = $data['tel'];
                if($res){
                    
                     $redis->databaseSelect('zwid');
                     $redis->hset('openid_platcode:'.$data['openid'].'_'.$data['platformId'],$userInfo);
                     $redis->expire('openid_platcode:'.$data['openid'].'_'.$data['platformId'],24*3600);
                     $msg = 0;
                }
            }
        }
        return $msg;
    }

/**
 * [getMore 获取更多]
 * @ckhero
 * @DateTime 2016-10-08
 * @return   [type]     [description]
 */
    public function getMore(){
        
        $productModel =  D('Product');
        $type = I('param.type');
        $start = I('param.start');
        $end = 5;
        if($type == 'self'){

            //$List = $productModel->getSelfProductListWithShop($_SESSION[$this->platformCode]['tel'],$start,$end);
            $List = $this->getSelfProductListWithShop($tel,$start,$end);
        }elseif($type=='all'){
             
            $currTime = date('Y-m-d H:i:s');

            $PlatformIdLottery = $productModel->getPlatformIdLottery($this->redis);
            $List = $productModel->getProductWithPlatformName(' and fp.platform_id not in ('.$PlatformIdLottery.')',$start,$end,$this->cache);

           // $ProductIdLottery = $productModel->getProductIdLottery($this->redis);
            //$List = $productModel->getProductWithPlatformName(' and p.id not in ('.$ProductIdLottery.')',$start,$end,false);
            $List = resetProductList($List,$this->resetUrl,$type);
        }
        foreach($List as $key=>$val){

            $goodsList[] = $val['id'];
        }
        doLog('LeTianBang/getMoreAnalysis', '乐天邦公众号加载更多','',json_encode($goodsList),$this->redisLog);
        $data['status'] = 1;
        $data['info'] = $productModel->getLastSql();
        if(count($List) > 0 ){
            
            $data['status'] = 0 ;
            $data['list'] = $List;
        }

        $this->ajaxReturn($data);
    }

    public function search(){
        
        $productModel =  D('Product');
        $key = I('param.key','','htmlspecialchars');
        $submit  =I('param.submit');
        $start = I('param.start',0,'intval');
        $end = $start + 5;
        if($submit  == 'submit'){
             
            $PlatformIdLottery = $productModel->getPlatformIdLottery($this->redis);
            $currTime = date('Y-m-d H:i:s');
            $List = $productModel->getProductWithPlatformName(" and fp.platform_id not in (".$PlatformIdLottery.") and p.name like '%".$key."%'",$start,$end,$this->cache);
            $data['1231'] = $List;
            if(count($List) > 0 ){
            
                $data['status'] = 0 ;
                $data['list'] = resetProductList($List,$this->resetUrl);
            }else{

                $data['status'] = 1;
            }
        }
         $data ['info'] = M('platform')->getLastSql();
        $this->ajaxReturn($data);
    }

/**
 * [getSelfProductListWithShop 对我的优惠券做缓存]
 * @ckhero
 * @DateTime 2016-10-25
 * @param    [type]     $tel   [description]
 * @param    integer    $start [description]
 * @param    integer    $end   [description]
 * @return   [type]            [description]
 */
    public function getSelfProductListWithShop($tel,$start=0,$end=5){
       
        $ptuserid = $_SESSION[$this->platformCode]['id']; 
        $listKey = 'LETIANBANG::selfProductList_id:'.$ptuserid;
        $data = array();
        $productModel = D('Product');
        $this->redis->databaseSelect('LeTianBang');

        //判断是否有新的订单生成
        if($this->redis->get("LETIANBANG::refresh_id".$ptuserid) !=1 && $start==0 && $this->redis->exists($listKey)){

            $res = M('order')
               ->table('tb_order as o')
               ->field('o.ptid,o.id as orderid,op.productid')
               ->join('tb_order_product op on o.id = op.orderid')
               ->where('o.ptuserid in (%s) and op.createtime>"%s" and o.pay_status in (0,2)',array(getUserByTel($tel,$this->cache,','),date('Y-m-d H:i:s',time()-300)))
               ->select();
               if($res['0']['ptid']){

                foreach($res as $Key=>$val){
                    
                    $k  = "LETIANBANG::detail_ptid_orderid_productid:".$val['ptid'].'_'.$val['orderid'].'_'.$val['productid'];
                    if(!$this->redis->exists($k)) $this->redis->lset($listKey,$k,'L');
                }
                $this->redis->expire($listKey,300);
               }
               $this->redis->set("LETIANBANG::refresh_id".$ptuserid,1);
               $this->redis->expire("LETIANBANG::refresh_id".$ptuserid,10);   //每十秒读取一次订单数据
        }
        $productIdList = $this->redis->lget($listKey,'R',$start,$start+$end-1);
        if($start>0 && empty($productIdList)){

            $data = array(); //没有更多
        }elseif($start == 0 && empty($productIdList)){

            $productList = $productModel->getSelfProductListWithShop($tel,'',0,0);
            $productList = resetProductList($productList,$this->resetUrl,'self'); //重构商品
            $saleId = array();
            $unSaleId = array();
            $sale = array();
            $unSale = array();
            foreach($productList as $key=>$val){   //排序 下架的放在后面
                
                $detailKey = "LETIANBANG::detail_ptid_orderid_productid:".$val['ptid'].'_'.$val['orderid'].'_'.$val['id'];
                if($val['status'] == 1){

                    $sale[] = $val;
                    $saleId[] = $detailKey;
                }else{

                    $unSale[] = $val;
                    $unSaleId[] = $detailKey;
                }
                $this->redis->hset($detailKey,$val);//做缓存
                $this->redis->expire($detailKey,500);//过期时间500s
            }
            $productList = array_merge($sale,$unSale);
            $productIdList = array_merge($saleId,$unSaleId);
            $data = array_slice($productList, 0,5);
            $this->redis->lset($listKey,$productIdList,'R');
            $this->redis->expire($listKey,300);
            return $data;
        }else{
            if(!empty($productIdList[0])){
                
                foreach($productIdList as $key=>$val){

                    $detail = $this->redis->hget($val);
                    if(empty($detail)){

                        $res = $productModel->getSelfProductListWithShop($tel,' and p.id ='.substr($val, strrpos($val,'_')+1));
                        $res = resetProductList($res,$this->resetUrl,'self'); //重构商品
                        $detail = $res[0];
                        $this->redis->hset($val,$detail);
                        $this->redis->expire($val,500); 
                    }

                    $data[] = $detail;
                }
            }
            return $data;
        }
        
    }
/**
 * [doOrder 用户下单系统]
 * @ckhero
 * @DateTime 2016-10-26
 * @return   [type]     [description]
 */
       public function doOrder() {
         
         $product_id    = I('post.product_id', 0);
         $platform_id   = I('post.ptid',0,'intval');
         $pay_price     = I('post.pay_price',0,'float');
         $quality       = I('post.quality', 1);
         $pay_type      = I('post.pay_type', 0);
         $addr_id       = I('post.addr_id', 0);
         $type_id       = I('post.type_id',1);
         $specification = htmlspecialchars_decode(I('post.info',null));

         $tel  = $_SESSION[$this->platformCode]['tel'];


        //判断是否是中奖商品
        if($_SESSION['PLATFORM_CODE'] =='LETIANBANG'){
            
            //获取抽奖平台的id
            $lotteryPtList = M('platform')->field('id')->where(array('name'=>array('like',array('%抽奖%','%转盘%'))))->select();
            // $lotteryPtList = array_column($lotteryPtList,'id');
            foreach($lotteryPtList as $kk =>$vv){

                $lotteryPtList2[] = $vv['id'];
            }
            if(in_array($ptid,$lotteryPtList2)){
                
                $data = array();
                $data = array('state' => 0, 'msg' => '亲爱的客户，这是抽奖商品无法领取。');
                $this->ajaxReturn($data);
            }
        }
        
        $is_login = $this->checkLogin();

        if ($is_login) {
            
            //判断用户是否可以进行购买操作  
            $is_buy = $this->checkBuy($platform_id,$tel);
            if(!$is_buy) $this->ajaxReturn(array('state'=>4,'msg'=>'无法进行购买操作'));

            $ProductModel = M('Product');
            $product_info = $ProductModel->where(array('id' => $product_id))->find();  //不能加cache
            $PlatformModel = M('Platform');
            $platform_info = $PlatformModel->where(array('id' => $platform_id))->cache(true, 300)->find();
            if(is_array(json_decode($product_info['specification'],true))) $this->ajaxReturn(array('state'=>3,'msg'=>'该商品需要选择规格，请前往【'.$platform_info['name'].'】购买'));

            // 检查用户是否在该平台领过该优惠券
            $is_get = $this->checkOrder($product_id, $is_buy['id'], $platform_id);
            if (!$is_get&&empty($pay_price)) {
                if ($platform_info['code']=='MSYH') {
                    $dataJson = array('state' => 0, 'msg' => '亲爱的客户，您已经领取一张了，不能重复领取。');
                } else {
                    $dataJson = array('state' => 0, 'msg' => '亲爱的客户，您已经领取一张了，不能重复领取，领取后会有短信通知，也可至我的优惠券查看。');
                }
                $this->ajaxReturn($dataJson);
            }

            $PlatformProductModel = D('Home/PlatformProduct');

            //区分是否是一元购
            if($type_id==4){

                $isExistArr = D('product')->checkProductExist($product_id,$quality);
                $isExist = $isExistArr['state'];
                if($isExist == 2){

                   $msg = '亲爱的用户，你还能购买'.$isExistArr['quantity'].'份，请重新选择购买数量！';
                }else{

                   $msg = '曾经有个机会摆在你面前，你没抓紧！现在销售一空，你才后悔莫及~';
                }

            }else{
                //$isExistArr = $PlatformProductModel->checkProductExistNew($product_id, true,$quality);  //no cache
                //自己的乐天邦

                $isExist = $PlatformProductModel->checkProductExist($product_id, true,$platform_id);  //no cache
                if($isExist == 2){

                   $msg = '亲爱的用户，你还能购买'.$isExistArr['quantity'].'份，请重新选择购买数量！';
                }else{
                   $msg = '亲爱的客户，库存不足，无法下单。';
                }

            }

            if ($isExist!=1) {
                doLog('Member/orderinsufficient', "用户下订单-库存不足", $product_id,'', $this->redisLog);
                $dataJson = array('state' => 0, 'msg' => $msg);
                $this->ajaxReturn($dataJson);
            }

            
            $ProductCodeModel = M('ProductCode');
            $CustomerModel = M('Customer');
            $OrderModel = D('order');
           
            $product_code = $ProductCodeModel->where(array('productid' => $product_id, 'status' => 0))->lock(true)->find();
            $start_time = strtotime($product_info['start']);
            $end_time = strtotime($product_info['end']);

            if ($start_time>time()||$end_time<time()) {
                doLog('Member/orderexpired', "用户下订单-优惠券已过期", $product_id,'', $this->redisLog);
                $dataJson = array('state' => 0, 'msg' => '亲爱的客户，该优惠券已过期，已无法领用。');
                exit(json_encode($dataJson));
            }
            $customer_info = $CustomerModel->where(array('id' => $platform_info['customer_id']))->cache(true, 300)->find();

            //宣臻扣除积分接口
            /*
            $point_dct_reslut = null;
            if ($platform_info['PLATFORM_CODE']=='XZZSH') {
                $point_dct_reslut = pointsDeduct($is_buy['openid'], 'T99124', 88);
                if (empty($point_dct_reslut['result'])||intval($point_dct_reslut['isSucc'])!=1) {
                    doLog('Member/error', "用户下订单-宣臻接口错误", $product_id,'', $this->redisLog);
                    $dataJson = array('state' => 0, 'msg' => '亲爱的客户，系统繁忙请重新领取。');
                    exit(json_encode($dataJson));
                }
            }*/

            if ($product_info['verification']==1) {
                if (empty($product_code)) {
                    $state5 = false;
                } else {

                    $state5 = $ProductCodeModel->where(array('id' => $product_code['id'], 'status' => '0'))->lock(true)->data(array('status' => 1, 'updatetime' => date('Y-m-d H:i:s')))->save();
                }
            } else {
                $state5 = true;
            }
            if ($state5) {
                //订单表里生成订单记录
                $data = array();
                $data['ordenum'] = date('YmdHis') . createNonceStr(4, '1234567890');
                $data['tel'] = $tel;
                $data['ptuserid'] = $is_buy['id'];
                if($product_info['price']>0 && $product_info['ifpay']==1){

                    $data['pay_status'] = 1;
                }else{

                    $data['pay_status'] = 0;
                }

                $data['pay_type']       = $pay_type;
                $data['pay_price']      = $pay_price;
                $data['pay_account']    = '';
                $data['transaction_no'] = '';
                $data['pay_time']       = '1900-01-01 00:00:00';
                $data['addr_id']        = $addr_id;
                $data['ptid']           = $platform_info['id'];
                $data['ptname']         = $platform_info['name'];
                $data['remark']         = '';
                $data['updatetime']     = date('Y-m-d H:i:s');
                $data['ordertime']      = date('Y-m-d H:i:s');
                $data['createtime']     = date('Y-m-d H:i:s');
                $data['updatetime']     = date('Y-m-d H:i:s');
                $data['traffic_code']   = $this->platformCode;
                $id = $OrderModel->data($data)->add();

                //订单商品表里生成订单记录
                $OrderProductModel = D('OrderProduct');
                $product_data = array();
                $product_data['orderid']        = $id;
                $product_data['productid']      = $product_id;
                $product_data['productname']    = $product_info['name'];
                $product_data['producttypeid']  = $product_info['type_id'];
                $product_data['ptid']           = $platform_info['id'];
                $product_data['ptname']         = $platform_info['name'];
                //$product_data['smsreturnvalue'] = '';
                $product_data['status']         = $product_info['ifpay'];
                $product_data['quality']        = $quality;
                $product_data['productcodeid']  = $product_code['id'];
                $product_data['productcode']    = $product_code['couponcode'];
                $product_data['createtime']     = date('Y-m-d H:i:s');
                $product_data['updatetime']     = date('Y-m-d H:i:s');
                $product_data['tel']            = $tel;
                $product_data['specification']  = $specification;
                $od_id = $OrderProductModel->data($product_data)->add();

                //检验订单是否正常生成否则撤销操作
                if ($id&&$od_id) {
                    //若是一元购无需生成订单
                    if($type_id !=4){

                        if(online_redis_server && $product_info['price']>0 && $product_info['ifpay']==1){

                            $this->redis->databaseSelect('order');
                            $this->redis->set('waitPayPtid'.$platform_id.'Productid'.$product_id.'Tradesn:'.$data['ordenum'],$quality,15*60);     //锁定购买份数
                        }else{

                            $PlatformProductModel->where(array('platform_id' => $platform_id, 'product_id' => $product_id, 'total' => array('gt', 0)))->setInc('salequantity', $quality);
                        }
                    }


                     if(online_redis_server && $product_info['price']>0 && $product_info['ifpay']==1){ //需要支付的通过redis来锁定

                        $this->redis->databaseSelect('order');
                        if($type_id ==4){

                            $this->redis->set('waitPayProductid'.$product_id.'Tradesn:'.$data['ordenum'],$quality,15*60);     //锁定购买份数
                        }
                     }else{

                        $ProductModel->where(array('id' => $product_id, 'total' => array('gt', 0)))->setInc('saleallquantity', $quality);
                     }

                    //宣臻发货通知接口-成功
                    /*
                    if ($platform_info['PLATFORM_CODE']=='XZZSH') {
                        if (!empty($point_dct_reslut)) {
                            deliverNotice($point_dct_reslut['result']);
                        }
                    }*/
                } else {
                    if ($id) {
                        $OrderModel->where(array('id' => $id))->delete();
                    }
                    if ($od_id) {
                        $OrderProductModel->where(array('id' => $od_id))->delete();
                    }
                    if ($product_info['verification']==1&&$state5) {
                        $ProductCodeModel->where(array('id' => $product_code['id'], 'status' => '1'))->lock(true)->data(array('status' => 0, 'updatetime' => date('Y-m-d H:i:s')))->save();
                    }
                    //宣臻发货通知接口-失败
                    /*
                    if ($_SESSION['PLATFORM_CODE']=='XZZSH') {
                        if (!empty($point_dct_reslut)) {
                            deliverNotice($point_dct_reslut['result'], "SMSFail");
                        }
                    }*/
                    doLog('Member/error', "用户下订单-系统繁忙", $product_id,'', $this->redisLog);
                    $dataJson = array('state' => 0, 'msg' => '对不起，系统繁忙，稍后请重试一次。');
                    $this->ajaxReturn($dataJson);
                }

                if (!empty($pay_price)) {     //需要付钱的商品  在支付完成之后 发短信
                    $dataJson = array('state' => 2, 'msg' => '下单成功请及时支付。', 'order_id' => $id);
                    $this->ajaxReturn($dataJson);
                }

                //发送短信，并将返回值保存到订单表
                $return_sms = NULL;
                if ($product_info['issms']==1) {
                    $msg = str_replace('{channel}', $customer_info['name'], $product_info['smstpl']);
                    $msg = str_replace('{couponcode}', $product_code['couponcode'], $msg);
                    $return_sms = send_msg($tel, $msg);
                    //$return_sms = null;
                    $return['msg']  = $return_sms;
                    $coupon['time'] = round(microtime(true)-$t1,3); //统计时间;
                    $coupon['code'] = $product_data['productcode'];

                    $coupon['time'] = round(microtime(true)-$t1,3);

                    doLog('Member/ordersuccessful', "用户下订单-成功", $product_id,json_encode($coupon), $this->redisLog);
                    //动账抽奖不需要日志分析
                    if($_SESSION['PLATFORM_CODE']!='NYYHD'){
                        doLog('Member/doOrder/loganalysis', "用户下订单-短信返回结果", $product_id,json_encode($return), $this->redisLog);
                    }  else{
                        doLog('Member/doOrder', "用户下订单-短信返回结果", $product_id,json_encode($return), $this->redisLog);
                    }
                    if($_SESSION['PLATFORM_CODE']=='NYYHD'){
                        //农行动账抽奖,特殊处理
                        $nh_url = U('Nyyh/returnResult');
                        $dataJson = array('state' => 1, 'msg' => '亲爱的客户，您已成功领取奖品，5秒后自动跳转', 'return_url' => $nh_url);
                    }elseif ($_SESSION['PLATFORM_CODE']=='MSYH') {
                        $dataJson = array('state' => 1, 'msg' => '您已领取成功啦，稍后会有短信通知。');
                    }elseif ( $_SESSION['PLATFORM_CODE']=='NYYH' ) {
                        $dataJson = array('state' => 1, 'msg' => '您已领取成功啦，稍后会有短信通知，也可至【粉丝福利】查看详情。');
                    }
                    else {
                        $dataJson = array('state' => 1, 'msg' => '您已领取成功啦，稍后会有短信通知，也可至【个人中心】查看详情。');
                    }
                } else {
                    //是否调用第三方接口，默认正常提示，1发送短信，2跳转链接
                    if ($product_info['extersysdock']==1) {
                        $url = $product_info['interfaceaddr'];
                        $post_data = array();
                        $key = 'zcnmedia';
                        $post_data['uId'] = encrypt($key, $session['tel']);
                        $post_data['merchantId'] = 'XHS';
                        $post_data['timestamp'] = time();
                        $param = http_build_query($post_data);
                        $url = $url . "?" . $param;
                        $return_data = send_curl($url);
                        $return['msg'] = $return_data;
                        doLog('Member/externallink', "用户下订单-第三方接口", $product_id,json_encode($return), $this->redisLog);
                        $json_data = json_decode($return_data, true);
                        $return_sms = $json_data['status'] . ':' . $json_data['returnMessage'] . ':' . $json_data['returnCode'];

                        //针对第三方接口返回值，进行提示
                        if ($json_data['status']!=1||$json_data['returnCode']!='0000') {
                            if ($id) {
                                $OrderModel->where(array('id' => $id))->delete();
                            }
                            if ($od_id) {
                                $OrderProductModel->where(array('id' => $od_id))->delete();
                            }
                            if ($product_info['verification']==1&&$state5) {
                                $ProductCodeModel->where(array('id' => $product_code['id'], 'status' => '1'))->lock(true)->data(array('status' => 0, 'updatetime' => date('Y-m-d H:i:s')))->save();
                            }
                            doLog('Member/orderfailed', "用户下订单-失败", $product_id,'', $this->redisLog);
                            $dataJson = array('state' => 0, 'msg' => '' . $json_data['returnMessage']);
                        } else {
                            doLog('Member/doOrder/loganalysis', "用户下订单-成功", $product_id,'', $this->redisLog);
                            if ($_SESSION['PLATFORM_CODE']=='MSYH') {
                                $dataJson = array('state' => 1, 'msg' => '您已领取成功啦，稍后会有短信通知。');
                            } elseif ( $_SESSION['PLATFORM_CODE']=='NYYH' ) {
                                $dataJson = array('state' => 1, 'msg' => '您已领取成功啦，稍后会有短信通知，也可至【粉丝福利】查看详情。');
                            }
                            else {
                                $dataJson = array('state' => 1, 'msg' => '您已领取成功啦，稍后会有短信通知。也可至【我的订单】查看详情。');
                            }
                        }
                    } elseif ($product_info['extersysdock']==2) {

                          doLog('Member/doOrder/loganalysis', "用户下订单-成功", $product_id,'', $this->redisLog);
                          $dataJson = array('state' => 1, 'msg' => '尊敬的客户您好，请在弹出的页面上，填写预约试驾信息并提交，试驾成功后才能领取礼品', 'return_url' => $product_info['interfaceaddr']);


                    } else {
                        doLog('Member/doOrder/loganalysis', "用户下订单-成功", $product_id,'', $this->redisLog);
                        if ($_SESSION['PLATFORM_CODE']=='MSYH') {
                            $dataJson = array('state' => 1, 'msg' => '您已领取成功啦，稍后会有短信通知。');
                        } elseif ( $_SESSION['PLATFORM_CODE']=='NYYH' ) {
                            $dataJson = array('state' => 1, 'msg' => '您已领取成功啦，稍后会有短信通知，也可至【粉丝福利】查看详情。');
                        }
                        else {
                            $dataJson = array('state' => 1, 'msg' => '您已领取成功啦，稍后会有短信通知。也可至【我的订单】查看详情。');
                        }
                    }
                }

                $OrderProductModel->where(array('id' => $od_id))->data(array('smsreturnvalue' => $return_sms))->save();
                $this->ajaxReturn($dataJson);
            } else {
                doLog('Member/error', "用户下订单-系统繁忙", $product_id,'', $this->redisLog);
                $dataJson = array('state' => 0, 'msg' => '对不起，系统繁忙，稍后请重试一次。');
                $this->ajaxReturn($dataJson);
            }
        } else {
            $data = array('state' => -1, 'msg' => '对不起，请您先登录！');
            $this->ajaxReturn($data);
        }
    }
//判断是否可以进行购买操作
private function checkBuy($ptid,$tel){

    if(!is_tel($tel)) return false;
    $res = M('pt_user')->field('id,openid')->where('ptid = %d and telephone="%s"',$ptid,$tel)->select();
    if(count($res)==1){    //平台和电话号码一对一   否则不可购买

        return $res[0];
    }else{

        return false;
    }
}

/*
     * 检查商品是否用此号码领取过
     * @param $product_id 商品id
     * @param $tel 手机号码
     * @return false已领取，true未领取
     * */

    function checkOrder($product_id, $uid, $pid) {
        $OrderModel = D('Order');
        // $product_info = $OrderModel->query('SELECT * FROM __ORDER__ where id in ( SELECT `orderid` FROM __ORDER_PRODUCT__ WHERE productid = ' . $product_id . ' AND ptid = ' . $pid . ' ) AND ptuserid = ' . $uid);
        //搜索是否存在该用户在该平台有该商品的订单
        $sql = "SELECT o.* FROM  __ORDER__ AS o JOIN __ORDER_PRODUCT__  AS p ON p.orderid = o.id AND p.productid= '".$product_id."' AND p.ptid='".$pid."' WHERE o.ptuserid='".$uid."'"; //邵晓凌：是否加 limit 1?
        $product_info = $OrderModel->query($sql) ;
        if ($product_info) {
            return false;
        } else {
            return true;
        }
    }
}
 ?>