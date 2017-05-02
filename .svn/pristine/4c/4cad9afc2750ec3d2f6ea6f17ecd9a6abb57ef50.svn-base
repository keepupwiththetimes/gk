<?php
namespace Home\Model;

use Think\Model;

class OneCoinModel extends Model
{

    public function __construct()
    {
        parent::__construct();
        $this->tablePrefix = C('DB_PREFIX');
    }

    /**
     * [setRecord 设置参与信息]
     * @ckhero
     * @DateTime 2016-07-07
     * 
     * @param [type] $data
     *            [description]
     */
    public function setRecord($data)
    {
        $map['trade_sn'] = $data['trade_sn'];
        $info = $this->where($map)->select();
        $start = count($info);
        
        $one_coin_product = M('one_coin_product')->where(array(
            'one_coin_pid' => $data['productid']
        ))->find(); // 优惠券信息
        $ticket = explode(',', $one_coin_product['ticket_pid']);
        $ticketNum = count($ticket);
        
        $nowin = array();
        
        for ($i = $start; $i < $data['quality']; $i ++) {
            
            $res = $this->where("productid=" . $data['productid'])
                ->order('id desc')
                ->find();
            
            // 生成中奖号码
            $redis = new \Vendor\Redis\DefaultRedis(); // 微信回调的时候不走 common文件所以重新 连接 redis
            $redis->databaseSelect('one_coin');
            $joinno = $redis->pop("one_coin_joinno_list_" . $data['productid'], 'R'); // 中奖号码需要redis支持
            
            $arr = array();
            $arr = array(
                
                'joinno' => $joinno,
                'ptuserid' => $data['ptuserid'],
                'tel' => $data['tel'],
                'ptid' => $data['ptid'],
                'ptname' => $data['ptname'],
                'productid' => $data['productid'],
                'stage' => $one_coin_product['stage'],
                'addtime' => date('Y-m-d H:i:s'),
                'trade_sn' => $data['trade_sn']
            );
            $nowin[] = $arr;
            $joinnolist .= $joinno . ",";
        }
        
        $res = $this->addAll($nowin); // 添加购买记录
                                      
        // 对购买记录进行缓存
        if (online_redis_server) {
            
            $purchase = array();
            $purchase = array(
                
                'num' => count($nowin),
                'tel' => '***' . substr($nowin[0]['tel'], 6, 5),
                'joinnolist' => trim($joinnolist, ',')
            );
            $redis->hset("purchase:" . $data['trade_sn'], $purchase);
            $redis->lset('purchase_list_stage:' . $one_coin_product['stage'], $data['trade_sn']);
            // $redis->ltrim('purchase_list_stage:'.$one_coin_product['stage'],0,10);
        }
        $nowin_num = count($nowin);
        if ($res && $nowin_num > 0) { // 没有奖券退出循环
            
            $i = 0;
            while ($i < $nowin_num) {
                // file_put_contents("debug.log","一元购:".$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']."\n".'session信息:'. "\n",FILE_APPEND);
                $ticketNum = count($ticket); // 有几种优惠券可以发
                if ($ticketNum <= 0)
                    break;
                
                $no = ($nowin[$i]['joinno'] - 10001) % $ticketNum;
                $ticketId = $ticket[$no]; // 优惠券id
                if ($ticketId > 0) {
                    
                    $res = R('Notify/doOrderOneCoin', array(
                        'productid' => $ticketId,
                        'nowin' => array(
                            $nowin[$i]
                        )
                    ));
                    
                    if (! $res) { // 当其中一个奖券发完的时候 调用其他奖券；
                        
                        $redis->databaseSelect('one_coin');
                        if (! $redis->get('errorMsgSend:' . $ticketId)) {
                            doLogNoSession('OneCoinModel/error', '一元夺宝优惠券发完', $ticketId, '优惠券发完', $data['ptuserid'], $data['ptid'], $data['ptname']);
                            // 此处添加：发送短信给Aaron的手机，告诉他某张优惠券已经发完
                            $msg = "亲爱的管理员，本期一元夺宝优惠券id=" . $ticketId . "的优惠券已经发完，请及时补充,谢谢！";
                            // $tel = "15699763679";
                            $msg = send_msg(ADMIN_MOBILE_PHONE, $msg); // ADMIN_MOBILE_PHONE在根目录的index.php定义
                            $redis->set('errorMsgSend:' . $ticketId, 1);
                            $redis->expire('errorMsgSend:' . $ticketId, 24 * 3600);
                        }
                        
                        unset($ticket[$no]);
                        sort($ticket);
                        continue;
                    }
                }
                
                $i += 1;
            }
        }
        return true;
    }

    /**
     * [getList 获取中奖列表]
     * @ckhero
     * @DateTime 2016-07-07
     * 
     * @param [type] $productid
     *            [description]
     * @param [type] $ptuserid
     *            [description]
     * @param [type] $start
     *            [description]
     * @param integer $num
     *            [description]
     * @return [type] [description]
     */
    public function getSelfList($productid, $ptuserid, $start = 0, $num = 0)
    {
        $where = array(
            
            'ptuserid' => $ptuserid,
            'productid' => $productid
        );
        if ($num > 0) {
            
            $limit = $start . "," . $num;
        }
        
        $res = $this->where($where)
            ->limit($limit)
            ->order('id desc')
            ->select(); // 去掉缓存，要不然用户买后不能马上看到
        return $res;
    }

