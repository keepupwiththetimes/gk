<?php
namespace Home\Controller;

use Think\Controller;

class WheelController extends CommonController
{

    private $thankNum, $timeOutStart, $timeOutLimit, $signRewardKey, $dayLeftSecond, $shareRewardKey;

    public function __construct()
    {
        // 获取城市列表
        header('Access-Control-Allow-Origin:*');
        parent::__construct();
   
        //$this->platform_config = getPlatformConfig($this->platform_code); // C('PLATFORM.' . $this->platform_code);
        //检测活动是否已上架
        $this->checkTme();
        
        $this->people = $this->getPeopleNew(); // 获取活跃人数；
                                               // 登陆检测
        $this->signRewardKey = "SignReward:".$this->user_id; //签到奖励在redis中的ke
        $this->shareRewardKey = "ShareReward:".$this->user_id; //分享后记录的redis中的key
        $this->dayLeftSecond = strtotime(date('Y-m-d 00:00:00', strtotime('+1 day'))) - time(); //当天剩余的秒数
        $login = array(
            'getRecord'
        );
        if (in_array(ACTION_NAME, $login) && ! $this->checkLogin()) { // 是否需要登陆/是否已经登陆

            $this->ajaxReturn(array(
                'state' => 11
            ));
        }

        if ((strcasecmp($this->platform_code, 'NYYHCJ') == 0) || (strcasecmp($this->platform_code, 'SHNSYHCJ') == 0)) {

            $this->thankNum = 1; // 谢谢惠顾的个数；
        } else {

            $this->thankNum = 2; // 谢谢惠顾的个数；
        }

        //page_title
        if(empty($this->page_title)) {           
                $this->assign('page_title', '乐天邦 - 抽奖');
        }
        $this->ipLimit = 1; // 此ip一天能中的次数上限
        $this->telLimit = 1; // 此电话号码已周能中的次数上限

        $this->timeOutStart = "2017-03-17 00:00:00"; //订单超时起算时间
        $this->timeOutLimit = date('Y-m-d H:i:s', strtotime("-10 day")); //十天前的时间
    }

    // 掌上惠首页展示
    public function index()
    { 
        // $logoPrizeList = array(
        //     'NYYHCJ'
        // );
        // if (in_array($this->platform_code, $logoPrizeList))
        //     $logoNeed = 1; // 转盘需要中需要有商品图片
        // if(in_array($this->platform_code, array('SXYZZHCJ', 'WXSHZSY', 'JJLXWZCJ', 'ZHCJ'))) {

        //     $newTheme = 1;
        // }
        if ($this->refresh) {

            S('lottery' . $this->platform_id, null);
            S('clean', 1);
            doLog('Wheel/index', "clean概率缓存", '', '', $this->redisLog);
        }
        $lotteryS = S('lottery' . $this->platform_id);
        $PlatformProductModel = D('PlatformProduct'); // 获取中奖的列表
        $lottery_list = $PlatformProductModel->getLotteryList(1, 0);
        $p_num = 1;
        
        foreach ($lottery_list as $k => $v) {
            $online_time = strtotime($v['onlinetime']);
            $offline_time = strtotime($v['offlinetime']);

            if ($online_time < time() && $offline_time > time()) { // 判断活动是在活动时间；避免一日活动在非活动时间内上线
                $prize_list[$p_num] = $v['name'];
               // if ($logoNeed || $newTheme)
                    
                    $home_picture = !empty($v['luckydraw_picture']) ? $v['luckydraw_picture'] : "/Public/Home/Default/Image/product/LL.png";
                    $prize_logo[$p_num] = RESOURCE_PATH . $home_picture;
                    // 生成抽奖列表
                if (! $lotteryS[$v['pid']]) {

                    $todayKey = 'Wheel::productPtid:' . $v['id'] . '_' . $this->platform_id;
                    if (online_redis_server) {

                        $this->redis->databaseSelect('wheel');
                        $today = $this->redis->get($todayKey);
                    }
                    if (empty($today)) { // 缓存中数据为空 折从数据库中读取

                        $today = M('order_product')->where("productid=%d and ptid=%d and createtime>'%s'", array(
                            $v['id'],
                            $this->platform_id,
                            date('Y-m-d')
                        ))->count();
                        if (online_redis_server) {

                            $this->redis->set($todayKey, $today);
                            $this->redis->expire($todayKey, $this->dayLeftSecond);
                        } 
                    }
                    $lotteryS[$v['pid']]['date'] = day($v['onlinetime'], $v['offlinetime'], 1);
                    $lotteryS[$v['pid']]['left'] = day(date('Y-m-d H:i:s'), $v['offlinetime'], 1);
                    $lotteryS[$v['pid']]['percent'] = round(($v['num'] - $v['donum'] + $today) / ($this->people * $lotteryS[$v['pid']]['left']), 8);
                    $s = 1;
                }
                $prize_lottery[$p_num] = array(

                    'product_id' => $v['id'],
                    'lottery_id' => $v['pid'],
                    'start' => $v['onlinetime'],
                    'end' => $v['offlinetime'],
                    'date' => $lotteryS[$v['pid']]['date'],
                    'num' => $v['num'],
                    'donum' => $v['donum'],
                    'percent' => $lotteryS[$v['pid']]['percent'],
                    'product_name' => $v['name'],
                    'originalPercent' => $v['originalPercent']
                );
                $p_num ++;
            }
        }
        if ($s) { // 缓存有更新

            S('lottery' . $this->platform_id, $lotteryS, 0); // TODO: expire time 若做成永久有效则在后台添加数据的时候 更新缓存
            doLog('Wheel/index', "缓存生成", '', json_encode(S('lottery' . $this->platform_id)), $this->redisLog);
        }

        $_SESSION[$_SESSION['PLATFORM_CODE']]['prize_list'] = array();
        $_SESSION[$_SESSION['PLATFORM_CODE']]['prize_lottery'] = array();

        $prize_list = insertStrToArr($prize_list, '谢谢参与', $this->thankNum);
        $prize_lottery = insertStrToArr($prize_lottery, '谢谢参与', $this->thankNum);

        //if ($logoNeed || $newTheme)
        $prize_logo = insertStrToArr($prize_logo, RESOURCE_PATH . '/Public/Home/Default/Image/wheel2/luck-draw3/THANKS.png', $this->thankNum);

            // 转盘每个商品的图片
        if (! empty($prize_logo)) {

            foreach ($prize_logo as $key => $val) {

                $prize_logo_list .= '"' . $val . '",';
            }
        }

        $_SESSION[$_SESSION['PLATFORM_CODE']]['prize_list'] = $prize_list;
        $_SESSION[$_SESSION['PLATFORM_CODE']]['prize_lottery'] = $prize_lottery;

        unset($lottery_list);

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
        $image_path = $this->platform_config['IMG_PATH'] ? $this->platform_config['IMG_PATH'] : '/';
        $url_config = array();
        $url_config['INDEX_FOOT_URL'] = $this->platform_config['INDEX_FOOT_URL'] ? $this->platform_config['INDEX_FOOT_URL'] : 'javascript:;';
        $font_color = $this->platform_config['FONT_COLOR'] ? $this->platform_config['FONT_COLOR'] : '#E5302F';
        // 是否有签到；
        $platform_info = D('platform')->where('code="%s"', $this->platform_code)
            ->cache(true, 300)
            ->find();
        $sign_info['sign_cycle'] = $platform_info['checkcycle'];
        $sign_info['sign_num'] = getContinueSign();
        $sign_info['sign_percent'] = round($sign_info['sign_num'] / $sign_info['sign_cycle'] * 100);
        $sign_info['sign_left'] = $sign_info['sign_cycle'] - $sign_info['sign_num'];

        $this->assign('sign_info', $sign_info); // 邵晓凌：还需要这些程序吗？？？
        $this->assign('image_path', $image_path);

        $this->assign('font_color', $font_color);
        $this->assign('url_config', $url_config);
        $this->assign('prize_str', $prize_str);
        $this->assign('prize_bg', $prize_bg);
        //if ($logoNeed || $newTheme)
        $this->assign('prize_logo_list', $prize_logo_list);
        $this->assign('mode', getPlatformConfig($this->platform_code)); 

        if ((strcasecmp($this->platform_code, 'ZHCJ') == 0) && $this->isveryfirsttimecheckin()) {

            $first = true;
        } else {

            $first = false;
        }
        $this->assign('first', $first); // 是否是第一次抽奖 //邵晓凌：是否还需要？？？？
                                        // 圆心
                                        //
        if (strcasecmp($this->platform_code, 'ZHCJ') == 0) {

            $turnTable = array(
                168,
                60
            );
        } else {

            $turnTable = array(
                195,
                50
            );
        }
        //重置分享中的link --begin
        //带一个uid作为分享标识
        $shareDetail = $this->platform_config['SHARE'];
        $shareLinkParamStr = substr($shareDetail['link'], strpos($shareDetail['link'], '?') + 1);
        $shareLinkDomain = substr($shareDetail['link'], 0, strpos($shareDetail['link'], '?')+1);
        parse_str($shareLinkParamStr, $shareLinkParamArr);
        $shareLinkParamArr['callback_uri'] = $shareLinkParamArr['callback_uri']."/uid/".$this->user_id;
        $shareDetail['link'] = $shareLinkDomain.http_build_query($shareLinkParamArr);
        $this->assign('SHARE', $shareDetail);
        //重置分享中的link --end
        
       // var_dump(parse_str($shareLinkParam, '&'));
        //var_dump(parse_str());
        $recentPrize = getRecentPrize($this->platform_id, $this->redis);
        $this->assign('recentPrize', $recentPrize);
        $this->assign('turnTable', $turnTable);
        $this->assign('platformUrl', $this->platform_config['PLATFORM_URL']);
        doLog('Wheel/index/loganalysis', "抽奖首页展示", '', '', $this->redisLog); //dashboard需要此数据
        //获取今明两天的抽奖次数
        // if ($this->platform_code == "ZHCJ") {

        //     $signReward = $this->getSignReward();
        //     $this->assign('signReward', $signReward);
        // }
        $themeNo = isset($this->platform_config['WHEEL_THEME'])? $this->platform_config['WHEEL_THEME']: 0;
        
        $masked_phone_number= empty($_SESSION[$_SESSION['PLATFORM_CODE']]['tel']) ? '' : substr_replace($_SESSION[$_SESSION['PLATFORM_CODE']]['tel'], '****', 3,4);
        $this->assign('masked_phone_number',$masked_phone_number);
        
        $this->display('index'.$themeNo);
    }

    /**
     * 签到
     */
   /* public function sign()
    {
        $is_login = $this->checkLogin();
        if (! $is_login) {

            $this->ajaxReturn(array(
                'state' => 11
            ));
        }
        $platform_info = D('platform')->where('code="%s"', $this->platform_code)
            ->cache(true, 300)
            ->find();
        $continueSignNum = getContinueSign();
        if (intval($continueSignNum) < intval($platform_info['checkcycle'])) {

            $state = doSign($this->redis, $this->redisLog);
            $msg = '';
            if (intval($state) == 1) {

                if ((strcasecmp($this->platform_code, 'ZHCJ') == 0) && $this->isveryfirsttimecheckin()) {

                    $msg = '亲爱的客户，第一次签到后有一次抽奖机会';
                } else {

                    $msg = '亲爱的客户，签到成功';
                }
            } elseif (intval($state) == - 1) {
                $msg = '亲爱的客户，您今天已经签到过了';
            } else {
                $msg = '亲爱的客户，签到失败,请稍候重试';
            }
        } else {
            $state = 0;
            $msg = '亲爱的客户，您有一次抽奖机会，请你先抽奖，再签到';
        }

        $data = array(
            'state' => $state,
            'msg' => $msg
        );

        exit(json_encode($data));
    }
    */

