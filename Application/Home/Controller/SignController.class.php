<?php
/**
 * Created by PhpStorm.
 * User: jysdhr
 * Date: 2017/4/26
 * Time: 10:43
 * Description:
 */

namespace Home\Controller;


class SignController extends CommonController
{
    /**
     * SignController constructor.
     */
    private $signModel;

    public function __construct()
    {
        parent::__construct();
        $this->signModel = D('Sign');
    }

    /**
     * @Description:
     * @User:jysdhr
     */
    public function doSign($redis)
    {
        //redis中设置禁止重入
        $redis->databaseSelect('Sign');
        //当重入锁存在时 禁止重入
        if ($redis->exists('SignReentrantLock_' . $this->user_id))
            return;
        $redis->set('SignReentrantLock_' . $this->user_id, 1 ,3600);
        //如果不存在平台编码  返回0
        if (!$this->platform_config['PLATFORM_CODE']) return $this->ReentrantLock($redis, 0);
        //查询当前用户的24小时内的签到记录
        $sign_record_data = $this->signModel->userSignRecord($this->user_id, $this->platform_id, date('Y-m-d H:i:s', strtotime(date('Y-m-d'))), date('Y-m-d H:i:s', (strtotime(date('Y-m-d')) + 24 * 3600)));
        //如果存在签到记录，返回-1 已签到
        if (!empty($sign_record_data)) return $this->ReentrantLock($redis, -1);
        //不存在签到记录，将用户签到数据存入数据库
        $user_data = array();
        $user_data['userid'] = $this->user_id; //用户id
        $user_data['channelid'] = $this->platform_info['customer_id']; // 平台渠道id
        $user_data['ptid'] = $this->platform_id; // 平台id
        $user_data['signtime'] = date('Y-m-d H:i:s');
        if (!$this->signModel->addUserSignRecord($user_data)) return $this->ReentrantLock($redis, 0);////插入数据失败 返回0
        //成功
        //招行签到的特殊处理
        if ((online_redis_server == true) && ($_SESSION['PLATFORM_CODE'] == 'ZHCJ'))
            $this->specialDelByZSYH($redis);
        //查询用户签到记录总计Signday
        if ($info = $this->signModel->userSignDay($this->user_id, $this->platform_id)) {
            //有记录
            //更新累计签到记录
            $this->signModel->updateUserSignDay($this->user_id, $this->platform_id, $info['signtotal']);
            return $this->ReentrantLock($redis, 1);
        } else {
            $sign_day_data = array();
            $sign_day_data['userid'] = $this->user_id; // $_SESSION['_USER']['id'];
            $sign_day_data['channelid'] = $this->platform_info['customer_id'];// $_SESSION['_USER']['customer_id'];
            $sign_day_data['ptid'] = $this->platform_id; // $_SESSION['_USER']['platform_id'];
            $sign_day_data['signtotal'] = 1;
            $sign_day_data['signday'] = 1;
            $sign_day_data['createtime'] = date('Y-m-d H:i:s');
            $sign_day_data['updatetime'] = date('Y-m-d H:i:s');
            $this->signModel->addUserSignDay($sign_day_data);
            // ///////////以下语句是为招行抽奖平台设计的：当用户从来没有签到过的话，历史用户数加1.
            if ((online_redis_server == true) && ($_SESSION['PLATFORM_CODE'] == 'ZHCJ')) {
                $this->signTotalByZSYH($redis);
            }
            return $this->ReentrantLock($redis, 1);
        }
    }

    /**
     * @Description:解除重入锁并返回数据
     * @User:jysdhr
     */
    private function ReentrantLock($redis, $code)
    {
        $redis->databaseSelect('Sign');
        $redis->delete('SignReentrantLock_' . $this->user_id);
        if (1 == $code)
            $redis->set('UserDateSignRecord_' . $this->user_id . date('Ymd'), 'sucess',24*3600);
        return $code;
    }

    private function specialDelByZSYH($redis)
    {
        if (empty($redis))
            $redis = new \Vendor\Redis\DefaultRedis();
        $redis->databaseSelect();
        $dateStr = date('Ymd');
        $cmb_luckydraw_today_user_total = $redis->get('cmb_luckydraw_today_user_total' . '_' . $dateStr);
        if ($cmb_luckydraw_today_user_total) {
            $redis->set('cmb_luckydraw_today_user_total' . '_' . $dateStr, $cmb_luckydraw_today_user_total + 1, 24 * 60 * 60);
        }
        // /////同步招行签到记录
        $checkInTime = date('Y-m-d%20H:i:s', strtotime($_REQUEST['REQUEST_TIME']));
        $checkInTimeEncoded = urlencode(base64_encode($checkInTime));
        $openidEncoded = urlencode(base64_encode($_SESSION['ZHCJ']['ZhcjOpenid']));
        if (DEBUG_ON_LOCALHOST_OR_201TESTINGSERVER == FALSE) {
            $url = 'https://pointbonus.cmbchina.com/IMSPActivities/checkIn/index?openid=' . $openidEncoded . '&checkInTime=' . $checkInTimeEncoded;
        } else {
            $url = 'http://pointbonustest.dev.cmbchina.com/IMSPActivities/checkIn/index?openid=' . $openidEncoded . '&checkInTime=' . $checkInTimeEncoded;
        }
        if ($_SESSION['ZHCJ']['ZhcjOpenid']) {
            $redis->set('cmd_send_url:' . $_SESSION['ZHCJ']['ZhcjOpenid'], $url, 24 * 60 * 60);
        }
    }

    private function signTotalByZSYH($redis)
    {
        if (empty($redis))
            $redis = new \Vendor\Redis\DefaultRedis();
        $redis->databaseSelect();
        $cmb_luckydraw_user_total = $redis->get('cmb_luckydraw_user_total'); // 'cmb_luckydraw_user_total' . '_' .$dateStr
        if ($cmb_luckydraw_user_total) {
            $redis->set('cmb_luckydraw_user_total', $cmb_luckydraw_user_total + 1);
        }
    }
}