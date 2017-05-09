<?php
namespace Home\Controller;

use Think\Controller;

class ShareJumpController extends Controller
{

    public function __construct()
    {
        parent::__construct();
    }

    public function index()
    {
        $platform_code  = I('get.platcode');
        
        $platform_config = getPlatformConfig(strtoupper($platform_code));//C('PLATFORM.' . $session_platform_code);   
        if (empty($platform_config)) {

            $this->error('信息有误');
        }

        redirect($platform_config['SHARE']['link']);
    }
}
