<?php
namespace Home\Controller;

use Think\Controller;

class CrontabController extends Controller
{

    public function __construct()
    {
        parent::__construct();
        $this->redis = new \Vendor\Redis\DefaultRedis(C('REDIS_HOST_DEFAULT'), C('REDIS_PORT_DEFAULT'), C('REDIS_AUTH_DEFAULT'));   //默认redis实例
    }

    public function getredis()
    {
        $this->redis->databaseSelect('Ip');
        $redis_list = $this->redis->keys("Ip*");
        echo "<pre>";
        $redis_arr = [];
        foreach ($redis_list as $k => $v) {
        	$result = $this->redis->hget($v) ;
        	$redis_arr[] =  $result[province] . '; '. $result[city] . '; ' . $result[type];
        }
        var_dump($redis_arr);
        die;
    }

    /**
     * [baiduIp 定时获取百度的location]
     * @ckhero
     * @DateTime 2017-02-09
     * @return   [type]     [description]
     */
    public function baiduIp()
    {
        //设置页面300s执行时间
        set_time_limit(300);
        doLogNoSession('Crontab/baiduIp', '每天更新一次ip start', '', '', '', '', '');
        //百度api连接
        $apiUrl = "https://api.map.baidu.com/location/ip?ip=ipno&ak=E9FAeIguIYQe6M0NcYxEUpMb";
        if (!DEBUG_ON_LOCALHOST_OR_201TESTINGSERVER) {
            $connection = array(
                'db_type' => 'mysql',
                'db_host' => '139.224.0.198',
                'db_user' => 'ptlog',
                'db_pwd' => 'ptlog2016',
                'db_port' => 3306,
                'db_name' => 'ptlog',
                'db_charset' => 'utf8'
            );
            $ptLog = new \Home\Model\UatLogModel('pt_log', 'tb_', $connection);
        } else {
            $ptLog = M('pt_log');
        }
        $start = 1;//limit开始标记
        $count = 0;//计算从百度取得不重复ip的总数
        $total = 0;//计算处理ip总数（包含重复）
        $start_time = microtime(true);
        while (true) {
            usleep(1000);
            $ipList = $ptLog->field('count(*) as num, ip')
                ->where("createtime > '%s' ", date('Y-m-d', strtotime('-1 day')))
                ->group('ip')
                ->order('num desc')
                ->limit($start, 100)
                ->cache(3600)
                ->select();
            //如果为空 跳出循环
            if (empty($ipList)) break;
            //
            $this->redis->databaseSelect('Ip');

            //设置file_get_contents 超时时间
            $opts = array(
                'http' => array(
                    'method' => "GET",
                    'timeout' => 3,
                )
            );
            $ipipnet = new  \Vendor\Ipipnet\Ipipnet();
            foreach ($ipList as $key => $val) {
                $total++;
                if (empty($val['ip'])) {
                    continue;
                }
                //判断该ip是否存redis 并且从百度取
                if ($this->redis->existsRedis('Ip:' . $val['ip'])) {
                    //ip详情
                    $ipdetail = $this->redis->hget('Ip:' . $val['ip']);
                    //如果失效时间大于3600*24s的跳过此循环
                    if ($this->redis->expireTime('Ip:' . $val['ip'], 1) > 3600 * 24) continue;
                }
                $res = file_get_contents(str_replace('ipno', $val['ip'], $apiUrl), false, stream_context_create($opts));
                $res = json_decode($res, true);
                if ($res['content']['address_detail']['province']) {

                    $ipList[$key]['crontab_province'] = $res['content']['address_detail']['province'];
                    $ipList[$key]['crontab_city'] = isset($res['content']['address_detail']['city']) ? $res['content']['address_detail']['city'] : 0;
                    //baidu正确率最高。 所以我们解析地址的时候，先取baidu, 然后再取geoip或者ipipnet应该没有问题。
                    // 对地址的解析，省份基本上都很准，区别在于地市级上的精确度。

                    $district = $this->compareIpipnetAndGeoip($val['ip'], $ipList[$key]['crontab_province'], $ipList[$key]['crontab_city'], $ipipnet);
                    $location = array(
                        'province' => $district['province'],
                        'city' => $district['city'],
                        'type' => $district['type'],
                    );
                    $this->redis->hset('Ip:' . $val['ip'], $location);
                    $this->redis->expire('Ip:' . $val['ip'], 3600 * 24 * 30);
                    $count++;
                    //当总数==50
                    if ($count >= 50) break 2;//break while
                }
            }
            //每次往后取500个
            $start += 100;
        }
        //获取redis 中的IP个数
        $redis_ips = $this->redis->keys('Ip*');
        $end_time = microtime(true);
        $load_time = $end_time - $start_time;
        doLogNoSession('Crontab/baiduIp', '每天更新一次ip end', '', '处理ip数为' . $total . '(redis从百度取出不重复IP共' . $count . '个),页面处理时间为' . $load_time . 's', '', '', '');
    }

