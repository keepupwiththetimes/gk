<?php
namespace Home\Model;

use Think\Model;

class ProductModel extends Model
{

    private $redis;

    public function __construct()
    {
        if (online_redis_server)
            $this->redis = new \Vendor\Redis\DefaultRedis();
        parent::__construct();
        $this->tablePrefix = C('DB_PREFIX');
    }

    /**
     * [getproduct 获取商品信息]
     * @ckhero
     * @DateTime 2016-07-07
     * 
     * @param [type] $type
     *            [商品类型]
     * @param [type] $start
     *            [开始]
     * @param [type] $num
     *            [几个结果]
     * @param string $desc
     *            [排序方式]
     * @return [type] [description]
     */
    public function getproduct($type, $start, $num, $desc = 'id asc', $string = "")
    {
        // 似乎没有任何一个地方调用此函数。邵晓凌
        $map['type'] = $type;
        $map['status'] = 1;
        if (! empty($string)) {
            
            $map['_string'] = $string;
        }
        $res = $this->where($map)
            ->limit($start, $num)
            ->order($desc)
            ->cache(true, 60)
            ->select();
        return $res;
    }

    public function checkProductExist($product_id, $quantity = 0)
    {
        $productInfo = $this->where("id = %d", $product_id)->find(); // 要判断商品是否卖完，不能设太长的缓存
        
        if (empty($productInfo)) {
            
            return array(
                'state' => 0
            ); // 商品不存在
        }
        
        $end = strtotime($productInfo['end']);
        $start = strtotime($productInfo['start']);
        if ($start > time() || $end < time()) {
            
            return array(
                'state' => 0
            ); // 商品未在上架期间
        }
        
        // 检查商品在当前平台内是否已经售完
        
        $can_sale = $productInfo['total'] - $productInfo['saleallquantity'];
        if (online_redis_server) {
            
            $this->redis->databaseSelect('order');
            $waitPayList = $this->redis->keys('waitPayProductid' . $product_id . '*'); // 获取被锁定的列表
            if (! empty($waitPayList)) {
                
                foreach ($waitPayList as $key => $val) {
                    
                    $can_sale -= $this->redis->get($val);
                }
            }
        }
        if ($can_sale <= 0) {
            return array(
                'state' => 0
            );
        }
        
        if ($quantity - $can_sale > 0) {
            
            return array(
                'state' => 2,
                'quantity' => $can_sale
            ); // quantity 还能购买份数
        }
        
        return array(
            'state' => 1
        );
    }

    public function getOneCoin($where = "", $limit = "", $order = "")
    {
        $res = $this->table($this->tablePrefix . "product a")
            ->join($this->tablePrefix . "one_coin_product b on a.id=b.one_coin_pid")
            ->where($where)
            ->limit($limit)
            ->order($order)
            ->
        // ->cache(true, 300)
        select();
        
        return $res;
    }

    /**
     * [stageList 获取已经开始的期号列表]
     * @ckhero
     * @DateTime 2016-08-09
     * 
     * @param [type] $stageId
     *            [当前期号id ，用于判断当前期号是否在期号缓存里]
     * @return [type] [保存了期号的数组]
     */
    public function stageList($redis, $stageId)
    { // 邵晓凌修改
                                                 
        // $redis = new \Vendor\Redis\Redis();
        $list = $redis->hget('stageList');
        
        if (! in_array($stageId, $list)) {
            
            $stageList = $this->table($this->tablePrefix . "product p")
                ->join($this->tablePrefix . "one_coin_product o on p.id=o.one_coin_pid")
                ->where("p.start<'" . date('Y-m-d H:i:s') . "'")
                ->field('o.stage')
                ->order('o.stage asc')
                ->select();
            foreach ($stageList as $key => $val) {
                
                $list[$key] = $val['stage'];
            }
            
            $redis->hset('stageList', $list);
        }
        
        return $list;
    }
    
    /**
     * [productCallBack 过期商品回收]
     * @ckhero
     * @DateTime 2017-02-20
     * @param    [type]     $data [description]
     * @return   [type]           [description]
     */
    public function productCallBack($data)
    {
        if(!is_array($data)) {

            return false;
        }
        //需要更新的商品详情
        $updateProduct = array();
        foreach($data as $key=>$val) {

            $res = M('order')->where("pay_status=0 and id=%d", $val['id'])->save(array('pay_status' => 6));
            //订单状态更新成功的才进行商品回收操作
            if($res){
                
                //需要回收的商品详情，num为product中需要回收的个数,ptid是platform_product中回收的个数
                $updateProduct[$val['productid']]['num'] +=1;
                $updateProduct[$val['productid']][$val['ptid']] +=1;
            }
        }
        
        //待更新的数据为空
        if(!is_array($updateProduct)) {

            return false;
        }

        //处理商品里的数量；
        //$key为商品id
        foreach($updateProduct as $key => $val) {
            
            //$k为num的时候是需要更新product，其他情况为平台id
            foreach($val as $k => $v) {
                
                if($v > 0) {
                     //更新product的数据
                    if($k == 'num') {
                        
                        //$this->where("id = %d", $key)->lock(true)->find();  
                        $this->where("id = %d", $key)->setDec('saleallquantity', $v);
                    //更新platform_product的数据
                    } else {
                        //平台id真实有效
                        if($k > 0) {

                            //M('platform_product')->where("platform_id = %d and product_id = %d", $k, $key)->lock(true)->find();  
                            M('platform_product')->where("platform_id = %d and product_id = %d", $k, $key)->setDec('salequantity', $v);
                        }
                    }
                }
            }
        }

        return true;
    }
    // /**
    // * [getSelfProductListWithShop 获取我的优惠券带商品的使用规则]
    // * @ckhero
    // * @DateTime 2016-10-17
    // * @param [type] $tel [description]
    // * @param integer $start [description]
    // * @param integer $end [description]
    // * @return [type] [description]
    // */
    // function getSelfProductListWithShop($tel,$where='',$start =0,$end =5,$cache=false){
    
