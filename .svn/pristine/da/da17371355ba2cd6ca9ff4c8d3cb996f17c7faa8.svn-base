<?php 
// function setZwid($session,$createtime,$redis,$redisLog){

//    if(empty($session['openid'])){  //openid不存在

//     doLog('Common/error', "setZwid - openid不存在",'','',$redisLog );
//     return false;
//    }
//    $zwcmopenid = empty($session['zwcmopenid']) ?  $_SESSION[$_SESSION['PLATFORM_CODE']]['id']: $session['zwcmopenid'];
//    if(empty($zwcmopenid)) return;
//    //$res = M('zwid')->where(array('zwcmopenid'=>$zwcmopenid))->find();
//    $res = M('zwid')->where("zwcmopenid='%s'", $zwcmopenid)->find();
//    if(empty($res)){

//     $new_zwid = array('zwcmopenid'=>$zwcmopenid,'openid_platcode'=>$session['openid'].':'.$session['platform_id'],'addtime'=>date('Y-m-d H:i:s'),'createtime'=>$createtime);
//     $res = M('zwid')->data($new_zwid)->add();
//     cookie('zwid',$res,300);
//     $new_zwid2['id'] = $res;
//     $redis->hset('zwid:'.$res,array_merge($new_zwid2,$new_zwid));
//     $redis->expire('zwid:'.$res,24*3600);
//     $redis->set('zwcmopenid:'.$zwcmopenid,$res);
//     $redis->expire('zwcmopenid:'.$zwcmopenid,24*3600);

//    }else{

//     $openid_platcode = explode(',', $res['openid_platcode']);

//     if(!in_array($session['openid'].'_'.$session['platform_id'], $openid_platcode)){

//       $openid_platcode[] =$session['openid'].'_'.$session['platform_id'];
//       $openid_platcode_new = implode($openid_platcode, ',');
//       //M('zwid')->where("id=".$res['id'])->save(array('openid_platcode'=>$openid_platcode_new));
//       M('zwid')->where("id=%d", $res['id'])->save(array('openid_platcode'=>$openid_platcode_new));
//       $res['openid_platcode'] = $openid_platcode_new;
//       $redis->hset('zwid:'.$res['id'],$res);
//       $redis->expire('zwid:'.$res['id'],24*3600);

//     }else
//     {
//       $zwid = $redis->hget('zwid:'.$res['id']);
//       $zwcmopenid2 = $redis->get('zwcmopenid:'.$zwcmopenid);
//       if(empty($zwid)) $redis->hset('zwid:'.$res['id'],$res);
//       $redis->expire('zwid:'.$res['id'],24*3600);
//       if(empty($zwcmopenid2)) $redis->set('zwcmopenid:'.$zwcmopenid,$res['id']);
//       $redis->expire('zwcmopenid:'.$zwcmopenid,24*3600);
//      }

//    }
// }
// /**
//  * [getSelfProductList 获取我的优惠券]
//  * @ckhero
//  * @DateTime 2016-10-09
//  * @param    [type]     $tel   [description]
//  * @param    integer    $start [description]
//  * @param    integer    $end   [description]
//  * @return   [type]            [description]
//  */
// // function getSelfProductList($tel,$start =0,$end =5){

// //     $exceptPlatform = M('platform')->field('id')->where('code in (\'TESTACCOUNT\',\'CMBPAY\')')->cache(true,3600)->select();
// //     foreach($exceptPlatform as $key=>$val){

// //         $str .= $val['id'].',';
// //     }
// //     $str = trim($str,',');
// //     $subSql = M('pt_user')->field('id')->where("telephone = %d and ptid not in(".$str.")",$tel)->buildSql();
// //     $tablePrefix = C('DB_PREFIX');
// //     $res = M('order_product')
// //           ->field('p.* ,'.$tablePrefix.'order_product.productcode,'.$tablePrefix.'order_product.ptid')
// //           ->join($tablePrefix.'order o on o.id= orderid')
// //           ->join($tablePrefix.'product p on p.id= productid')
// //          // ->where($tablePrefix.'order_product.tel=%d and o.pay_status in (0,2) and o.ptuserid in ('.$subSql .')',$tel)   这样子需要订单号码也是登陆号码
// //           ->where('o.pay_status in (0,2) and o.ptuserid in ('.$subSql .')',$tel)
// //           ->order($tablePrefix.'order_product.createtime desc')
// //           ->limit($start,$end)
// //           ->select();
// //     return $res;
// // }

