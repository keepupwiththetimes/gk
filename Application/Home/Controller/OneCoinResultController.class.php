<?php
namespace Home\Controller;

use \Think\Controller;

class OneCoinResultController extends Controller
{

    public function index()
    {
        if (I('get.dosubmit' == 'dosubmit')) {
            
            $productid = array(
                I('get.productid')
            );
        } else {
            
            $productid1 = M('one_coin')->field('productid')
                ->group('productid')
                ->select(); // ->cache(true, 300) 去掉缓存
            $productid2 = M('one_coin')->field('productid')
                ->where(array(
                'status' => 1
            ))
                ->group('productid')
                ->select(); // ->cache(true, 300)
            foreach ($productid1 as $key => $val) {
                
                $productid11[] = $val['productid'];
            }
            
            foreach ($productid2 as $key => $val) {
                
                $productid22[] = $val['productid'];
            }
            
            if (empty($productid22)) {
                
                $productid = $productid11;
            } else {
                
                $productid = array_diff($productid11, $productid22);
            }
        }
        
        if (empty($productid[0])) {
            
            $state = 2;
            $info = 'none';
        } else {
            // TODO: 2016-10-20 如果启用一元购，需要修改SQL语句防注入
            $productList = M('product')->field('id,smstpl,end')
                ->where(array(
                'id' => array(
                    'in',
                    $productid
                ),
                'end' => array(
                    'lt',
                    date('Y-m-d H:i:s', time() + 60)
                )
            ))
                ->select();
            foreach ($productList as $key => $val) { // 去除没结束的商品
                $now = date('Y-m-d H:i:s');
                if ($val['end'] >= $now) {
                    
                    unset($productList);
                }
            }
            if (empty($productList)) { // 没有结束的商品
                
                $info = '活动还没结束';
                $state = 22;
            } else {
                
                $info = D('OneCoin')->setResult($productList);
                $state = 1;
            }
        }
        
        if (empty($_SESSION['PLATFORM_CODE'])) {
            
            $_SESSION['PLATFORM_CODE'] = 'yiyuangou';
            $_SESSION[$_SESSION['PLATFORM_CODE']]['platform_id'] = - 1;
        }
        doLogNoSession('OneCoinResult/index', '一元夺宝活动结束批量生成结果', '', json_encode($info), '', '', '');
        $this->ajaxReturn(array(
            'state' => $state,
            'info' => $info
        ));
    }
}
?>