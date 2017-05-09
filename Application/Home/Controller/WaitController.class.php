<?php
namespace Home\Controller;

use Think\Controller;
use Common\Api\WxApi;

class WaitController extends Controller
{

    private $wxApi, $platform_config;

    public function index()
    {
        $this->wxApi = new WxApi(C('APP_ID'), C('APP_SECRET'));
        /* 微信分享签名-----start */
        $sign_package = $this->wxApi->getSignPackage();
        $this->assign('sign_package', $sign_package);
        /* 微信分享签名-----end */
        
        $default_prize_list = array(
            0 => '蜘蛛网15元||抵用券一份',
            1 => '北美特色枫香||烤鸡翅兑换券||一张',
            2 => '滴滴代驾||红包优惠券一份',
            3 => 'ipad air||16G平板电脑',
            4 => 'iPhone6||16G智能手机',
            5 => 'apple watch||智能手表'
        );
        
        if ($_SESSION[$_SESSION['PLATFORM_CODE']]['platform_id']) {
            $PlatformProductModel = D('PlatformProduct');
            $product_list = $PlatformProductModel->getProductList($_SESSION[$_SESSION['PLATFORM_CODE']]['platform_id'], 1, 0, 8);
            $p_num = 1;
            if ($product_list) {
                foreach ($product_list as $k => $v) {
                    $prize_list[$p_num] = $v['name'];
                    $p_num ++;
                }
            } else {
                $prize_list = null;
            }
            
            $prize_list = empty($prize_list) ? $default_prize_list : $prize_list;
            $prize_list = array_filter($prize_list);
        } else {
            $prize_list = $default_prize_list;
        }
        
        unset($product_list);
        $prize_list = array_filter(insertStrToArr($prize_list));
        $prize_str = '';
        $prize_bg = '';
        
        foreach ($prize_list as $key => $items) {
            $prize_str .= '"' . $items . '",';
            if ($key % 2 == 0) {
                if ($this->platform_config['FONT_BACKGROUND_COLOR_1']) {
                    $prize_bg .= '"' . $this->platform_config['FONT_BACKGROUND_COLOR_1'] . '",';
                } else {
                    $prize_bg .= '"#FFF4D6",';
                }
            } else {
                if ($this->platform_config['FONT_BACKGROUND_COLOR_2']) {
                    $prize_bg .= '"' . $this->platform_config['FONT_BACKGROUND_COLOR_2'] . '",';
                } else {
                    $prize_bg .= '"#FFFFFF",';
                }
            }
        }
        
        if ($_SESSION['PLATFORM_CODE']) {
            $this->platform_config = getPlatformConfig($_SESSION['PLATFORM_CODE']); 
        }
        
        $image_path = $this->platform_config['IMG_PATH'] ? $this->platform_config['IMG_PATH'] : '/';
        
        $font_color = $this->platform_config['FONT_COLOR'] ? $this->platform_config['FONT_COLOR'] : '#E5302F';
        
        $url_config = array();
        
        $url_config['WAIT_RULE_URL'] = $this->platform_config['WAIT_RULE_URL'] ? $this->platform_config['WAIT_RULE_URL'] : 'javascript:;';
        $url_config['WAIT_FOOT_URL'] = $this->platform_config['WAIT_FOOT_URL'] ? $this->platform_config['WAIT_FOOT_URL'] : 'javascript:;';
        
        $this->assign('image_path', $image_path);
        $this->assign('font_color', $font_color);
        $this->assign('url_config', $url_config);
        $this->assign('prize_str', $prize_str);
        $this->assign('prize_bg', $prize_bg);
        $this->display('index');
    }
}