// /**
//  * [resetProductList 充值商品列表]
//  * @ckhero
//  * @DateTime 2016-10-12
//  * @param    [type]     $list   [description]
//  * @param    [type]     $url    [description]
//  * @return   [type]             [description]
//  */
// function resetProductList($list,$url,$type){

//     foreach($list as $key=>$val){
//         //$status  = D('Product')->checkProductExist($val['id']);
//         $res[$key]['url']          = U(str_replace('id/', 'id/'.$val['id'], $url));
//         $res[$key]['status']       = D('PlatformProduct')->checkProductExist($val['id'],false,$val['ptid']);
//         //$res[$key]['status']       = $status['state'];
//         $res[$key]['pic']          = RESOURCE_PATH.$val['detail_picture'];
//         $res[$key]['start']        = date('Y.m.d',strtotime($val['start']));
//         $res[$key]['end']          = date('Y.m.d',strtotime($val['end']));
//         $res[$key]['name']         = $val['name'];
//         $res[$key]['verification']         = $val['verification'];
//         $res[$key]['productCode']  = $val['productcode'];
//         $res[$key]['price']  = $val['price'];
//         $res[$key]['ifpay']  = $val['ifpay'];
//         $res[$key]['id']  = $val['id'];
//         $res[$key]['ptid']  = $val['ptid'];
//         $res[$key]['orderid']  = $val['orderid']?$val['orderid']:0;
//         if(empty($val['platformCode'])){

//             $res[$key]['platformCode'] = getPlatformCodeById($val['ptid']);
//         }else{

//             $res[$key]['platformCode'] = $val['platformCode'];
//         }

//         if($type=='self'){

//             if($val['ifpay']==1 && $val['price']>0){

//                 $res[$key]['buyUrl'] = platformWeixinGuanzhu($res[$key]['platformCode']);

//                 // $res[$key]['buyUrl'] = "javascript:buy(".$val['id'].",".$val['price'].")";
//                 $res[$key]['buyType'] = '立即购买';
//             }else{

//                 if(empty($val['remark']) && empty($val['mremark'])){

//                     $res[$key]['buyUrl'] = $res[$key]['url'];
//                 }else{

//                     if($val['ifstore'] == 1){

//                         $res[$key]['buyUrl'] = U('Home/Map/lists/product_id/'.$val['id']);
//                     }else{

//                         if(preg_match('/http/', $val['remark'])){

//                             $res[$key]['buyUrl'] = $val['remark'];
//                         }else{

//                             preg_match("/([\'\"]).*(\\1)/", $val['mremark'], $url2);

//                             if(!empty($url2[0])){

//                                 $url2[0] = preg_replace('/[\'\"]/', '', $url2[0]);
//                                 $res[$key]['buyUrl'] = $url2[0];
//                             }else{

//                                $res[$key]['buyUrl'] = $res[$key]['url'];
//                             }
//                         }
//                     }
//                 }
//                 $res[$key]['buyType'] = '立即使用';
//             }
//         }else{

//             $res[$key]['buyUrl'] = platformWeixinGuanzhu($res[$key]['platformCode']);
//             if($val['price']>0 && $val['ifpay']==1){

//                 $res[$key]['buyType'] = '立即购买';
//             }else{

//                 $res[$key]['buyType'] = '立即领取';
//             }
//         }
//     }
//     return $res;
// }

// /**
//  * [getPlatformCodeById 根据ptid查询 平台名字]
//  * @ckhero
//  * @DateTime 2016-10-09
//  * @param    [type]     $ptid [description]
//  * @return   [type]           [description]
//  */
// function getPlatformCodeById($ptid){

//     if($ptid){

//         $res = M('platform')->where("id=%d",$ptid)->cache(true,3600)->find();
//         if(mb_strlen($res['displayname'],'UTF8')>7){

