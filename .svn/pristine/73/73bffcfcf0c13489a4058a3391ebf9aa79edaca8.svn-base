<?php  
namespace LeTianBang\Model;
use Think\Model;

class ProductModel extends Model{

     private $redis;
     public function __construct(){
        
        if(online_redis_server) $this->redis = new \Vendor\Redis\DefaultRedis();
        parent::__construct();
        $this->tablePrefix = C('DB_PREFIX');
     }

    /**
 * [getSelfProductListWithShop 获取我的优惠券带商品的使用规则]
 * @ckhero
 * @DateTime 2016-10-17
 * @param    [type]     $tel   [description]
 * @param    integer    $start [description]
 * @param    integer    $end   [description]
 * @return   [type]            [description]
 */
function getSelfProductListWithShop($tel,$where='',$start =0,$end =5,$cache=false){

    $ptuseridList = getUserByTel($tel,$cache,',');
    $tablePrefix = C('DB_PREFIX');
    $res = M('order_product')
          ->field('p.* ,fp.offlinetime as end,fp.onlinetime as start,o.id as orderid,'.$tablePrefix.'order_product.productcode,'.$tablePrefix.'order_product.ptid,m.remark as mremark')
          ->join($tablePrefix.'order o on o.id= orderid')
          ->join($tablePrefix.'product p on p.id= productid')
          ->join($tablePrefix.'brand b on b.id=p.brand_id')
          ->join($tablePrefix.'merchant m on m.id=b.merchant_id')
          ->join($tablePrefix.'platform_product fp on fp.product_id=p.id and fp.platform_id = o.ptid')
         //->where($tablePrefix.'order_product.tel=%d and o.pay_status in (0,2) and o.ptuserid in ('.$subSql .')',$tel)   这样子需要订单号码也是登陆号码
          ->where('o.pay_status in (0,2) and o.ptuserid in ('.$ptuseridList .')'.$where,$tel)
          ->order($tablePrefix.'order_product.createtime desc')
          ->limit($start,$end)
          ->group('o.id')
          ->cache($cache,300)
          ->select();
    return $res;
}

/**
 * [getProductWithPlatformName 获取商品列表]
 * @ckhero
 * @DateTime 2016-10-09
 * @param    string     $where [description]
 * @param    integer    $start [description]
 * @param    integer    $end   [description]
 * @param    integer    $cache   [是否需要缓存]
 * @return   [type]            [description]
 */
function getProductWithPlatformName($where = '',$start=0,$end = 5, $cache=false){
    
    $tablePrefix = C('DB_PREFIX');
    $currTime = date('Y-m-d H:00:00');
    $res = $this->table($tablePrefix.'product p')
                ->field('p.id,p.detail_picture,fp.onlinetime as start,fp.offlinetime as end,p.name,p.verification,p.price,p.ifpay,fp.platform_id as ptid,fp.createtime,f.displayname as platformCode,f.sort')
                ->join($tablePrefix.'platform_product fp on p.id=fp.product_id')
                ->join($tablePrefix.'platform f on fp.platform_id=f.id')
                ->where('fp.status=1  and fp.onlinetime<="'.$currTime.'" and fp.offlinetime>="'.$currTime.'" and p.type_id=1 and p.status = 1 and f.id=fp.platform_id  '.$where)
                ->order('f.sort desc,fp.createtime desc')
                ->buildSql();
    $res = $this->table($res.'as s')
                ->group('s.id')
                ->order('s.createtime desc')
                ->limit($start,$end)
                ->cache($cache,300)
                ->select();
    return $res;
}




/**
 * [getProductIdCj 获取抽奖平台的商品id]
 * @ckhero
 * @DateTime 2016-10-10
 * @return   [type]     [description]
 */
function getPlatformIdLottery($redis = false){
    $res  = '';

    if($redis){
        $redis->databaseSelect();
        $res = $redis->get('LotteryPlatformList');
    }
    if(empty($res)){
        $where['name'] = array('like',array('%抽奖%','%转盘%'));
        $where['code'] = array('in',array('TESTACCOUNT','CMBPAY'));
        $where['_logic'] = 'OR';
        $ProductIdLottery = M('platform')->field('id')->where($where)->select();
        foreach($ProductIdLottery as $key => $val){

            $res .=$val['id'].',';
        }
        $res = trim($res,',');
        if($redis){
            $redis->expire('LotteryPlatformList',300);
        }
    }
    return $res;
}
}
?>

