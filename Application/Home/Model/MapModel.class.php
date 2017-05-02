<?php
namespace Home\Model;

use Think\Model;

class MapModel extends Model
{

    protected $tableName = 'shop';

    public function getShopByProductId($product_id, $page = 1)
    {
        $page_size = 8;
        $limit_start = ($page - 1) * $page_size;
        $ProductModel = D('product');
        // $where = array('id'=>$product_id,'status'=>1);
        // $shop_str = $ProductModel->field('shop_id')->where($where)->find();
        $shop_str = $ProductModel->field('shop_id')
            ->where("id=%d and status=1", $product_id)
            ->find();
        if (empty($shop_str['shop_id'])) {
            $shop_list = NULL;
        } else {
            $shop_list = $this->where(array(
                'id' => array(
                    'in',
                    $shop_str['shop_id']
                )
            ))
                ->limit($limit_start, $page_size)
                ->order('sort asc')
                ->select();
        }
        return $shop_list;
    }
}