    // $ptuseridList = getUserByTel($tel,$cache,',');
    // $tablePrefix = C('DB_PREFIX');
    // $res = M('order_product')
    // ->field('p.* ,fp.offlinetime as end,fp.onlinetime as start,o.id as orderid,'.$tablePrefix.'order_product.productcode,'.$tablePrefix.'order_product.ptid,m.remark as mremark')
    // ->join($tablePrefix.'order o on o.id= orderid')
    // ->join($tablePrefix.'product p on p.id= productid')
    // ->join($tablePrefix.'brand b on b.id=p.brand_id')
    // ->join($tablePrefix.'merchant m on m.id=b.merchant_id')
    // ->join($tablePrefix.'platform_product fp on fp.product_id=p.id and fp.platform_id = o.ptid')
    // //->where($tablePrefix.'order_product.tel=%d and o.pay_status in (0,2) and o.ptuserid in ('.$subSql .')',$tel) 这样子需要订单号码也是登陆号码
    // ->where('o.pay_status in (0,2) and o.ptuserid in ('.$ptuseridList .')'.$where,$tel)
    // ->order($tablePrefix.'order_product.createtime desc')
    // ->limit($start,$end)
    // ->cache($cache,300)
    // ->select();
    // return $res;
    // }
    
    // /**
    // * [getProductWithPlatformName 获取商品列表]
    // * @ckhero
    // * @DateTime 2016-10-09
    // * @param string $where [description]
    // * @param integer $start [description]
    // * @param integer $end [description]
    // * @param integer $cache [是否需要缓存]
    // * @return [type] [description]
    // */
    // function getProductWithPlatformName($where = '',$start=0,$end = 5, $cache=false){
    
    // $tablePrefix = C('DB_PREFIX');
    // $currTime = date('Y-m-d H:00:00');
    // $res = $this->table($tablePrefix.'product p')
    // ->field('p.id,p.detail_picture,fp.onlinetime as start,fp.offlinetime as end,p.name,p.verification,p.price,p.ifpay,fp.platform_id as ptid,fp.createtime,f.displayname as platformCode,f.sort')
    // ->join($tablePrefix.'platform_product fp on p.id=fp.product_id')
    // ->join($tablePrefix.'platform f on fp.platform_id=f.id')
    // ->where('fp.status=1 and fp.onlinetime<="'.$currTime.'" and fp.offlinetime>="'.$currTime.'" and p.type_id=1 and p.status = 1 and f.id=fp.platform_id '.$where)
    // ->order('f.sort desc,fp.createtime desc')
    // ->buildSql();
    // $res = $this->table($res.'as s')
    // ->group('s.id')
    // ->order('s.createtime desc')
    // ->limit($start,$end)
    // ->cache($cache,300)
    // ->select();
    // return $res;
    // }

/**
 * [getProductIdCj 获取抽奖平台的商品id]
 * @ckhero
 * @DateTime 2016-10-10
 * 
 * @return [type] [description]
 */
    // function getProductIdLottery($redis = false){
    // $res = '';
    
    // if($redis){
    // $redis->databaseSelect();
    // $res = $redis->get('LotteryProductList');
    // }
    // if(empty($res)){
    // $where['name'] = array('like',array('%抽奖%','%转盘%'));
    // $where['code'] = array('in',array('TESTACCOUNT','CMBPAY'));
    // $where['_logic'] = 'OR';
    // $subSql = M('platform')->field('id')->where($where)->buildSql();
    // $ProductIdLottery = M('platform_product')
    // ->field('product_id')
    // ->where("platform_id in ".$subSql)
    // ->group('product_id')
    // ->select();
    // foreach($ProductIdLottery as $key => $val){
    
    // $res .=$val['product_id'].',';
    // }
    // $res = trim($res,',');
    // if($redis){
    // $redis->expire('LotteryProductList',300);
    // }
    // }
    // return $res;
    // }

/**
 * [getProductIdCj 获取抽奖平台的商品id]
 * @ckhero
 * @DateTime 2016-10-10
 * 
 * @return [type] [description]
 */
    // function getPlatformIdLottery($redis = false){
    // $res = '';
    
    // if($redis){
    // $redis->databaseSelect();
    // $res = $redis->get('LotteryPlatformList');
    // }
    // if(empty($res)){
    // $where['name'] = array('like',array('%抽奖%','%转盘%'));
    // $where['code'] = array('in',array('TESTACCOUNT','CMBPAY'));
    // $where['_logic'] = 'OR';
    // $ProductIdLottery = M('platform')->field('id')->where($where)->select();
    // foreach($ProductIdLottery as $key => $val){
    
    // $res .=$val['id'].',';
    // }
    // $res = trim($res,',');
    // if($redis){
    // $redis->expire('LotteryPlatformList',300);
    // }
    // }
    // return $res;
    // }
}
?>

