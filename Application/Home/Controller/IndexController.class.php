<?php
namespace Home\Controller;

use Think\Controller;

class IndexController extends CommonController
{

    public function __construct()
    {
        // 获取城市列表
        parent::__construct();
        define('MAX_SCORE', 3000000); // 计算出来的得分的最高分；此值与该商品在数据库里面的sort值（用来标示置顶的商品）相加之和的值为最后排序的依据。
        define('MAX_SCORE_PER_CATEGORY', 128);
        define('INITIAL_SCORE_PER_CATEGORY', 32);
        define('DEBUG_PRINT_SCORES', false); // 正式环境里面必须为false. 把商品得分的信息打印到文件， 只在调试环境使用
        $this->expire = 300; // 缓存过期时间， 5分钟
        $this->numOfProductsShownOnEachPage = 6; //在横排的形式下，每屏幕最多显示6个商品
        $this->maxProductsShownOnHomePage = 3 * $this->numOfProductsShownOnEachPage; //在横排的形式下，首页最多显示3屏幕，每屏幕6个商品。其他商品需要点击“更多”按钮才能查看
        $this->totalProductsNum = 0; //在数据库里面，本渠道可以显示的商品数量。用来在前端展示的时候决定是否显示“更多”按钮。
        $this->assign('platformCode', $this->platform_code);
        
    }
    
    // 乐天邦首页展示
    public function index()
    {
    	$time_0 = microtime(true);
    	$res = $this->getShowList(0, $this->maxProductsShownOnHomePage - 1);
        $productNum = count($res['product_list_1']);
        /*if($productNum > 12) { //超过3页

        	if ($productNumRemainder = 6 - $productNum % 6 != 6) {

            	for ($i = 0; $i < $productNumRemainder; $i++) {

                	$res['product_list_1'][$productNum + $i] = $res['product_list_1'][$i];
                } 
            }        
   		}*/
        $elapseTime = round(microtime(true) - $time_0, 4);
        if ($elapseTime > 0.2) {
        	dolog('Index/debug', '给优惠券排序超过200ms', '', 'latency(index): ' . $elapseTime, $this->redisLog);
        }           
        
        
        $mode = getPlatformConfig($this->platform_code); // C('PLATFORM.'.$_SESSION['PLATFORM_CODE']);
        $mode['URL_GAME'] = ONE_COIN_URL . "index.php/Home/OneCoin/index/sid/" . session_id();
        $this->assign('product_list_1', $res['product_list_1']);
        $this->assign('mode', $mode);

        if(empty($this->page_title)) {

            $this->assign('page_title', '乐天邦 - 您的专享优惠');
        }
        $product_id_list = array_chunk($res['goods'], 6);
        $this->assign('product_id_list', json_encode($product_id_list));
        doLog('Index/index/loganalysis', "首页展示", '', json_encode($product_id_list[0]), $this->redisLog);
        $this->assign('num_of_total_product_available', $this->totalProductsNum);
        $this->assign('indextwourl',U('Home/Index/indexTwo?platcode/' . $this->platform_code));
        $this->display('index');
    }
    
    // 防盗版信息
    public function show()
    {
        $this->display('show');
    }
    
    // 免责声明
    public function disclaimer()
    {
        doLog('Index/disclaimer', "免责声明", '', '', $this->redisLog);
        $this->display('disclaimer');
    }
    // /**
    // * [getMore 新版getmore 首页换一组]
    // * @ckhero
    // * @DateTime 2016-09-02
    // * @return [type] [description]
    // */
    public function getMore()
    {
        
        // 邵晓凌： 原来的 echo header("Access-Control-Allow-Origin: *"); 为什么删掉了？
        $cur_page = I('param.page', 2, 'intval');
        $start = ($cur_page - 1) * $this->numOfProductsShownOnEachPage;
        
        $res = $this->getShowList($start, $start + $this->numOfProductsShownOnEachPage- 1, 2);
        $product_list_id = $res['goods'];
        $product_list = $res['product_list_1'];
        if ($res['goodsAdded'] == $this->numOfProductsShownOnEachPage) { // 补充的数据为6 条
            
            $json_data = array(
                'state' => 2,
                'msg' => '数据不足',
                'list_data' => $product_list
            );
            doLog('Index/getMore', '数据不足', '', json_encode($product_list_id), $this->redisLog);
        } elseif ($res['goodsAdded'] > 0) { // 补充的数据小鱼6条
            
            $json_data = array(
                'state' => 1,
                'msg' => '全部加载',
                'list_data' => $product_list
            );
            doLog('Index/getMore/loganalysis', '首页全部加载', '', json_encode($product_list_id), $this->redisLog);
        } else { // 正常
            $json_data = array(
                'state' => 0,
                'msg' => '加载成功',
                'list_data' => $product_list
            );
            doLog('Index/getMore/loganalysis', '首页加载更多', '', json_encode($product_list_id), $this->redisLog);
        }
        
        $this->ajaxReturn($json_data);
    }
    
   

    private function setScores($productList, $zwid, $platformid, $redisKey){

    	if (FALSE == DEBUG_ON_LOCALHOST_OR_201TESTINGSERVER){	//在测试环境里面，为了容易暴露问题，不强制设为0
    		if( $zwid == null ) $zwid = 0;
    	}
        $bothMonth = D('action_record')->getMonth(array($zwid, $platformid), array_column($productList, 'id'), 2);
        foreach ($productList as $key => $val) {
            $PlatformProductModel = D('PlatformProduct');
            $val['product_exist'] = $PlatformProductModel->checkProductExist($val['id']); // 验证商品是否可正常购买或者领取
            if ($val['product_exist'] != 1) { // 已下线的商品 分数为0
                $score = 0;
            } else {
                $noRankingNeeded = false;
                if ( ( count($productList) <= 2 ) || (strcasecmp($this->platform_code, 'TESTACCOUNT') == 0) )//TESTACCOUNT, 或者商品总数量小于或者等于2 不需要排序
                    $noRankingNeeded = true;
                if (($val['sort'] < MAX_SCORE) && !$noRankingNeeded) {
                // 邵晓凌：要综合考虑天和月的数据
                // 邵晓凌：每个指标封顶128. 初始值为 32.
                // 每个商品在针对用户的表现
                    $personalscore = INITIAL_SCORE_PER_CATEGORY; // 掌握id为空的时候使用默认分数，排序按商品的sorts 排序
                    if ($zwid > 0) {
                        if (DEBUG_PRINT_SCORES)
                            file_put_contents("debug1.log", "zwid=" . $zwid . ", product=" . $val['name'] . "\n", FILE_APPEND);
                                    
                                    // $PeopleTotal = $this->analysisPeople($zwid,$val['id']); //获取用户当日和当月的数据
                            $currDay = is_array($bothMonth[$val['id']]['currDay']) ? $bothMonth[$val['id']]['currDay'] : array();
                            $currMonth = is_array($bothMonth[$val['id']][0]) ? $bothMonth[$val['id']][0] : array();
                            $personalscore = $this->analysisPersonScore($currDay, $currMonth, $personalscore); // 邵晓凌：此处应该传入最近一个月的数据$PeopleMonth
                                                                                                                       // $personalscore = $this->analysisPersonScore($PeopleTotal['currDay'], $PeopleTotal['month'], $personalscore);                                                                                                                  
                    }
                            // 此处以后处理每个商品在本渠道的质量得分
                    $redisPlatformProduct = 'Score::PlatformProduct:' . $platformid . '_' . $val['id'];
                    if (! $this->redisLog->existsRedis($redisPlatformProduct)) {
                        $platformMonth = $bothMonth[$val['id']][1];
                        $platformscore = $this->analysisPlatformScore($platformMonth, 1);
                        $this->redisLog->set($redisPlatformProduct, $platformscore, $this->expire); // 5分钟失效
                    } else {
                        $platformscore = floatval($this->redisLog->get($redisPlatformProduct));
                    }
                            
                            // 此处以后处理每个商品的LBS等方面的质量得分
                            // $lbsscore = INITIAL_SCORE_PER_CATEGORY;
//                            $category_model = D('Category');
//                            $category_score = $category_model->get_score($_SESSION[$this->platform_code]['zwcmopenid'],$val['category_id']);
//                            $category_score = round(($category_score+255)/255,4);//暂时先注释掉 ，分类分先不计算
                    $score = $personalscore * $platformscore; //* $category_score; // * $lbsscore; //目前还不计算$lbsscore
                    if ($score > MAX_SCORE)
                        $score = MAX_SCORE; // 3000000封顶，原因是因为有些渠道如招行规定一些商品需要置顶，那些商品是用sort=3000001来标识的。
                    if ($zwid=2008582)
                        //jiang的openid的首页分记录
                        file_put_contents("final_score.log", 'personalscore=' . $personalscore . ', platformscore=' . $platformscore .", category_score=".$category_score .", final score=" . $score . "\n", FILE_APPEND);
                    } else { // 置顶的商品；如果有多个置顶的商品，以sort的值排序
                        $score = $val['sort'];
                    }
                }
                $this->redisLog->zset($redisKey, $score, $val['id']);
                    
                // 把商品详细信息缓存到redis,过期时间 5 分钟
                // $val['product_exist'] = $PlatformProductModel->checkProductExist($val['id']); //验证商品是否可正常购买或者领取
                $val['start'] = date('Y-m-d', strtotime($val['start']));
                $val['end'] = date('Y-m-d', strtotime($val['end']));
                $val['home_picture'] = RESOURCE_PATH . $val['home_picture'];
                $val['detail_picture'] = RESOURCE_PATH . $val['detail_picture'];
                $val['url'] = U('Home/Product/view/id/' . $val['id'] . '/click_num/' . $key . '/platcode/' . $this->platform_code);
                $this->redisLog->hset('Score::proudctDetail:' . $val['id'] . "_" . $platformid, $val);
                $this->redisLog->expire('Score::proudctDetail:' . $val['id'] . "_" . $platformid, $this->expire + 20); // 商品信息缓存多20s，避免id列表缓存未过期。商品信息已过期的情况
            }
            $this->redisLog->expire('Score::PersonScoreZwidPtid:' . $zwid . '_' . $platformid, $this->expire); // 5分钟过期
    }

