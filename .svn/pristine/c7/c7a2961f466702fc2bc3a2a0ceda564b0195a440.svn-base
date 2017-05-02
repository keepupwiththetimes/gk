<?php
namespace Home\Model;

use Think\Model;

class ActionRecordModel extends Model
{

    public function __construct()
    {
        parent::__construct();
        $this->tablePrefix = C('DB_PREFIX');
    }

    public function getMonth($name, $productId, $type = 0)
    {    

        //没有商品的情况
        if(empty($productId)){

            return false;
        }
        $where = '';
        if ($type == 0 || $type == 1) {
            
            $where = " and type ='" . $type . "'";
        }
        if (is_array($name)) {
            $nameStr = implode($name, ',');
        } else {
            
            $nameStr = $name;
        }
        
        if (is_array($productId)) {
            $productIdStr = implode($productId, ',');
        } else {
            
            $productIdStr = $productId;
        }
        $monthTotal = $this->field('detail,productId,type')
            ->where("name in (%s) and productId in(%s) and date>'%s'" . $where, array(
            $nameStr,
            $productIdStr,
            date('Y-m-d', strtotime("-30 day"))
        ))
            ->select();
        $currDate = date('Y-m-d');
        foreach ($monthTotal as $key => $val) {
            
            $detail = array();
            $detail = json_decode($val['detail'], true);
            if (! is_array($detail))
                continue;
            foreach ($detail as $k => $v) {
                
                if (in_array($k, array(
                    'ptid',
                    'productid',
                    'date',
                    'zwid'
                )))
                    continue;
                if ($type == 2) {
                    
                    if ($detail['date'] == $currDate && $val['type'] == 0) {
                        
                        $month[$val['productId']]['currDay'][$k] += $v;
                    } else {
                        
                        $month[$val['productId']][$val['type']][$k] += $v;
                    }
                } else {
                    
                    $month[$val['productId']][$val['type']][$k] += $v;
                }
            }
        }
        return $month;
    }
}