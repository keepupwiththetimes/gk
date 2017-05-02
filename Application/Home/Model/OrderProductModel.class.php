<?php
namespace Home\Model;

use Think\Model;

class OrderProductModel extends Model
{
    
    public function __construct()
    {
        parent::__construct();
        $this->tablePrefix = C('DB_PREFIX');
    }


    public function getOrderDetail($order_id)
    {
        $order_list = array();
        $order_detail = $this->query("select id,ordenum,ptuserid,ptname,createtime,addr_id,remark from tb_order where id = '{$order_id}'");
        if (isset($order_detail[0]) && ! empty($order_detail[0])) {
            $order_list['detail'] = $order_detail[0];
            $order_list['address'] = array();
            if (! empty($order_detail[0]['addr_id'])) {
                $address = $this->query("select * from tb_user_mailing_addr where id = '{$order_detail[0]['addr_id']}'");
                if (isset($address[0]) && ! empty($address[0])) {
                    $order_list['address'] = $address[0];
                }
            }
            $order_list['detail']['price'] = 0;
            $_list = $this->query("select m.id as m_id,m.merchantshort as m_name,p.id as p_id,p.home_picture as p_pic,
                                             p.name as p_name,o.quality as o_quality,o.specification as o_specification,
                                             p.end,p.type_id,p.price as p_price,
                                             p.original_price as p_original_price,p.shipping_cost as p_shipping_cost
                                             from
                                             tb_order_product o
                                             inner join tb_product p on p.id = o.productid
                                             inner join tb_brand b on   p.brand_id = b.id
                                             inner join tb_merchant m on m.id = b.merchant_id
                                             where orderid = '{$order_id}' order by m.id");
            if (! empty($_list)) {
                foreach ($_list as $k => $v) {
                    $order_list['data'][$v['m_id']]['name'] = $v['m_name'];
                    $order_list['data'][$v['m_id']]['list'][] = $v;
                    $order_list['data'][$v['m_id']]['shipping_cost'] = isset($order_list['data'][$v['m_id']]['shipping_cost']) ? $order_list['data'][$v['m_id']]['shipping_cost'] + $v['p_shipping_cost'] : $v['p_shipping_cost'];
                    $order_list['detail']['price'] += ($v['p_shipping_cost'] + $v['p_price'] * $v['o_quality']);
                }
            }
        }
        return $order_list;
    }
    
    /**
     * [getTimeOutOrder 处理过期未领的订单]
     * @ckhero
     * @DateTime 2017-02-20
     * @param    string     $orderid [description]
     * @return   [type]              [description]
     */
    public function  getTimeOutOrder($orderid = '', $time = 10) 
    {   
        //没有订单号的情况，获取所有抽奖平台的过期订单
        if(empty($orderid)) {
            
            //抽奖平台的ptid
            $subPtidSql = M('platform')->alias('p')
                                       ->field('p.id')
                                       ->join($this->tablePrefix."platform_config as pc on pc.PLATFORM_CODE = p.code")
                                       ->where("pc.APP_MODE=%d", 2)
                                       ->buildSql();
            $beginTime = '2017-03-17 00:00:00'; //功能开始时间
            $endTime = date('Y-m-d H:i:s', strtotime("-".$time." day")); //过期截止时间
            $res = $this->alias('op')
                        ->field('op.productid, o.id, o.ptid')
                        ->join($this->tablePrefix."order o on o.id =op.orderid")
                        ->where("op.ptid in (%s) and o.createtime > '%s' and o.createtime < '%s' and o.pay_status = 0 ", $subPtidSql, $beginTime, $endTime)
                        ->select();
        //查询单独一个订单的情况
        } else {

            $res = $this->alias('op')
                        ->field('op.productid, o.id, o.ptid')
                        ->join($this->tablePrefix."order o on o.id =op.orderid")
                        ->where("op.orderid=%d and o.pay_status = 0", $orderid)
                        ->select();
        }

        return $res;
    }
}