//             $res = mb_substr($res['displayname'],0,7,'UTF8').'...';
//         }else{

//             $res = $res['displayname'];
//         }
//         return $res;
//     }
// }



// function getShopUrl($productid){
//     $tablePrefix = C('DB_PREFIX');
//     $res = M('product')
//            ->alias('p')
//            ->join($tablePrefix.'brand b on b.id=p.brand_id')
//            ->join($tablePrefix.'merchant m on b.merchant_id = m.id')
//            //->where('p.id='.$productid)
//            ->where('p.id=%d', $productid)
//            ->field('m.remark')
//            ->find();
//     if(empty($res['remark'])) $res['remark'] = '敬请期待！';
//     return $res['remark'];
// }

// function platformWeixinGuanzhu($platformCode){

//     switch($platformCode){

//         case '招商银行':
//             $url = 'http://mp.weixin.qq.com/s?__biz=MzIwNTMzMDQyOQ==&mid=100000063&idx=1&sn=941277a079bcb3d6cb9d7ddc411e8a31&chksm=1733c3ad20444abbe681b1fffdac770f9fe1c03b8fb6c2962fbd10fd17f3e76c25f94c51b6a1#rd';
//             break;

//         case '锦江礼享':
//             $url = 'http://mp.weixin.qq.com/s?__biz=MzIwNTMzMDQyOQ==&mid=100000074&idx=1&sn=722d29c3b4ddc1c9c3c96fb608d0e9e6&chksm=1733c3d820444ace9c32d106087b2304b573eb93234530979c7743c200aaaaed4fa987e0ea24#rd';
//             break;

//         case '农业银行':
//             $url = 'http://mp.weixin.qq.com/s?__biz=MzIwNTMzMDQyOQ==&mid=100000072&idx=1&sn=4dbaf60af307aa62a284be0b11a6cdeb&chksm=1733c3da20444acceed70d380b4ad9b7245f831cb90bdc0e36d096ef888b1169c8290181774e#rd';
//             break;

//         case '山西邮政':
//             $url = 'http://mp.weixin.qq.com/s?__biz=MzIwNTMzMDQyOQ==&mid=100000069&idx=1&sn=9fa8fe41a57d555381d0ab1331fa3eda&chksm=1733c3d720444ac13c6562a864effaefd3fbff53ecb610c4030982c8c828da2dc24bcaa87dcb#rd';
//             break;

//         case '大陆汽车':
//             $url = 'http://mp.weixin.qq.com/s?__biz=MzIwNTMzMDQyOQ==&mid=100000067&idx=1&sn=415f5bf0c1421dad97c7f99e0ba7a6dd&chksm=1733c3d120444ac7d4138723c0762feecba08e55261e75e91bbde0ed2a3258d91ad521661e10#rd';
//             break;

//         case '一箱有货':
//             $url = 'http://mp.weixin.qq.com/s?__biz=MzIwNTMzMDQyOQ==&mid=100000070&idx=1&sn=dc04d04531db46f3cf081476d8dc45af&chksm=1733c3d420444ac253c64957dc5ba7a27fa44daebb7e1f50d9ea4c4dc2caf0c6c14197056754#rd';
//             break;

//         case '中国石油':
//             $url = 'http://mp.weixin.qq.com/s?__biz=MzIwNTMzMDQyOQ==&mid=100000065&idx=1&sn=b582835547e4e27ffa8e5945ded29276&chksm=1733c3d320444ac577ff0ee72fa8944c54549d004d5a472c6e714dd48e56cfb031bd0142fbce#rd';
//             break;

//         case '民生银行':
//             $url = 'http://mp.weixin.qq.com/s?__biz=MzIwNTMzMDQyOQ==&mid=100000083&idx=1&sn=6e689f1e5d7c950658d0000d9ef74a1d&chksm=1733c3c120444ad7f80064788838041c4f86133a387aad8618a814feb48c93ff37eef5ca0517&scene=0#wechat_redirect';
//             break;

