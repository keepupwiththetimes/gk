<?php
namespace Home\Controller;

use Think\Controller;
// class LogAnalysisController extends CommonController{
class LogAnalysisController extends Controller
{

    private $num;
    private  static $ReentrantLock=true;//重入锁

    public function __construct()
    {
        set_time_limit(0); // 设置程序超时时间为buchaoshi
        parent::__construct();
        
        $this->redis = new \Vendor\Redis\DefaultRedis(C('REDIS_HOST_DEFAULT'), C('REDIS_PORT_DEFAULT'), C('REDIS_AUTH_DEFAULT'));
        $this->redisLog = new \Vendor\Redis\DefaultRedis(C('REDIS_HOST_LOG'), C('REDIS_PORT_LOG'), C('REDIS_AUTH_LOG'));
        $this->num = 50; // 每次处理的日志条数50条 (50条到100是比较合理的数据)
        // 没有必要检查这些渠道
        $this->noCheckList = array(
            'XYXJYPX', // 小荧星培训， 数据库	status=0
        	'XZZSH', //宣榛, 已经下线 2017-02-20 status=0
        	'CXH', // 纯享会, 还没有上线 2017-03-29 status=0
            
            'FLNZPCJ', // 费莱尼,没有抽奖
            'LOVECLUBCJ', // LOVECLUB,没有抽奖
            
            'TESTACCOUNT', // 测试账号
            'LETIANBANG', // 乐天邦公众号
            'CMBPAY', // 推送专用账号
            'BJDLQC', // 大陆汽车公众号
            'ZQX', // 上海最前线, 已经上线 2017-03-29
            'MSYHC', // 民生银行总行APP, 还没有上线 2017-03-29
            'MSYH'
        ) ;
        
        
    }

    public function index()
    {
        if ($this->redisLog->get('waiting'))
            exit(); // 如果已经有进程在等待 退出程序
        $wait = $this->redisLog->get('wait');
        if ($wait) { // 判断是否需要等待
            
            $this->redisLog->set('waiting', 1);
            $this->redisLog->expire('waiting', 1800);
        }
        $waittime = 0;
        while ($wait) { // 判断是否需要等待， 要防止死循环！
            $waittime = $waittime + 1;
            if ($waittime > 20) {
                $this->redisLog->del('waiting');
                exit(); // 等待时间超过10分钟的话，直接放弃。
            }
            sleep(30); // 在等待的进程每隔30s 去判断上是否可以结束等待；
            $wait = $this->redisLog->get('wait');
            if (! $wait) {
                
                $this->redisLog->del('waiting');
            }
        }
        $this->redisLog->set('wait', 1); // 开始执行程序，在程序执行完成之前告诉后续的程序进行等待
        $this->redisLog->expire('wait', 1800);
        
        $time_0 = microtime(true);
        if (DEBUG_ON_LOCALHOST_OR_201TESTINGSERVER == FALSE)
            $this->getDriveInfo(); // 发提醒给没有提交试驾信息的用户
        $this->analysis();
        if (DEBUG_ON_LOCALHOST_OR_201TESTINGSERVER == FALSE)
            $this->checkProductAvailability(); // 发提醒给管理员， 告诉他哪些平台要过期，哪些优惠券将发完
        $elapseTime = microtime(true) - $time_0;
        if (($elapseTime > 10 * 60)) {
            file_put_contents("debug.log", "检查LogAnalysis执行时间超过10分钟" . $elapseTime . "\n", FILE_APPEND);
            // //dolog('Index/debug','检查平台优惠券是否发完超过0.5秒','', 'latency(checkProductAvailability): '. $elapseTime, $this->redisLog );
        }

        if (DEBUG_ON_LOCALHOST_OR_201TESTINGSERVER == FALSE)
            $this->calculateYesterdayActiveUserNew();

        if (DEBUG_ON_LOCALHOST_OR_201TESTINGSERVER == FALSE)
            //分析给招行平台没有发出的签到记录，统一发出并记录返回值
            $this->reissureZHsignRecord();
//        if (DEBUG_ON_LOCALHOST_OR_201TESTINGSERVER == FALSE)
        if (DEBUG_ON_LOCALHOST_OR_201TESTINGSERVER == FALSE){
            //每15分钟处理一次分类分数
            $this->get_user_category_score_by_redis_to_addall();
        }

        $this->redisLog->del('wait'); // 释放wait和waiting 允许后面的程序 进来；
    }

    /**
     * [analysis 分析处理数据]
     * @ckhero
     * @DateTime 2016-08-23
     * 
     * @return [type] [description]
     */
    public function analysis()
    {
        $logid = $this->redisLog->get('logid');
        
        if ($logid == 1) { // log队列id为1 将id设为2，后续的记录放在队列2中
            
            $this->redisLog->set('logid', 2);
        } else {
            
            $this->redisLog->set('logid', 1);
        }
        $logNum = $this->redisLog->lsize($logid . 'pt_log_id'); // 获取已经存了多少条记录
        
        for ($i = 0; $i < ceil($logNum / $this->num); $i ++) {
            
            usleep(100000); // 每执行一次睡0.1秒
            
            $data = $this->getLogData($logid, $i * $this->num, ($i + 1) * $this->num - 1); // 获取日志
            
            $this->redis->databaseSelect('zwid'); // 选择zwid 数据库
            
            $PeopleDayTotal = $this->logDataPackageDay($data, 1); // 处理个人的优惠券日志
            $PlatformDayTotal = $this->logDataPackageDay($data, 2); // 处理平台的优惠券日志
            
            $data = null; // 收回该内存块的引用计数
                          
            // $this->logRedis($PeopleDayTotal,1); //将处理好的个人日志 进行缓存
                          // $this->logRedis($PlatformDayTotal,2); //将处理好的平台日志 进行缓存 //time_nanosleep
            $this->logDb($PeopleDayTotal, 0, 'zwid'); // 将处理好的个人日志 记录到数据库
            $this->logDb($PlatformDayTotal, 1, 'ptid'); // 将处理好的平台日志 进记录到数据库
            
            $PeopleDayTotal = null;
            $PlatformDayTotal = null;
        }
        
        $this->redisLog->del($logid . 'pt_log_id'); // 删除已经处理完成的日志列表
    }

    /**
     * [getLogData 获取缓存中的日志 （获取之后缓存中的日志并将日志添加到数据）]
     * @ckhero
     * @DateTime 2016-08-19
     * 
     * @param [type] $redis
     *            [redis连接]
     * @return [type] [array]
     */
    public function getLogData($logid, $start, $end)
    {
        $logidList = $this->redisLog->lget($logid . 'pt_log_id', 'R', $start, $end);
        foreach ($logidList as $key => $val) {
            
            $logDetail = $this->redisLog->hget($logid . 'pt_log_detail:' . ($key + 1 + $start)); // 可能消耗大量内存
            ksort($logDetail); // 对获取的数组按键值排序
            $data[] = $logDetail;
            $this->redisLog->del($logid . 'pt_log_detail:' . ($key + 1 + $start));
        }
        
        M('pt_log')->addAll($data); // 大批量存入数据库，是否需要很长数据，是否会影响其他数据库操作。
                                    // 在正式服务器上执行这段代码
                                    //
        if (! DEBUG_ON_LOCALHOST_OR_201TESTINGSERVER) {
            
            $connection = array(
                'db_type' => 'mysql',
                'db_host' => '139.224.0.198',
                'db_user' => 'ptlog',
                'db_pwd' => 'ptlog2016',
                'db_port' => 3306,
                'db_name' => 'ptlog',
                'db_charset' => 'utf8'
            );
            $uatLog = new \Home\Model\UatLogModel('pt_log', 'tb_', $connection);
            $uatLog->addAll($data);
        }
        return $data;
    }