    private function setCollectionDate4User($data){
    
        // 获取此用户在此渠道所有的点赞记录
        $ProductIDsLiked = D('UserCollection')->field('productid')
            ->where(array(
            'ptuserid' => $this->user_id,
            'productid' => array(
                'in',
                implode(',', $data['goods'])
            )
            ))
            ->select();
        foreach ($ProductIDsLiked as $key => $val) { // select取出来的数据是一个二维数组 array(array('product'=>77)),转为以为数组用于判断
            $ProductIDsLikedNew[] = $val['productid'];
        }
        
        foreach ($data['goods'] as $key => $val) {
            $productDetail = $this->redisLog->hget('Score::proudctDetail:' . $val . "_" . $this->platform_id);
            $productDetail['iscollect'] = false; // //判断是否收藏
            if (in_array($val, $ProductIDsLikedNew))
                $productDetail['iscollect'] = true;
            $data['product_list_1'][] = $productDetail; // 获取没法商品的信息
        }
        return $data;
        
    }


    /**
     * [getShowListNew 获取首页的商品列表]
     * @ckhero
     * @DateTime 2016-09-02
     * 
     * @param [type] $start
     *            [开始]
     * @param [type] $end
     *            [结束]
     * @param [type] $type
     *            [默认为1首页加载，2为加载更多]
     * @return [type] [description]
     */
    private function getShowList($start = 0, $end = 5, $type = 1)
    {
$time_0 = microtime(true);
        $redisKey = 'Score::PersonScoreZwidPtid:' . $this->zwid. '_' . $this->platform_id;
        
        $productList = D('PlatformProduct')->getProductList(1, 0, 0); // 取该平台的所有数据
$time_1 = microtime(true);
		$this->setScores($productList, $this->zwid, $this->platform_id, $redisKey);
$time_2 = microtime(true);
        //当所有商品的数量小于等于6的时候，首页的“换一组”隐藏
		$this->totalProductsNum = $this->redisLog->zCard($redisKey);
               
		$data['goods'] = $this->redisLog->zget($redisKey, $start, $end, 'desc'); // 得到商品id列表
        /*//if ($type == 2 && ($goodsNum = count($data['goods'])) != $this->hideNextPageButton) { // 在点击“换一组”的情况下
          if ($type == 2 && ($goodsNum = count($data['goods'])) < 7) {
        	$goodsAdd = $this->redisLog->zget('Score::PersonScoreZwidPtid:' . $zwid . '_' . $platformid, 0, $this->numOfProductsShownOnEachPage- $goodsNum - 1, 'desc'); // 得到商品id列表
            $data['goods'] = array_merge($data['goods'], $goodsAdd);
            $data['goodsAdded'] = $this->numOfProductsShownOnEachPage- $goodsNum; // 表示当前页商品已不足，值为补充的条数
        }*/
        
        $data = $this->setCollectionDate4User($data);
$time_3 = microtime(true);
if ($elapseTime > 0.2) {
	dolog('Index/debug', '给优惠券排序超过200ms', '', 'latency: t1=' . round($time_1- $time_0, 3). ', t2=' . round($time_2- $time_1, 3). ', t3=' . round($time_3- $time_2, 3), $this->redisLog);
} 
        
        return $data;
    }

    /**
     * [analysisPeople 获取当天的值；以及当前的一个的值]
     * @ckhero
     * @DateTime 2016-09-09
     * 
     * @param [type] $zwid
     *            [zwid]
     * @param [type] $ProductId
     *            [商品id]
     * @return [type] [currDay为当天的数据，month最近三十天的记录不包括当天的记录]
     */
    private function analysisPeople($zwid, $ProductId)
    {
        $redisKey = 'PeopleDay:' . $zwid . '_' . $ProductId . '_';
        // $data['currDay'] = $this->redisLog->hget($redisKey.date('Y-m-d'));
        $res = M('action_record')->field('detail')
            ->where("name=%d and productId =%d and date='%s' and type ='%s'", array(
            $zwid,
            $ProductId,
            date('Y-m-d'),
            0
        ))
            ->find();
        $data['currDay'] = json_decode($res['detail'], true);
        $monthKey = 'PeopleMonth:' . $zwid . '_' . $ProductId;
        $month = $this->redisLog->hget($monthKey);
        if (empty($month)) {
            // $PeopleDayList = $this->redisLog->keys($redisKey.'*');
            // for($i=1;$i<=30;$i++){
            
            // $PeopleDayList[] = $redisKey.date('Y-m-d',strtotime("-".$i." day"));
            // }
            // foreach($PeopleDayList as $dayKey){
            
            // if($dayKey == $redisKey.date('Y-m-d')) continue; //排除当天的数据
            // $day = array();
            // $day = $this->redisLog->hget($dayKey);
            // if(is_array($day)){
            
            // foreach($day as $key=>$val){
            
            // if($key == 'date' || $key =='productid' || $key == 'zwid') continue;
            // $month[$key] += $val;
            // }
            // }
            // }
            //
            $month = D('action_record')->getMonth($zwid, $ProductId, 0);
            $this->redisLog->hset($monthKey, $month[$ProductId][0]);
            $this->redisLog->expire($monthKey, mktime(23, 59, 59, date('d'), date('m'), date('Y')) - time()); // 设置缓存到当天结束;
        }
        $data['month'] = $month;
        return $data;
    }

    /**
     * [analysisPersonScore 计算商品针对用户个人的得分]
     * @ckhero
     * @DateTime 2016-09-19
     * 
     * @param [type] $PeopleDay
     *            [description]
     * @param [type] $PeopleMonth
     *            [description]
     * @param [type] $InitialScore
     *            [description]
     * @return [type] [description]
     */
    private function analysisPersonScore($PeopleDay, $PeopleMonth, $InitialScore)
    {
        $score = $InitialScore;
        
        // 假定用户短时间内不会重复领取；很大一部分商户也不允许同一用户多此领取同一张优惠券
        // 假定用户当天内不会重复购买；但是可以重复购买, 而且一个月内可能重复购买
        if (intval($PeopleDay['download']) > 0 || intval($PeopleMonth['download']) > 0 || (intval($PeopleDay['buy']) > 0))
            return 0.0001;
            
            // 如果用户从来没有看到过优惠券，那么至少要给此优惠券一个展示的机会。
        if ((empty($PeopleDay) && (empty($PeopleMonth))) || ($PeopleMonth['buy'] + $PeopleMonth['share'] + $PeopleDay['share'] + $PeopleMonth['collect'] + $PeopleDay['collect'] + $PeopleMonth['view'] + $PeopleDay['view'] + $PeopleDay['show'] + $PeopleMonth['show'] == 0))
            return $InitialScore * 8;
            
            // 先计算加分的，再计算减分的，这是为了避免$score成为0后无论如何做左移操作都还是0的情况
        if (intval($PeopleMonth['buy']) > 0)
            $score += intval($PeopleMonth['buy']) * 8; // ( $score << intval($PeopleMonth['buy']));
                                                                                               
        // /分享： 月的增加3次日的增加6次
        $shareNum = intval($PeopleMonth['share']) + intval($PeopleDay['share']);
        if ($shareNum > 0)
            $score += intval($PeopleMonth['share']) * 6 + intval($PeopleDay['share']) * 48; // $score << ( $shareNum << 1 ); //$PeopleMonth不包含$PeopleDay的数据
        if (DEBUG_PRINT_SCORES)
            file_put_contents("debug.log", "P1score=" . $score . ", shareNum=" . $shareNum . "\n", FILE_APPEND);
            
            // 收藏： 月的增加3次日的增加6次
        $collectNum = intval($PeopleMonth['collect']) + intval($PeopleDay['collect']);
        if ($collectNum > 0)
            $score += intval($PeopleMonth['collect']) * 6 + intval($PeopleDay['collect']) * 48; // $score << ( $collectNum << 1); //$PeopleMonth不包含$PeopleDay的数据
        if (DEBUG_PRINT_SCORES)
            file_put_contents("debug.log", "P2score=" . $score . ", collectNum=" . $collectNum . "\n", FILE_APPEND);
            
            // 每看一次详情页面，月的增加一次，日的增加二次可被展示的次数
        $viewNum = intval($PeopleMonth['view']) + intval($PeopleDay['view']);
        if ($viewNum > 0)
            $score += intval($PeopleMonth['view']) * 2 + intval($PeopleDay['view']) * 16; // $score << ( $viewNum << 1 ); //$PeopleMonth不包含$PeopleDay的数据
        if (DEBUG_PRINT_SCORES)
            file_put_contents("debug.log", "P3score=" . $score . ", viewNum=" . $viewNum . "\n", FILE_APPEND);
        
        if (($PeopleDay['share'] + $PeopleDay['collect']) < 1) {
            
            // 频次控制，如果用户没有查看优惠券详情的话，一天最多针对一个用户展示3次, 一个月最多5次（假如用户没有做任何操作如下载、购买等））
            $dayShowMinusViewNum = intval($PeopleDay['show']) - intval($PeopleDay['view']);
            if ($dayShowMinusViewNum >= 0)
                $score -= $dayShowMinusViewNum * 8; // $score >> ( (intval($PeopleDay['show'])) << 1); //假设初始值为32 一天最多4次
            
            $monthShowMinusViewNum = intval($PeopleMonth['show']) - intval($PeopleMonth['view']);
            if ($monthShowMinusViewNum >= 0)
                $score -= $monthShowMinusViewNum * 2; // $score >> (( intval($PeopleMonth['show']))<<1) ; //假设初始值为32.一个月最多16次
            if (DEBUG_PRINT_SCORES)
                file_put_contents("debug.log", "P5score=" . $score . ", show-view(d)=" . (intval($PeopleDay['show']) - intval($PeopleDay['view'])) . ", show-view(m)=" . (intval($PeopleMonth['show']) - intval($PeopleMonth['view'])) . "\n", FILE_APPEND);
        }
        
        if ($score <= 0)
            $score = ($shareNum + $collectNum + $viewNum + 2 * $PeopleMonth['buy']) + 0.01; // 为了防止很多商品的最终得分都是0， 所以在这里用了一个较小的值。
        if (DEBUG_PRINT_SCORES)
            file_put_contents("debug.log", "P4score=" . $score . ", show-view(d)=" . (intval($PeopleDay['show']) - intval($PeopleDay['view'])) . ", show-view(m)=" . (intval($PeopleMonth['show']) - intval($PeopleMonth['view'])) . "\n", FILE_APPEND);
        
        return $score;
    }

    

