<?php
namespace Home\Model;

use Think\Model;

class UserCollectionModel extends Model
{

    /**
     * [getproductPointByUserProduct 根据用户id 平台id 优惠券id
     * @param  [int] $user_id     [用户id]
     * @param  [int] $platform_id [平台id]
     * @param  [string] $product_ids [产品id集合]
     * @return [array]              [用户收藏集合]
     */
    public function getproductPointByUserProduct($user_id, $platform_id, $product_ids){
        $usercollection = M('UserCollection');
        $usercollection_info = $usercollection->field('productid')
            ->where(array(
                'ptuserid' => $user_id,
                'platformid' => $platform_id,
                'productid' => array(
                    'in',
                    implode(',', $product_ids)
                )
            ))
            ->select();
        return $usercollection_info;
    }

    /**
     * [getProductPointByProduct 获取优惠券的所有收藏记录
     * @return [array]              [用户收藏集合]
     */
    public function getProductPointByProduct(){
        $usercollection = M('UserCollection');
        $usercollection_info = $usercollection->field(array('count(id)'=>'point','productid'))
            ->group('productid')
            ->select();
        return $usercollection_info;
    }

}