    /**
     * @Description:百度IP对比ipipnet和geoip
     * @User:jysdhr
     * @param $ip
     * @param $province
     * @param $city
     */
    private function compareIpipnetAndGeoip($ip, $province, $city, $ipipnet)
    {
        //从ipipnet取
        $result = $ipipnet->find($ip);
        $baidu_province = $province;
        $baidu_city = mb_ereg_replace('市', '', $city);
        //省份只比较前2位
        if (mb_substr($baidu_province, 0, 2) . $baidu_city == mb_substr($result[1], 0, 2) . $result[2]) return array('province' => $baidu_province, 'city' => $baidu_city, 'type' => 'baidu');
        //baiduip和ipip不一样
        $geoIp = new \Vendor\GeoIp\GeoIp();
        $data = $geoIp->city($ip);
        //处理geoip的福建为闽的情况
        $geoip_province = mb_ereg_replace('闽', '福建', $data['province']);
        //ipip和geoip比较 一样返回ipip 省份只比较前2位
        if (mb_substr($result[1], 0, 2) . $result[2] == mb_substr($geoip_province, 0, 2) . $data['city'] && '' != mb_substr($result[1], 0, 2) . $result[2]) return array('province' => $result[1], 'city' => $result[2], 'type' => 'ipip');
        //baiduip ipip geoip三者都不一样 返回百度
        return array('province' => $baidu_province, 'city' => $baidu_city, 'type' => 'baidu');
    }

    /**
     * @auth jysdhr
     * @description ip分析
     */
    public function get_static_html()
    {
        set_time_limit(0);
        $apiUrl = "https://api.map.baidu.com/location/ip?ip=ipno&ak=E9FAeIguIYQe6M0NcYxEUpMb";
        $ip138_url = "http://api.ip138.com/query/?ip=ipno&datatype=jsonp&callback=find&token=c47f4c7ced6d0337c7847d88e114def7";

        //if (!DEBUG_ON_LOCALHOST_OR_201TESTINGSERVER) {

        $connection = array(
            'db_type' => 'mysql',
            'db_host' => '139.224.0.198',
            'db_user' => 'ptlog',
            'db_pwd' => 'ptlog2016',
            'db_port' => 3306,
            'db_name' => 'ptlog',
            'db_charset' => 'utf8'
        );
        $ptLog = new \Home\Model\UatLogModel('pt_log', 'tb_', $connection);
//        } else {
//            $ptLog = M('pt_log');
//        }

        $myip = new \Vendor\Ipipnet\Ipipnet();
        $geoIp = new \Vendor\GeoIp\GeoIp();

        $ipList = $ptLog->field('count(*) as num, ip')
            ->where("createtime > '%s' ", date('Y-m-d', strtotime('-1 day')))
            ->group('ip')
            ->order('num desc')
            ->limit(300)
//            ->cache(3600)
            ->select();
        //设置file_get_contents 超时时间
        $opts = array(
            'http' => array(
                'method' => "GET",
                'timeout' => 3,
            )
        );
        $ip_score_total = array('baiduip_total' => 0, 'geoip_total' => 0, 'ipipnet_total' => 0, 'ip138_total' => 0);
        foreach ($ipList as $k => $v) {
            //程序运行 
            $res = D('PlatformProduct')->get_user_district($v['ip']);
            $ipList[$k]['crontab_province'] = $res['province'];
            $ipList[$k]['crontab_city'] = $res['city'];
            /*
            if ($this->redis->existsRedis('Ip:' . $v['ip'])) {
                $res = $this->redis->hget('Ip:' . $v['ip']);
                $ipList[$k]['crontab_province'] = $res['province'];
                $ipList[$k]['crontab_city'] = $res['city'];
            } else {
                $geoIp = new \Vendor\GeoIp\GeoIp();
                //调用获取地理位置的类
                $locationInfo = $geoIp->city($v['ip']);
                $ipList[$k]['crontab_province'] = $locationInfo['province'];
                $ipList[$k]['crontab_city'] = $locationInfo['city'];
            }*/
            //从百度取
            $res = file_get_contents(str_replace('ipno', $v['ip'], $apiUrl), false, stream_context_create($opts));
            $res = json_decode($res, true);
            if ($res['content']['address_detail']['province']) {
                $temp = mb_ereg_replace('市', '', $res['content']['address_detail']['province']);
                $ipList[$k]['baiduip_province'] = mb_ereg_replace('省', '', $temp);
                $ipList[$k]['baiduip_city'] = mb_ereg_replace('市', '', $res['content']['address_detail']['city']);
            }
            //从geoip获取
            $geo_ip_result = $geoIp->city($v['ip']);
            $ipList[$k]['geoip_province'] = mb_ereg_replace('省', '', $geo_ip_result['province']);
            $ipList[$k]['geoip_city'] = mb_ereg_replace('市', '', $geo_ip_result['city']);
            //从Ipipnet取

            $result = $myip->find($v['ip']);
            $ipList[$k]['ipipnet_province'] = mb_ereg_replace('市', '', $result[1]);
            $ipList[$k]['ipipnet_city'] = mb_ereg_replace('省', '', $result[2]);

            //ip138
            $res = file_get_contents(str_replace('ipno', $v['ip'], $ip138_url), false, stream_context_create($opts));
            //正则过滤
            $reg = '/{.+}/i';
            preg_match($reg, $res, $match);
//            var_dump(json_decode($match[0],true));die;
            $ip138_result = json_decode($match[0], true);
            $ipList[$k]['ip138_province'] = $ip138_result['data'][1];
            $ipList[$k]['ip138_city'] = $ip138_result['data'][2];
//            //ip权重
            $ip_district = array(
                'baidu' => $ipList[$k]['baiduip_province'] . $ipList[$k]['baiduip_city'],
                'geoip' => $ipList[$k]['geoip_province'] . $ipList[$k]['geoip_city'],
                'ipipnet' => $ipList[$k]['ipipnet_province'] . $ipList[$k]['ipipnet_city'],
                'ip138' => $ipList[$k]['ip138_province'] . $ipList[$k]['ip138_city'],
            );
            $ip_score = array();
            foreach ($ip_district as $key => $val) {
                if (isset($ip_score[$val])) {
                    $ip_score[$val]['count'] += 1;
                } else {
                    $ip_score[$val]['name'] = $val;
                    $ip_score[$val]['count'] = 1;
                }
            }
            //ip分数
            $ipList[$k]['baidu_score'] = $ip_score[$ipList[$k]['baiduip_province'] . $ipList[$k]['baiduip_city']]['count'] * 25;
            $ipList[$k]['geoip_score'] = $ip_score[$ipList[$k]['geoip_province'] . $ipList[$k]['geoip_city']]['count'] * 25;
            $ipList[$k]['ipipnet_score'] = $ip_score[$ipList[$k]['ipipnet_province'] . $ipList[$k]['ipipnet_city']]['count'] * 25;
            $ipList[$k]['ip138_score'] = $ip_score[$ipList[$k]['ip138_province'] . $ipList[$k]['ip138_city']]['count'] * 25;


            $ip_score_total['baiduip_total'] += $ipList[$k]['baidu_score'];
            $ip_score_total['geoip_total'] += $ipList[$k]['geoip_score'];
            $ip_score_total['ip138_total'] += $ipList[$k]['ip138_score'];
            $ip_score_total['ipipnet_total'] += $ipList[$k]['ipipnet_score'];
            //是否存入redis
            $ipList[$k]['is_redis'] = ($this->redis->existsRedis('Ip:' . $v['ip'])) ? '已存redis' : '未存redis';
        }
        //平均分为
        $count = count($ipList);
        $avg_score = array(
            'baiduip_avg' => $ip_score_total['baiduip_total'] / $count,
            'geoip_avg' => $ip_score_total['geoip_total'] / $count,
            'ip138_avg' => $ip_score_total['ip138_total'] / $count,
            'ipipnet_avg' => $ip_score_total['ipipnet_total'] / $count,
        );
        //分析ip的准确率
        $this->assign('ipList', $ipList)->assign('avg_score', $avg_score);
        $this->display('UnitTemp/ip_analysis');
    }