    /**
     * [analysisPatform 计算平台一个月的总计]
     * @ckhero
     * @DateTime 2016-09-20
     * 
     * @return [type] [description]
     */
    private function analysisPlatform($productid)
    {        
        if (empty($month)) {
            $month = array();
           
            $month = D('action_record')->getMonth($this->platform_id, $productid, 1);
            $this->redisLog->hset("Platform_30_Day:" . $this->platform_id. "_" . $productid, $month[$productid][1]);
            $this->redisLog->expire("Platform_30_Day:" . $this->platform_id. "_" . $productid, 24 * 3600); // 过期时间为24小时
        }
        
        return $month;
    }

    private function calculateActiveUserNum(){
        $this->redisLog->databaseSelect();
        $redisKeyAverageDAU =  'avergaeDAUinpast7days' . '_' . date('Ymd'); 
        if ($this->redisLog->existsRedis($redisKeyAverageDAU)) {
            return $this->redisLog->get($redisKeyAverageDAU);
        }
        $res = M('dau')->where('ptid=%d', $this->platform_id)
        ->limit(7)
        ->order('activedate desc')
        ->select();
        
        $res = array_column($res, 'dailyactiveuser');
        
        if (count($res) > 0) {
            $averageDAU = array_sum($res) / count($res);
        }
        if ($averageDAU < 1000) $averageDAU = 1000;
        $this->redisLog->set($redisKeyAverageDAU, $averageDAU, 24 * 60 * 60);
         
    }
    
    /**
     * [analysisPatformScore 计算商品在平台的得分]
     * @ckhero
     * @DateTime 2016-09-21
     * 
     * @param [type] $platformMonth
     *            [description]
     * @param [type] $InitialScore
     *            [description]
     * @return [type] [description]
     */
    private function analysisPlatformScore($platformMonth, $InitialScore)
    {
        // 因为目前不能分享产品详情页面，所以不计算分享的得分， 2016-10-26
        // 每个商品在平台上的展示量小于一定值（大约相当于3天的PV）时，强制置顶。
        if (DEBUG_PRINT_SCORES)
            file_put_contents("debug.log", "average DAU=" . $this->calculateActiveUserNum() . "\n", FILE_APPEND);
        if (empty($platformMonth) || empty($platformMonth['show']) || intval($platformMonth['show']) < 2 * $this->calculateActiveUserNum())  
            return $InitialScore * 64; //2017-02-12， 目前出现不少优惠券展示数量小于1000的。需要给每个优惠券被展示的机会
            // 详情页访问次数和展示次数的比例:（view/show）
        $viewRatio = intval($platformMonth['view']) / intval($platformMonth['show']); // 小数点后取四位
        $viewscore = $viewRatio / 0.01; //在正式环境里面，约有0.3%至3%左右的点击率
        // (购买|下载)次数 和详情页访问次数的的比例；((buy|download)/view);
        if (DEBUG_PRINT_SCORES)
            file_put_contents("debug.log", "viewscore=" . $viewscore . ", viewRatio=" . $viewRatio . "\n", FILE_APPEND);
        
        $buyNum = intval($platformMonth['buy']) == 0 ? intval($platformMonth['download']) : intval($platformMonth['buy']);
        $buyRatio = intval($platformMonth['view']) == 0 ? 0 : $buyNum / intval($platformMonth['show']); // 默认(购买|下载)操作都是在详情页进行的
        $buyscore = $buyRatio / 0.0004;
        if (DEBUG_PRINT_SCORES)
            file_put_contents("debug.log", "buyscore=" . $buyscore . ", buyRatio=" . $buyRatio . "\n", FILE_APPEND);
            
            // 收藏和展示次数的比例；(collect/show)
        $collectRatio = intval($platformMonth['collect']) / intval($platformMonth['show']); // 小数点后取四位
        $collectscore = $collectRatio / 0.01; //在正式环境里面，约有0.3%至1.8%左右的收藏率
        if (DEBUG_PRINT_SCORES)
            file_put_contents("debug.log", "collectscore=" . $collectscore . ", collectRatio=" . $collectRatio . "\n", FILE_APPEND);
        
        $score = $viewscore + $buyscore + $collectscore;
        // 这个得分还需要考虑展示的平均位置。位置越低，则得分越高
        if ($score <= 0)
            $score = 1 / (1 + $platformMonth['show']); // 为了防止很多商品的最终得分都是0， 所以在这里用了一个较小的值。
        
        return $score; // * $InitialScore;
    }
    
    // //以下代码供单元测试用////
    