    /**
     * [getOtherList 获取其他人的记录]
     * @ckhero
     * @DateTime 2016-07-07
     * 
     * @param [type] $productid
     *            [description]
     * @param integer $start
     *            [description]
     * @param integer $num
     *            [description]
     * @return [type] [description]
     */
    public function getOtherList($productid, $start = 0, $num = 0)
    {
        $where['productid'] = $productid;
        if ($num > 0) {
            
            $limit = $start . "," . $num;
        }
        $res = $this->field("*,count(*) as num,group_concat(joinno) as joinnolist")
            ->where($where)
            ->limit($limit)
            ->group('trade_sn')
            ->order('id desc')
            ->select();
        foreach ($res as $key => $val) {
            
            // $val['tel'] = substr($val['tel'], 0,3).'...'.substr($val['tel'], 7,10);
            $val['tel'] = '***' . substr($val['tel'], 6, 5);
            $val['joinnolist'] = explode(',', $val['joinnolist']);
            $list[] = $val;
        }
        
        return $list;
    }

    /**
     * [getOtherListRedis 通过redis 获取购买列表]
     * @ckhero
     * @DateTime 2016-08-16
     * 
     * @param [type] $redis
     *            [引用的redis]
     * @param [type] $stage
     *            [期号]
     * @param integer $start
     *            [开始编号]
     * @param integer $end
     *            [结束编号]
     * @param integer $productid
     *            [商品id]
     * @return [type] [description]
     */
    public function getOtherListRedis($redis, $stage, $start = 0, $end = 0, $productid = 0)
    {
        $purchaseList = $redis->lget("purchase_list_stage:" . $stage, 'R', $start, $end); // R表示使用 lrange 函数 ====获取订单号列表 再根据订单号 获得每个订单的详细信息
        if ($start == 0 && empty($purchaseList)) { // 如果是首页 并且缓存中数据为空的时候从数据库中读取
            
            $total = $this->getOtherList($productid);
            if (empty($total))
                return false;
            
            foreach ($total as $key => $val) {
                
                if ($key < 10) { // 生成首页的十条数据
                    
                    $res[] = $val;
                }
                $val['joinnolist'] = implode(',', $val['joinnolist']);
                $purchase_list_stage[] = $val['trade_sn'];
                $purchase_trade_sn = array(
                    
                    'tel' => $val['tel'],
                    'num' => $val['num'],
                    'joinnolist' => $val['joinnolist']
                );
                $redis->hset("purchase:" . $val['trade_sn'], $purchase_trade_sn);
            }
            $redis->lset('purchase_list_stage:' . $stage, $purchase_list_stage, 'R'); // 取的数据为倒叙排的 故 使用右插入 可得最新的在左边
        } else {
            
            foreach ($purchaseList as $key => $val) {
                
                $purchase = array();
                $purchase = $redis->hget("purchase:" . $val);
                $purchase['joinnolist'] = explode(',', $purchase['joinnolist']);
                $res[] = $purchase;
            }
        }
        
        return $res;
    }

    /**
     * [setResult 活动到期处理结果]
     * @ckhero
     * @DateTime 2016-08-16
     * 
     * @param integer $productid
     *            [description]
     */
    public function setResult($productid = 0)
    {
        foreach ($productid as $key => $val) {
            
            // $customer = M('customer')->where(array('code'=>$_SESSION['PLATFORM_CODE']))->find();
            
            $res = $this->field('joinno,tel,ptuserid')
                ->where(array(
                'productid' => $val['id'],
                'status' => 1
            ))
                ->find(); // 判断是否已经出中奖结果
            
            if (empty($res)) {
                
                // 根据参与人数计算结果
                $res2 = $this->where(array(
                    'productid' => $val['id']
                ))->select();
                if (empty($res2)) {
                    
                    return false;
                }
                // 分成七等分。取每一等分的最后一个人
                $num = count($res2);
                // $avg = floor($num/7);
                $total = 0;
                // for($i=1;$i<=6;$i++){
                
                // $total += substr($res2[$avg*$i-1]['tel'],2,7);
                // }
                
                // $total += substr($res2[$num-1]['tel'],2,7);
                foreach ($res2 as $k => $v) {
                    
                    $total += substr($v['tel'], 6, 5);
                }
                
                $map['joinno'] = $total % $num + 10001;
                $map['productid'] = $val['id'];
                $this->where($map)->save(array(
                    'status' => 1
                ));
                $res = $this->table('tb_one_coin a ,tb_product b')
                    ->where($map)
                    ->find();
                if (! empty($res)) {
                    
                    $user = $this->field('b.channelname')
                        ->join($this->tablePrefix . "one_coin a," . $this->tablePrefix . "pt_user b")
                        ->where("a.ptuserid=b.id and a.joinno=" . $map['joinno'] . " and a.productid=" . $map['productid'])
                        ->find();
                    
                    // 发送短信
                    
                    $msg = str_replace('{channel}', $user['channelname'], $val['smstpl']) . "[中奖编号为" . $map['joinno'] . "]";
                    // $msg = str_replace('{couponcode}', $product_code['couponcode'], $msg);
                    $tel = $res['tel'];
                    $msg = send_msg($tel, $msg);
                    doLogNoSession('OneCoinModel/setResult', '发送中奖短信', '', date('Y-m-d H:i:s') . "|商品编号:" . $val['id'] . "|中奖人:" . $res['ptuserid'] . "|电话号码：" . $res['tel'] . "|短信发送结果：" . $msg, '', '', '');
                    // dolog('OneCoinModel/setResult',date('Y-m-d H:i:s')."|商品编号:".$val['id']."|中奖人:".$res['ptuserid']."|电话号码：".$res['tel']."|短信发送结果：".$msg);
                    $list[$val['id']]['no'] = $map['joinno'];
                    $list[$val['id']]['tel'] = '***' . substr($tel, 6, 5);
                }
            } else {
                
                $list[$val['id']]['no'] = $res['joinno'];
                $list[$val['id']]['tel'] = '***' . substr($res['tel'], 6, 5);
            }
        }
        return $list;
    }
}
?>