    /**
     * [logDataPackageDay 对日志进行处理]
     * @ckhero
     * @DateTime 2016-08-19
     * 
     * @param [type] $data
     *            [日志记录]
     * @param integer $type
     *            [1为针对个人 2 位针对平台]
     * @return [type] [description]
     */
    public function logDataPackageDay($data, $type = 1)
    {
        foreach ($data as $key => $val) {
            
            $productid = intval($val['productid']);
            $ptid = intval($val['ptid']);
            
            $concat = $val['action'];
            if ($concat == 'Index/index/loganalysis' || $concat == 'Index/getMore/loganalysis') { // 将首页产品的展现放到数组里，后面处理展现日志的时候用到
                $showList[] = $val;
                continue;
            }
            if ($productid == 0)
                continue; // 商品id为空跳过
            if ($type == 2 && $ptid == 0)
                continue; // 计算平台的情况的时候平台id为0的时候 跳过
            
            $date = date('Y-m-d', strtotime($val['createtime']));
            if ($type == 1) { // 计算个人
                
                $zwid = $this->redis->get("zwcmopenid:" . $val['zwcmopenid']);
                if (empty($zwid))
                    continue;
                $listKey = "PeopleDay:" . $zwid . '_' . $productid . "_" . $date;
                $list[$listKey]['zwid'] = $zwid;
            } else { // 计算平台
                
                $listKey = 'PlatformDay:' . $ptid . '_' . $productid . '_' . $date;
                $list[$listKey]['ptid'] = $ptid;
            }
            
            $list[$listKey]['productid'] = $productid;
            $list[$listKey]['date'] = $date;
            $list[$listKey]['view'] = 0;
            $list[$listKey]['collect'] = 0;
            $list[$listKey]['download'] = 0;
            $list[$listKey]['buy'] = 0;
            $list[$listKey]['share'] = 0;
            if (($concat == 'Product/view/loganalysis') || ($concat == 'Wheel/viewProduct/loganalysis')) {
                
                $list[$listKey]['view'] += 1; // 详情页访问记录
                continue;
            }
            
            if ($concat == 'Member/doCollect/loganalysis') {
                
                $list[$listKey]['collect'] += 1; // 收藏记录
                continue;
            }
            
            if (($concat == 'Member/doOrder/loganalysis') || ($concat == 'Wheel/download/loganalysis')) {
                
                $list[$listKey]['download'] += 1; // 下载量即 领券记录
                continue;
            }
            
            if ($concat == 'Notify/Wxpay/loganalysis') {
                
                $list[$listKey]['buy'] += 1; // 购买量
                
                continue;
            }
            
            if ($concat == 'Product/Share/loganalysis') {
                
                $list[$listKey]['share'] += 1; // 购买量
                
                continue;
            }
            //抽奖平台的这些数据
            //Wheel/Join/loganalysis, 记录参与抽奖活动的次数。 即用户点击抽奖按钮的次数
            //Wheel/viewProduct/loganalysis  中奖页面， 即多少人看到中奖产品的详情页面
            //Wheel/download/loganalysis, 领取奖品的次数。
            //Wheel/index/loganalysis, 抽奖首页展示
        }
        
        // 计算首页展现量
        foreach ($showList as $key => $val) {
            
            if ($type == 1) {
                
                $id = $this->redis->get("zwcmopenid:" . $val['zwcmopenid']); // 每个循环里面的zwid不同
                $idname = 'zwid';
                $name = "PeopleDay:";
            } else {
                
                $id = intval($val['ptid']);
                $idname = 'ptid';
                $name = "PlatformDay:";
            }
            
            if ($id == 0)
                continue;
            
            $date = date('Y-m-d', strtotime($val['createtime']));
            
            $goods = array(); // 首页展现量的 producid为json串
            $goods = json_decode($val['details'], true); // array('goods'=>array('商品id'))
            if (empty($goods))
                continue;
            
            foreach ($goods as $k => $v) {
                
                $listKey = $name . $id . '_' . $v . "_" . $date;
                $list[$listKey][$idname] = $id; // 重复的会进行覆盖
                $list[$listKey]['productid'] = $v;
                $list[$listKey]['date'] = $date;
                $list[$listKey]['show'] += 1;
                $list[$listKey]['position'] += $k + 1;
            }
        }
        
        return $list;
    }

    /**
     * [logRedis 对日志进行缓存]
     * @ckhero
     * @DateTime 2016-08-19
     * 
     * @param [type] $data
     *            [处理过的数据]
     * @param integer $type
     *            [1为 针对个人 2位针对平台]
     * @return [type] [description]
     */
    public function logRedis($data, $type = 1)
    {
        foreach ($data as $key => $val) {
            
            $Day = array();
            $Day = $this->redisLog->hget($key);
            if (empty($Day)) {
                
                $Day = $val;
            } else {
                
                $Day['view'] += $val['view'];
                $Day['collect'] += $val['collect'];
                $Day['download'] += $val['download'];
                $Day['show'] += $val['show'];
                $Day['buy'] += $val['buy'];
                $Day['share'] += $val['share'];
                $Day['position'] += $val['position'];
            }
            $this->redisLog->hset($key, $Day);
            if ($type == 1)
                $this->redisLog->expire($key, 31 * 24 * 60 * 60); // 一个月过期
                                                                               
            // 对平台的月数据进行整理汇总（汇总的目的是减小从redis读取每个月记录的次数）
            if ($type == 2) {
                
                $date = date('Y-m', strtotime($val['date']));
                $MonthKey = 'PlatformMonth:' . $val['ptid'] . '_' . $val['productid'] . '_' . $date;
                $MonthTotal[$MonthKey]['view'] += $val['view'];
                $MonthTotal[$MonthKey]['collect'] += $val['collect'];
                $MonthTotal[$MonthKey]['download'] += $val['download'];
                $MonthTotal[$MonthKey]['show'] += $val['show'];
                $MonthTotal[$MonthKey]['buy'] += $val['buy'];
                $MonthTotal[$MonthKey]['share'] += $val['share'];
                $MonthTotal[$MonthKey]['position'] += $val['position'];
            }
        }
        
        // 对平台的月数据进行缓存
        foreach ($MonthTotal as $key => $val) {
            
            $Month = array();
            $Month = $this->redisLog->hget($key); // 读取当月的数据，如果有则进行更新，没有仅进行添加
            if (empty($Month)) {
                
                $Month = $val;
            } else {
                
                $Month['view'] += $val['view'];
                $Month['collect'] += $val['collect'];
                $Month['download'] += $val['download'];
                $Month['show'] += $val['show'];
                $Month['buy'] += $val['buy'];
                $Month['share'] += $val['share'];
                $Month['position'] += $val['position'];
            }
            
            $this->redisLog->hset($key, $Month); // 对月数据进行缓存
        }
        
        return true;
    }