    // 仅供测试使用 //http://uat.zwmedia.com.cn/wxzspfse/index.php/Home/Index/UnitTestAnalysisPersonScore?platcode=LOVECLUB
    function unitTestAnalysisPersonScore()
    {
        file_put_contents("debug.log", "\n\nNew Round of Unit Test:", FILE_APPEND);
        
        // test case 0 用户从来没有访问过乐天邦
        $PeopleDay = Json_decode('{"zwid":"1393","productid":"182","date":"2016-10-25","view":"0","collect":"0","show":"0","position":"0"}', true);
        $PeopleMonth = null;
        if (DEBUG_PRINT_SCORES)
            file_put_contents("debug.log", "\nInput Parameters: \nPeopleDay=" . json_encode($PeopleDay) . "\nPeopleMonth=" . json_encode($PeopleMonth) . "\n", FILE_APPEND);
        $final_score = $this->analysisPersonScore($PeopleDay, $PeopleMonth, INITIAL_SCORE_PER_CATEGORY);
        if (DEBUG_PRINT_SCORES)
            file_put_contents("debug.log", "Personal score=" . $final_score . "\n", FILE_APPEND);
            
            // test case 1 用户从来没有访问过乐天邦
        $PeopleDay = null;
        $PeopleMonth = null;
        if (DEBUG_PRINT_SCORES)
            file_put_contents("debug.log", "\nInput Parameters: \nPeopleDay=" . json_encode($PeopleDay) . "\nPeopleMonth=" . json_encode($PeopleMonth) . "\n", FILE_APPEND);
        $final_score = $this->analysisPersonScore($PeopleDay, $PeopleMonth, INITIAL_SCORE_PER_CATEGORY);
        if (DEBUG_PRINT_SCORES)
            file_put_contents("debug.log", "Personal score=" . $final_score . "\n", FILE_APPEND);
            
            // test cases 2-14 are really data colected from product environment
            // test case 2 用户当天访问过乐天邦，之前没有访问过乐天邦
            // personal day
        $PeopleDay = array(
            "show" => 7,
            "position" => 29,
            "view" => 0,
            "collect" => 0,
            "download" => 0,
            "buy" => 0,
            "share" => 0
        );
        $PeopleMonth = array(
            "show" => 4,
            "position" => 13,
            "view" => 0,
            "collect" => 0,
            "download" => 0,
            "buy" => 0,
            "share" => 0
        );
        // $PeopleDay = Json_decode('{"zwid":"1393","productid":"182","date":"2016-10-25","show":7,"position":29,"view":0,"collect":0,"download":0,"buy":0,"share":0}',true);
        // $PeopleMonth = array("show" => 2,"position" => 21,"view" => 0,"collect" => 1,"download" => 0,"buy" => 0,"share" => 0);
        if (DEBUG_PRINT_SCORES)
            file_put_contents("debug.log", "\nInput Parameters: \nPeopleDay=" . json_encode($PeopleDay) . "\nPeopleMonth=" . json_encode($PeopleMonth) . "\n", FILE_APPEND);
        $final_score = $this->analysisPersonScore($PeopleDay, $PeopleMonth, INITIAL_SCORE_PER_CATEGORY);
        if (DEBUG_PRINT_SCORES)
            file_put_contents("debug.log", "Personal score=" . $final_score . "\n", FILE_APPEND);
            
            // test case 3 用户当天访问乐天邦一次，在一天前也访问过乐天邦
            // personal day={"show":8,"position":42,"view":0,"collect":0,"download":0,"buy":0,"share":0},
            // month={"show":88,"position":367,"view":0,"collect":0,"download":0,"buy":0,"share":0}
        
        $PeopleDay = Json_decode('{"zwid":"1393","productid":"181","date":"2016-10-25","show":8,"position":42,"view":0,"collect":0,"download":0,"buy":0,"share":0}', true);
        $PeopleMonth = array(
            "show" => 88,
            "position" => 367,
            "view" => 0,
            "collect" => 0,
            "download" => 0,
            "buy" => 0,
            "share" => 0
        );
        if (DEBUG_PRINT_SCORES)
            file_put_contents("debug.log", "\nInput Parameters: \nPeopleDay=" . json_encode($PeopleDay) . "\nPeopleMonth=" . json_encode($PeopleMonth) . "\n", FILE_APPEND);
        $final_score = $this->analysisPersonScore($PeopleDay, $PeopleMonth, INITIAL_SCORE_PER_CATEGORY);
        if (DEBUG_PRINT_SCORES)
            file_put_contents("debug.log", "Personal score=" . $final_score . "\n", FILE_APPEND);
            
            // test case 4 用户当天访问乐天邦一次，在一天前也访问过乐天邦
            // personal day={"show":9,"position":10,"view":0,"collect":0,"download":0,"buy":0,"share":0},
            // month={"show":85,"position":133,"view":13,"collect":0,"download":0,"buy":0,"share":0}
        $PeopleDay = Json_decode('{"zwid":"1393","productid":"181","date":"2016-10-25","show":9,"position":10,"view":0,"collect":0,"download":0,"buy":0,"share":0}', true);
        $PeopleMonth = array(
            "show" => 85,
            "position" => 133,
            "view" => 13,
            "collect" => 0,
            "download" => 0,
            "buy" => 0,
            "share" => 0
        );
        if (DEBUG_PRINT_SCORES)
            file_put_contents("debug.log", "\nInput Parameters: \nPeopleDay=" . json_encode($PeopleDay) . "\nPeopleMonth=" . json_encode($PeopleMonth) . "\n", FILE_APPEND);
        $final_score = $this->analysisPersonScore($PeopleDay, $PeopleMonth, INITIAL_SCORE_PER_CATEGORY);
        if (DEBUG_PRINT_SCORES)
            file_put_contents("debug.log", "Personal score=" . $final_score . "\n", FILE_APPEND);
            
            // test case 5 用户当天访问乐天邦3次, 购买一次
            // personal day={"show":5,"position":10,"view":0,"collect":0,"download":0,"buy":0,"share":0},
            // month={"show":36,"position":93,"view":0,"collect":0,"download":0,"buy":0,"share":0}
        
        $PeopleDay = Json_decode('{"zwid":"1393","productid":"182","date":"2016-10-25","show":5,"position":10,"view":0,"collect":0,"download":0,"buy":0,"share":0}', true);
        $PeopleMonth = array(
            "show" => 36,
            "position" => 93,
            "view" => 0,
            "collect" => 0,
            "download" => 0,
            "buy" => 0,
            "share" => 0
        );
        if (DEBUG_PRINT_SCORES)
            file_put_contents("debug.log", "\nInput Parameters: \nPeopleDay=" . json_encode($PeopleDay) . "\nPeopleMonth=" . json_encode($PeopleMonth) . "\n", FILE_APPEND);
        $final_score = $this->analysisPersonScore($PeopleDay, $PeopleMonth, INITIAL_SCORE_PER_CATEGORY);
        if (DEBUG_PRINT_SCORES)
            file_put_contents("debug.log", "Personal score=" . $final_score . "\n", FILE_APPEND);
            
            // test case 6 用户当天访问乐天邦3次， 下载一次
            // personal day={"show":11,"position":32,"view":1,"collect":0,"download":0,"buy":0,"share":0},
            // month={"show":100,"position":329,"view":0,"collect":1,"download":0,"buy":0,"share":0}
        
        $PeopleDay = Json_decode('{"zwid":"1393","productid":"182","date":"2016-10-25","show":11,"position":32,"view":1,"collect":0,"download":0,"buy":0,"share":0}', true);
        $PeopleMonth = array(
            "show" => 100,
            "position" => 329,
            "view" => 0,
            "collect" => 1,
            "download" => 0,
            "buy" => 0,
            "share" => 0
        );
        if (DEBUG_PRINT_SCORES)
            file_put_contents("debug.log", "\nInput Parameters: \nPeopleDay=" . json_encode($PeopleDay) . "\nPeopleMonth=" . json_encode($PeopleMonth) . "\n", FILE_APPEND);
        $final_score = $this->analysisPersonScore($PeopleDay, $PeopleMonth, INITIAL_SCORE_PER_CATEGORY);
        if (DEBUG_PRINT_SCORES)
            file_put_contents("debug.log", "Personal score=" . $final_score . "\n", FILE_APPEND);
            
            // test case 7 用户当天访问乐天邦4次
            // personal day={"show":1,"position":3},
            // month={"show":18,"position":75,"view":2,"collect":0,"download":0,"buy":0,"share":0}
        
        $PeopleDay = Json_decode('{"zwid":"1393","productid":"182","date":"2016-10-25","view":"0","collect":"0","show":"1",
            "position":"3","download":"0","buy":"0","share":"0"}', true);
        $PeopleMonth = array(
            "show" => 18,
            "position" => 75,
            "view" => 2,
            "collect" => 0,
            "download" => 0,
            "buy" => 0,
            "share" => 0
        );
        if (DEBUG_PRINT_SCORES)
            file_put_contents("debug.log", "\nInput Parameters: \nPeopleDay=" . json_encode($PeopleDay) . "\nPeopleMonth=" . json_encode($PeopleMonth) . "\n", FILE_APPEND);
        $final_score = $this->analysisPersonScore($PeopleDay, $PeopleMonth, INITIAL_SCORE_PER_CATEGORY);
        if (DEBUG_PRINT_SCORES)
            file_put_contents("debug.log", "Personal score=" . $final_score . "\n", FILE_APPEND);
            
            // test case 8 用户当天没有访问过乐天邦， 当月访问过3次
            // personal day={"show":9,"position":19,"view":0,"collect":0,"download":0,"buy":0,"share":0},
            // month={"show":94,"position":266,"view":4,"collect":0,"download":0,"buy":0,"share":0}
        
        $PeopleDay = Json_decode('{"zwid":"1393","productid":"182","date":"2016-10-25","show":9,"position":19,"view":0,"collect":0,"download":0,"buy":0,"share":0}', true);
        $PeopleMonth = array(
            "show" => 94,
            "position" => 266,
            "view" => 4,
            "collect" => 0,
            "download" => 0,
            "buy" => 0,
            "share" => 0
        );
        if (DEBUG_PRINT_SCORES)
            file_put_contents("debug.log", "\nInput Parameters: \nPeopleDay=" . json_encode($PeopleDay) . "\nPeopleMonth=" . json_encode($PeopleMonth) . "\n", FILE_APPEND);
        $final_score = $this->analysisPersonScore($PeopleDay, $PeopleMonth, INITIAL_SCORE_PER_CATEGORY);
        if (DEBUG_PRINT_SCORES)
            file_put_contents("debug.log", "Personal score=" . $final_score . "\n", FILE_APPEND);
            
            // test case 9 用户当天没有访问过乐天邦， 当月访问过6次
            // personal day={"show":4,"position":21,"view":0,"collect":0,"download":0,"buy":0,"share":0},
            // month={"show":33,"position":153,"view":0,"collect":1,"download":0,"buy":0,"share":0}
        
        $PeopleDay = Json_decode('{"zwid":"1393","productid":"182","date":"2016-10-25","show":4,"position":21,"view":0,"collect":0,"download":0,"buy":0,"share":0}', true);
        $PeopleMonth = array(
            "show" => 33,
            "position" => 153,
            "view" => 0,
            "collect" => 1,
            "download" => 0,
            "buy" => 0,
            "share" => 0
        );
        if (DEBUG_PRINT_SCORES)
            file_put_contents("debug.log", "\nInput Parameters: \nPeopleDay=" . json_encode($PeopleDay) . "\nPeopleMonth=" . json_encode($PeopleMonth) . "\n", FILE_APPEND);
        $final_score = $this->analysisPersonScore($PeopleDay, $PeopleMonth, INITIAL_SCORE_PER_CATEGORY);
        if (DEBUG_PRINT_SCORES)
            file_put_contents("debug.log", "Personal score=" . $final_score . "\n", FILE_APPEND);
            
            // test case 10 用户当天没有访问过乐天邦， 当月访问过7次, 下载一次
            // personal day={"show":9,"position":48,"view":0,"collect":0,"download":0,"buy":0,"share":0},
            // month={"show":73,"position":399,"view":1,"collect":0,"download":0,"buy":0,"share":0}
        
        $PeopleDay = Json_decode('{"zwid":"1393","productid":"182","date":"2016-10-25","show":9,"position":48,"view":0,"collect":0,"download":0,"buy":0,"share":0}', true);
        $PeopleMonth = array(
            "show" => 73,
            "position" => 399,
            "view" => 1,
            "collect" => 0,
            "download" => 0,
            "buy" => 0,
            "share" => 0
        );
        if (DEBUG_PRINT_SCORES)
            file_put_contents("debug.log", "\nInput Parameters: \nPeopleDay=" . json_encode($PeopleDay) . "\nPeopleMonth=" . json_encode($PeopleMonth) . "\n", FILE_APPEND);
        $final_score = $this->analysisPersonScore($PeopleDay, $PeopleMonth, INITIAL_SCORE_PER_CATEGORY);
        if (DEBUG_PRINT_SCORES)
            file_put_contents("debug.log", "Personal score=" . $final_score . "\n", FILE_APPEND);
            
            // test case 11 用户当天没有访问过乐天邦， 当月访问过12次， 购买一次
            // personal day={"show":1,"position":1}, month={"show":38,"position":172,"view":0,"collect":1,"download":0,"buy":0,"share":0}
        
        $PeopleDay = Json_decode('{"zwid":"1393","productid":"182","date":"2016-10-25","show":1,"position":1,"view":0,"collect":0,"download":0,"buy":0,"share":0}', true);
        $PeopleMonth = array(
            "show" => 38,
            "position" => 172,
            "view" => 0,
            "collect" => 1,
            "download" => 0,
            "buy" => 0,
            "share" => 0
        );
        if (DEBUG_PRINT_SCORES)
            file_put_contents("debug.log", "\nInput Parameters: \nPeopleDay=" . json_encode($PeopleDay) . "\nPeopleMonth=" . json_encode($PeopleMonth) . "\n", FILE_APPEND);
        $final_score = $this->analysisPersonScore($PeopleDay, $PeopleMonth, INITIAL_SCORE_PER_CATEGORY);
        if (DEBUG_PRINT_SCORES)
            file_put_contents("debug.log", "Personal score=" . $final_score . "\n", FILE_APPEND);
            
            // test case 12 用户当天没有访问过乐天邦， 当月访问过7次
            // personal day={"show":2,"position":7,"view":1,"collect":0,"download":0,"buy":0,"share":0},
            // month={"show":15,"position":64,"view":0,"collect":0,"download":0,"buy":0,"share":0}
        
        $PeopleDay = Json_decode('{"zwid":"1393","productid":"182","date":"2016-10-25","show":2,"position":7,"view":1,"collect":0,"download":0,"buy":0,"share":0}', true);
        $PeopleMonth = array(
            "show" => 15,
            "position" => 64,
            "view" => 0,
            "collect" => 0,
            "download" => 0,
            "buy" => 0,
            "share" => 0
        );
        if (DEBUG_PRINT_SCORES)
            file_put_contents("debug.log", "\nInput Parameters: \nPeopleDay=" . json_encode($PeopleDay) . "\nPeopleMonth=" . json_encode($PeopleMonth) . "\n", FILE_APPEND);
        $final_score = $this->analysisPersonScore($PeopleDay, $PeopleMonth, INITIAL_SCORE_PER_CATEGORY);
        if (DEBUG_PRINT_SCORES)
            file_put_contents("debug.log", "Personal score=" . $final_score . "\n", FILE_APPEND);
            
            // test case 13 用户当天没有访问过乐天邦， 当月访问过7次
            // personal day={"show":11,"position":50,"view":0,"collect":0,"download":0,"buy":0,"share":0},
            // month={"show":103,"position":394,"view":0,"collect":0,"download":0,"buy":0,"share":0}
        
        $PeopleDay = Json_decode('{"zwid":"1393","productid":"182","date":"2016-10-25","show":11,"position":50,"view":0,"collect":0,"download":0,"buy":0,"share":0}', true);
        $PeopleMonth = array(
            "show" => 103,
            "position" => 394,
            "view" => 0,
            "collect" => 0,
            "download" => 0,
            "buy" => 0,
            "share" => 0
        );
        if (DEBUG_PRINT_SCORES)
            file_put_contents("debug.log", "\nInput Parameters: \nPeopleDay=" . json_encode($PeopleDay) . "\nPeopleMonth=" . json_encode($PeopleMonth) . "\n", FILE_APPEND);
        $final_score = $this->analysisPersonScore($PeopleDay, $PeopleMonth, INITIAL_SCORE_PER_CATEGORY);
        if (DEBUG_PRINT_SCORES)
            file_put_contents("debug.log", "Personal score=" . $final_score . "\n", FILE_APPEND);
            
            // test case 14 用户当天访问过一次乐天邦， 当月访问过12次
            // personal day={"show":12,"position":53,"view":0,"collect":0,"download":0,"buy":0,"share":0},
            // month={"show":60,"position":228,"view":8,"collect":0,"download":0,"buy":0,"share":0}
        
        $PeopleDay = Json_decode('{"zwid":"1393","productid":"182","date":"2016-10-25","show":12,"position":53,"view":0,"collect":0,"download":0,"buy":0,"share":0}', true);
        $PeopleMonth = array(
            "show" => 60,
            "position" => 228,
            "view" => 8,
            "collect" => 0,
            "download" => 0,
            "buy" => 0,
            "share" => 0
        );
        if (DEBUG_PRINT_SCORES)
            file_put_contents("debug.log", "\nInput Parameters: \nPeopleDay=" . json_encode($PeopleDay) . "\nPeopleMonth=" . json_encode($PeopleMonth) . "\n", FILE_APPEND);
        $final_score = $this->analysisPersonScore($PeopleDay, $PeopleMonth, INITIAL_SCORE_PER_CATEGORY);
        if (DEBUG_PRINT_SCORES)
            file_put_contents("debug.log", "Personal score=" . $final_score . "\n", FILE_APPEND);
            
            // /////////////////////////////////////////////////////////////////////////////////////////////////////////
            // test case 20 用户当天访问过乐天邦，之前没有访问过乐天邦
        $PeopleDay = Json_decode('{"zwid":"1393","productid":"182","date":"2016-10-25","view":"1","collect":"1","show":"1","position":"2"}', true);
        $PeopleMonth = null;
        if (DEBUG_PRINT_SCORES)
            file_put_contents("debug.log", "\nInput Parameters: \nPeopleDay=" . json_encode($PeopleDay) . "\nPeopleMonth=" . json_encode($PeopleMonth) . "\n", FILE_APPEND);
        $final_score = $this->analysisPersonScore($PeopleDay, $PeopleMonth, INITIAL_SCORE_PER_CATEGORY);
        if (DEBUG_PRINT_SCORES)
            file_put_contents("debug.log", "Personal score=" . $final_score . "\n", FILE_APPEND);
            
            // test case 21 用户当天访问乐天邦一次，在一天前也访问过乐天邦
        $PeopleDay = Json_decode('{"zwid":"1393","productid":"181","date":"2016-10-25","show":"1","position":"3"}', true);
        $PeopleMonth = array(
            "show" => 2,
            "position" => 21,
            "view" => 0,
            "collect" => 0,
            "download" => 0,
            "buy" => 0,
            "share" => 0
        );
        if (DEBUG_PRINT_SCORES)
            file_put_contents("debug.log", "\nInput Parameters: \nPeopleDay=" . json_encode($PeopleDay) . "\nPeopleMonth=" . json_encode($PeopleMonth) . "\n", FILE_APPEND);
        $final_score = $this->analysisPersonScore($PeopleDay, $PeopleMonth, INITIAL_SCORE_PER_CATEGORY);
        if (DEBUG_PRINT_SCORES)
            file_put_contents("debug.log", "Personal score=" . $final_score . "\n", FILE_APPEND);
            
            // test case 22 用户当天访问乐天邦一次，在一天前也访问过乐天邦
        $PeopleDay = Json_decode('{"zwid":"1393","productid":"181","date":"2016-10-25","show":"1","position":"3"}', true);
        $PeopleMonth = array(
            "show" => 2,
            "position" => 21,
            "view" => 0,
            "collect" => 1,
            "download" => 0,
            "buy" => 0,
            "share" => 0
        );
        if (DEBUG_PRINT_SCORES)
            file_put_contents("debug.log", "\nInput Parameters: \nPeopleDay=" . json_encode($PeopleDay) . "\nPeopleMonth=" . json_encode($PeopleMonth) . "\n", FILE_APPEND);
        $final_score = $this->analysisPersonScore($PeopleDay, $PeopleMonth, INITIAL_SCORE_PER_CATEGORY);
        if (DEBUG_PRINT_SCORES)
            file_put_contents("debug.log", "Personal score=" . $final_score . "\n", FILE_APPEND);
            
            // test case 23 用户当天访问乐天邦3次, 购买一次
        $PeopleDay = Json_decode('{"zwid":"1393","productid":"182","date":"2016-10-25","view":"2","collect":"1","show":"3","position":"6","download":"0","buy":"0","share":"0"}', true);
        $PeopleMonth = array(
            "show" => 2,
            "position" => 54,
            "view" => 1,
            "collect" => 0,
            "download" => 0,
            "buy" => 1,
            "share" => 0
        );
        if (DEBUG_PRINT_SCORES)
            file_put_contents("debug.log", "\nInput Parameters: \nPeopleDay=" . json_encode($PeopleDay) . "\nPeopleMonth=" . json_encode($PeopleMonth) . "\n", FILE_APPEND);
        $final_score = $this->analysisPersonScore($PeopleDay, $PeopleMonth, INITIAL_SCORE_PER_CATEGORY);
        if (DEBUG_PRINT_SCORES)
            file_put_contents("debug.log", "Personal score=" . $final_score . "\n", FILE_APPEND);
            
            // test case 24 用户当天访问乐天邦3次， 下载一次
        $PeopleDay = Json_decode('{"zwid":"1393","productid":"182","date":"2016-10-25","view":"2","collect":"1","show":"3","position":"6","download":"0","buy":"1","share":"0"}', true);
        $PeopleMonth = array(
            "show" => 2,
            "position" => 54,
            "view" => 1,
            "collect" => 0,
            "download" => 1,
            "buy" => 0,
            "share" => 0
        );
        if (DEBUG_PRINT_SCORES)
            file_put_contents("debug.log", "\nInput Parameters: \nPeopleDay=" . json_encode($PeopleDay) . "\nPeopleMonth=" . json_encode($PeopleMonth) . "\n", FILE_APPEND);
        $final_score = $this->analysisPersonScore($PeopleDay, $PeopleMonth, INITIAL_SCORE_PER_CATEGORY);
        if (DEBUG_PRINT_SCORES)
            file_put_contents("debug.log", "Personal score=" . $final_score . "\n", FILE_APPEND);
            
            // test case 25 用户当天访问乐天邦4次
        $PeopleDay = Json_decode('{"zwid":"1393","productid":"182","date":"2016-10-25","view":"3","collect":"1","show":"4",
            "position":"10","download":"1","buy":"0","share":"0"}', true);
        $PeopleMonth = array(
            "show" => 0,
            "position" => 50,
            "view" => 0,
            "collect" => 0,
            "download" => 0,
            "buy" => 0,
            "share" => 0
        );
        if (DEBUG_PRINT_SCORES)
            file_put_contents("debug.log", "\nInput Parameters: \nPeopleDay=" . json_encode($PeopleDay) . "\nPeopleMonth=" . json_encode($PeopleMonth) . "\n", FILE_APPEND);
        $final_score = $this->analysisPersonScore($PeopleDay, $PeopleMonth, INITIAL_SCORE_PER_CATEGORY);
        if (DEBUG_PRINT_SCORES)
            file_put_contents("debug.log", "Personal score=" . $final_score . "\n", FILE_APPEND);
            
            // test case 26 用户当天没有访问过乐天邦， 当月访问过3次
        $PeopleDay = null;
        $PeopleMonth = array(
            "show" => 3,
            "position" => 50,
            "view" => 1,
            "collect" => 1,
            "download" => 0,
            "buy" => 0,
            "share" => 0
        );
        if (DEBUG_PRINT_SCORES)
            file_put_contents("debug.log", "\nInput Parameters: \nPeopleDay=" . json_encode($PeopleDay) . "\nPeopleMonth=" . json_encode($PeopleMonth) . "\n", FILE_APPEND);
        $final_score = $this->analysisPersonScore($PeopleDay, $PeopleMonth, INITIAL_SCORE_PER_CATEGORY);
        if (DEBUG_PRINT_SCORES)
            file_put_contents("debug.log", "Personal score=" . $final_score . "\n", FILE_APPEND);
            
            // test case 27 用户当天没有访问过乐天邦， 当月访问过6次
        $PeopleDay = null;
        $PeopleMonth = array(
            "show" => 6,
            "position" => 50,
            "view" => 3,
            "collect" => 1,
            "download" => 0,
            "buy" => 0,
            "share" => 0
        );
        if (DEBUG_PRINT_SCORES)
            file_put_contents("debug.log", "\nInput Parameters: \nPeopleDay=" . json_encode($PeopleDay) . "\nPeopleMonth=" . json_encode($PeopleMonth) . "\n", FILE_APPEND);
        $final_score = $this->analysisPersonScore($PeopleDay, $PeopleMonth, INITIAL_SCORE_PER_CATEGORY);
        if (DEBUG_PRINT_SCORES)
            file_put_contents("debug.log", "Personal score=" . $final_score . "\n", FILE_APPEND);
            
            // test case 28 用户当天没有访问过乐天邦， 当月访问过7次, 下载一次
        $PeopleDay = null;
        $PeopleMonth = array(
            "show" => 7,
            "position" => 50,
            "view" => 5,
            "collect" => 1,
            "download" => 1,
            "buy" => 0,
            "share" => 0
        );
        if (DEBUG_PRINT_SCORES)
            file_put_contents("debug.log", "\nInput Parameters: \nPeopleDay=" . json_encode($PeopleDay) . "\nPeopleMonth=" . json_encode($PeopleMonth) . "\n", FILE_APPEND);
        $final_score = $this->analysisPersonScore($PeopleDay, $PeopleMonth, INITIAL_SCORE_PER_CATEGORY);
        if (DEBUG_PRINT_SCORES)
            file_put_contents("debug.log", "Personal score=" . $final_score . "\n", FILE_APPEND);
            
            // test case 29 用户当天没有访问过乐天邦， 当月访问过12次， 购买一次
        $PeopleDay = null;
        $PeopleMonth = array(
            "show" => 12,
            "position" => 50,
            "view" => 4,
            "collect" => 1,
            "download" => 0,
            "buy" => 1,
            "share" => 0
        );
        if (DEBUG_PRINT_SCORES)
            file_put_contents("debug.log", "\nInput Parameters: \nPeopleDay=" . json_encode($PeopleDay) . "\nPeopleMonth=" . json_encode($PeopleMonth) . "\n", FILE_APPEND);
        $final_score = $this->analysisPersonScore($PeopleDay, $PeopleMonth, INITIAL_SCORE_PER_CATEGORY);
        if (DEBUG_PRINT_SCORES)
            file_put_contents("debug.log", "Personal score=" . $final_score . "\n", FILE_APPEND);
            
            // test case 30 用户当天没有访问过乐天邦， 当月访问过7次
        $PeopleDay = null;
        $PeopleMonth = array(
            "show" => 4,
            "position" => 50,
            "view" => 2,
            "collect" => 1,
            "download" => 0,
            "buy" => 0,
            "share" => 0
        );
        if (DEBUG_PRINT_SCORES)
            file_put_contents("debug.log", "\nInput Parameters: \nPeopleDay=" . json_encode($PeopleDay) . "\nPeopleMonth=" . json_encode($PeopleMonth) . "\n", FILE_APPEND);
        $final_score = $this->analysisPersonScore($PeopleDay, $PeopleMonth, INITIAL_SCORE_PER_CATEGORY);
        if (DEBUG_PRINT_SCORES)
            file_put_contents("debug.log", "Personal score=" . $final_score . "\n", FILE_APPEND);
            
            // test case 31 用户当天没有访问过乐天邦， 当月访问过7次
        $PeopleDay = null;
        $PeopleMonth = array(
            "show" => 6,
            "position" => 50,
            "view" => 2,
            "collect" => 1,
            "download" => 0,
            "buy" => 0,
            "share" => 0
        );
        if (DEBUG_PRINT_SCORES)
            file_put_contents("debug.log", "\nInput Parameters: \nPeopleDay=" . json_encode($PeopleDay) . "\nPeopleMonth=" . json_encode($PeopleMonth) . "\n", FILE_APPEND);
        $final_score = $this->analysisPersonScore($PeopleDay, $PeopleMonth, INITIAL_SCORE_PER_CATEGORY);
        if (DEBUG_PRINT_SCORES)
            file_put_contents("debug.log", "Personal score=" . $final_score . "\n", FILE_APPEND);
            
            // test case 32 用户当天访问过一次乐天邦， 当月访问过12次
        $PeopleDay = Json_decode('{"zwid":"1393","productid":"182","date":"2016-10-25","view":"0","collect":"0","show":"1",
            "position":"10","download":"0","buy":"0","share":"0"}', true);
        $PeopleMonth = array(
            "show" => 12,
            "position" => 50,
            "view" => 4,
            "collect" => 1,
            "download" => 0,
            "buy" => 1,
            "share" => 0
        );
        if (DEBUG_PRINT_SCORES)
            file_put_contents("debug.log", "\nInput Parameters: \nPeopleDay=" . json_encode($PeopleDay) . "\nPeopleMonth=" . json_encode($PeopleMonth) . "\n", FILE_APPEND);
        $final_score = $this->analysisPersonScore($PeopleDay, $PeopleMonth, INITIAL_SCORE_PER_CATEGORY);
        if (DEBUG_PRINT_SCORES)
            file_put_contents("debug.log", "Personal score=" . $final_score . "\n", FILE_APPEND);
    }
    
