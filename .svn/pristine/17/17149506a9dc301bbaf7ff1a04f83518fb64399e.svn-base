<?php
namespace Home\Controller;

use Think\Controller;
use Think\Model;

class MapController extends CommonController
{
    // 地图展示
    public function view()
    {
        $MapModel = D('Map');
        $id = I('param.id', 0, 'intval');
        // $shop_info = $MapModel->where(array('id'=>$id))->find();
        $shop_info = $MapModel->where("id=%d", $id)->find();
        $this->assign('shop_info', $shop_info);
        doLog('Map/view', '地图展示', '', '', $this->redisLog);
        $this->display('view');
    }
    
    // 门店列表
    public function lists()
    {
        $MapModel = D('Map');
        $product_id = I('param.product_id', - 1, 'intval');
        $page = I('param.page', 1);
        $shop_list = $MapModel->getShopByProductId($product_id, $page);
        $method = I('param.method', '');
        if ($method == 'ajax') {
            doLog('Map/lists', "加载更多", '', '', $this->redisLog);
            if ($shop_list) {
                $json_data = array(
                    'state' => 1,
                    'msg' => '加载完成',
                    'list_data' => $shop_list
                );
                exit(json_encode($json_data));
            } else {
                $json_data = array(
                    'state' => 0,
                    'msg' => '没有内容可加载了！'
                );
                exit(json_encode($json_data));
            }
        } else {
            $this->assign('is_more', count($shop_list) == 8 ? 1 : 0);
            $this->assign('product_id', $product_id);
            $this->assign('shop_list', $shop_list);
            // var_dump($shop_list);
            doLog('Map/lists', '门店列表', '', '', $this->redisLog);
            $this->display('lists');
        }
    }
    
    // 商户门店列表
    public function lists2()
    {
        $ProductModel = M('product');
        $product_id = I('param.product_id', 0, 'intval');
        $method = I('param.method', '');
        $page = I('param.page', 1, 'intval');
        $page_size = 8;
        $limit_start = ($page - 1) * $page_size;
        $shop_list = $ProductModel->alias('p')
            ->join('__BRAND__ b on p.brand_id = b.id')
            ->join('__MERCHANT__ m on m.id=b.merchant_id')
            ->
        // ->where(array('p.id'=>$product_id))
        where("p.id=%d", $product_id)
            ->limit($limit_start, $page_size)
            ->field('p.name,m.*')
            ->cache(true, 300)
            ->select();
        
        if ($method == 'ajax') {
            doLog('Map/lists2', "加载更多", '', '', $this->redisLog);
            if ($shop_list) {
                $json_data = array(
                    'state' => 1,
                    'msg' => '加载完成',
                    'list_data' => $shop_list
                );
                exit(json_encode($json_data));
            } else {
                $json_data = array(
                    'state' => 0,
                    'msg' => '没有内容可加载了！'
                );
                exit(json_encode($json_data));
            }
        } else {
            $this->assign('is_more', count($shop_list) == 8 ? 1 : 0);
            $this->assign('product_id', $product_id);
            $this->assign('shop_list', $shop_list);
            doLog('Map/lists2', '商户门店列表', '', '', $this->redisLog);
            // var_dump($shop_list);
            if(empty($this->page_title)){
                $this->assign('page_title', '适用门店');
            }
            $this->display('lists');
        }
    }
}