    /**
     * [logDb 将行为日志记录到数据库]
     * @ckhero
     * @DateTime 2016-12-02
     * 
     * @param [type] $data
     *            [description]
     * @param integer $type
     *            [description]
     * @param string $idname
     *            [description]
     * @return [type] [description]
     */
    public function logDb($data, $type = 0, $idname = 'zwid')
    {
        $dataAll = array();
        foreach ($data as $key => $val) {
            
            $Day = array();
            $where = array(
                'name' => $val[$idname],
                'productId' => $val['productid'],
                'date' => $val['date']
            );
            $where['type'] = $type;
            $Day = M('action_record')->where("name=%d and productId=%d and date='%s' and type='%s'", $where)->find();
            if (empty($Day)) {
                
                $where['detail'] = json_encode($val);
                $where['createtime'] = date('Y-m-d H:i:s');
                $dataAll[] = $where;
            } else {
                $DayDetail = array();
                $DayDetail = json_decode($Day['detail'], true);
                $DayDetail['view'] += $val['view'] > 0 ? $val['view'] : 0;
                $DayDetail['collect'] += $val['collect'] > 0 ? $val['collect'] : 0;
                $DayDetail['download'] += $val['download'] > 0 ? $val['download'] : 0;
                $DayDetail['show'] += $val['show'] > 0 ? $val['show'] : 0;
                $DayDetail['buy'] += $val['buy'] > 0 ? $val['buy'] : 0;
                $DayDetail['share'] += $val['share'] > 0 ? $val['share'] : 0;
                $DayDetail['position'] += $val['position'] > 0 ? $val['position'] : 0;
                $updateData['detail'] = json_encode($DayDetail);
                M('action_record')->where("id =%d", $Day['id'])->save($updateData);
            }
            // 对平台的月数据进行整理汇总（汇总的目的是减小从redis读取每个月记录的次数）
            // if($type==1){
            
            // $date = date('Y-m',strtotime($val['date']));
            // $MonthKey = 'PlatformMonth:'.$val['ptid'].'_'.$val['productid'].'_'.$date;
            
            // $MonthTotal[$MonthKey]['ptid'] += $val['ptid'];
            // $MonthTotal[$MonthKey]['date'] += $date;
            // $MonthTotal[$MonthKey]['productid'] += $val['productid'];
            
            // $MonthTotal[$MonthKey]['view'] += $val['view'];
            // $MonthTotal[$MonthKey]['collect'] += $val['collect'];
            // $MonthTotal[$MonthKey]['download'] += $val['download'];
            // $MonthTotal[$MonthKey]['show'] += $val['show'];
            // $MonthTotal[$MonthKey]['buy'] += $val['buy'];
            // $MonthTotal[$MonthKey]['share'] += $val['share'];
            // $MonthTotal[$MonthKey]['position'] += $val['position'];
            // }
        }
        M('action_record')->addAll($dataAll); // 一次将数据进行添加 条数不会超过 $this->num = 50; //每次处理的日志条数50条 (50条到100是比较合理的数据)
                                              
        // 对平台的月数据进行缓存
                                              // if($type==1){
                                              
        // $MonthAll = array();
                                              // foreach($MonthTotal as $key => $val){
                                              
        // $Month = array();
                                              // $Month = $this->redisLog->hget($key); //读取当月的数据，如果有则进行更新，没有仅进行添加
                                              
        // $Month = array();
                                              // $where = array($val[$idname],$val['productid'],$val['date']);
                                              // $where['type'] = $type;
                                              // $Month = M('action_record')->where("name=%d and productId=%d and date='%s' and type=%d",$where)->find();
                                              
        // if(empty($Month)){
                                              
        // $Month = $val;
                                              // }else{
                                              
        // $Month['view'] += $val['view'];
                                              // $Month['collect'] += $val['collect'];
                                              // $Month['download'] += $val['download'];
                                              // $Month['show'] += $val['show'];
                                              // $Month['buy'] += $val['buy'];
                                              // $Month['share'] += $val['share'];
                                              // $Month['position'] += $val['position'];
                                              // }
                                              
        // $this->redisLog->hset($key,$Month); //对月数据进行缓存
                                              // }
                                              // }
        
        return true;
    }
    