    // 仅供测试使用 //http://122.225.81.27/wxzspfse/index.php/Home/Index/UnitTestAnalysisPlatformScore?platcode=HBCX
    function unitTestAnalysisPlatformScore()
    {
        file_put_contents("debug.log", "\n\nNew Round of Unit Test of AnalysisPlatformScore:", FILE_APPEND);
        
        // test case 1, 全新的渠道
        $platformMonth = null;
        file_put_contents("debug.log", "\nInput Parameters: \nplatformMonth=" . json_encode($platformMonth) . "\n", FILE_APPEND);
        $final_score = $this->analysisPlatformScore($platformMonth, INITIAL_SCORE_PER_CATEGORY);
        if (DEBUG_PRINT_SCORES)
            file_put_contents("debug.log", "Platform score=" . $final_score . "\n", FILE_APPEND);
            
            // test case 2, 全新的渠道
        $platformMonth = array(
            "show" => 0,
            "position" => 0,
            "view" => 0,
            "collect" => 0,
            "download" => 0,
            "buy" => 0,
            "share" => 0
        );
        file_put_contents("debug.log", "\nInput Parameters: \nplatformMonth=" . json_encode($platformMonth) . "\n", FILE_APPEND);
        $final_score = $this->analysisPlatformScore($platformMonth, INITIAL_SCORE_PER_CATEGORY);
        if (DEBUG_PRINT_SCORES)
            file_put_contents("debug.log", "Platform score=" . $final_score . "\n", FILE_APPEND);
            
            // test case 3, 渠道的PV很少
        $platformMonth = array(
            "show" => 50,
            "position" => 0,
            "view" => 4,
            "collect" => 4,
            "download" => 1,
            "buy" => 3,
            "share" => 0
        );
        file_put_contents("debug.log", "\nInput Parameters: \nplatformMonth=" . json_encode($platformMonth) . "\n", FILE_APPEND);
        $final_score = $this->analysisPlatformScore($platformMonth, INITIAL_SCORE_PER_CATEGORY);
        if (DEBUG_PRINT_SCORES)
            file_put_contents("debug.log", "Platform score=" . $final_score . "\n", FILE_APPEND);
            
            // test case 4, 没有任何下载、购买和点赞
        $platformMonth = array(
            "show" => 2000,
            "position" => 69,
            "view" => 0,
            "collect" => 0,
            "download" => 0,
            "buy" => 0,
            "share" => 0
        );
        file_put_contents("debug.log", "\nInput Parameters: \nplatformMonth=" . json_encode($platformMonth) . "\n", FILE_APPEND);
        $final_score = $this->analysisPlatformScore($platformMonth, INITIAL_SCORE_PER_CATEGORY);
        if (DEBUG_PRINT_SCORES)
            file_put_contents("debug.log", "Platform score=" . $final_score . "\n", FILE_APPEND);
            
            // test case 5, view ratio 0.4%
        $platformMonth = array(
            "show" => 10000,
            "position" => 69,
            "view" => 20,
            "collect" => 0,
            "download" => 0,
            "buy" => 0,
            "share" => 0
        );
        file_put_contents("debug.log", "\nInput Parameters: \nplatformMonth=" . json_encode($platformMonth) . "\n", FILE_APPEND);
        $final_score = $this->analysisPlatformScore($platformMonth, INITIAL_SCORE_PER_CATEGORY);
        if (DEBUG_PRINT_SCORES)
            file_put_contents("debug.log", "Platform score=" . $final_score . "\n", FILE_APPEND);
            
            // test case 6, view ratio 0.4%
        $platformMonth = array(
            "show" => 10000,
            "position" => 69,
            "view" => 40,
            "collect" => 0,
            "download" => 0,
            "buy" => 0,
            "share" => 0
        );
        file_put_contents("debug.log", "\nInput Parameters: \nplatformMonth=" . json_encode($platformMonth) . "\n", FILE_APPEND);
        $final_score = $this->analysisPlatformScore($platformMonth, INITIAL_SCORE_PER_CATEGORY);
        if (DEBUG_PRINT_SCORES)
            file_put_contents("debug.log", "Platform score=" . $final_score . "\n", FILE_APPEND);
            
            // test case 7, view ratio 0.4%
        $platformMonth = array(
            "show" => 10000,
            "position" => 69,
            "view" => 80,
            "collect" => 0,
            "download" => 0,
            "buy" => 0,
            "share" => 0
        );
        file_put_contents("debug.log", "\nInput Parameters: \nplatformMonth=" . json_encode($platformMonth) . "\n", FILE_APPEND);
        $final_score = $this->analysisPlatformScore($platformMonth, INITIAL_SCORE_PER_CATEGORY);
        if (DEBUG_PRINT_SCORES)
            file_put_contents("debug.log", "Platform score=" . $final_score . "\n", FILE_APPEND);
            
            // test case 8, buy ratio 0.04%
        $platformMonth = array(
            "show" => 10000,
            "position" => 69,
            "view" => 2,
            "collect" => 0,
            "download" => 0,
            "buy" => 2,
            "share" => 0
        );
        file_put_contents("debug.log", "\nInput Parameters: \nplatformMonth=" . json_encode($platformMonth) . "\n", FILE_APPEND);
        $final_score = $this->analysisPlatformScore($platformMonth, INITIAL_SCORE_PER_CATEGORY);
        if (DEBUG_PRINT_SCORES)
            file_put_contents("debug.log", "Platform score=" . $final_score . "\n", FILE_APPEND);
            
            // test case 9, buy ratio 0.04%
        $platformMonth = array(
            "show" => 10000,
            "position" => 69,
            "view" => 4,
            "collect" => 0,
            "download" => 4,
            "buy" => 0,
            "share" => 0
        );
        file_put_contents("debug.log", "\nInput Parameters: \nplatformMonth=" . json_encode($platformMonth) . "\n", FILE_APPEND);
        $final_score = $this->analysisPlatformScore($platformMonth, INITIAL_SCORE_PER_CATEGORY);
        if (DEBUG_PRINT_SCORES)
            file_put_contents("debug.log", "Platform score=" . $final_score . "\n", FILE_APPEND);
            
            // test case 10, buy ratio 0.04%
        $platformMonth = array(
            "show" => 10000,
            "position" => 69,
            "view" => 10,
            "collect" => 0,
            "download" => 10,
            "buy" => 0,
            "share" => 0
        );
        file_put_contents("debug.log", "\nInput Parameters: \nplatformMonth=" . json_encode($platformMonth) . "\n", FILE_APPEND);
        $final_score = $this->analysisPlatformScore($platformMonth, INITIAL_SCORE_PER_CATEGORY);
        if (DEBUG_PRINT_SCORES)
            file_put_contents("debug.log", "Platform score=" . $final_score . "\n", FILE_APPEND);
            
            // test case 11, collect ratio 0.08%
        $platformMonth = array(
            "show" => 10000,
            "position" => 69,
            "view" => 4,
            "collect" => 4,
            "download" => 0,
            "buy" => 0,
            "share" => 0
        );
        file_put_contents("debug.log", "\nInput Parameters: \nplatformMonth=" . json_encode($platformMonth) . "\n", FILE_APPEND);
        $final_score = $this->analysisPlatformScore($platformMonth, INITIAL_SCORE_PER_CATEGORY);
        if (DEBUG_PRINT_SCORES)
            file_put_contents("debug.log", "Platform score=" . $final_score . "\n", FILE_APPEND);
            
            // test case 12, collect ratio 0.08%
        $platformMonth = array(
            "show" => 10000,
            "position" => 69,
            "view" => 8,
            "collect" => 8,
            "download" => 15,
            "buy" => 6,
            "share" => 0
        );
        file_put_contents("debug.log", "\nInput Parameters: \nplatformMonth=" . json_encode($platformMonth) . "\n", FILE_APPEND);
        $final_score = $this->analysisPlatformScore($platformMonth, INITIAL_SCORE_PER_CATEGORY);
        if (DEBUG_PRINT_SCORES)
            file_put_contents("debug.log", "Platform score=" . $final_score . "\n", FILE_APPEND);
            
            // test case 13, collect ratio 0.08%
        $platformMonth = array(
            "show" => 10000,
            "position" => 69,
            "view" => 12,
            "collect" => 12,
            "download" => 0,
            "buy" => 0,
            "share" => 0
        );
        file_put_contents("debug.log", "\nInput Parameters: \nplatformMonth=" . json_encode($platformMonth) . "\n", FILE_APPEND);
        $final_score = $this->analysisPlatformScore($platformMonth, INITIAL_SCORE_PER_CATEGORY);
        if (DEBUG_PRINT_SCORES)
            file_put_contents("debug.log", "Platform score=" . $final_score . "\n", FILE_APPEND);
            
            // test case 14, combo
        $platformMonth = array(
            "show" => 10000,
            "position" => 69,
            "view" => 30,
            "collect" => 5,
            "download" => 2,
            "buy" => 0,
            "share" => 1
        );
        file_put_contents("debug.log", "\nInput Parameters: \nplatformMonth=" . json_encode($platformMonth) . "\n", FILE_APPEND);
        $final_score = $this->analysisPlatformScore($platformMonth, INITIAL_SCORE_PER_CATEGORY);
        if (DEBUG_PRINT_SCORES)
            file_put_contents("debug.log", "Platform score=" . $final_score . "\n", FILE_APPEND);
            
            // test case 15, combo
        $platformMonth = array(
            "show" => 10000,
            "position" => 69,
            "view" => 50,
            "collect" => 5,
            "download" => 2,
            "buy" => 0,
            "share" => 1
        );
        file_put_contents("debug.log", "\nInput Parameters: \nplatformMonth=" . json_encode($platformMonth) . "\n", FILE_APPEND);
        $final_score = $this->analysisPlatformScore($platformMonth, INITIAL_SCORE_PER_CATEGORY);
        if (DEBUG_PRINT_SCORES)
            file_put_contents("debug.log", "Platform score=" . $final_score . "\n", FILE_APPEND);
            
            // test case 16, combo
        $platformMonth = array(
            "show" => 10000,
            "position" => 69,
            "view" => 50,
            "collect" => 20,
            "download" => 0,
            "buy" => 0,
            "share" => 0
        );
        file_put_contents("debug.log", "\nInput Parameters: \nplatformMonth=" . json_encode($platformMonth) . "\n", FILE_APPEND);
        $final_score = $this->analysisPlatformScore($platformMonth, INITIAL_SCORE_PER_CATEGORY);
        if (DEBUG_PRINT_SCORES)
            file_put_contents("debug.log", "Platform score=" . $final_score . "\n", FILE_APPEND);
            
            // test case 17, combo
        $platformMonth = array(
            "show" => 10000,
            "position" => 69,
            "view" => 50,
            "collect" => 20,
            "download" => 0,
            "buy" => 10,
            "share" => 80
        );
        file_put_contents("debug.log", "\nInput Parameters: \nplatformMonth=" . json_encode($platformMonth) . "\n", FILE_APPEND);
        $final_score = $this->analysisPlatformScore($platformMonth, INITIAL_SCORE_PER_CATEGORY);
        if (DEBUG_PRINT_SCORES)
            file_put_contents("debug.log", "Platform score=" . $final_score . "\n", FILE_APPEND);
            
            // test case 18, combo
        $platformMonth = array(
            "show" => 10000,
            "position" => 69,
            "view" => 500,
            "collect" => 100,
            "download" => 0,
            "buy" => 50,
            "share" => 80
        );
        file_put_contents("debug.log", "\nInput Parameters: \nplatformMonth=" . json_encode($platformMonth) . "\n", FILE_APPEND);
        $final_score = $this->analysisPlatformScore($platformMonth, INITIAL_SCORE_PER_CATEGORY);
        if (DEBUG_PRINT_SCORES)
            file_put_contents("debug.log", "Platform score=" . $final_score . "\n", FILE_APPEND);
    }

