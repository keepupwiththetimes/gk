<?php
namespace Home\Controller;

use Think\Controller;

class OneCoinController extends CommonController
{

    public function __construct()
    {
        parent::__construct();
        $this->num = 9;
    }

    public function index()
    {
        
        // 获取活动信息
        $stage = I('get.id', 0);
        // 根据期号求 productid
        $product = D('product');
        
        if ($stage > 0) {
            
            $productInfo = $product->getOneCoin("b.stage>=" . $stage, 2, "b.stage asc"); // url里有期号 根据期号求当期活动 以及其下一期活动
        } else {
            
            $productInfo = $product->getOneCoin("a.start<='" . date('Y-m-d H:i:s') . "'", 1, "b.stage desc"); // 不知道当前期号的情况下 先计算当前是哪一期 （1）
            if (empty($productInfo)) {
                
                // $productInfo = array();
                // $productInfo = $product->getOneCoin('',1,"b.stage asc");
                $this->error('一元夺宝活动还没有开始');
            } else {
                
                $res = $product->getOneCoin("b.stage>" . $productInfo[0]['stage'], 1, "b.stage asc"); // 根据（1）z中求出来的期号 计算其下一期期 ??
                if (! empty($res)) {
                    
                    $productInfo[] = $res[0];
                }
                // sort($productInfo);
            }
        }
        
        if (! empty($productInfo[1])) { // 如果有下一期 还得判断下一期活动是否已结束（ 已结束则没有下一期）
            
            if ($productInfo[1]['end'] <= date('Y-m-d H:i:s')) {
                
                unset($productInfo[1]);
            } else {
                
                $productInfo[1]['home_picture'] = RESOURCE_PATH . $productInfo[1]['detail_picture'];
            }
        }
        
        $end = strtotime($productInfo[0]['end']);
        $OneCoin = D('OneCoin');
        if ($end > time()) { // 活动还没结束
            
            $this->assign('serverTime', time());
        } else {
            $end = 0;
            if (($end + 10) < time()) { // 考虑网络时延，等30秒后再让服务器计算中奖结果
                                          
                // 活动结束
                $result = $OneCoin->setResult(array(
                    array(
                        'id' => $productInfo[0]['id'],
                        'smstpl' => $productInfo[0]['smstpl']
                    )
                ));
                dolog('OneCoin/index', '一元夺宝活动结束', $productInfo[0]['id'], json_encode($result), $this->redisLog);
            }
        }
        $this->assign('endTime', $end);
        
        // 用户中奖碼
        
        $selfList = $OneCoin->getSelfList($productInfo[0]['id'], $_SESSION[$_SESSION['PLATFORM_CODE']]['id']);
        $this->assign('selfList', $selfList);
        
        if (! online_redis_server) {
            
            $otherList = $OneCoin->getOtherList($productInfo[0]['id'], 0, $this->num); // 查询最近的购买记录
        } else {
            
            $this->redis->databaseSelect('one_coin');
            $otherList = $OneCoin->getOtherListRedis($this->redis, $productInfo[0]['stage'], 0, $this->num, $productInfo[0]['id']); // 查询最近的购买记录
                                                                                                                                 
            // 期号列表
            $stageList = $product->stageList($this->redis, $productInfo[0]['stage']); // 邵晓凌修改，减少redis的连接数目
            $initialSlide = 1;
            foreach ($stageList as $key => $val) {
                
                if ($val == $productInfo[0]['stage']) {
                    
                    $initialSlide = $key;
                    break;
                }
            }
            $this->assign('stageList', $stageList);
            $this->assign('initialSlide', $initialSlide);
            
            // 中奖码设置
            $joinno_list = $this->redis->lSize("one_coin_joinno_list_" . $productInfo[0]['id']); // 判断中奖码是否已存在
            if (! $joinno_list && ($productInfo[0]['total'] - $productInfo[0]['saleallquantity']) > 0 && $end > time()) { // 中奖不存在 &剩余数量大于0 & 活动还没结束 生成中奖码
                
                for ($i = $productInfo[0]['saleallquantity']; $i < $productInfo[0]['total']; $i ++) {
                    
                    $this->redis->lset("one_coin_joinno_list_" . $productInfo[0]['id'], 10001 + $i);
                }
            }
        }
        
        // 判断是否是第一次参与；
        $isFirstBuy = $this->isFirstBuy();
        $this->assign('isFirstBuy', $isFirstBuy);
        $this->assign('otherList', $otherList);
        $productInfo[0]['home_picture'] = RESOURCE_PATH . $productInfo[0]['home_picture'];
        
        $platform_id = $_SESSION[$_SESSION['PLATFORM_CODE']]['platform_id'];
        $this->assign('end', $end);
        $this->assign('productInfo', $productInfo);
        $this->assign('platform_id', $platform_id);
        $this->assign('result', array(
            'no' => $result[$productInfo[0]['id']]['no'],
            'tel' => $result[$productInfo[0]['id']]['tel']
        ));
        $this->assign('productid', $productInfo[0]['id']);
        $this->assign('stage', $productInfo[0]['stage']);
        dolog('OneCoin/index', '访问一元夺宝', $productInfo[0]['id'], '', $this->redisLog);
        
        $this->display('index');
    }

    /**
     * [get_more 获取更多中奖纪录]
     * @ckhero
     * @DateTime 2016-08-16
     * 
     * @return [type] [json]
     */
    public function get_more()
    {
        if (I('post.dosubmit') == 'dosubmit') {
            
            $start = I('post.start', '', 'intval');
            if (online_redis_server) { // 如果有redis 则从redis中读取数据
                
                $this->redis->databaseSelect('one_coin');
                $res = D('OneCoin')->getOtherListRedis($this->redis, I('post.stage'), $start, $start + $this->num, I('post.product_id'));
            } else {
                
                $res = D('OneCoin')->getOtherList(I('post.product_id'), $start, $this->num);
            }
            if (empty($res)) {
                
                $status = 0;
            } else {
                
                $status = 1;
            }
            $data = array(
                'status' => $status,
                'list' => $res
            );
            $this->ajaxReturn($data);
        }
    }

    /**
     * [isFirstBuy description]
     * @ckhero
     * @DateTime 2016-09-02
     * 
     * @return boolean [true为需要提示false不需要提示]
     */
    public function isFirstBuy()
    {
        if (! online_redis_server)
            return false;
        
        $ptuserid = $_SESSION[$_SESSION['PLATFORM_CODE']]['id'];
        $isFirstBuy = $this->redis->get('ptuserid:' . $ptuserid);
        if ($isFirstBuy) {
            
            $isFirstBuy = false;
        } else {
            
            // $isFirstBuyRes = M('one_coin')->where(array('ptuserid'=>$ptuserid))->find();
            $isFirstBuyRes = M('one_coin')->where('ptuserid=%d', $ptuserid)->find();
            
            if (empty($isFirstBuyRes)) {
                
                $isFirstBuy = true;
            } else {
                
                $isFirstBuy = false;
            }
            
            $this->redis->set('ptuserid:' . $ptuserid, 1);
            $this->redis->expire('ptuserid:' . $ptuserid, 24 * 3600);
        }
        
        return $isFirstBuy;
    }
}
?>