    public function prize()
    {
        $is_login = $this->checkLogin();
        if (! $is_login && strcasecmp($this->platform_code, 'NYYHCJ') != 0) { // 没有登录且没有不是农行的时候进入
            if ((strcasecmp($this->platform_code, 'ZHCJ')) == 0 && $_SESSION[$_SESSION['PLATFORM_CODE']]['isbind'] == 1) {} else {

                $this->ajaxReturn(array(
                    'state' => 11,
                    'info' => $is_login
                ));
            }
        }
        // $platform_info = D('platform')->where('code="%s"', $this->platform_code)
        //     ->cache(true, 300)
        //     ->find();
        $platform_info = $this->platform_info;
        $lotteryS = S('lottery' . $this->platform_id); // 获取活动相关缓存
        $prize_list = $_SESSION[$_SESSION['PLATFORM_CODE']]['prize_list'];
        $prize_lottery = $_SESSION[$_SESSION['PLATFORM_CODE']]['prize_lottery'];

        $ajax = empty($_POST['ajax']) ? 0 : 1;

        // $isveryfirsttimecheckin = $this->isveryfirsttimecheckin();
        $isveryfirsttimecheckin = true;
        $signTotal = 0;
        if (strcasecmp($this->platform_code, 'ZHCJ') == 0)
            $signTotal = $this->getSignTotal();
        if ($ajax) {

            //获取今明两天的抽奖次数
            $signReward = $this->getSignReward();
            if ((empty($_SESSION[$_SESSION['PLATFORM_CODE']]['prize']['name']) && empty($_SESSION[$_SESSION['PLATFORM_CODE']]['prize']['id'])) || $signReward["left"] >= 1) {
                $checkRight = $this->checkRight($this->user_id, $this->platform_id, $signReward);

                if ($checkRight["state"] && $checkRight['left'] >= 1) {

                    $PlatformProductModel = D('PlatformProduct');

                    // 如果是农业银行 对商品进行重构 判断一个月内是否已经抽到过该商品

                    if (strcasecmp($this->platform_code, 'NYYHCJ') == 0) {

                        $prize_lottery = $this->prizeLotteryReset($prize_lottery);
                    }

                    // 算概率前先看能否抽中
                    //
                    $this->redis->databaseSelect('wheel');
                    if (! in_array($this->platform_code, array('ZHCJ','NYYHCJ'))) {

                        $ip = get_client_ip(0, true);
                        $doneTelNum = $this->redis->get('NoTel:' . $_SESSION[$_SESSION['PLATFORM_CODE']]['tel']); // 此tel已有多少人中奖 502行对这两个数据进行了缓存：
                        $doneIpNum = $this->redis->get('NoIp:' . $ip); // 此IP已有多少人中奖
                    }
                    if ((($doneTelNum >= $this->telLimit || $doneIpNum >= $this->ipLimit) && ! in_array($this->platform_code, array('ZHCJ','NYYHCJ'))) || $this->isBlackList($_SESSION[$_SESSION['PLATFORM_CODE']]['tel'])) { // 招行和农行不需要此限制

                        $item_num = array_search('谢谢参与', $prize_lottery);
                    } else {
                        // 计算奖品抽中的概率
                        // 商品百分比的数组
                        $originalPercentArr = array();
                        foreach ($prize_lottery as $key => $val) {
                            
                            $originalPercentArr[$key] = isset($val['originalPercent']) && $val['originalPercent'] > 0 ? $val['originalPercent'] : 0;  
                            if ($val == '谢谢参与') {
                                
                                $originalPercentArr[$key] = -2;
                                $sort[$key] = - 2; // 2作为标识 lottery（）中将键值转为整数的时候用到
                                $_SESSION[$_SESSION['PLATFORM_CODE']]['3q_id'] = $key;
                                continue;
                            }

                            if (strcasecmp($this->platform_code, 'ZHCJ') == 0 && $val['num'] <= 10 && $signTotal <= 10) { // 招行用户，贵重商品，签到天数小于10;

                                $sort[$key] = 0;
                                continue;
                            }

                            if ($val['state'] && (strcasecmp($this->platform_code, 'NYYHCJ') == 0)) { // 如果是农业银行 而且 当月已经抽到过了则概率为0

                                $sort[$key]= 0;
                                continue;
                            }

                            $now = date('Y-m-d H:i:s');
                            if ($val['donum'] >= $val['num'] || $val['start'] > $now || $val['end'] < $now) { // 判断活动是否开始或者结束（结束：时间到了或者数量超过上限）->避免打开页面后过一段时间抽奖导致抽奖过期

                                $sort[$key] = 0;
                                continue;
                            }

                            if ($PlatformProductModel->checkLotteryExist($val['lottery_id']) != 1) {

                                $sort[$key] = 0;
                                continue;
                            }

                            $past = day($val['start'], date('Y-m-d 00:00:00', strtotime('+1 day')), 1);
                            $date = $lotteryS[$val['lottery_id']]['date']; // 活动时间-
                            $left = day(date('Y-m-d H:i:s'), $val['end'], 1); // 剩余的时间
                                                                              // 从数量上进行控制
                            if ($val['donum'] > ($val['num'] * 1.2 * $past / $date)) {

                                $sort[$key] = 0;
                                continue;
                            }
                            // 从平均小速率上进行控制
                            $perPast = $val['donum'] / ($past * $this->people); // 消耗速率
                            $percent = $lotteryS[$val['lottery_id']]['percent']; // 希望的理论上的平均消耗速率

                            if ($perPast >= 1.2 * $percent) {

                                $sort[$key] = 0;
                                continue;
                            }
                            // 获取当天已经消耗多少了begin
                            // ########
                            // ########
                            $todayKey = 'Wheel::productPtid:' . $val['product_id'] . '_' . $this->platform_id;
                            if (online_redis_server) {

                                $this->redis->databaseSelect('wheel');
                                $today = $this->redis->get($todayKey);
                            } else {

                                $today = S($todayKey) ? S($todayKey) : 0; // 获取缓存中的消耗数量
                            }
                            if (empty($today)) { // 缓存中数据为空 折从数据库中读取

                                $today = M('order_product')->where("productid=%d and ptid=%d and createtime>'%s'", array(
                                    $val['product_id'],
                                    $this->platform_id,
                                    date('Y-m-d')
                                ))->count();
                                if (online_redis_server) {

                                    $this->redis->set($todayKey, $today);
                                    $this->redis->expire($todayKey, $this->dayLeftSecond);
                                } else {

                                    $today = S($todayKey, $today); // 获取缓存中的消耗数量
                                }
                            }

                            // 获取当天已经消耗多少了end
                            // ########
                            // ########
                            $judge = ($val['num'] - $val['donum'] + $today) / $left; // 判断 剩下来的是一天多个还是多个一天
                            if ($judge >= 1) { // 一天多个或一个

                                $numD = ceil($judge); // 一天理论上限
                                $judgeH = $numD / 24; // 判断是一个小时多个还是多个一个小时
                                $perHpast = $today / ($this->people * ((date('H') + 1) / 24));
                                if ($perHpast >= 1.2 * $percent) { // 当日的消耗速率超过平均消耗速率

                                    $sort[$key] = 0;
                                } else
                                    if ($perHpast < ($percent / 2)) { // 当日的消耗速率未超过平均消耗速率

                                        if ($judgeH > 1) {

                                            $sort[$key] = get_sort(24, date('H'), 24, $percent, $date, $left); // 一个小时派发多个
                                        } else {

                                            $sort[$key] = get_sort(24, date('H') + 1, $numD, $percent, $date, $left); // 多个小时派发一个
                                        }
                                    } else {

                                        $sort[$key] = $percent;
                                    }
                            } else { // 多天一个

                                if ($perPast < ($percent / 2)) {

                                    $sort[$key] = get_sort($lotteryS[$val['lottery_id']]['date'], $past, $val['num'], $percent, $date, $left);
                                } else {

                                    $sort[$key] = $percent;
                                }
                            }
                            // 最后6小时 最低概率为 0.5
                            $leftHour = round((strtotime($val['end']) - time()) / 3600);
                            $leftPercent = 1 - 0.1 * $leftHour;
                            if ($leftHour <= 5 && $sort[$key] < $leftPercent)
                                $sort[$key] = $leftPercent;
                            if ($leftHour == 0 && ($val['num'] - $val['donum']) > 0) { // 某商品在最后一小时的时候 优先抽这个商品

                                $item_num = $key;
                                doLog('Wheel/prizeLastOneHour', "最后一小时", '', '商品：' . json_encode($prize_lottery[$item_num]) . 'leftHour:' . $leftHour, $this->redisLog);
                                break;
                            }
                        }

                        if (! $item_num){
                            
                            //避免商品只有一个的情况  prize_lottery里谢谢参与的个数和  $this->thankNum;不符；导致出错
                            $countThank = array_count_values($prize_lottery);
                            if($countThank['谢谢参与'] >=1 && $countThank['谢谢参与'] <= 2){

                                $thankNum = $countThank['谢谢参与'];
                            }else{

                                $thankNum = $this->thankNum;
                            }
                           
                            $item_num = lottery($sort, 100000000, $thankNum); // 如果没有优先抽的商品则生成该商品
                        }
                    }
                    doLog('Wheel/prize', "抽奖概率", '', '商品：' . json_encode($prize_lottery[$item_num]) . ', item_num=' . $item_num . json_encode($sort), $this->redisLog);
                    //没中奖根据百分比再抽一次
                    if ($prize_lottery[$item_num] == '谢谢参与') {
                        
                        $item_num = lottery($originalPercentArr, 100000000, $thankNum);
                        doLog('Wheel/prize', "抽奖概率(抽奖概率)", '', '商品：' . json_encode($prize_lottery[$item_num]) . ', item_num=' . $item_num . json_encode($originalPercentArr), $this->redisLog);
                    }
                    if ($prize_lottery[$item_num] != '谢谢参与') {

                        $state = $PlatformProductModel->checkLotteryExist($prize_lottery[$item_num]['lottery_id']);
                        //商品已下架或者库存不足等情况
                        if ($state != 1) {

                            //$state = 1;
                            foreach ($prize_lottery as $key => $val) {

                                if ($val == '谢谢参与') {

                                    $item_num = $key;
                                    break;
                                }
                            }
                        } else { // 中奖的时候对 电话号码和ip做缓存
                                 //
                                 // 中奖的时候对 电话号码和ip做缓存
                                 //
                                 // 避免同一ip或者同一电话号码多次中奖
                                 //
                                 //
                            if (! in_array($this->platform_code, array(
                                'ZHCJ',
                                'NYYHCJ'
                            ))) {
                                if (! empty($_SESSION[$_SESSION['PLATFORM_CODE']]['tel'])) {

                                    $doneTelNum = $doneTelNum >= 1 ? $doneTelNum + 1 : 1;
                                    $this->redis->set('NoTel:' . $_SESSION[$_SESSION['PLATFORM_CODE']]['tel'], $doneTelNum);
                                    $this->redis->expire('NoTel:' . $_SESSION[$_SESSION['PLATFORM_CODE']]['tel'], 7 * 24 * 3600);
                                }

                                $doneIpNum = $doneIpNum >= 1 ? $doneIpNum + 1 : 1;
                                $this->redis->set('NoIp:' . $ip, $doneIpNum);
                                $this->redis->expire('NoIp:' . $ip, 24 * 3600);
                            }
                        }
                    } 
                    // else {

                    //     $state = 1;
                    // }

                    $_SESSION[$_SESSION['PLATFORM_CODE']]['prize']['name'] = $prize_list[$item_num];
                    //未中奖
                    if ($prize_lottery[$item_num] == '谢谢参与') {

                        $_SESSION[$_SESSION['PLATFORM_CODE']]['prize']['id'] = 0;
                        $_SESSION[$_SESSION['PLATFORM_CODE']]['prize']['lottery_id'] = 0;
                    //中奖
                    } else {

                        $_SESSION[$_SESSION['PLATFORM_CODE']]['prize']['id'] = $prize_lottery[$item_num]['product_id'];
                        $_SESSION[$_SESSION['PLATFORM_CODE']]['prize']['lottery_id'] = $prize_lottery[$item_num]['lottery_id'];
                    }

                    // 签到次数重置为0
                    if ($platform_info['checkcycle'] > 1) {

                        $where = array();
                        $where['userid'] = $_SESSION[$_SESSION['PLATFORM_CODE']]['id'];
                        $where['ptid'] = $this->platform_id;
                        D('sign_day')->where("userid=%d and ptid=%d", array(
                            $where['userid'],
                            $where['ptid']
                        ))->save(array(
                            'signday' => 0
                        ));
                    }

                    $return_result = $this->doOrder();
                    if ($_SESSION[$_SESSION['PLATFORM_CODE']]['prize']['name'] == "谢谢参与" || $_SESSION[$_SESSION['PLATFORM_CODE']]['prize']['id'] == '0') {
                        $state = 2;
                        if ($platform_info['checkcycle'] > 1) {

                            if ((strcasecmp($this->platform_code, 'ZHCJ') == 0) && $isveryfirsttimecheckin) {

                                $msg = '亲爱的客户，第一次签到后有一次抽奖机会';
                            } else {

                                $msg = '别灰心哦，再接再厉！累积签到' . $platform_info['checkcycle'] . '天即获得一次抽奖机会';
                            }
                        } else {

                            $msg = '亲，每天都有一次抽奖机会，记得明天再来哦';
                        }
                    } else {
                        if ($return_result['state'] == 1) {

                            $msg = $return_result['msg'];
                            $state = 1;
                        } else {
                            $item_num = $_SESSION[$_SESSION['PLATFORM_CODE']]['3q_id'];
                            $msg = $return_result['msg'];
                            $state = 2;
                        }
                    }
                } else {

                    $state = 0;
                    $item_num = 0;
                    if ($platform_info['checkcycle'] > 1) {

                        if ((strcasecmp($this->platform_code, 'ZHCJ') == 0) && $isveryfirsttimecheckin) {

                            $msg = '亲爱的客户，第一次签到后有一次抽奖机会';
                        } else {

                            $msg = '亲爱的客户，累积签到' . $platform_info['checkcycle'] . '天即获得一次抽奖机会';
                        }
                    } else {

                        $msg = '亲，每天都有一次抽奖机会，记得明天再来哦';
                    }
                }
            } else {
                $state = 0;
                $item_num = 0;
                if ($platform_info['checkcycle'] > 1) {

                    if ((strcasecmp($this->platform_code, 'ZHCJ') == 0) && $isveryfirsttimecheckin) {

                        $msg = '亲爱的客户，第一次签到后有一次抽奖机会';
                    } else {

                        $msg = '亲爱的客户，累积签到' . $platform_info['checkcycle'] . '天即获得一次抽奖机会';
                    }
                } else {

                    $msg = '亲，每天都有一次抽奖机会，记得明天再来哦';
                }
            }
            $data = array(
                'state' => $state,
                'item' => $item_num,
                'msg' => $msg,
                'product_name' => isset($prize_lottery[$item_num]['product_name']) ? $prize_lottery[$item_num]['product_name'] : '',
                'orderid' => isset($return_result['orderid']) ? $return_result['orderid'] : '',
                'leftNum' => ($checkRight['left'] - 1) >= 0 ? $checkRight['left'] - 1 : 0,
                'isShare' => isset($signReward['isShare']) ? $signReward['isShare'] : 0,
                'ifaddr'  => isset($return_result['ifaddr']) ? $return_result['ifaddr'] : 0,
            );
            exit(json_encode($data));
        } else {
            // $product_info = null;
            // if ($_SESSION[$_SESSION['PLATFORM_CODE']]['prize']['id'] != '0' && !empty($_SESSION[$_SESSION['PLATFORM_CODE']]['prize']['id'])) {
            // $product_info = getProductInfo($_SESSION[$_SESSION['PLATFORM_CODE']]['prize']['id']);
            // $this->assign('platform_id', $_SESSION[$_SESSION['PLATFORM_CODE']]['platform_id']);
            // }
            // $product_name = $product_info['name'] ? $product_info['name'] : '奖品介绍';
            // $this->assign('info', $product_info);
            // $this->assign('product_name', $product_name);
            // $this->display('prize');

            $product_info = null;
            if ($_SESSION[$_SESSION['PLATFORM_CODE']]['prize']['id'] != '0' && ! empty($_SESSION[$_SESSION['PLATFORM_CODE']]['prize']['id'])) {
                $product_info = getProductInfo($_SESSION[$_SESSION['PLATFORM_CODE']]['prize']['id']);
            }
            $this->assign('platform_id', $this->platform_id);
            $product_info['product_exist'] = D('platform_product')->checkProductExist($id);
            $product_info['detail_picture'] = RESOURCE_PATH . $product_info['detail_picture'];
            // 解析description中的XML数据
            $product_info['introduction'] = xmlToStr($product_info['description'], 'introduction');
            $product_info['rules'] = xmlToStr($product_info['description'], 'rules');
            $product_info['time'] = xmlToStr($product_info['description'], 'time');
            $product_name = $product_info['name'] ? $product_info['name'] : '奖品介绍';

            // 订单信息
            $order_id = $_SESSION[$_SESSION['PLATFORM_CODE']]['ORDER']['order_id'];
            if ($order_id <= 0) {

                $this->error('订单号有误');
            }
            $orderInfo = M('order')->where('id=%d', $order_id)->find();
            if (empty($orderInfo))
                $this->error('订单不存在,请到中奖纪录中查看');
            if ($orderInfo['tel']) {

                $status = 2;
            } else {

                $status = 0;
            }
            $this->assign('judgeLingqu', $status);
            $this->assign('orderid', $order_id);

            $this->assign('info', $product_info);
            $this->assign('product_name', $product_name);

            $cutomerServiceTel = getCustomerServiceTel($product_info['id'], $this->redis);
            if (empty($cutomerServiceTel))
                $cutomerServiceTel = "021-62457922"; // 如果商户没有客服电话，则把乐天邦的客服电话展示给用户
            $this->assign('cutomer_service_tel', $cutomerServiceTel);

            doLog('Wheel/viewProduct/loganalysis', "中奖商品详情", $id, json_encode($info), $this->redisLog); //dashboard
            $this->display('showProduct');
        }
    }

