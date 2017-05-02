<?php
namespace Home\Controller;

use Think\Controller;
//use Common\Api\WxApi;

class SessionController extends CommonController
{

    public function __construct()
    {
        parent::__construct();
    }

    public function index()
    {
        exit("<script> top.location = 'http://shop.happymath.org/m/zsh.html'; </script>");
    }

    /**
     * 这个公开方法，由掌尚汇提供给开发微信支付临时使用
     * 正式上线后，这段代码要删除掉
     */
    public function getAccessToken()
    {
        exit(S('access_token_' . C('APP_ID')));
    }
}