    /**
     * [setPageView 记录商品的pageview]
     * @ckhero
     * @DateTime 2017-03-10
     */
    public function setPageView () 
    {

        if (is_ajax()) {

            $product_id_list = I('param.product_id_list');
            doLog('Index/index/loganalysis', "首页滑动", '', json_encode($product_id_list), $this->redisLog);
        }
    }
    /**
     * [indexTwo 首页肇事]
     * @return [type] [description]
     */
    public function indexTwo()
    {
        $category_info = $this->getCategoryInfo();
        $this->assign('category_info', $category_info);
        $product_info = $this->getProductInfoByOption('', '', '', 1);
        // $product_info = $this->getShowListTWO(0, $this->maxProductsShownOnHomePage - 1, 1, '', 3)['product_list_1'];
        // $product_info = $this->dealProductInfo($product_info); 
        $product_ids = $this->getProductIDList($product_info);
        doLog('Index/index/loganalysis', "首页展示", '', json_encode($product_ids), $this->redisLog);
        $this->assign('product_info', $product_info);
        
        $this->display('indexTwo');
    }


    /**获取分类信息
     * @return [array]
     * create by gk
     */
    private function getCategoryInfo(){
        $category = D('Category');
        $categoryrediskey = "all_product_categories";
        if(!$this->redisLog->existsRedis($categoryrediskey)){
            $categoryinfo = $category->get_category_info();
            $this->redisLog->setArr($categoryrediskey, $categoryinfo);
            $this->redisLog->expire($categoryrediskey, 3600);
        }else{
            $categoryinfo = $this->redisLog->getArr($categoryrediskey);
        }
        return $categoryinfo;
    }