    /**
     * [handleTimeOutOrder 处理抽平台的超时订单]
     * @ckhero
     * @DateTime 2017-02-20
     * @return   [type]     [description]
     */
    public function handleTimeOutOrder()
    {

        $orderList = D('order_product')->getTimeOutOrder('', 10);
        if (!empty($orderList)) {
            doLogNoSession('Crontab/handleTimeOutOrder', '定时回收抽奖平台的超时订单', '', json_encode($orderList), '', '', '');
            D('product')->productCallBack($orderList);
        } else {

            doLogNoSession('Crontab/handleTimeOutOrder', '没有符合条件的订单', '', json_encode($orderList), '', '', '');
            echo "没有符合条件的订单";
        }
    }

    public function delRedisData(){
        if (!DEBUG_ON_LOCALHOST_OR_201TESTINGSERVER) {
            exit('此函数用于清除测试服务器redis数据,正式服务器禁止访问...');
        }
        if (IS_AJAX) {
            $port = I('post.port');
            $this->redis = new \Vendor\Redis\DefaultRedis(C('REDIS_HOST_DEFAULT'), $port, C('REDIS_AUTH_DEFAULT'));   //默认redis实例
            exit($this->redis->flushAll()? '1' : '0');
        }
        $this->display('UnitTemp/del_redis');
    }
    public function delRedisDataZH(){
        if (!DEBUG_ON_LOCALHOST_OR_201TESTINGSERVER) {
            exit('此函数用于清除测试服务器redis数据,正式服务器禁止访问...');
        }

        $port = 3198;
        $this->redis = new \Vendor\Redis\DefaultRedis(C('REDIS_HOST_DEFAULT'), $port, C('REDIS_AUTH_DEFAULT'));   //默认redis实例
        $this->redis->databaseSelect('wheel');
        $res = $this->redis->keys('Wheel::productPtid:*12');
        foreach ($res as $k => $v)
            $this->redis->delete($v);
        $share_record = $this->redis->keys('ShareReward:*');
        foreach ($share_record as $key => $value) {
            $this->redis->delete($value);
        }

    }
}

?>