    public function wait()
    {
        $this->display('wait');
    }

    // 加入订单
    public function doOrder()
    {
        $product_id = $_SESSION[$_SESSION['PLATFORM_CODE']]['prize']['id'];

        doLog('Wheel/Join/loganalysis', "参与抽奖", $product_id, '', $this->redisLog); // 记录抽奖次数
                                                                                   // $is_get = $this->checkOrder($_SESSION[$_SESSION['PLATFORM_CODE']]['id'], $_SESSION[$_SESSION['PLATFORM_CODE']]['platform_id']);
                                                                                   // if (!$is_get) {
                                                                                   // return array('state' => 0, 'msg' => '亲爱的客户，您已经领取一张了，不能重复领取。');
                                                                                   // }

        $PlatformProductModel = D('PlatformProduct');

        $ProductModel = M('Product');
        $PlatformModel = M('Platform');
        $ProductCodeModel = M('ProductCode');

        $OrderModel = D('order');
        $platform_info = $PlatformModel->where('id=%d', $this->platform_id)->find();
        $product_info = $ProductModel->where('id=%d', $product_id)->find();
        if ($product_id) {
            if ($PlatformProductModel->checkLotteryExist($_SESSION[$_SESSION['PLATFORM_CODE']]['prize']['lottery_id']) != 1) { // no cache
                doLog('Wheel/doOrder', "用户下订单-库存不足", $product_id, '', $this->redisLog);
                $msg = "亲爱的管理员，抽奖平台" . $this->platform_code . "的奖品" . $product_id . '名字:' . $product_info['name'] . "已经发完，请及时补充,谢谢！";
                send_msg(ADMIN_MOBILE_PHONE, $msg);
                $this->prizeLog($platform_info['id'], $platform_info['name']);
                return array(
                    'state' => 0,
                    'msg' => '亲爱的客户，奖品已经领完了或者活动已经结束。'
                );
            }
        }
        if ($product_info) {

            doLog('Wheel/Prize/loganalysis', "中奖", $product_id, '', $this->redisLog); // 参与抽奖

            if ($product_info['verification'] == 1) { // 要发优惠券

                $product_code = $ProductCodeModel->where('productid=%d and status=0', $product_id)
                    ->lock(true)
                    ->find();
                if (empty($product_code)) {

                    $state5 = false;
                } else {
                    $state5 = $ProductCodeModel->where('id=%d and status=0', $product_code['id'])
                        ->lock(true)
                        ->data(array(
                        'status' => 1,
                        'updatetime' => date('Y-m-d H:i:s')
                    ))
                        ->save();
                }
            } else {
                $state5 = true;
            }
        } else {
            $state5 = true;
        }

        if ($state5 && $this->platform_id) {
            // 订单表里生成订单记录

            $id = $this->prizeLog($platform_info['id'], $platform_info['name']);
            $_SESSION[$_SESSION['PLATFORM_CODE']]['ORDER']['order_id'] = $id;

            // 订单商品表里生成订单记录
            if ($product_info) {
                $OrderProductModel = D('OrderProduct');
                $product_data = array();
                $product_data['orderid'] = $id;
                $product_data['productid'] = $product_id;
                $product_data['productname'] = $product_info['name'];
                $product_data['producttypeid'] = $product_info['type_id'];
                $product_data['ptid'] = $platform_info['id'];
                $product_data['ptname'] = $platform_info['name'];

                $product_data['status'] = $product_info['ifpay'];
                $product_data['productcodeid'] = $product_code['id'];
                $product_data['productcode'] = $product_code['couponcode'];
                $product_data['createtime'] = date('Y-m-d H:i:s');
                $product_data['updatetime'] = date('Y-m-d H:i:s');
                $product_data['tel'] = '';
                $od_id = $OrderProductModel->data($product_data)->add();
                $_SESSION[$_SESSION['PLATFORM_CODE']]['ORDER']['product_order_id'] = $od_id;
                // 检验订单是否正常生成否则返回
                if ($id && $od_id) {
                    $PlatformProductModel->where("platform_id=%d and product_id=%d and total>0", array(
                        $this->platform_id,
                        $product_id
                    ))->setInc('salequantity', 1);
                    $ProductModel->where('id=%d and total>0', $product_id)->setInc('saleallquantity', 1);
                } else {
                    if ($id) {
                        $OrderModel->where('id=%d', $id)->delete();
                    }
                    if ($od_id) {
                        $OrderProductModel->where('id=%d', $od_id)->delete();
                    }
                    if ($product_info['verification'] == 1 && $state5) {
                        $ProductCodeModel->where('id=%d and status=1', $product_code['id'])
                            ->lock(true)
                            ->data(array(
                            'status' => 0,
                            'updatetime' => date('Y-m-d H:i:s')
                        ))
                            ->save();
                    }
                    doLog('Wheel/doOrder', "用户下订单-系统繁忙", $product_id, '', $this->redisLog);
                    unset($_SESSION[$_SESSION['PLATFORM_CODE']]['prize']);
                    return array(
                        'state' => 0,
                        'msg' => '对不起，系统繁忙，稍后请重试一次。'
                    );
                }
                // 生成今日已抽取缓存 begin

                $lottery_id = $_SESSION[$_SESSION['PLATFORM_CODE']]['prize']['lottery_id'];
                $todayKey = 'Wheel::productPtid:' . $product_id . '_' . $platform_info['id'];
                if (online_redis_server) {

                    $this->redis->databaseSelect('wheel');
                    $donum = $this->redis->get($todayKey);
                    if ($donum > 0) {

                        $donum += 1;
                    } else {

                        $donum = 1;
                    }
                    $this->redis->set($todayKey, $donum);
                    $this->redis->expire($todayKey, $this->dayLeftSecond);
                } else { // redis没有开启使用系统自带的缓存技术

                    $donum = S($todayKey) ? S($todayKey) : 0;
                    S($todayKey, $donum + 1, $this->dayLeftSecond);
                }

                // 生成今日已抽取缓存 end

                return array(
                    'state' => 1,
                    'msg' => '加入成功',
                    'orderid' => $id,
                    'ifaddr' => isset($product_info['ifaddr']) ? $product_info['ifaddr'] : 0,
                );
            }
        } else {

            doLog('Wheel/doOrder', "用户下订单-库存不足", $product_id, '', $this->redisLog);
            $msg = "亲爱的管理员，抽奖平台" . $this->platform_code . "的奖品" . $product_id . "优惠码有可能已经发完！";
            send_msg(ADMIN_MOBILE_PHONE, $msg);
            $this->prizeLog($platform_info['id'], $platform_info['name']);
            doLog('Wheel/doOrder', "用户下订单-系统繁忙", $product_id, '', $this->redisLog);
            unset($_SESSION[$_SESSION['PLATFORM_CODE']]['prize']);
            return array(
                'state' => 0,
                'msg' => '对不起，系统繁忙，稍后请重试一次。'
            );
        }
    }