    /**
     * @param  [string] $key 搜索关键词
     * @param  [int] $category_id 分类id
     * @param  [string] $order_by 排序方式（销量，价值，时间，人气-----升降）
     * @param  [int] 当前页数
     * @return [array]根据条件获取的商品信息
     */
    public function getProductInfoByOption($key = "", $category_id = "", $order_by = "", $page= ""){
        $key = I('param.key', '');
        $category_id = I('param.category_id', '');
        $order_by = I('param.order_by', '');
        $page = I('param.page', 1);
        if($page < 1){
            $page = 1;
        }
        $PlatformProductModel = D('PlatformProduct');
        $category = D('Category');
        $usercollection = D('UserCollection');
        $productrediskey = "sortproductinfo" . $this->user_id . intval($this->platform_id) . $order_by;

        if(!$this->redisLog->existsRedis($productrediskey)){
            if(!empty($key)){ /*
                if(!empty($order_by)){
                    //按优惠券名字搜索
                    $product_info_name = $PlatformProductModel->getProductList(1, '', '', $key, $category_id, $order_by, 1);
                    //按照优惠券description搜索的
                    $product_info_content = $PlatformProductModel->getProductList(1, '', '', $key, $category_id, $order_by, 2);

                foreach ($product_info_name as $key => $val) {

                    $product_info_name[$key]['url'] = U('Home/Product/view/id/' . $val['id'] . '/click_num/' . $key . '/platcode/' . $this->platform_code);
                }
                foreach ($product_info_content as $key => $val) {

                    $product_info_content[$key]['url'] = U('Home/Product/view/id/' . $val['id'] . '/click_num/' . $key . '/platcode/' . $this->platform_code);
                }
            }else{
                //按优惠券名字搜索
                $product_info_name = $this->getShowListTWO(0, $this->maxProductsShownOnHomePage - 1, 1, $key, 1)['product_list_1'];
                //按照优惠券description搜索的
                $product_info_content = $this->getShowListTWO(0, $this->maxProductsShownOnHomePage - 1, 1, $key, 2)['product_list_1'];

            }
            $product_info_name = $this->productSorting($product_info_name, $order_by);

            $product_info_content = $this->productSorting($product_info_content, $order_by);

            $product_info = $this->product_array_combine($product_info_name, $product_info_content);
*/

            }else{
                if(!empty($order_by)){
                    
                    $product_info = $PlatformProductModel->getProductList(1, '', '', $key, $category_id, $order_by, 3);
                    foreach ($product_info as $key => $val) {

                        $product_info[$key]['url'] = U('Home/Product/view/id/' . $val['id'] . '/click_num/' . $key . '/platcode/' . $this->platform_code);
                    }
                }else{
                    
                	$product_info = $this->getShowListTWO(0, $this->maxProductsShownOnHomePage - 1, 1, $key, 3)['product_list_1']; 
                }
                
            }

            $this->redisLog->setArr($productrediskey, $product_info);
            $this->redisLog->expire($productrediskey, $this->expire);
        }
        $product_info = $this->redisLog->getArr($productrediskey);
        $product_info = $this->dealProductInfo($product_info);
        $product_info = $this->productSorting($product_info, $order_by);
        $length = 10;
        $offset = ($page-1)*$length;
        $product_info = array_slice($product_info, $offset, $length);

        if(IS_AJAX){
            if(empty($product_info))
                exit(json_encode('2'));
            exit(json_encode($product_info));
        }else{
            if(empty($product_info))
                return 2;
            return $product_info;
        }
    }