//         case '上海农商银行':
//             $url = 'http://mp.weixin.qq.com/s?__biz=MzIwNTMzMDQyOQ==&mid=100000085&idx=1&sn=b5bf246b24c81b626251ae76d0cea457&chksm=1733c3c720444ad18a12eebb6562404250ab4a824c47fabb0560876c1ee30a95875f896e1bcc&scene=0#wechat_redirect';
//             break;

//         case '上海前线':
//             $url = 'http://mp.weixin.qq.com/s?__biz=MzIwNTMzMDQyOQ==&mid=100000077&idx=1&sn=f4729911044c7f9c82663eeb38e7cc7f&chksm=1733c3df20444ac95b55d9c9042cc3c74ec5d38270fc4050f1bff28cf8b634d81f973f3c25a0&scene=0#wechat_redirect';
//             break;

//         case '麻辣情医':
//             $url = 'http://mp.weixin.qq.com/s?__biz=MzIwNTMzMDQyOQ==&mid=100000076&idx=1&sn=5122f5340e2dbd3a7670111d2d0ff14b&chksm=1733c3de20444ac8f4e66df10b064dc0dc6734a07df06bfc8d072e31f5ee4b44eae4df0c1c7c&scene=0#wechat_redirect';
//             break;


//         default:
//         $url = 'http://mp.weixin.qq.com/s?__biz=MzIwNTMzMDQyOQ==&mid=100000026&idx=1&sn=99ad0b0fc3315cf9b4e6de14d18eca49&scene=18#wechat_redirect';
//         break;
//     }
//     return $url;
// }

// /**
//  * [getRecentPrize 转盘获取最近中奖的20条信息]
//  * @zeo
//  * @DateTime 2016-10-26
//  *
//  * @return   [type]           [description]
//  */
// function getRecentPrize(){
//   $pt_id = $_SESSION[$_SESSION['PLATFORM_CODE']]['platform_id'];
//   $date  = date('Y-m-d H:i:s%',strtotime("-60 day"));
//   $offlinetime = date('Y-m-d%');
//   $product_list = D('platform_product') ->field('product_id,total')
//                                               ->where('platform_id=%d and status= 1',$pt_id)
//                                         ->select();
//  foreach ($product_list as $k => $v) {
//    if($v['total']<10)$limit=1;
//    else if($v['total']<1000&&$v['total']>10)$limit=5;
//    else $limit=10;
//    $product_arr[] = D('order_product')->field('productname,tel')
//                                       ->where("createtime >='%s' and ptid='%d' and productid='%d' ",$date,$pt_id,$v['product_id'])
//                                       ->limit($limit)
//                                       ->order('id desc')
//                                       ->select();
//  }
//  foreach ($product_arr as $k => $v) {
//    foreach ($v as $k2 => $v2) {
//        if(!empty($v2['tel'])){
//          $v2['productname']= preg_replace('/\|\|/','',$v2['productname']);
//          $result[] = '用户'.substr($v2['tel'],0,3).'****'.substr($v2['tel'],7,4).'抽中'.  $v2['productname'] ;
//        }
//     }
//   }
//   shuffle($result);
//   return $result;
// }

// /**
//  * [getUserByTel 根据电话号查询用户列表]
//  * @ckhero
//  * @DateTime 2016-10-25
//  * @param    [type]     $tel   [description]
//  * @param    boolean    $cache [description]
//  * @param    string     $type  [description]
//  * @return   [type]            [description]
//  */
// function getUserByTel($tel,$cache=false,$type='arr'){

//     $tablePrefix = C('DB_PREFIX');
//     $res = M('pt_user')
//                ->table($tablePrefix.'pt_user as u')
//                ->field('u.id')
//                ->join($tablePrefix.'platform p on p.id=u.ptid')
//                ->where('p.code not in (\'TESTACCOUNT\',\'CMBPAY\') and telephone = %d ',$tel)
//                ->cache($cache,300)
//                ->select();
//     if($type =='arr'){

//         return $res;
//     }else{

//         $str = '';
//         foreach($res as $key=>$val){

//             $str .= $val['id'].$type;
//         }

//         return trim($str,$type);
//     }
// }
//  ?>