    /**
     * [prizeLog 抽奖操生成日志]
     * @ckhero
     * @DateTime 2016-09-20
     *
     * @param [type] $ptid
     *            [description]
     * @param [type] $ptname
     *            [description]
     * @return [type] [order表的id]
     */
    private function prizeLog($ptid, $ptname)
    {
        $data = array();
        $data['ordenum'] = date('YmdHis') . createNonceStr(4, '1234567890');
        $data['tel'] = '';
        $data['ptuserid'] = $_SESSION[$_SESSION['PLATFORM_CODE']]['id'];
        $data['ptid'] = $ptid;
        $data['ptname'] = $ptname;
        $data['updatetime'] = date('Y-m-d H:i:s');
        $data['ordertime'] = date('Y-m-d H:i:s');
        $data['createtime'] = date('Y-m-d H:i:s');
        $data['updatetime'] = date('Y-m-d H:i:s');
        $id = M('order')->data($data)->add();
        $this->redis->databaseSelect('wheel');
        $signReward = $this->redis->hget($this->signRewardKey);
        
        //可抽奖次数减一次         
        if (isset($signReward['today']) && $signReward['today'] >= 1 && $this->platform_code == 'ZHCJ') {
            
            if(($signReward['left'] - 1) <= 0) {

                $checkRight = $this->checkRight($this->user_id, $this->platform_id, $signReward);
                $left = $checkRight['left'];
            } else {

                $left = $signReward['left'] - 1;
            }
            $this->redis->hset($this->signRewardKey, $left, 'left');
        }
        return $id;
    }

    public function updateOrder($item_num = -1)
    {
        $order_id = I('post.orderid', 'intval');
        $order_info = M('order')->where('id=%d and ptuserid=%d', array(
            $order_id,
            $_SESSION[$_SESSION['PLATFORM_CODE']]['id']
        ))->find();

        if (empty($order_info))
            $this->ajaxReturn(array(
                'state' => 1,
                'msg' => '亲爱的客户，订单有误，请联系工作人员。'
            ));

        $platform_id = $order_info['ptid'];
        $productOrderInfo = M('order_product')->where('orderid=%d', $order_id)->find();
        $product_order_id = $productOrderInfo['id'];
        $product_id = $productOrderInfo['productid'];
        $is_get = $this->checkOrderStatus($order_info); // //多余,应该从前面的$order_info 里面取值
        if ($is_get) {
            $is_login = $this->checkLogin();
            if ($is_login) {
                doLog('Wheel/download/loganalysis', "领取奖品", $product_id, '', $this->redisLog);  //dashboard, 

                $OrderModel = D('order');
                $OrderProductModel = D('OrderProduct');
                $ProductModel = M('Product');
                $PlatformModel = M('Platform');
                $CustomerModel = M('Customer');
                $platform_info = $PlatformModel->where('id=%d', $platform_id)
                    ->cache(true, 300)
                    ->find();
                $product_info = $ProductModel->where('id=%d', $product_id)
                    ->cache(true, 300)
                    ->find();
                $order_product_info = $productOrderInfo;
                $customer_info = $CustomerModel->where('id=%d', $platform_info['customer_id'])
                    ->cache(true, 300)
                    ->find();
                // 发送短信，并将返回值保存到订单表
                $return_sms = NULL;
                
                
                if ($product_info['issms'] == 1) {
                    
                    if ( $this->platform_code == 'NYYHD' || $this->platform_code == 'NYYHCJ') {

                        $msg = str_replace('{channel}', $customer_info['name'], $product_info['smstpl']);
                        $msg = str_replace('{couponcode}', $order_product_info['productcode'], $msg);

                        if (strpos($msg, '{phone}') !== false) {    //如果商家要求传手机号码
                            $msg = str_replace('{phone}', urlencode(base64_encode($session['tel'])), $msg);
                        }
                    } else {

                        //模板选择
                        $smstpl = $this->getSmstpl($product_info['ifaddr']);
                        $msg = str_replace('{channel}', $customer_info['name'], $smstpl);
                        $msg = str_replace('{product_name}', $product_info['name'], $msg);
                    }
                    $return_sms = send_msg($_SESSION[$_SESSION['PLATFORM_CODE']]['tel'], $msg);
                    // $return_sms = null;
                    $return['msg'] = $return_sms;
                    $coupon['code'] = $order_product_info['productcode'];
                    doLog('Wheel/updateOrder', "用户下订单-短信返回结果", $product_id, json_encode($return), $this->redisLog);

                    doLog('Wheel/updateOrder', "用户下订单-成功", $product_id, json_encode($coupon), $this->redisLog);

                    $dataJson = array(
                        'state' => 1,
                        'msg' => '您已领取成功啦，稍后会有短信通知。',
                        'item' => $item_num
                    );
                } else {
                    $time_t0 = microtime(true);
                    // 是否调用第三方接口，默认正常提示，1发送短信，2跳转链接
                    if ($product_info['extersysdock'] == 1) {
                        if ($product_id == 9) {
                            if ($return_data->resultCode == 0) {
                                $dataJson = array(
                                    'state' => 0,
                                    'msg' => '' . $return_data->resultMsg,
                                    'item' => $item_num
                                );
                            } else {
                                $dataJson = array(
                                    'state' => 1,
                                    'msg' => '' . $return_data->resultMsg,
                                    'item' => $item_num
                                );
                            }
                        } else {
                            $url = $product_info['interfaceaddr'];
                            $post_data = array();
                            $key = 'zcnmedia';
                            $post_data['uId'] = encrypt($key, $_SESSION[$_SESSION['PLATFORM_CODE']]['tel']);
                            $post_data['merchantId'] = 'XHS';
                            $post_data['timestamp'] = time();
                            $param = http_build_query($post_data);
                            $url = $url . "?" . $param;
                            $return_data = send_curl($url);
                            $return['msg'] = $return_data;
                            doLog('Wheel/updateOrder', "用户下订单-短信返回结果", $product_id, json_encode($return), $this->redisLog);
                            $json_data = json_decode($return_data, true);
                            $return_sms = $json_data['status'] . ':' . $json_data['returnMessage'] . ':' . $json_data['returnCode'];
                            // 针对第三方接口返回值，进行提示
                            if ($json_data['status'] != 1 || $json_data['returnCode'] != '0000') {
                                doLog('Wheel/updateOrder', "用户下订单-失败", $product_id, '', $this->redisLog);
                                $dataJson = array(
                                    'state' => 0,
                                    'msg' => ' ' . $json_data['returnMessage']
                                );
                            } else {
                                doLog('Wheel/updateOrder', "用户下订单-成功", $product_id, '', $this->redisLog);
                                $dataJson = array(
                                    'state' => 1,
                                    'msg' => '您已领取成功啦，稍后会有短信通知。',
                                    'item' => $item_num
                                );
                            }
                        }
                        $time_getflow = microtime(true);
                        $elapseTime = ' extersysdock(=1): ' . ($time_getflow - $time_t0);
                        doLog('Wheel/debug', "发送短信耗时", $product_id, $elapseTime, $this->redisLog);
                    } elseif ($product_info['extersysdock'] == 2) {
                        doLog('Wheel/updateOrder', "用户下订单-成功", $product_id, '', $this->redisLog);
                        $dataJson = array(
                            'state' => 1,
                            'msg' => '尊敬的客户您好，请您点击【好】，在弹出的页面上，填写预约试驾信息并提交，才能领取成功，我们的客服会在48小时之内联系您！',
                            'return_url' => $product_info['interfaceaddr'],
                            'item' => $item_num
                        );
                    } elseif ($product_info['extersysdock'] == 3) {

                        if (intval($product_info['type_id']) == 3) {
                            // //送流量手机号码段验证
                            $time_t0 = microtime(true);

                            $return_info = get_flow($_SESSION[$_SESSION['PLATFORM_CODE']]['tel'], $order_id);
                            $return = json_decode($return_info, true);
                            $time_putflow = microtime(true);
                            $elapseTime = 'checkarea:' . ($time_checktelarea - $time_t0) . ',putFlowToUser:' . ($time_putflow - $time_checktelarea);
                            doLog('Wheel/debug', "发送短信耗时", $product_id, $elapseTime, $this->redisLog);
                            if (strval($return['Code']) != '0') {
                                $OrderModel->where('id=%d', $order_id)
                                    ->data(array(
                                    //'tel' => $_SESSION['PLATFORM_CODE']['tel'],
                                    'updatetime' => date("Y-m-d:H:i:s")
                                ))
                                    ->save(); // 邵晓凌： 加 limit 1???
                                $OrderProductModel->where('id=%d', $product_order_id)
                                    ->data(array(
                                    'smsreturnvalue' => $return_info,
                                    'tel' => $_SESSION[$_SESSION['PLATFORM_CODE']]['tel'],
                                    'updatetime' => date("Y-m-d:H:i:s"),
                                   // 'productname' => $product_no_str
                                ))
                                    ->save(); // 邵晓凌： 加 limit 1???
                                $dataJson = array(
                                    'state' => 0,
                                    'msg' => '亲爱的客户，请您再试一次，若还是不能领取请联系我们客服。',
                                    'item' => $item_num
                                );
                                exit(json_encode($dataJson));
                            }
                            $return_sms = $return_info;
                        }
                        $dataJson = array(
                            'state' => 1,
                            'msg' => '亲爱的客户，您已成功领取，稍后会短信通知您。',
                            'item' => $item_num
                        );
                    } else {
                        doLog('Wheel/updateOrder', "用户下订单-成功", $product_id, '', $this->redisLog);
                        $dataJson = array(
                            'state' => 1,
                            'msg' => '您已领取成功啦，稍后会有短信通知。',
                            'item' => $item_num
                        );
                    }
                }
                 
                $OrderModel->where('id=%d', $order_id)
                    ->data(array(
                    'tel' => $_SESSION[$_SESSION['PLATFORM_CODE']]['tel'],
                    'pay_status' => '2'
                ))
                    ->save(); // 邵晓凌： 加 limit 1??? //状态改为1
                $OrderProductModel->where('id=%d', $product_order_id)
                    ->data(array(
                    'smsreturnvalue' => $return_sms,
                    'tel' => $_SESSION[$_SESSION['PLATFORM_CODE']]['tel']
                ))
                    ->save(); // 邵晓凌： 加 limit 1???
               //更新order_extra中的收货地址
                if ($product_info['ifaddr'] == 1) {

                    $addrDetail = M('user_mailing_addr')->field('name, tel, province, city, address')->where("id = %d", $order_info['addr_id'])->find();
                    M('order_extra')->add(array('order_id' => $order_id, 'addr_detail' => json_encode($addrDetail)));
                }
                $dataJson['orderid'] = $order_id;
                exit(json_encode($dataJson));
            } else {
                $dataJson = array(
                    'state' => - 1,
                    'msg' => '亲，请您先填写手机号码再领取！'
                );
                exit(json_encode($dataJson));
            }
        } else {
            $dataJson = array(
                'state' => 0,
                'msg' => '亲，您已经领取过了,无法重复领取！'
            );
            exit(json_encode($dataJson));
        }
    }

    // 订单存在返回false
    function checkRight($uid, $pid, $signReward)
    {
        $OrderModel = D('Order');
        $map = array();
        $map['ptuserid'] = $uid;
        $map['ptid'] = $pid;
        $order_info = $OrderModel->where("ptuserid=%d and ptid=%d and ordertime between '%s' and '%s'", array(
            $map['ptuserid'],
            $map['ptid'],
            date('Y-m-d H:i:s', strtotime(date('Y-m-d'))),
            date('Y-m-d H:i:s', (strtotime(date('Y-m-d')) + 24 * 3600))
        ))->select();
        
        //今日已使用的抽奖次数
        $lotteryNum = count($order_info);
        // 判斷是否需要簽到
        // $platform_info = D('platform')->where('code="%s"', $this->platform_code)
        //     ->cache(true, 300)
        //     ->find();
        $platform_info = $this->platform_info;
        //剩余的抽奖次数
        $data["left"] = $signReward['today'] - $lotteryNum;
        if ($platform_info['checkcycle'] > 1) {

            $continueSignNum = getContinueSign();
            if ((strcasecmp($this->platform_code, 'ZHCJ') == 0) && $this->isveryfirsttimecheckin() && $continueSignNum == 1 && $lotteryNum < $signReward['today']) {

                //return true;
                $data['state'] = true;
                return $data;
            }

            if ($lotteryNum < $signReward['today'] && (intval($continueSignNum) == intval($platform_info['checkcycle']))) {
                //return 1;
                $data['state'] = true;
                return $data;
            } else {
                //return 0;
                $data["state"] = false;
                return $data;
            }
        } else {

            if ($lotteryNum < $signReward['today']) {
                //return true;
                //
                $data['state'] = true;
                return $data;
            } else {
                //return false;
                $data["state"] = false;
                return $data;
            }
        }
    }