    // 此函数检查： 1. 是否有平台在一天之内失效； 2. 是否有某个平台的优惠券会在一天之后全部失效； 3. 是否有某个平台的优惠券已经全部发完（实时检查）
    private function checkProductAvailability()
    { 
    	$product_checked = array();	//非抽奖平台商品中已经被检查过的商品的集合
    	
        $platforms = D('platform')->field('id, code, name, end')
            ->where("status=1")
            ->cache(true, 60 * 10)
            ->select();
        $product_code_count_array = array(); // 对每个有券码的优惠券的可用数量缓存在数组里面，减少数据库查询次数
        foreach ($platforms as $pkey => $thePlatform) {
            if (empty($thePlatform))
                continue;
                // 排除TESTACCUNTY、CMPAY等
            if (in_array($thePlatform['code'], $this->noCheckList))
                continue;
                
            //判断是否为抽奖平台
            $platform_config = getPlatformConfig($thePlatform['code']);
            $isLuckydraw = (2 == intval($platform_config['APP_MODE']));
                
            // 首先检查是否有平台在第二天到期
            if (strtotime($thePlatform['end']) < strtotime("+2 days"))
                $this->alertAdminUsingSMS("亲爱的管理员，乐天邦渠道" . $thePlatform['name'] . "将在一天之内到期，请知晓。谢谢！", $thePlatform['id'], '', 6);
                
            // 然后检查每一个平台的所有优惠券是否在第二天到期
            // 检查每一个平台当前的优惠券数量是否已经全部消化完
            $allProductsInvalidTomorrow = true; // 本平台的所有的优惠券第2天全要到期下架
            $number_of_valid_coupon = 0; // 本平台所有可以供发放得优惠券总数量
            $platform_id = $thePlatform['id'];
            
            $product_list = D('PlatformProduct')->join('tb_product on tb_platform_product.product_id=tb_product.id')
                	->field('tb_product.id,
   					 tb_product.total as total1,
   					 tb_product.saleallquantity,
   					 tb_product.start,
   					 tb_product.end,
   					 tb_product.verification,
                     tb_product.ifpay,  
                     tb_product.price, 
   					 tb_platform_product.total as total2,
   					 tb_platform_product.salequantity,
   					 tb_platform_product.onlinetime,
   					 tb_platform_product.offlinetime')
                ->where('tb_platform_product.platform_id=' . $platform_id . ' and tb_platform_product.status=1 and tb_product.status=1 ')
                ->cache(true, 60 * 10)
                ->select();           
            
            if ($product_list != null) {
                foreach ($product_list as $key => $item) {
                	if ( !$isLuckydraw && array_key_exists($item[id], $product_checked)){ //已经计算过此商品，取之前计算的值
                		if ( $product_checked[$item[id]] > 0 ) {
                			$allProductsInvalidTomorrow = false;
                			$number_of_valid_coupon += $product_checked[$item[id]];
                		}
                		continue; //此商品已经检查过了
                	}
                	$product_remaining_number[$item[id]] = 0;
                	
                    if (strtotime($item['start']) > time())
                        continue;
                    if (strtotime($item['end']) < time())
                       continue;
                    if (strtotime($item['onlinetime']) > time())
                       continue;
                    if (strtotime($item['offlinetime']) < time())
                       continue;
                    
                    
                    //用来检查是否在正式环境有极低的商品在出售，以防止管理员误操作
                    if ($item['ifpay'] == 1) {
                        if ( $item['price'] < 40.0 ){ //价格太低了， 假设商品没有低于 40 人民币的
                            $this->alertAdminUsingSMS("亲爱的管理员，乐天邦渠道名为" . $thePlatform['name'] . "的商品id=" . $item['id'] . "的销售价格很低，请确认是否有误。谢谢！", $thePlatform['id'], $item['id'], 0);
                        }
                    }
                    
                    if ( $isLuckydraw ){	//抽奖平台的商品数量要同时看tb_product和 tb_platform_product表格，取两者的最小值
                    	$number_of_valid_coupon_this_product = min($item['total1'] - $item['saleallquantity'], $item['total2'] - $item['salequantity']);
                    	if (($number_of_valid_coupon_this_product < 10) && ($item['total2'] > 60)) // 有些优惠券的总数就比较少，如果是这样的话，没有必要在少于10张的时候提醒。
                    		$this->alertAdminUsingSMS("亲爱的管理员，乐天邦渠道名为" . $thePlatform['name'] . "的优惠券id=" . $item['id'] . "的数量已经少于10张，请知晓。谢谢！", $thePlatform['id'], $item['id'], 2);                  	
                    } else{	//乐天邦平台的商品数量只需要要看tb_product表格
                    	$number_of_valid_coupon_this_product = $item['total1'] - $item['saleallquantity'];
                    	if ($number_of_valid_coupon_this_product < 30) // 有些优惠券的总数就比较少，如果是这样的话，没有必要在少于10张的时候提醒。
                    		$this->alertAdminUsingSMS("亲爱的管理员，乐天邦渠道的优惠券id=" . $item['id'] . "的数量已经少于30张，请知晓。谢谢！", '', $item['id'], 2);
                    	
                    }
                    if ((strtotime($item['end']) < strtotime("+2 days")) || (strtotime($item['offlinetime']) < strtotime("+2 days")))
                    	$this->alertAdminUsingSMS("亲爱的管理员，乐天邦渠道名为" . $thePlatform['name'] . "的优惠券id=" . $item['id'] . "将在一天之后过期，请知晓。谢谢！", $thePlatform['id'], $item['id'], 6);
                    	else
                    		$allProductsInvalidTomorrow = false;
                                     
                    if ($number_of_valid_coupon_this_product <= 0) continue;    
                        
                    if ($item['verification'] == 1) {
                        if (array_key_exists($item['id'], $product_code_count_array)) {
                            $valid_code_count = $product_code_count_array[$item['id']];
                        } else {
                            $valid_code_count = D('ProductCode')->where(array(
                                'productid' => $item['id'],
                                'status' => 0
                            ))->count();
                            $product_code_count_array[$item['id']] = $valid_code_count;
                        }
                        
                        if (empty($valid_code_count) || ($valid_code_count <= 30)) {
                        	//if ( $isLuckydraw && $item['total2'] > 60) // 有些优惠券的总数就比较少，如果是这样的话，没有必要在少于10张的时候提醒。
                        	if ( $item['total1'] > 60) 
                                $this->alertAdminUsingSMS("亲爱的管理员，乐天邦渠道名为" . $thePlatform['name'] . "的优惠券id=" . $item['id'] . "的券码已经少于30张，请知晓。谢谢！", $thePlatform['id'], $item['id'], 0);
                        }
                        $number_of_valid_coupon_this_product = min($valid_code_count, $number_of_valid_coupon_this_product);
                    }
                    $number_of_valid_coupon += $number_of_valid_coupon_this_product;
                    
                    if (!$isLuckydraw){
                    	$product_checked[$item['id']] = $number_of_valid_coupon_this_product;//此商品已经被检查过了，以后可以忽略了
                    }                 
                    
                }
            }
           
            if ($allProductsInvalidTomorrow == true)
                $this->alertAdminUsingSMS("亲爱的管理员，乐天邦渠道名为" . $thePlatform['name'] . "的所有优惠券将在一天之内到期，请知晓。谢谢！", $thePlatform['id'], '', 6);
           
            if ($number_of_valid_coupon == 0)
                $this->alertAdminUsingSMS("亲爱的管理员，乐天邦渠道名为" . $thePlatform['name'] . "的所有优惠券已经发放完毕，请知晓。谢谢！", $thePlatform['id'], '', 0);
                // 计算平台总的可用优惠券数目，低于一定数目报警
            
            if ((strcasecmp($thePlatform['code'], 'NYYHD') == 0) && ($number_of_valid_coupon < 500))
                $this->alertAdminUsingSMS("亲爱的管理员，乐天邦渠道名为" . $thePlatform['name'] . "的优惠券不足500张了，请知晓。谢谢！", $thePlatform['id'], '', 0);
            
            usleep(10000);
        }
    }

    private function alertAdminUsingSMS($msg, $platform_id, $product_id, $interval_in_hours)
    {
        // $interval_in_hours = 0 : 马上发, 每隔15分钟发一次
        // $interval_in_hours > 0 : 在工作时间发
    	
        if (empty($product_id))
            $redisKey = 'Alert_Admin_' . $platform_id;
        else {
            $redisKey = 'Alert_Admin_' . $platform_id . '_' . $product_id;
        }
            
        $redisResult = $this->redisLog->get($redisKey);
        if ($redisResult >= 2)
            return; // 一天之内每个只发2次
        
        if ($interval_in_hours == 0) {
            send_msg(ADMIN_MOBILE_PHONE, $msg);
//            file_put_contents("debug.log", 'SENT_MSG_TO_ADMIN' . date('Y-m-d H:i:s') . $msg . "\n", FILE_APPEND);
        } else {
            $hournum = Date('H');
            if (($hournum > 6) && ($hournum < 24)) {
                send_msg(ADMIN_MOBILE_PHONE, $msg);
//                file_put_contents("debug.log", 'SENT_MSG_TO_ADMIN' . date('Y-m-d H:i:s') . $msg . "\n", FILE_APPEND);
            } else
                return;
        }
        // $this->redisLog->set($redisKey, time() , 60 * 60 * $interval_in_hours + 20 * 60);
        if ($redisResult == false)
            $this->redisLog->set($redisKey, 1, 60 * 60 * 24);
        else
            $this->redisLog->set($redisKey, $redisResult + 1, 60 * 60 * 24);
    }
    
    // 获取领取试驾券用户的手机号。 如果用户在领券后没有填写信息，在第2天中午发短信提醒一次。
    // 正式环境添加这段代码的时间为： 2016年11月9日，
    public function getDriveInfo()
    {
        $time = (int) date('Hi');
        if ($time > 1100 && $time < 1200) {
            // 今天是否发送过短信了
            $issend = $this->redisLog->get('DriveInfo');
            if (empty($issend)) {
                // 每日第一次发送短信,设置缓存
                $this->redisLog->set('DriveInfo', 1);
                $this->redisLog->expire('DriveInfo', 3600 * 20);
                $date = date('Y-m-d%', strtotime('-1 day'));
                // 获取昨天领取试驾优惠券的手机号
                // $info = D('order_product')->field('tel')->where("createtime like '%s' and productid = %d",$date,229)->select();
                $info = D('order_product')->field('tel')
                    ->where("createtime like '%s' and productid in (%d,%d)", $date, 229, 219)
                    ->select();
                $post_data = '&data=' . json_encode($info);
                file_put_contents("debug.log", "getDriveInfo: phone num list:" . $post_data . "\n", FILE_APPEND);
                $url = HOST_URL . '/levin0707/data/checkdriveinfo.php?action=check' . $post_data;
                file_put_contents("debug.log", "getDriveInfo: url:" . $url . "\n", FILE_APPEND);
                $result = send_curl($url);
            }
        }
    }
    
    // 计算前一天各个平台和渠道的活跃用户数，并将几个存到tb_dau表格中
    //此方法不太精确，现在被calculateYesterdayActiveUserNew替代。但是当每天日志量超过5百万条后可以考虑切换回这个函数
 /*   private function calculateYesterdayActiveUser()
    {
        $hournum = Date('H');
        if ($hournum < 2)
            return;
                    
        $activeUserNumRedisKey = 'calculate_dau_' . date('Ymd', strtotime('-1 days'));
        if ($this->redisLog->existsRedis($activeUserNumRedisKey))
            return; // 昨天的数据已经生成，直接退出
        
        $pt_ids = D('platform')->field('distinct (id)')
            ->where('status=1')
            ->cache(3600)
            ->select();
        foreach ($pt_ids as $k => $v) {
            $activeplatformRedisKey = $activeUserNumRedisKey. '_platform_'.$v['id'];
            if ($this->redisLog->existsRedis($activeplatformRedisKey)) continue;
            $platform_active_result = D('pt_user')->field('count(distinct `zwcmopenid`) count')
                ->where("ptid=%d and (browsetime like '%s' )", array($v['id'], date('Y-m-d%', strtotime('last day'))))
                ->find();
            //file_put_contents("debug.log", 'calculateYesterdayActiveUser:' . D('pt_user')->getLastSql() . "\n", FILE_APPEND);
            $platform_new_user_result = D('pt_user')->field('count(distinct `zwcmopenid`) count')
                ->where("ptid=%d and (createtime like '%s' )", array( $v['id'], date('Y-m-d%', strtotime('last day')) ))
                ->find();
            //file_put_contents("debug.log", 'calculateYesterdayNewUser:  ' . D('pt_user')->getLastSql() . "\n", FILE_APPEND);
            
            $data = array();
            $data['ptid'] = $v['id'];
            $data['customer_id'] = '';
            $data['activedate'] = date('Y-m-d', strtotime('last day'));
            $data['dailyactiveuser'] = $platform_active_result[count];
            $data['dailynewuser'] = $platform_new_user_result[count];
            if ($data['dailyactiveuser'] + $data['dailynewuser'] > 0) {
                D('dau')->data($data)->add();
            }
            $this->redisLog->set($activeplatformRedisKey, date('Y-m-d H:i:s'), 48 * 60 * 60);
            usleep(10000); // 每执行一次睡0.01秒
        }
        // //下面算各个渠道的活跃用户数//
        
        $customer_id = D('platform')->field('distinct (customer_id)')
            ->where("customer_id > 0")
            ->cache(3600)
            ->select();
        
        foreach ($customer_id as $k => $v) {
            $activeCutomerRedisKey = $activeUserNumRedisKey. '_customer_'. $v['customer_id'];
            if ($this->redisLog->existsRedis($activeCutomerRedisKey)) continue;
            
            $pt_list = D('platform')->field('id')
                ->where('customer_id=%s', $v['customer_id'])
                ->cache(3600)
                ->select();
            $pt_array = array();
            foreach ($pt_list as $kk => $vv) {
                $pt_array[] = $vv['id'];
            }
            
            $pt_array = implode(",", $pt_array);
            $whereStr = "(ptid in (" . $pt_array . ")) and (browsetime like '" . date('Y-m-d%', strtotime('last day')) . "')";
            $customer_active_result = D('pt_user')->field('count(distinct `zwcmopenid`) count')
                ->where($whereStr)
                ->find();
                        
            $whereStr4NewUser = "(ptid in (" . $pt_array . ")) and (createtime like '" . date('Y-m-d%', strtotime('last day')) . "')";
            $customer_new_user_result = D('pt_user')->field('count(distinct `zwcmopenid`) count')
                ->where($whereStr4NewUser)
                ->find();
            
            
            $data = array();
            $data['ptid'] = '';
            $data['customer_id'] = $v['customer_id'];
            $data['activedate'] = date('Y-m-d', strtotime('last day'));
            $data['dailyactiveuser'] = $customer_active_result['count'];
            $data['dailynewuser'] = $customer_new_user_result['count'];
            if ($data['dailyactiveuser'] + $data['dailynewuser'] > 0) {
                D('dau')->data($data)->add();
            }
            unset($data);
            $this->redisLog->set($activeplatformRedisKey, date('Y-m-d H:i:s'), 48 * 60 * 60);
            usleep(10000); // 每执行一次睡0.01秒
        }
        //计算乐天邦所有平台当天的所有用户数，去掉重复的。
        $ltb_active_user = D('pt_user')->field('count(distinct `zwcmopenid`) count')
            ->where("browsetime like '%s' ", array(date('Y-m-d%', strtotime('last day'))))
            ->find();
        $ltb_new_user = D('pt_user')->field('count(distinct `zwcmopenid`) count')
            ->where("createtime like '%s' ", array(date('Y-m-d%', strtotime('last day'))))
            ->find();
        unset($data);
        $data = array();
        $data['ptid'] = '-1';
        $data['customer_id'] = '-1';
        $data['activedate'] = date('Y-m-d', strtotime('last day'));
        $data['dailyactiveuser'] = $ltb_active_user['count'];
        $data['dailynewuser'] = $ltb_new_user['count'];
        if ($data['dailyactiveuser'] + $data['dailynewuser'] > 0) {
            D('dau')->data($data)->add();
            //file_put_contents("debug.log", 'calculateYesterdayLTBUser:' . D('dau')->getLastSql() . "\n",FILE_APPEND);
        }
            
        $this->redisLog->set($activeUserNumRedisKey, date('Y-m-d H:i:s'), 48 * 60 * 60); // 2天之内有效
        
        //$elapseTime = microtime(true) - $time_0;
    }
    */
    //此函数是用来给管理员查看所有平台的当前可用商品的可领取数量， 以html方式展示
    //访问方式：http://m.zwmedia.com.cn/wxzspfse/index.php/Home/LogAnalysis/checkProductAvailability4Admin
    function checkProductAvailability4Admin()
    {
    
        echo "<html><head><title></title></head><body>";
        
        
    
        $platforms = D('platform')->field('id, code, name, end')
            ->where("status=1")
            ->cache(true, 60 * 10)
            ->select();
        $product_code_count_array = array(); // 对每个有券码的优惠券的可用数量缓存在数组里面，减少数据库查询次数
        foreach ($platforms as $pkey => $thePlatform) {
            if (empty($thePlatform)) continue;
                // 排除TESTACCUNTY、CMPAY等
            if (in_array($thePlatform['code'], $this->noCheckList)) continue;
    
            //print out platform name
            //file_put_contents("debug.log", "平台名:" . $thePlatform['name'] . "\n", FILE_APPEND);
            echo "平台名:" . $thePlatform['name'] . "<br/>";
            echo '<table border="1" ><tr><th>商品名</th><th>剩余有效优惠券数量(张)</th><th>剩余有效天数</th></tr>';
            
            //判断是否为抽奖平台
            $platform_config = getPlatformConfig($thePlatform['code']);
            $isLuckydraw = (2 == intval($platform_config['APP_MODE']));
            
            $platform_id = $thePlatform['id'];
            $product_list = D('PlatformProduct')->join('tb_product on tb_platform_product.product_id=tb_product.id')
                        ->field('tb_product.id,
                            tb_product.name,
   					 tb_product.total as total1,
   					 tb_product.saleallquantity,
   					 tb_product.start,
   					 tb_product.end,
   					 tb_product.verification,
   					 tb_platform_product.total as total2,
   					 tb_platform_product.salequantity,
   					 tb_platform_product.onlinetime,
   					 tb_platform_product.offlinetime')
       					 ->where('tb_platform_product.platform_id=' . $platform_id . ' and tb_platform_product.status=1 and tb_product.status=1 ')
       					 ->cache(true, 60 * 10)
       					 ->select();
    
       		if ($product_list == null) continue;
       		foreach ($product_list as $key => $item) {
       			if (strtotime($item['start']) > time()) continue;
       			if (strtotime($item['onlinetime']) > time()) continue;
       			if (strtotime($item['end']) < time()) continue;
       			if (strtotime($item['offlinetime']) < time()) continue;
    
       			if ($isLuckydraw){
       				$number_of_valid_coupon_this_product = min($item['total1'] - $item['saleallquantity'], $item['total2'] - $item['salequantity']);
       			}else{
       				$number_of_valid_coupon_this_product = $item['total1'] - $item['saleallquantity'];
       			}
       			if ($number_of_valid_coupon_this_product <= 0) continue;
    
       			if ($item['verification'] == 1) {
       				 if (array_key_exists($item['id'], $product_code_count_array)) {
       				     $valid_code_count = $product_code_count_array[$item['id']];
       			      } else {
       					 $valid_code_count = D('ProductCode')->where(array(
       					                          'productid' => $item['id'],
       					                          'status' => 0
       					                          ))->count();
       					 $product_code_count_array[$item['id']] = $valid_code_count;
       				}
       				$number_of_valid_coupon_this_product = min($valid_code_count, $number_of_valid_coupon_this_product);
       			 }
       			 if ( $item['end'] < $item['offlinetime'] )
       			     $valid_days = $this->daysFromNow( $item['end'] ) ;
       			 else
       			     $valid_days = $this->daysFromNow( $item['offlinetime'] );
       			 //PRINT OUT product info
       			 //echo "商品名:" . $item['name'] . ', 剩余有效优惠券数量：' . $number_of_valid_coupon_this_product . ', 剩余有效日期：' . $valid_days .  "天 <br/>";
       			 echo "<tr><td>" . $item['name'] . "</td><td>" . $number_of_valid_coupon_this_product . "</td><td>" . $valid_days ."</td></tr>";
       			 //file_put_contents("debug.log", "商品名:" . $item['name'] . '=' . $number_of_valid_coupon_this_product . ', valid days=' . $valid_days . "\n", FILE_APPEND);
       		}      	
       		
       		
       		echo "</table>";
       		echo " <br/>";
       		usleep(1000);
        }
        echo "</body></html>";
    }
    
    private function daysFromNow ($endTime)
    {
        $second1 = strtotime($endTime);
        $second2 = time();
    
        return round( ($second1 - $second2) / 86400, 1);
    }

    /**
     * 补发招行签到记录
     */
    public function reissureZHsignRecord(){
        $time_0 = time();
        //选择默认库
        $this->redis->databaseSelect();
        $send_url_arr = $this->redis->keys('cmd_send_url:*');
        if (empty($send_url_arr)) return;
        foreach ($send_url_arr as $key => $val ){
            if ( time() - $time_0 > 3 * 60 ){
                return;
            }
            //循环发送请求
            $send_url = $this->redis->get($val);
            $return = send_curl($send_url, '', '', '', 5); // 招行系统经常有问题，设成5秒超时是为了避免用户长时间等待
            //截取openid
            $openid = substr($val, strlen('cmd_send_url:') );
            if (!empty($return)){
                $return_arr = json_decode($return,true);
                if ($return_arr['returnCode'] == '0000' || $return_arr['returnCode']=='1001') {
                    //删除该redis  
                   $this->redis->del($val);
                }
            }
            //记日志
            dolog('Wheel/zhSignResult', $openid, '', 'Retry, result='. $return . ', openid=' . $openid . ', url=' . $send_url, $this->redisLog);
        }
        
        unset($send_url_arr);
        $send_url_arr = $this->redisLog->keys('telSendToZHUrl_*');
        if (empty($send_url_arr)) return;
        foreach ($send_url_arr as $key => $val ){
            if ( time() - $time_0 > 3 * 60 ){
                return;
            }
            //循环发送请求
            $send_url = $this->redisLog->get($val);
            $return = send_curl($send_url, '', '', '', 5); // 招行系统经常有问题，设成5秒超时是为了避免用户长时间等待
            //截取openid
            $openid = explode("_", $val )[1];
            if (!empty($return)){
                //删除该redis
                $this->redisLog->del($val);
            }
            //记日志
            dolog('Wheel/zhSendPhone', $openid, '', 'Retry, result='. $return . ', openid=' . $openid . ', url=' . $send_url, $this->redisLog);
        }
        
    }

    //此函数是用来给招行查看日签的数据统计， 以html方式展示
    //访问方式：http://m.zwmedia.com.cn/wxzspfse/index.php/Home/LogAnalysis/checkCMBCheckinData
    //已经给招行提供dashboard， 此函数可以删掉
    public function checkCMBCheckinData()
    {
    
        echo "<html><head><title>招行日签数据统计</title></head><body>";
    
        $sql = "Select Date(createtime) activedate, count(openid) activeusernumber, sum(thumbup) thumbup , sum(audioplayback) audioplayback, sum(sharenum) sharenum, avg(duration) duration ,  avg(pageloadtime)/1000 pageloadtime, sum(sharePVnum) sharePVnum from tb_checkin_data where Date(createtime) > '2017-01-19' group by Date(createtime) order by Date(createtime) desc";
        $result = M()->query($sql);
        echo '<table border="1" ><tr><th>日期</th><th>人数</th><th>点赞次数</th><th>播放音乐次数</th><th>分享次数</th><th>来自分享的PV次数</th><th>平均停留时长（秒）</th><th>平均页面加载时间（秒）</th></tr>';
        
        foreach ($result as $oneDayData) {
            if (empty($oneDayData)) continue;
           
            echo '<tr align="right">';
            echo "<td>" . $oneDayData['activedate'] . "</td><td>" . 
                $oneDayData['activeusernumber'] . "</td><td>" . $oneDayData['thumbup'] ."</td><td>"
                . $oneDayData['audioplayback'] ."</td><td>". $oneDayData['sharenum'] ."</td><td>" . $oneDayData['sharePVnum'] ."</td><td>"
                . round($oneDayData['duration'], 1) ."</td><td>". round($oneDayData['pageloadtime'], 3) ."</td>" ;   		 
           		
           	echo "</tr> ";
        }
        echo "</table>";
        echo "</body></html>";
    }

    /**
     * @Description:每15分钟计算一次用户和分类的得分计入数据库
     * @User:jysdhr
     */
    public function get_user_category_score_by_redis_to_addall()
    {
        if (!self::$ReentrantLock) return;//如果运行这个方法时，上次还未执行完。那就等下次一起执行
        $runing_time_start = time();//获取当前时间戳
        self::$ReentrantLock = false;
        //切换存的队列
        $category_queue_index = $this->redisLog->get('Category_queue_index');
        if ($category_queue_index == 1) {
            $this->redisLog->set('Category_queue_index',2);
        }else{
            $this->redisLog->set('Category_queue_index',1);
        }
        $user_category_score_arr = [];
        $queue_length = $this->redisLog->llen('Category_queue' . $category_queue_index);
        for ($i = 0; $i < $queue_length; $i++) {
            if (time() - $runing_time_start  > 180) return;//当运行时间超过三分钟，中断程序
            //从redis队列中pop出zwcmopenid和分类id的记录,
            $temp_arr = json_decode($this->redisLog->pop('Category_queue' . $category_queue_index, 'l'), true);
//            var_dump($temp_arr);die;
            if ($temp_arr['zwid'] == '') continue;
            //计算出每个用户对应的分类id分数
            if (isset($user_category_score_arr[$temp_arr['zwid']][$temp_arr['category_id']])){
                $user_category_score_arr[$temp_arr['zwid']][$temp_arr['category_id']]['score'] += $temp_arr['score'];
            }else{
                $user_category_score_arr[$temp_arr['zwid']][$temp_arr['category_id']]['zwid'] = $temp_arr['zwid'];
                $user_category_score_arr[$temp_arr['zwid']][$temp_arr['category_id']]['category_id'] = $temp_arr['category_id'];
                $user_category_score_arr[$temp_arr['zwid']][$temp_arr['category_id']]['score'] = $temp_arr['score'];
            }
        }
        //实例化分类模型
        if (is_array($user_category_score_arr)){
            foreach ($user_category_score_arr as $k => $v){
                usleep(10000);//休息1ms
                foreach ($v as $kk => $vv)
                    $this->update_or_insert_records($vv);
            }
        }
        self::$ReentrantLock == true;
    }
    public function update_or_insert_records($data){
        $res = M('user_category_score')->field('id')->where("zwid = %d and category_id = %d",$data['zwid'],$data['category_id'])->find();
        if ($res){
            $sql = sprintf("UPDATE tb_user_category_score SET score=score+%d WHERE zwid = %d and category_id = %d ", $data['score'], $data['zwid'], $data['category_id']);
            M()->execute($sql);
        }else{
            M('user_category_score')->add($data);
        }
    }
    
    
    //从tb_pt_log表格里面取指定日期的第一条记录的id, 用来提高数据库查询速度
    // theDate 的格式： 2017-03-22
    private function getFirstIDFromLog( $theDate ){
    	if(empty($theDate)) return 0;
    	if(strtotime($theDate) < strtotime('2017-01-01')) return 0; // 在实际运行的过程中，我们不去计算很久以前的数据
    	$time_0 = time();
    	
    	$redisKey = 'FIRST_ID_OF_DATE_' . $theDate;
    	$redisResult = $this->redisLog->get($redisKey);
    	if ( $redisResult != FALSE ) return $redisResult;
    	
    	//如果redis里面没有，则从数据库里面取; 每天约80万条数据, 2017-03-27
    	$resultMaxIDQuery = D('pt_log')->query("select id from tb_pt_log order by id desc limit 1");
    	$latestID = $resultMaxIDQuery[0]['id'];
    	
    	while ($latestID > 0){
    		$latestID -= 100000; //每次回退10万条记录，看是否是一天前的记录
    		if ( $latestID < 0 ) break;
    		usleep(1000);
    		$whereStr = "select id, createtime from tb_pt_log where id > " . $latestID . "  order by id limit 1";
    		$resultMaxIDQuery = D('pt_log')->query($whereStr);
    		if ( strtotime($resultMaxIDQuery[0]['createtime']) > strtotime($theDate)) continue;
    		for( $index = $latestID + 10000 ; $index <= $latestID + 100000 ; $index += 10000 ){ //每次前进1万条记录
    			$whereStr = "select id, createtime from tb_pt_log  where id > " . $index . "  order by id limit 1";
    			$resultIDQuery = D('pt_log')->query($whereStr);
    			if ( strtotime($resultIDQuery[0]['createtime']) >= strtotime($theDate)) break;
    		}
    		$index -= 10000;
    		if  ( $index < 1 ) $index = 1;
    		$this->redisLog->set($redisKey, $index, 48 * 60 * 60);
    		$elapseTime = microtime(true) - $time_0;
    		//file_put_contents("debug.log", 'getFirstIDFromLog: date=' . $theDate  . ', result=' . $index . ', it took '. $elapseTime ." seconds.\n", FILE_APPEND);
    		return $index;
    	}
    	return 1;
    	
    }
    
    
    // 计算前一天各个平台和渠道的活跃用户数，并将几个存到tb_dau表格中
    public function calculateYesterdayActiveUserNew()
    {
    	$hournum = Date('H');
    	if ($hournum < 2)
    		return;
    		
    		$time_initial = microtime(true);
    		$activeUserNumRedisKey = 'calculate_dau_' . date('Ymd', strtotime('-1 days'));
    		if ($this->redisLog->existsRedis($activeUserNumRedisKey))
    			return; // 昨天的数据已经生成，直接退出
    			
    			$pt_ids = D('platform')->field('id')->distinct(true)
    			->where('status=1')
    			->cache(3600)
    			->select();
    			
    			$firstIDYesterday = $this->getFirstIDFromLog(date('Y-m-d', strtotime('last day')));
    			$lastIDYesterday = $this->getFirstIDFromLog(date('Y-m-d')) + 10000; //因为不是精确的查找，所以应该往后移10000，用来把当天的所有记录都算进去
    			
    			if ( $lastIDYesterday - $firstIDYesterday > 5000000){
    				//如果一天的日志数据有5百万条以上，这个方法可能会太慢，可能无法得出结果。目前是每天月80万条。 邵晓凌 2017-03-27
    				send_msg(ADMIN_MOBILE_PHONE, '亲爱的管理员，请通知程序员。当天的日志超过5百万条，需要修改calculateYesterdayActiveUser函数。');
    				return;
    			}
    			//先计算各个平台的数据
    			foreach ($pt_ids as $k => $v) {
    				$activeplatformRedisKey = $activeUserNumRedisKey. '_platform_'.$v['id'];
    				if ($this->redisLog->existsRedis($activeplatformRedisKey)) continue;
    				if ( microtime(true) - $time_initial > 180)  return; //设3分钟超时
    				$time_0 = microtime(true);
    				$data = array();
    				$data['ptid'] = $v['id'];
    				$data['customer_id'] = '';
    				$data['activedate'] = date('Y-m-d', strtotime('last day'));
    				
    				$platform_new_user_result = D('pt_user')->field('count(distinct `zwcmopenid`) count')
    				->where("ptid=%d and Date(createtime)='%s' ", array( $v['id'], date('Y-m-d', strtotime('last day')) ))
    				->find();
    				$data['dailynewuser'] = $platform_new_user_result[count];
    				
    				$queryActiveUser = sprintf("select count(distinct zwcmopenid) count from tb_pt_log where id>%d and id<%d and ptid=%d  and  Date(createtime)='%s' limit 1", $firstIDYesterday, $lastIDYesterday, $v['id'], date('Y-m-d', strtotime('last day')) );
    				$resultActiveUser = D('pt_log')->query($queryActiveUser);
    				$data['dailyactiveuser'] = $resultActiveUser[0]['count'];
    				
    				if ($data['dailyactiveuser'] + $data['dailynewuser'] > 0) {
    					//计算登录用户数
    					$queryCheckinUser = sprintf("select count(distinct zwcmopenid) count from tb_pt_log where id>%d and id<%d and ptid=%d  and  Date(createtime)='%s' and action = 'Member/login' limit 1", $firstIDYesterday, $lastIDYesterday, $v['id'], date('Y-m-d', strtotime('last day')) );
    					$resultLoginQuery = D('pt_log')->query($queryCheckinUser);
    					$data['dailycheckinuser'] = $resultLoginQuery[0]['count'];
    					$elapseTime = microtime(true) - $time_0;
    					D('dau')->data($data)->add();
    					file_put_contents("debug.log", 'calculate_Yesterday_platform data:  ptid=' . $v['id'] . ',  ' . json_encode($data). ', it took '  . $elapseTime . ' seconds' . "\n", FILE_APPEND);
    					//file_put_contents("debug.log", 'calculate_Yesterday_platform data:  ptid=' . $v['id'] . ',  ' . D('dau')->getLastSql() . "\n", FILE_APPEND);
    				}
    				$this->redisLog->set($activeplatformRedisKey, date('Y-m-d H:i:s'), 48 * 60 * 60);
    				usleep(10000); // 每执行一次睡0.01秒
    			}
    			
    			/////下面算各个渠道的活跃用户数/////
    			
    			$customer_id = D('platform')->field('customer_id')->distinct(true)
    			->where("customer_id > 0 and status=1")
    			->cache(3600)
    			->select();
    			file_put_contents("debug.log", 'customer id='  . json_encode($customer_id). "\n", FILE_APPEND);
    			foreach ($customer_id as $k => $v) {
    				$activeCutomerRedisKey = $activeUserNumRedisKey. '_customer_'. $v['customer_id'];
    				if ($this->redisLog->existsRedis($activeCutomerRedisKey)) continue;
    				if ( microtime(true) - $time_initial > 180)  return; //设3分钟超时
    				
    				$time_0 = microtime(true);
    				$data = array();
    				$data['ptid'] = '';
    				$data['customer_id'] = $v['customer_id'];
    				$data['activedate'] = date('Y-m-d', strtotime('last day'));
    				
    				$pt_list = D('platform')->field('id')
    				->where('customer_id=%s', $v['customer_id'])
    				->cache(3600)
    				->select();
    				if (empty($pt_list)) continue;
    				$pt_array = array();
    				foreach ($pt_list as $kk => $vv) {
    					$pt_array[] = $vv['id'];
    				}
    				$pt_array = implode(",", $pt_array);
    				$whereStr = sprintf("select count(distinct zwcmopenid) count from tb_pt_log where id>%d and id<%d and ptid in  (%s)  and  Date(createtime)='%s' limit 1", $firstIDYesterday, $lastIDYesterday, $pt_array, date('Y-m-d', strtotime('last day')) );
    				
    				$customer_active_result = D('pt_log')->query($whereStr); //D('pt_log')->field('count(distinct `zwcmopenid`) count')->where($whereStr)->find();
    				$data['dailyactiveuser'] = $customer_active_result[0]['count'];
    				
    				$whereStr4NewUser = sprintf("select count(distinct zwcmopenid) count from tb_pt_user where ptid in  (%s)  and  Date(createtime)='%s' limit 1", $pt_array, date('Y-m-d', strtotime('last day')) );
    				$customer_new_user_result = D('pt_user')->query($whereStr4NewUser); //->field('count(distinct `zwcmopenid`) count')->where($whereStr4NewUser)->find();
    				$data['dailynewuser'] = $customer_new_user_result[0]['count'];
    				
    				if ($data['dailyactiveuser'] + $data['dailynewuser'] > 0) {
    					$queryCheckinUser = sprintf("select count(distinct zwcmopenid) count from tb_pt_log where id>%d and id<%d and ptid in (%s)  and  Date(createtime)='%s' and action = 'Member/login' limit 1", $firstIDYesterday, $lastIDYesterday, $pt_array, date('Y-m-d', strtotime('last day')) );
    					$resultLoginQuery = D('pt_log')->query($queryCheckinUser);
    					$data['dailycheckinuser'] = $resultLoginQuery[0]['count'];
    					$elapseTime = microtime(true) - $time_0;
    					
    					D('dau')->data($data)->add();
    					file_put_contents("debug.log", 'calculateYesterday Customer User Number:' . json_encode($data). ', it took '  . $elapseTime . ' seconds' . "\n",FILE_APPEND);
    					//file_put_contents("debug.log", 'calculateYesterday Customer User Number:' . D('dau')->getLastSql() . "\n",FILE_APPEND);
    					
    				}
    				unset($data);
    				$this->redisLog->set($activeCutomerRedisKey, date('Y-m-d H:i:s'), 48 * 60 * 60);
    				usleep(10000); // 每执行一次睡0.01秒
    			}
    			
    			$time_0 = microtime(true);
    			//计算乐天邦所有平台当天的所有用户数，去掉重复的。
    			$ltb_active_user = D('pt_log')->field('count(distinct `zwcmopenid`) count')
    			->where(" id>%d and id<%d and Date(createtime)='%s' ", array($firstIDYesterday, $lastIDYesterday, date('Y-m-d', strtotime('last day'))))
    			->find();
    			
    			$ltb_new_user = D('pt_user')->field('count(distinct `zwcmopenid`) count')
    			->where("Date(createtime) = '%s' ", array(date('Y-m-d', strtotime('last day'))))
    			->find();
    			
    			unset($data);
    			$data = array();
    			$data['ptid'] = '-1';
    			$data['customer_id'] = '-1';
    			$data['activedate'] = date('Y-m-d', strtotime('last day'));
    			$data['dailyactiveuser'] = $ltb_active_user['count'];
    			$data['dailynewuser'] = $ltb_new_user['count'];
    			if ($data['dailyactiveuser'] + $data['dailynewuser'] > 0) {
    				$queryLTBCheckinUser = sprintf("select count(distinct zwcmopenid) count from tb_pt_log where id>%d and id<%d and  Date(createtime)='%s' and action = 'Member/login' limit 1", $firstIDYesterday, $lastIDYesterday, date('Y-m-d', strtotime('last day')) );
    				$resultLTBCheckinQuery = D('pt_log')->query($queryLTBCheckinUser);
    				$data['dailycheckinuser'] = $resultLTBCheckinQuery[0]['count'];
    				
    				D('dau')->data($data)->add();
    				$elapseTime = microtime(true) - $time_0;
    				file_put_contents("debug.log", 'calculateYesterdayLTBData:' . json_encode($data). ', it took ' . $elapseTime . " seconds\n",FILE_APPEND);
    				//file_put_contents("debug.log", 'calculateYesterdayLTBData:' . D('dau')->getLastSql() . ', it took ' . $elapseTime . " seconds\n",FILE_APPEND);
    			}
    			
    			$this->redisLog->set($activeUserNumRedisKey, date('Y-m-d H:i:s'), 48 * 60 * 60); // 2天之内有效
    			
    }
    
    
    
    
}
?>