    private function product_array_combine($product_info_name, $product_info_content){

        $product_ids = array();
        foreach ($product_info_name as $key => $value) {
            $product_ids[] = $value['id'];
        }
        foreach ($product_info_content as $keys => $val) {
            if( in_array($val['id'], $product_ids) ){
                unset($product_info_content[$keys]);
            }
        }
        return array_merge($product_info_name, $product_info_content);
    }
    /**
     * create by gk
     * [productSorting 优惠券排序]
     * @param  [array] $product_info [优惠券]
     * @param  [string] $order_by [优惠券排序方式]
     * @return [array]               [排好序的优惠券]
     */
    private function productSorting($product_info, $order_by){

        $PlatformProductModel = D('PlatformProduct');
        if(empty($product_info)){
            return null;
        }
        //$product_info = $this->dealProductInfo($product_info, $order_by);
        if(!empty($order_by)){
            switch($order_by){
                case 'popularityinc':
                    sort($product_info);
                    $product_info = $PlatformProductModel->productInfoOrderByPopularInc($product_info);
                    break;
                case 'popularitydesc':
                    sort($product_info);
                    $product_info = $PlatformProductModel->productInfoOrderByPopularDesc($product_info);
                    break;
                default:
                    #code
                    break;
            }

        }
        return $product_info;

    }
    /**
     * [getAllPointFromRedis 获取redis里的点赞数]
     * @return [type] [array]
     */
    private function getAllPointFromRedis(){

        $usercollection = D('UserCollection');
        $product_point_key = "all_product_point";
        if(!$this->redisLog->existsRedis($product_point_key)){
            $productAllPointSqlResult= $usercollection->getProductPointByProduct();
            foreach ($productAllPointSqlResult as $key => $value) {
                $productallpoint[$value['productid']] = $value['point'];
            }
            $this->redisLog->setArr($product_point_key, $productallpoint);
            $this->redisLog->expire($product_point_key, 3600);
            
        }
        return $productallpoint = $this->redisLog->getArr($product_point_key);
    }

    /**
     * [getAllSaleQuantity 获取所有的销售量]
     * @return [array] [description]
     */
    private function getAllSaleQuantity(){
        $platformproduct = D('platformProduct');
        $product_sale_key = "all_product_sale";
        if(!$this->redisLog->existsRedis($product_sale_key)){
            $productAllBuySqlResult= $platformproduct->getProductAllSaleQuantity();
            foreach ($productAllBuySqlResult as $key => $val) {
                $productallbuy[$val['id']] = $val['saleallquantity'];
            }
            $this->redisLog->setArr($product_sale_key, $productallbuy);
            $this->redisLog->expire($product_sale_key, 3600);
            
        }
        return $productallbuy = $this->redisLog->getArr($product_sale_key);

    }

    private function getUserPlatformIsCollectInfo($product_ids){
        $product_is_collect_key = "user_product_is_collect" . $this->user_id . intval($this->platform_id);
        $platformproduct = D('platformProduct');
        if(!$this->redisLog->existsRedis($product_is_collect_key)){
        	$allids = $platformproduct->getproductcollectByUserProduct($this->platform_id);

            $usercollection = D('UserCollection');
            $ProductIDsLikedDSqlResult= $usercollection->getproductPointByUserProduct($this->user_id, intval($this->platform_id), $product_ids);
            
            foreach ($ProductIDsLikedDSqlResult as $key => $val) {
                $ProductIDsLiked[] = $val['productid']; 
            }
            
            $allidscollection = array();
            foreach ($allids as $key => $val) {
                if(in_array($val['id'], $ProductIDsLiked)){
                    $allidscollection[$val['id']] = 1;
                }else{
                    $allidscollection[$val['id']] = 0;
                }
            }
            $this->redisLog->setArr($product_is_collect_key, $allidscollection);
            $this->redisLog->expire($product_is_collect_key, $this->expire); //5 mins
            
        }
        return $this->redisLog->getArr($product_is_collect_key);

    }
    
    private function getProductIDList($product_info){
    	
    	$product_ids = array();
    	foreach ($product_info as $pro_key => $val) {
    		$product_ids[] = $val['id'];
    	}
    	return $product_ids;  	
    }
    
    /**
     * [dealProductInfo 处理优惠券的一些显示参数]
     * @param  [array] $product_info [优惠券信息]
     * @param  [array] $productallpointnew [优惠券所有点赞信息]
     * @param  [array] $ProductIDsLikedNew [优惠券该用户点赞信息]
     * @param  [string] $order_by [排序方式]
     * @return [array]               [优惠券信息]
     */
    private function dealProductInfo($product_info, $product_ids = '', $order_by = '')
    {
        $PlatformProductModel = D('PlatformProduct');
        $category = D('Category');
        $usercollection = D('UserCollection');

        if(empty($product_info)) return null;
        $product_ids = $this->getProductIDList($product_info);
        

        $ProductIDsLikedDSqlResult = array();
        $ProductIDsLiked = array();
        $productAllPointSqlResult = array();
        $productallpoint = array();
        // 获取此用户在此渠道所有的点赞记录
        if(!empty($product_ids)){
            $ProductIDsLiked = $this->getUserPlatformIsCollectInfo($product_ids);
            $productallpoint = $this->getAllPointFromRedis();
            $productallbuy = $this->getAllSaleQuantity();
        }


        foreach ($product_info as $keys => $value) {
            if(!empty($productallpoint)){
                $product_info[$keys]['point'] = 0;
                if($productallpoint[$value['id']] > 0){
                    $product_info[$keys]['point'] = $productallpoint[$value['id']];
                }
            }

            if(!empty($value)){
                $singlecategoryrediskey = "categoryname".$value['id'];
                if(!$this->redisLog->existsRedis($singlecategoryrediskey)){
                    if(strpos($value['category_id'],',') !== false){
                        // $category_id =  reset(explode(',',$value['category_id']));
                        
                        $category_display_info = $category->getCategoryByCategroyIdS($value['category_id']);
                        
                        $category_display_name = "";
                        foreach ($category_display_info as $key => $value) {
                            $category_display_name .= $value['display_name'] . "/";
                        }
                        $category_display_name = rtrim($category_display_name, '/');
                    }else{
                        $category_id =  $value['category_id'];
                        $category_display_name = $category->get_category_by_categroy_id($category_id)['display_name'];
                    }
                    $this->redisLog->set($singlecategoryrediskey, $category_display_name);
                    $this->redisLog->expire($singlecategoryrediskey, 3600);
                }
                $category_display_name = $this->redisLog->get($singlecategoryrediskey);
                $product_info[$keys]['category_name'] = $category_display_name;
            }
            if(!empty($value['home_picture']) && strpos($value['home_picture'], RESOURCE_PATH ) === false ){
                $product_info[$keys]['home_picture'] = RESOURCE_PATH . $value['home_picture'];
            }
            if(!empty($value['detail_picture']) && !empty($order_by)){
                $product_info[$keys]['detail_picture'] = '';
               // $product_info[$keys]['detail_picture'] = RESOURCE_PATH . $value['detail_picture'];
            }
            if(!empty($ProductIDsLiked)){
                $product_info[$keys]['iscollect'] = 0; 
                // checkCollect($val); //判断是否收藏
                if (1 ==  $ProductIDsLiked[$value['id']])
                    $product_info[$keys]['iscollect'] = 1;
            }
            if($productallbuy[$value['id']] > 0){
                $product_info[$keys]['saleallquantity'] = $productallbuy[$value['id']];
            }
            $product_exist = 0;
            $product_exist = $PlatformProductModel->checkProductExist($value['id']);

            $show = 0;
            if ($value['num_per_span']==1 ) {

                $rData = array(
                      
                      0 => $value['id'],
                      1 => $this->user_id,
                      2 => $this->platform_id,
                      3 => $value['repeat_span'],
                      4 => $value['num_per_span'],
                );
                $isGet = R('Member/checkOrder', $rData);
                if($isGet['status'] == 2){    //不能再领取了
                    $show = 1;
                }

                
            }
             if ($product_exist == 0 || $product_exist == -2) {

                $product_info[$keys]['buttonname'] =  '已结束';
                $product_info[$keys]['buttonid'] =  '0';
                $product_info[$keys]['pay_status'] =  2;
            //商品已售完
            } elseif ($product_exist == -1){
                $product_info[$keys]['buttonname'] =  '已售完';
                $product_info[$keys]['buttonid'] =  '0';
                $product_info[$keys]['pay_status'] = 3;
            //商品未售完 且动未结束
            } else {
                
                //跳到合作方购买的。(类似一号店)
                //if ($value['verification'] == 5) {
                if($value['ifpay'] == 1){
                    $product_info[$keys]['buttonname'] =  '立即购买';
                    $product_info[$keys]['buttonid'] =  '1';
                    $product_info[$keys]['pay_status'] = 1;
                //个人中心过来领取只有一次并且已经领取的
                } elseif ($show == 1) {
                    $product_info[$keys]['buttonname'] =  '已领取';
                    $product_info[$keys]['buttonid'] =  '1';
                    $product_info[$keys]['pay_status'] =  4;
                //正常情况
                } else {
                    $product_info[$keys]['buttonname'] =  '领 取';
                    $product_info[$keys]['buttonid'] =  '1';
                    $product_info[$keys]['pay_status'] = 0;
                }
            }


        }  
        return $product_info; 
    }
     /**
     * [getShowListTWO 获取首页的商品列表]
     * @copy by gk from ckhero
     * @DateTime 2017-04-19
     * 
     * @param [type] $start
     *            [开始]
     * @param [type] $end
     *            [结束]
     * @param [type] $type
     *            [默认为1首页加载，2为加载更多]
     * @param [string] $search_key 搜索关键词
     * @param [string] $select_type 搜素类型 1 title 2content
     * @return [type] [description]
     */
    private function getShowListTWO($start = 0, $end = 5, $type = 1, $search_key = '', $select_type)
    {

        $zwid = $this->zwid > 0 ? $this->zwid : 0;// 获取不到的时候值为0 ；
        $redisKey = 'Score::PersonScoreZwidPtid:' . $zwid . '_' . $this->platform_id;
        $productList = D('PlatformProduct')->getProductList(1, '', '', $search_key, '', '', $select_type); // 取该平台的所有数据
        $this->setScores($productList, $zwid, $this->platform_id, $redisKey);
               
        $data['goods'] = $this->redisLog->zget('Score::PersonScoreZwidPtid:' . $zwid . '_' . $this->platform_id, $start, $end, 'desc'); 
       
        $data = $this->setCollectionDate4User($data); 
 
        return $data;
    }
}