    // 订单存在返回false
    function checkOrder($uid, $pid)
    {
        $OrderModel = D('Order');
        $map = array();
        $map['ptuserid'] = $uid;
        $map['ptid'] = $pid;
        $order_info = $OrderModel->where("ptuserid=%d and ptid=%d and ordertime between '%s' and '%s'", array(
            $map['ptuserid'],
            $map['ptid'],
            date('Y-m-d H:i:s', strtotime(date('Y-m-d'))),
            date('Y-m-d H:i:s', (strtotime(date('Y-m-d')) + 24 * 3600))
        ))->find();

        if ($order_info) {
            return false;
        } else {
            return true;
        }
    }

    // 活动时间检测
    function checkTme()
    {
        if (time() < strtotime($this->platform_config['ACTIVITY_START']) || time() > strtotime($this->platform_config['ACTIVITY_END'])) {
            redirect(U('Home/Wait/index'));
        }
    }

    // 订单存在返回false
    function checkOrderStatus($order_info)
    {
        // $OrderModel = D('Order');
        // $order_info = $OrderModel->where("id=%d", $order_id)->find();
        if ($order_info['pay_status'] == 2) {

            return false;
        }
        if ($this->timeOutStart > $order_info['createtime'] && $order_info['tel']) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * [isveryfirsttimecheckin 判断是否是第一次抽奖]
     * @ckhero
     * @DateTime 2016-07-18
     *
     * @return [type] [description]
     */
    public function isveryfirsttimecheckin()
    { // 在第一次签到，直到第一次抽奖前都是true
        $SignDay = D('sign_day');
        $where['userid'] = $_SESSION[$_SESSION['PLATFORM_CODE']]['id'];
        $where['ptid'] = $this->platform_id;
        $sign_day_data = $SignDay->where("userid=%d and ptid=%d", array(
            $where['userid'],
            $where['ptid']
        ))->find();

        if ($sign_day_data) {
            if (intval($sign_day_data['signtotal']) == 0)
                return true;
            if ((intval($sign_day_data['signtotal']) == 1) && (intval($sign_day_data['signday']) == 1)) // 签到但是没有领奖
                return true;
        } else {
            return true;
        }
        return false;
    }

    /**
     * [getSignTotal 获取签到总天数]
     * @ckhero
     * @DateTime 2016-12-20
     *
     * @return [type] [description]
     */
    public function getSignTotal()
    {
        $SignDay = D('sign_day');
        $where['userid'] = $_SESSION[$_SESSION['PLATFORM_CODE']]['id'];
        $where['ptid'] = $this->platform_id;
        $sign_day_data = $SignDay->where("userid=%d and ptid=%d", array(
            $where['userid'],
            $where['ptid']
        ))->find();
        $signTotal = $sign_day_data['signtotal'] > 0 ? $sign_day_data['signtotal'] : 0;
        return $signTotal;
    }
    
    /**
     * [getRecord 获取中奖纪录]
     * @ckhero
     * @DateTime 2017-03-13
     * @return   [type]     [description]
     */
    public function getRecord()
    {

        if (!is_ajax()) {

            $this->error(L('_ILLEGAL_REQUEST_'), C('WEB_URL') . C('ERROR_PAGE'));
        }
        if (I('get.dosubmit') == 'dosubmit') {

            $list = D('order')->alias('o')
                ->join('JOIN __ORDER_PRODUCT__ op ON o.id = op.orderid')
                ->join('JOIN __PRODUCT__ p ON p.id = op.productid')
                ->where("o.ptuserid=%d and o.ptid=%d", array(
                $_SESSION[$_SESSION['PLATFORM_CODE']]['id'],
                $this->platform_id
            ))
                ->field('o.id,o.tel, o.createtime, o.pay_status, op.productid,op.productname,op.updatetime,op.productcode,o.id, p.ifAddr')
                ->order('o.id desc')
                ->select();
            if (empty($list)) {

                $state = 0;
            } else {

                $state = 1;

                foreach ($list as $key => $val) {
                    //状态为6 的或者创建时间大于截止时间并且距现在为10天前的并且状态为0的为过期订单
                    if($val['pay_status'] == 6 || ($val['createtime'] < $this->timeOutLimit && $val['createtime'] > $this->timeOutStart && $val['pay_status'] ==0 )) {

                        $list[$key]['status'] = 0; //超时订单
                    } else {

                        if (($val['pay_status'] == 2 && $val['createtime'] > $this->timeOutStart) || ($val['tel'] && $val['createtime'] <= $this->timeOutStart)) {

                            $list[$key]['status'] = 1;//已领取订单
                        } else {

                            $list[$key]['status'] = 2;//未领取订单
                        }
                    }
                    //截取日期后的pay_status 为2 即为领取过的订单,截止日期前的不作处理
                    if($val['createtime'] > $this->timeOutStart && $val['pay_status'] == 2) { 
                        
                        $list[$key]['url'] = U('Order/orderDetail', array('id' => $val['id']));
                    } else {

                         $list[$key]['url'] = U('Wheel/showProduct', array(
                        'id' => $val['productid'],
                        'orderid' => $val['id']
                    ));
                    }
                }
            }
            $this->ajaxReturn(array(
                'state' => $state,
                'list' => $list
            ));
        }
    }

    public function showProduct()
    {
        $productid = I('get.id', 0, 'intval');

        $product_info = D('platform_product')->getProductInfo($productid);
        $this->assign('platform_id', $this->platform_id);
        $product_info['product_exist'] = D('platform_product')->checkProductExist($id);
        $product_info['detail_picture'] = RESOURCE_PATH . $product_info['detail_picture'];
        // 解析description中的XML数据
        $product_info['introduction'] = xmlToStr($product_info['description'], 'introduction');
        $product_info['rules'] = xmlToStr($product_info['description'], 'rules');
        $product_info['time'] = xmlToStr($product_info['description'], 'time');
        $product_name = $product_info['name'] ? $product_info['name'] : '奖品介绍';
        // 农行动账
        if (strcasecmp($this->platform_code, 'NYYHD') == 0) {

            $this->assign('info', $product_info);
            $this->assign('product_name', $product_name);
            $this->display('nyyhdPrize');
            exit();
        }
        // 订单信息
        $order_id = I('get.orderid');
        $orderInfo = M('order')->where('id=%d', $order_id)->find();

        //订单超时未领-begin
        if($orderInfo['pay_status'] == 6){

            $this->error('该商品超时未领,下次请赶早哦！');
        }
        //订单生成时间在十天前并且订单是在此功能上线之后生成的并且订单中电话号码为空的, 为超时未领商品
        if($orderInfo['createtime'] < $this->timeOutLimit && $orderInfo['createtime'] > $this->timeOutStart && $orderInfo['pay_status'] == 0 ) {
            
            //再次确认订单为未领商品
            $timeOutOrder = D('order_product')->getTimeOutOrder($order_id);   
            if($timeOutOrder[0]['id'] == $order_id && !empty($timeOutOrder[0]['id'])) {

                //超时未领商品回库;
                D('product')->productCallBack($timeOutOrder);
                doLog('Wheel/ShowProduct', '订单回收', '', json_encode($timeOutOrder), $this->redisLog);
                $this->error('该商品超时未领,下次请赶早！');
            }
        }
        //订单超时未领-end
        
        if (empty($orderInfo))
            $this->error('订单不存在');
        if (($orderInfo['createtime'] <= $this->timeOutStart && $orderInfo['tel']) || ($orderInfo['createtime'] > $this->timeOutStart && $orderInfo['pay_status'] == 2)) {

            $status = 2;
            $this->redirect('Order/orderDetail', array('id' => $order_id));
        } else {

            $status = 0;
        }

        $this->assign('judgeLingqu', $status);

        $this->assign('orderid', $order_id);
        $this->assign('info', $product_info);
        $this->assign('product_name', $product_name);

        $cutomerServiceTel = getCustomerServiceTel($product_info['id'], $this->redis);
        if (empty($cutomerServiceTel))
            $cutomerServiceTel = "021-62457922"; // 如果商户没有客服电话，则把乐天邦的客服电话展示给用户
        $this->assign('cutomer_service_tel', $cutomerServiceTel);

        doLog('Wheel/ShowProduct', 'Showproduct', '', '', $this->redisLog);
        
        $this->display('showProduct');
    }

    // 计算活跃人数
    /*
    public function getPeople()
    {

        // 计算活跃人数
        if (online_redis_server) {
            $this->redis->databaseSelect('wheel');
            $people = $this->redis->hget('WheelPeople:' . $this->platform_id);
        }

        if (empty($people['num']) || $people['time'] < strtotime("-1 day")) {

            $num = M('pt_log')->where("ptid=%d AND createtime>='%s' and createtime<'%s'", array(
                $this->platform_id,
                date('Y-m-d 00:00:00', strtotime('-7 day')),
                date('Y-m-d 00:00:00')
            ))->count('distinct(openid)');
            $people = array();
            $people['num'] = ceil($num / 7);

            if ($people['num'] < 200) {

                $people['num'] = 200;
            }
            $people['time'] = time();

            if (online_redis_server) {

                $this->redis->hset("WheelPeople:" . $this->platform_id, $people);
                $this->redis->expire("WheelPeople:" . $this->platform_id, 1800); // 设置成 半小时过期
            }

            $this->refresh = 1; // 更新概率缓存
        }
        return $people['num'];
    }
*/
    // 计算活跃人数
    public function getPeopleNew()
    {

        // 计算活跃人数
        $this->redis->databaseSelect('wheel');
        $people = $this->redis->hget('WheelPeople:' . $this->platform_id);
        if (empty($people['num']) || $people['time'] < strtotime("-1 day")) {

            $res = M('dau')->where('ptid=%d', $this->platform_id)
                ->limit(7)
                ->order('activedate desc')
                ->select();

            $res = array_column($res, 'dailyactiveuser');
            if (count($res) > 0) {

                $avg = ceil(array_sum($res) / count($res));
                if ($avg > 1000) {

                    $people['num'] = $avg;
                } else {

                    $people['num'] = 1000;
                }
            } else {

                $people['num'] = 1000;
            }
            $people['time'] = time();
            $this->redis->hset("WheelPeople:" . $this->platform_id, $people);
            $this->redis->expire("WheelPeople:" . $this->platform_id, 1800); // 设置成 半小时过期
            $this->refresh = 1; // 更新概率缓存
        }
        return $people['num'];
    }

    // 计算招行抽奖平台历史总人数
    private function getCmbTotalUserNumber($ptid)
    {
        $redisKey = 'cmb_luckydraw_user_total';
        $this->redis->databaseSelect();
        $num = $this->redis->get($redisKey);
        if ($num != false)
            return $num;

        $sign_day = D('sign_day');
        $usertotal = $sign_day->where("ptid=%d", $ptid)
            ->field('count(id) usertotal')
            ->find(); // 历史总人数

        $this->redis->set($redisKey, $usertotal['usertotal']); // 永远有效
                                                               
        return $usertotal['usertotal'];
    }

    /**
     * @Description:zh签到页面
     * @User:jysdhr
     */
    public function zhWelcome(){
        // 重新注册分享配置
        $share = $this->platform_config['SHARE'];
        $share['link'] = 'http://oauth.cc.cmbchina.com/OauthPortal/wechat/oauth?oauth_id=75cade02&callback_uri=http%3a%2f%2fm.zwmedia.com.cn%2fwxzspfse%2findex.php%2fHome%2fWheel%2fzhWelcome%2fplatcode%2fZHCJ&scope=snsapi_base';
        $this->assign('SHARE', $share);
        $send_url = U('Home/Ajax/cmbSendSignMsg', array(
            'openid' => $_SESSION['ZHCJ']['ZhcjOpenid']
        ));
        if (!$this->redis->exists('UserDateSignRecord_' . $this->user_id . date('Ymd'))){
            //第一次签到时 去进行doSign操作
            //签到
            $sign_controller = A('Sign');
            $ifSignSuccess = $sign_controller->doSign($this->redis);
        }
        $this->assign('ifSignSuccess', $ifSignSuccess);
        //签到失败跳转错误页面
        if (0 == $ifSignSuccess) redirect('Wheel/index');
        //缓存session
        $session = $_SESSION[$this->platform_code];
        //签到成功

        $dateStr = date('Ymd');
        $sign_record = D('sign_record');
        //默认redis库
        $this->redis->databaseSelect();
        if (1 == $ifSignSuccess){
            dolog('Wheel/cmb_sign', '招行签到', '', json_encode('sign_result:' . $ifSignSuccess), $this->redisLog);
            //第一次
            $this->assign('send_url', $send_url);
            $data = $this->userTitle();
            if ((online_redis_server == true) && (strcasecmp($this->platform_code, 'ZHCJ') == 0)) {

                $data['usertotal'] = $this->getCmbTotalUserNumber($this->platform_id);

                $this->redis->databaseSelect();
                $cmb_luckydraw_today_user_total = $this->redis->get('cmb_luckydraw_today_user_total' . '_' . $dateStr);
                if (! $cmb_luckydraw_today_user_total) { // //缓存中不存在从数据库中读取

                    $data['usertoday'] = $this->_userToday($sign_record,$this->platform_id,$dateStr);
                } else {
                    $data['usertoday'] = $cmb_luckydraw_today_user_total;
                }
                $data['signtime'] = date('H:i'); // 签到时间
                $this->redis->databaseSelect('zwid');
                $redis_res = $this->redis->hget('openid_platcode:' . $session['openid'] . '_' . $session['platform_id']);
                $temp_arr = [
                    'username' => json_decode(preg_replace("/\\\ue[0-9a-f]{3}/",'\u002a',$redis_res['username'])),
                    'picture' => $redis_res['picture']
                ];
                $data = array_merge($data, $temp_arr);
                //选为默认
                $this->redis->databaseSelect();
                $this->redis->hset('zh_welcome_userid:' . $session['id'] . $dateStr, $data);
                $this->redis->EXPIRE('zh_welcome_userid:' . $session['id'] . $dateStr, 3600 * 24); // 设置24小时后过期
            }
        }elseif ( -1 == $ifSignSuccess ){
            //已签到
            $data = $this->redis->hget('zh_welcome_userid:' . $session['id'] . $dateStr);
            $data['usertotal'] = $this->getCmbTotalUserNumber($this->platform_id);
        }
        $this->assign('data', $data);
        $this->display('zhWelcome');

    }
    public function userTitle(){
        //称号数组
        $title = array(
            '呆萌葵花籽',
            '懵懂小嫩芽',
            '机灵小幼苗',
            '茁壮大花苗',
            '可爱花骨朵',
            '淘气小葵花',
            '阳光向日葵',
            '金装葵花油'
        );
        $sign_model = D('Sign');
        $userSignDay = $sign_model->userSignDay($this->user_id,$this->platform_id);
        //用户累计签到次数
        $data['signtotal'] = $userSignDay['signtotal'];
        //用户称号
        if ($data['signtotal'] < 3)
            $data['title'] = $title[0];
        else if ($userSignDay['signtotal'] >= 3 && $data['signtotal'] < 6)
            $data['title'] = $title[1];
        else if ($data['signtotal'] >= 6 && $data['signtotal'] < 10)
            $data['title'] = $title[2];
        else if ($data['signtotal'] >= 10 && $data['signtotal'] < 30)
            $data['title'] = $title[3];
        else if ($data['signtotal'] >= 30 && $data['signtotal'] < 90)
            $data['title'] = $title[4];
        else if ($data['signtotal'] >= 90 && $data['signtotal'] < 180)
            $data['title'] = $title[5];
        else if ($data['signtotal'] >= 180 && $data['signtotal'] < 360)
            $data['title'] = $title[6];
        else if ($data['signtotal'] >= 360)
            $data['title'] = $title[7];
        return $data;
    }
    /**
     * ***
     * 招行转盘欢迎页面,签到
     * ****
     * tip:弃用2017.5.2 by jysdhr
     */
//    public function zhWelcomeOld()
//    {
//        // 重新注册分享配置
//        $share = $this->platform_config['SHARE'];
//        $share['link'] = 'http://oauth.cc.cmbchina.com/OauthPortal/wechat/oauth?oauth_id=75cade02&callback_uri=http%3a%2f%2fm.zwmedia.com.cn%2fwxzspfse%2findex.php%2fHome%2fWheel%2fzhWelcome%2fplatcode%2fZHCJ&scope=snsapi_base';
//        $this->assign('SHARE', $share);
//        $send_url = U('Home/Ajax/cmbSendSignMsg', array(
//            'openid' => $_SESSION['ZHCJ']['ZhcjOpenid']
//        ));
//        $this->assign('send_url', '');
//        $dateStr = date('Ymd');
//        // 签到
////        $ifSignSuccess = doSign($this->redis, $this->redisLog);
//        $sign_controller = A('Sign');
//        $ifSignSuccess = $sign_controller->doSign($this->redis);
//        $this->assign('ifSignSuccess', $ifSignSuccess);
//        //第一次签到成功才记录
//        if (1 == $ifSignSuccess)
//            dolog('Wheel/cmb_sign', '招行签到', '', json_encode('sign_result:' . $ifSignSuccess), $this->redisLog);
//        //dolog('Wheel/delete', '招行签到2', '', 'url='.$_SERVER['REQUEST_URI']. ' nickname:'. json_encode($_GET['nickname']).' picture:'.$_GET['headimgurl'], $this->redisLog); //COPY
//        $session = $_SESSION[$_SESSION['PLATFORM_CODE']];
//        $ptid = intval($this->platform_id);
//        if (online_redis_server == true) {
//            if (empty($this->redis)) {
//                $this->redis = new \Vendor\Redis\DefaultRedis(C('REDIS_HOST_DEFAULT'), C('REDIS_PORT_DEFAULT'), C('REDIS_AUTH_DEFAULT'));
//            }
//            $redis = $this->redis;
//            $redis->databaseSelect();
//        }
//        // 签到成功
//        if ($ifSignSuccess == 1) {
//            $this->assign('send_url', $send_url);
//            $sign_day = D('sign_day');
//            $sign_record = D('sign_record');
//            $title = array(
//                '呆萌葵花籽',
//                '懵懂小嫩芽',
//                '机灵小幼苗',
//                '茁壮大花苗',
//                '可爱花骨朵',
//                '淘气小葵花',
//                '阳光向日葵',
//                '金装葵花油'
//            );
//            // 生成数据
//            $sign_total_map['userid'] = $session['id'];
//            $signtotal = $sign_day->where("userid=%d", $sign_total_map['userid'])
//                ->field('signtotal')
//                ->find(); // 累计天数
//            $data['signtotal'] = $signtotal['signtotal'];
//
//            if ((online_redis_server == true) && (strcasecmp($this->platform_code, 'ZHCJ') == 0)) {
//
//                $data['usertotal'] = $this->getCmbTotalUserNumber($ptid);
//
//                $redis->databaseSelect();
//                $cmb_luckydraw_today_user_total = $redis->get('cmb_luckydraw_today_user_total' . '_' . $dateStr);
//                if (! $cmb_luckydraw_today_user_total) { // //缓存中不存在从数据库中读取
//
//                    $data['usertoday'] = $this->_userToday($sign_record,$ptid,$dateStr);
//                } else {
//                    $data['usertoday'] = $cmb_luckydraw_today_user_total;
//                }
//            } else { // 没有redis服务器的情况，主要是在本机调试。
//                $data['usertotal'] = $this->getCmbTotalUserNumber($ptid); // 历史总人数
//                $data['usertoday'] = $this->_userToday($sign_record,$ptid,$dateStr);
//            }
//
//            $data['signtime'] = date('H:i'); // 签到时间
//                                             // 设置称号
//            if ($data['signtotal'] < 3)
//                $data['title'] = $title[0];
//            else
//                if ($data['signtotal'] >= 3 && $data['signtotal'] < 6)
//                    $data['title'] = $title[1];
//                else
//                    if ($data['signtotal'] >= 6 && $data['signtotal'] < 10)
//                        $data['title'] = $title[2];
//                    else
//                        if ($data['signtotal'] >= 10 && $data['signtotal'] < 30)
//                            $data['title'] = $title[3];
//                        else
//                            if ($data['signtotal'] >= 30 && $data['signtotal'] < 90)
//                                $data['title'] = $title[4];
//                            else
//                                if ($data['signtotal'] >= 90 && $data['signtotal'] < 180)
//                                    $data['title'] = $title[5];
//                                else
//                                    if ($data['signtotal'] >= 180 && $data['signtotal'] < 360)
//                                        $data['title'] = $title[6];
//                                    else
//                                        if ($data['signtotal'] >= 360)
//                                            $data['title'] = $title[7];
//            if (online_redis_server == true) {
//                $redis->databaseSelect('zwid');
//                $redis_res = $redis->hget('openid_platcode:' . $session['openid'] . '_' . $session['platform_id']);
//                $temp_arr = [
//                    'username' => json_decode(preg_replace("/\\\ue[0-9a-f]{3}/",'\u002a',$redis_res['username'])),
//                    'picture' => $redis_res['picture']
//                ];
//                $data = array_merge($data, $temp_arr);
//                //选为默认
//                $redis->databaseSelect();
//                $redis->hset('zh_welcome_userid:' . $session['id'] . $dateStr, $data);
//                $redis->EXPIRE('zh_welcome_userid:' . $session['id'] . $dateStr, 3600 * 24); // 设置24小时后过期
//            }
//            //dolog('Wheel/delete', '招行签到第一次签到2', '', 'nickname:'.$data['username'].' picture:'.$data['picture'], $this->redisLog); //COPY
//            $this->assign('data', $data);
//            $this->display('zhWelcome');
//        }  // 今日已签到
//else if ($ifSignSuccess == - 1) {
//                if (online_redis_server == true) {
//                    $data = $redis->hget('zh_welcome_userid:' . $session['id'] . $dateStr);
//                    if ($data) { // 若redis存在数据
//                        $data['usertotal'] = $this->getCmbTotalUserNumber($ptid);
//                        //dolog('Wheel/delete', '招行签到已签到(redis)2', '', 'nickname:'. json_encode($data['username']).' picture:'.$data['picture'], $this->redisLog);  //COPY
//                        $this->assign('data', $data);
//                        $this->display('zhWelcome');
//                    } else { // 签到过,但redis中没数据
//                        $sign_day = D('sign_day');
//                        $sign_record = D('sign_record');
//                        $title = array(
//                            '呆萌葵花籽',
//                            '懵懂小嫩芽',
//                            '机灵小幼苗',
//                            '茁壮大花苗',
//                            '可爱花骨朵', // ' 可爱花骨朵',
//                            '淘气小葵花', // ' 淘气小葵花',
//                            '阳光向日葵',
//                            '金装葵花油'
//                        ) // ' 金装葵花油'
//;
//                        // 生成数据
//                        $signtotal = $sign_day->where("userid=%d", $session['id'])
//                            ->field('signtotal ')
//                            ->find(); // 累计天数
//                        $data['signtotal'] = $signtotal['signtotal'];
//                        $data['usertotal'] = $this->getCmbTotalUserNumber($ptid);
//                        //计算今日第几个签到
//                        $data['usertoday'] = $this->_userToday($sign_record,$ptid,$dateStr);
//
//                        $sign_time = $sign_record->field('signtime')
//                            ->where("userid=%d and signtime like '%s'", array(
//                            $session['id'],
//                            date('Y-m-d') . '%'
//                        ))
//                            ->find();
//                        $data['signtime'] = date('H:i', strtotime($sign_time['signtime'])); // 签到时间
//
//                        if ($data['signtotal'] < 3)
//                            $data['title'] = $title[0];
//                        else
//                            if ($data['signtotal'] >= 3 && $data['signtotal'] < 7)
//                                $data['title'] = $title[1];
//                            else
//                                if ($data['signtotal'] >= 7 && $data['signtotal'] < 10)
//                                    $data['title'] = $title[2];
//                                else
//                                    if ($data['signtotal'] >= 10 && $data['signtotal'] < 30)
//                                        $data['title'] = $title[3];
//                                    else
//                                        if ($data['signtotal'] >= 30 && $data['signtotal'] < 90)
//                                            $data['title'] = $title[4];
//                                        else
//                                            if ($data['signtotal'] >= 90 && $data['signtotal'] < 180)
//                                                $data['title'] = $title[5];
//                                            else
//                                                if ($data['signtotal'] >= 180 && $data['signtotal'] < 360)
//                                                    $data['title'] = $title[6];
//                                                else
//                                                    if ($data['signtotal'] >= 360)
//                                                        $data['title'] = $title[7];
//                            // 从数据库中查找头像和昵称
//                        $session = $_SESSION[$_SESSION['PLATFORM_CODE']];
//                        $pt_user = M('pt_user');
//                        $res = $pt_user->where("openid='%s' AND ptid=%d ", array(
//                            $session['openid'],
//                            $session['platform_id']
//                        ))
//                            ->field('username,picture')
//                            ->find();
//                        $temp_arr = [
//                            'username' => json_decode(preg_replace("/\\\ue[0-9a-f]{3}/",'\u002a',$res['username'])),
//                            'picture' => $res['picture']
//                        ];
//                        $data = array_merge($data, $temp_arr);
//                        $redis->hset('zh_welcome_userid:' . $session['id'] . $dateStr, $data);
//                        $redis->EXPIRE('zh_welcome_userid:' . $session['id'] . $dateStr, 3600 * 24); // 设置24小时后过期
//                        //dolog('Wheel/delete', '招行签到已签到(数据库)2', '', 'nickname:'. json_encode($data['username']).' picture:'.$data['picture'], $this->redisLog);  //COPY
//                        $this->assign('data', $data);
//                        $this->display('zhWelcome');
//                    }
//                }
//            }  // 签到失败
//else
//                if ($ifSignSuccess == 0) {
//                    redirect('Wheel/index');
//                }
//    }
    //计算用户今日第几个签到的
    private function _userToday($sign_record,$ptid,$dateStr){
        $this->redis->databaseSelect();
        $cmb_luckydraw_today_user_total = $this->redis->get('cmb_luckydraw_today_user_total' . '_' . $dateStr);
        if ( $cmb_luckydraw_today_user_total ){
            $data = $this->redis->hget('zh_welcome_userid:' . $this->user_id . $dateStr);
            if ($data){
                return $data['usertoday'];
            }else{
                return $cmb_luckydraw_today_user_total;
            }
        }       
        
        //每天凌晨访问10分钟以内redis中没值的话认为是第一个签到的人
        if (date('H') == 0 && date('i') < 10) {

            $usertoday['usertoday'] = 1;
        } else {

            //如果要百分之百精确的话，应该先查询数据库，看该用户当天是否签到过；如果签到过，则应该查询有多少用户在该用户前签到，以此作为正确的值
            //目前是比较简单的做法，因为我们一般情况下不会去清理redis里面的记录。
            $usertoday = $sign_record->where("ptid=%d and signtime like '%s'", array($ptid, date('Y-m-d') . '%' ))
                ->field('count(userid) usertoday')
                ->find(); // 今日签到人数
        }
       
        $this->redis->set('cmb_luckydraw_today_user_total' . '_' . $dateStr, $usertoday['usertoday'], 24 * 60 * 60); // 一天的缓存有效时间
        return $usertoday['usertoday'];
    }
    /**
     * ***
     * 招行转盘每日一签页面
     * ****
     */
    public function signPicture()
    {
        if (I('param.daily')) {
            $name = 's' . date('Ymd');
            doLog('Wheel/signPicture', "招行每日一签", '', '', $this->redisLog);
        } else {
            $name = date('Ymd');
        }
        $pathinfo = scandir('/var/www/html/ltb_static_files/zhWelcome');
        foreach ($pathinfo as $k => $v) {
            if (preg_match("/$name/", $v)) {
                $result[] = $v;
            }
        }
        shuffle($result);

        // jiang 日签
        // 重新注册分享配置
        $share = $this->platform_config['SHARE'];
        // 把当前日签图分享出去
        $share['link'] = HOST_URL . '/wxzspfse/index.php/Home/Wheel/signPicture/daily/1/platcode/ZHCJ/';
        
        // 通过未抽中奖进来的页面
        if (! I('param.daily')) {
            if (! isset($result[0]))
                $result[0] = 'default.jpg';
            $imgurl = RESOURCE_PATH . '/Public/Home/Default/Image/zsh2.0/zhWelcome/' . $result[0];
            //$share['link'] = HOST_URL . '/wxzspfse/index.php/Home/Wheel/signPicture/platcode/ZHCJ/';
            $this->assign('SHARE', $share);
            $this->assign('imgurl', $imgurl);
            $this->assign('hide', 1);
            $this->display('sign');
            exit();
        }
        $share_flag = 0;
        if (isset($_GET['signPicture'])){
//            echo 1;die;
            $result[0] = $_GET['signPicture'];
            $share_flag = 1;
            //分享进来
            $checkin_data_id = $_GET['checkin_data_id'];
            //然后记录
            $sql = sprintf("UPDATE tb_checkin_data SET sharePVnum=sharePVnum+1 , updatetime = '%s' WHERE id = %d ",date('Y-m-d',time()),$checkin_data_id);
            M()->execute($sql);
        }
        // start
        $flag = 0;
        // 如果指定日签图片
        if (isset($result[0])) {
            // 在music数组中查找图片对应的音乐
            $temp_arr = explode('.', $result[0]);
            $music_url = RESOURCE_PATH . '/Public/Home/Default/Audio/' . $temp_arr[0] . '.mp3';
            $music_info = file_exists('./Public/Home/Default/Audio/' . $temp_arr[0] . '.mp3') ? $music_url : RESOURCE_PATH . '/Public/Home/Default/Audio/default.mp3';
            $_SESSION[$_SESSION['PLATFORM_CODE']]['sign_picture'] = $result[0];
            $flag = 1;
            $checkin_data = M('checkin_data');
            $res = $checkin_data->where("openid ='%s' AND ptid = %d AND picture = '%s'", array(
                $_SESSION[$_SESSION['PLATFORM_CODE']]['openid'],
                $this->platform_id,
                $result[0]
            ))
                ->field('id,thumbup')
                ->find();

            if (! $res && $share_flag == 0) {
                // 如果没查到用户信息说明用户每天第一次进到该页面
//                $checkin_data = M('checkin_data');
                //include_once ("./Application/Home/Model/SignModel.class.php");
                //$wheel_model = D('Wheel');
                $checkin_data_id = $this->addSignAction('thumbup', $result[0], 0);
                $share['link'] =  HOST_URL . '/wxzspfse/index.php/Home/Wheel/signPicture/daily/1/platcode/ZHCJ?signPicture=' . $result[0] .'&checkin_data_id='. $checkin_data_id  ;
                $res['thumbup'] = 0;
            }else{
                if ($res) {
                    $checkin_data_id = $res['id'];
                }
                $share['link'] =  HOST_URL . '/wxzspfse/index.php/Home/Wheel/signPicture/daily/1/platcode/ZHCJ?signPicture=' . $result[0] .'&checkin_data_id='. $checkin_data_id  ;
            }
            // 查询当前图片的总点赞数
            if(online_redis_server ){

                $this->redis->databaseSelect();
                $res['totalthumbup'] = $this->redis->hget('sign_picture_totalthumbup:'.$result[0],'decode');
                if (!$res['totalthumbup']){
                    $res['totalthumbup'] = $checkin_data->where("thumbup = %d AND picture = '%s'", 1, $result[0])
                        ->field("count(id) as totalthumbup")
                        ->find()['totalthumbup'];
                    $this->redis->hset('sign_picture_totalthumbup:'.$result[0],$res['totalthumbup']);
                    $this->redis->EXPIRE('sign_picture_totalthumbup:'.$result[0],60 * 30);//有效时间30分钟
                }
            }else{
                //没有redis环境  本地测试用
                $res['totalthumbup'] = $checkin_data->where("thumbup = %d AND picture = '%s'", 1, $result[0])
                    ->field("count(id) as totalthumbup")
                    ->find()['totalthumbup'];
            }

            $this->assign('res', $res);
        } else {
            $result[0] = 'default.jpg';
            $music_info = RESOURCE_PATH . '/Public/Home/Default/Audio/default.mp3';
        }
        $imgurl = (isset($_GET['signPicture'])) ? (RESOURCE_PATH . '/Public/Home/Default/Image/zsh2.0/zhWelcome/' . $_GET['signPicture']) : (RESOURCE_PATH . '/Public/Home/Default/Image/zsh2.0/zhWelcome/' . $result[0]);
        $state = (isset($_GET['signPicture']) && (substr($_GET['signPicture'], 1, 8) < date('Ymd'))) ? 0 : 1;
        $this->assign('imgurl', $imgurl)->assign('musicurl', $music_info);
        $this->assign('flag', $flag)->assign('state', $state);
        $this->assign('SHARE', $share);
        $this->display('sign');
    }

    /**
     * @description 日签页面的数据记录

     * @param $type 分为点赞/评论
     *            => thumbup/usercomments
      
     * @param $picture 图片名
     * @param $value 点赞/评论的参数
     * @return bool 是否插入成功
     */
    public function addSignAction( $type, $picture, $value)
    {
        $checkin_data = M('checkin_data');
        $data['date'] = strtotime(date('Y-m-d'));
        $data['openid'] = $_SESSION[$_SESSION['PLATFORM_CODE']]['openid'];
        $data['ptid'] = $this->platform_id;
        $data['picture'] = $picture;
        $data['createtime'] = $data['updatetime'] = date('Y-m-d H:i:s',$_SERVER['REQUEST_TIME']);
        $data[$type] = $value;
        $res = $checkin_data->add($data);
        return (FALSE === $res) ? FALSE : $res;
    }
    /**
     * 获得日历年份/月份的签到记录
     */
    public function getSignCalendar()
    {
        if (IS_POST) {
            $session = $_SESSION[$_SESSION['PLATFORM_CODE']];
            $recive_date = I('post.sign_date');
            $lastDay = $recive_date . '-' . date('t', $recive_date);
            $unix_times_start = strtotime($recive_date);
            $unix_time_end = strtotime($lastDay);
            // 获取到年月开始时间和结束时间戳
            $checkin_data = M('checkin_data');
            // 获取当前月份签到过的日期
            // 从redis中取
            $date_str = date('Ymd');
            $this->redis->databaseSelect();
            if (! (online_redis_server && ! empty($temp_arr = $this->redis->hget('zh_signed_date_per_month:' . $recive_date . $date_str . $session['openid'] . $session['platform_id'] . $date_str)))) {
                $res = $checkin_data->where("date > %d AND date < %d AND openid = '%s' AND ptid = %d", array(
                    $unix_times_start,
                    $unix_time_end,
                    $session['openid'],
                    $session['platform_id']
                ))
                    ->field("FROM_UNIXTIME(date, '%Y-%m-%d') as date")
                    ->group('date')
                    ->select();
                // 存redis 失效时间24小时
                // 如果res不为空
                if (isset($res[0])) {
                    $temp_arr = array();
                    foreach ($res as $k => &$v) {
                        $temp_arr[] = $v['date'];
                    }
                } else {
                    $temp_arr = [];
                }
                $this->redis->hset('zh_signed_date_per_month:' . $recive_date . $date_str . $session['openid'] . $session['platform_id'], $temp_arr);
                $this->redis->EXPIRE('zh_signed_date_per_month:' . $recive_date . $date_str . $session['openid'] . $session['platform_id'], 3600 * 24);
            }
            $rs['code'] = 1;
            $rs['msg'] = '查询成功';
            $rs['sign_date'] = $temp_arr;
        } else {
            $rs['code'] = 3;
            $rs['msg'] = '参数请求错误';
        }
        exit(json_encode($rs));
    }

    /**
     * 获得该用户选中日期的日签数据
     */
    public function getDateSignDetail()
    {
        $time = I('post.time');
        // 如果redis服务器在线并且不查询今日日期
        $session = $_SESSION[$_SESSION['PLATFORM_CODE']];
        $sign_time = date('Y-m-d',time());
        $sign_time_detail = date('Y-m-d H:i:s',time());
        if (online_redis_server && (strtotime($time) < strtotime(date('Ymd')))) {
            //记录用户点击日历签次数
        	$sql = sprintf("UPDATE tb_sign_record SET click_history_sign_date = click_history_sign_date+1 , updatetime = '%s' WHERE userid = %d  AND  DATE(signtime) = '%s' AND  ptid = %d ",$sign_time_detail,$session['id'], $sign_time,$session['platform_id']);

//            echo $sql;die;
            M()->execute($sql);
            $this->redis->databaseSelect();
            // 从redis中取数据
            $sign_detail = $this->redis->hget('zh_day_sign_picture:' . $time . $session['openid'], 'decode');
            if ($sign_detail) {
                $sign_detail = json_decode($sign_detail);
                $response_data = json_decode(json_encode($sign_detail), true);
            } else {
                // redis没有 .从数据库取
                $recive_date = strtotime($time);
                $checkin_data = M('checkin_data');
                $res['data'] = $checkin_data->where("date = %d AND openid = '%s' AND ptid = %d", array(
                    $recive_date,
                    $session['openid'],
                    $session['platform_id']
                ))->select();
                
                foreach ($res['data'] as &$v) {
                    $v['totalthumbup'] = $checkin_data->where("thumbup = %d AND picture = '%s'", 1, $v['picture'])
                        ->field("count(id) as totalthumbup")
                        ->find()['totalthumbup'];
                    $v['share_link'] = HOST_URL . '/wxzspfse/index.php/Home/Wheel/signPicture/daily/1/platcode/ZHCJ?signPicture=' . $v['picture'] .'&checkin_data_id=' .$v['id'];
                }
                // 存redis
                $this->redis->hset('zh_day_sign_picture:' .$time. $session['openid'], json_encode($res));
                $this->redis->EXPIRE('zh_day_sign_picture:' .$time. $session['openid'], 60 * 10); // 10分钟有效期
                // 存点赞总数
                $response_data = $res;
            }
        } else {
            // 不读redis
            $recive_date = strtotime($time);
            $checkin_data = M('checkin_data');
            $res['data'] = $checkin_data->where("date = %d AND openid = '%s' AND ptid = %d", array(
                $recive_date,
                $session['openid'],
                $session['platform_id']
            ))->select();
            foreach ($res['data'] as &$v) {
                $v['totalthumbup'] = $checkin_data->where("thumbup = %d AND picture = '%s'", 1, $v['picture'])
                    ->field("count(id) as totalthumbup")
                    ->find()['totalthumbup'];
            }
            $response_data = $res;
        }
        if (isset($response_data['data'][0])) {
            foreach ($response_data['data'] as $k => &$v) {
                $temp_arr = explode('.', $v['picture']);
                $v['picture'] = RESOURCE_PATH . '/Public/Home/Default/Image/zsh2.0/zhWelcome/' . $v['picture'];
                $music_url = RESOURCE_PATH . '/Public/Home/Default/Audio/' . $temp_arr[0] . '.mp3';
                $v['music'] = file_exists('./Public/Home/Default/Audio/' . $temp_arr[0] . '.mp3') ? $music_url : RESOURCE_PATH . '/Public/Home/Default/Audio/default.mp3';
            }
        }
        unset($sign_detail, $res);
        // 设定是否禁用用户评论和点赞
        $state = (strtotime($time) < strtotime(date('Ymd'))) ? 0 : 1;
        $flag = (strtotime($time) < strtotime(date('Ymd'))) ? 1 : 0;
        $response_data['state'] = $state;
        $response_data['flag'] = $flag;
        exit(json_encode($response_data));
    }

    /**
     *
     * @return array @description 返回用户评论
     */
    private function _comments()
    {
        return array(
            'GREATWORDS' => 1,
            'GREATDESIGN' => 2,
            'POSITIVEENGRY' => 4,
            'GREATIMG' => 8,
            'GREATCREATE' => 16,
            'HMC' => 32
        );
    }

    /**
     * 评论操作
     */
    public function commentsAction()
    {
        if (IS_POST) {
            $data['usercomments'] = I('post.comments_code');
            // 验证规则
            $rules = array(
                array(
                    'usercomments',
                    'require',
                    '评论编码必须！'
                ),
                array(
                    'usercomments',
                    array(
                        0,
                        63
                    ),
                    '评论范围超出！',
                    0,
                    'between'
                )
            );
            $checkin_data = M("checkin_data");
            if (! $checkin_data->validate($rules)->create($data)) {
                // 如果创建失败 表示验证没有通过 输出错误提示信息
                $rs['code'] = 3;
                $rs['msg'] = $checkin_data->getError();
                exit(json_encode($rs));
            }
            // find数据
            $session = $_SESSION[$_SESSION['PLATFORM_CODE']];
            $res = $checkin_data->where("openid ='%s' AND ptid = %d AND picture = '%s'", array(
                $session['openid'],
                $session['platform_id'],
                $session['sign_picture']
            ))
                ->field('usercomments')
                ->find();
            if ($res) {
                // 更新操作
                $data['updatetime'] = date('Y-m-d H:i:s',$_SERVER['REQUEST_TIME']);
                if (FLASE === $checkin_data->where("openid ='%s' AND ptid = %d AND picture = '%s'", array(
                    $session['openid'],
                    $session['platform_id'],
                    $session['sign_picture']
                ))->save($data)) {
                    $rs['code'] = 2;
                    $rs['msg'] = '失败，请重新提交';
                } else {
                    $rs['code'] = 1;
                    $rs['msg'] = '成功';
                }
            } else {
                if ($this->addSignAction('usercomments', $session['sign_picture'], $data['usercomments'])) {
                    $rs['code'] = 1;
                    $rs['msg'] = '成功';
                } else {
                    $rs['code'] = 2;
                    $rs['msg'] = '失败，请重新提交';
                }
            }
        } else {
            $rs['code'] = 0;
            $rs['msg'] = '请求参数错误!';
        }
        exit(json_encode($rs));
    }

    /**
     * [sessionDestroy 清除缓存释放sessionid]
     * @ckhero
     * @DateTime 2016-07-27
     *
     * @return [type] [description]
     */
    public function sessionDestroy()
    {
        echo session_id();
        session_unset();
        session_destroy();
        $sessionid = session_id();
        echo session_id();

        var_dump($_SESSION);
    }

    /**
     * [prizeLotteryReset 对抽奖的商品重构]
     * @ckhero
     * @DateTime 2016-08-30
     *
     * @param [type] $data
     *            [description]
     * @return [type] [description]
     */
    public function prizeLotteryReset($data)
    {
        $ptuserid = $_SESSION[$_SESSION['PLATFORM_CODE']]['id'];
        foreach ($data as $key => $val) {

            if ($val == '谢谢参与')
                continue;
            $order = M('order')->field('o.id')
                ->table('tb_order o,tb_order_product p ')
                ->where('o.id = p.orderid and o.ptuserid =' . $ptuserid . ' and p.productid=' . $val['product_id'] . ' and o.createtime>"' . date('Y-m-d H:i:s', strtotime('-1 month')) . '"', array(
                $order_id
            ))
                ->find();
            if ($order['id']) {

                $data[$key]['state'] = 1; // 不可被抽中；
            }
        }
        return $data;
    }
    
    /**
     * [isBlackList d判断参与抽奖的号码是否在黑名单里]
     * @ckhero
     * @DateTime 2017-03-02
     * @param    string     $tel [description]
     * @return   boolean         [true/false]
     */
    private function isBlackList ($tel = '')
    {

        if (empty($tel)) {

            return false;
        }

        $res = M('black_tel_list')->where("tel = %d", $tel)->find();
        if($res['id'] > 0) {

            return true;
        } else {

            return false;
        }
    }
    
    //模板选择
    private function getSmstpl ($type = 0)
    {

        if ($type == 1) {

            $smstpl = "尊敬的{channel}客户，恭喜您抽中{product_name}一份，请前往抽奖转盘页的中奖纪录查看您的中奖信息，我们会在3个工作日内您安排发货，请保持通讯畅通。";
        } else {

            $smstpl = "尊敬的{channel}客户，恭喜您抽中{product_name}一份，请前往抽奖转盘页的中奖纪录查看您的奖品。";
        }

        return $smstpl;
    }
    
    /**
     * [getSignReward 获取今明两天的抽奖次数]
     * @ckhero
     * @DateTime 2017-03-17
     * @return   [type]     [description]
     */
    private function getSignReward() 
    {   
        //非招行平台直接返回
        if (strcasecmp($this->platform_code, 'ZHCJ') != 0) {

            $signReward = array(
                            
                            "today" => 1,
                            "tommorrow" => 1,
                            "left" => 1,
                            "isShare" => 0,
                        );
            return $signReward;
        }
        $this->redis->databaseSelect('wheel');
        //$signRewardKey = "SignReward:".$this->user_id;
        $signReward    =  $this->redis->hget($this->signRewardKey);
        //redis中存在使用redis的值
        if(isset($signReward["today"]) && $signReward["today"] >= 1 && isset($signReward["tomorrow"]) && $signReward["tomorrow"] >= 1 && isset($signReward["date"]) && date('Y-m-d') == $signReward["date"]) {

            return $signReward;
        } else {
            
            //获取签到次数
            $signReward             =  array();
            $signtotal              = M('sign_day')->field('signtotal, updatetime')->where('userid = %d', $this->user_id)->find();
            //没签到过的情况下 先去签到
            //有可能死循环，因为zhwelcome中签到失败的情况下面会跳到抽奖页面
            if ($signtotal['updatetime'] < date('Y-m-d 00:00:00')) {
              
                $this->redirect('Home/Wheel/zhWelcome');
            }
            $today = $this->getLotteryNum($signtotal['signtotal']);
            //如果有分享  增加一次抽奖次数
            if ($this->redis->get($this->shareRewardKey) == 1) {
                
                $signReward['isShare'] = 1;
                $today += 1;
            } else {

                $signReward['isShare'] = 0;
            }
            //如果有邀请奖励增加抽奖次数
            $inviterRewardKey = 'InviterReward:'.$this->user_id; //邀请奖励在redis中的key
            $inviterReward = $this->redis->get($inviterRewardKey);
            if ($inviterReward > 0) {

                $today += $inviterReward;
            }
            $signReward['today']    = $today;
            $signReward['tomorrow'] = $this->getLotteryNum($signtotal['signtotal'] + 1);
            $signReward['date']     = date('Y-m-d');
            $getTodayLeft = $this->checkRight($this->user_id, $this->platform_id, $signReward);
            $signReward['left'] = $getTodayLeft['state'] ? $getTodayLeft['left'] : 0;
            $this->redis->hset($this->signRewardKey, $signReward);
            $this->redis->expire($this->signRewardKey, $this->dayLeftSecond);
            return $signReward;
        }
    }
    
    /**
     * [getLotteryNum 计算某一天抽奖次数]
     * @ckhero
     * @DateTime 2017-03-17
     * @param    [int]     $signtotal [签到天数]
     * @return   [int]                [抽奖次数]
     */
    private function getLotteryNum($signtotal)
    {
        $num = 1;

        if ($signtotal >= 7 && $signtotal < 10) {
            
            if ($signtotal == 7 || $signtotal == 8) {

                $num  = 2;
            }
        } elseif ($signtotal >= 10 && $signtotal < 30) {
        
            if ($signtotal % 4 == 2) {

                $num = 2;
            }
        } elseif ($signtotal >= 30 && $signtotal < 90) {
            
            if ($signtotal % 6 == 0) {

                $num = 2;
            }
        } elseif ($signtotal >= 90 && $signtotal < 180) {
            
            if ($signtotal % 4 == 2 && $signtotal < 170) {

                $num = 2;
            }
        } elseif ($signtotal >= 180 && $signtotal < 365) {
            
            if ($signtotal % 3 == 0 && $signtotal < 330) {

                $num = 2;
            }
        } elseif ($signtotal >= 365) {
            
            if ($signtotal % 5 == 0 && $signtotal < 865) {

                $num = 2;
            }
        }
        
        return $num;
    }
    
    /**
     * [shareReward 分享增加抽奖次数]
     * @ckhero
     * @DateTime 2017-03-21
     * @return   [type]     [description]
     */
    public function shareReward()
    {
        
        $this->redis->databaseSelect('wheel');
        if ($this->redis->get($this->shareRewardKey) != 1) {

            $this->redis->set($this->shareRewardKey, 1, $this->dayLeftSecond);
            $signReward = $this->redis->hget($this->signRewardKey);
            // if ($signReward['today'] > 0) {
                
            //     //更新抽奖状态
            //     $this->redis->hset($this->signRewardKey, $signReward['today'] + 1, 'today');
            //     $this->redis->hset($this->signRewardKey, $signReward['left'] + 1, 'left');
            // }
            //避免多次分享数据异常 重新生成数据
            $this->redis->delete($this->signRewardKey);
            $this->getSignReward();
            return 1;
        }
    }
    
    /**
     * [getSignRewardAjax 获取可抽奖次数]
     * @ckhero
     * @DateTime 2017-03-31
     * @return   [type]     [description]
     */
    public function getSignRewardAjax()
    {

        if (is_ajax()) {
           
           $signReward = $this->getSignReward();
           $this->ajaxReturn($signReward);
        }
    }
}
