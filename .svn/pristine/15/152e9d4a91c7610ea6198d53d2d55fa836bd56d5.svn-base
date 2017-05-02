<?php
/**
 * Created by PhpStorm.
 * User: jysdhr
 * Date: 2017/4/26
 * Time: 10:30
 * Description:签到模块模型
 */

namespace Home\Model;

use Think\Model;


class SignModel
{
    /**
     * SignModel constructor.
     */
//    protected $autoCheckFields = false;
    private $SignRecord ,$SignDay;
    public function __construct()
    {
//        parent::__construct();
        $this->SignRecord = M('sign_record');
        $this->SignDay = M('sign_day');
    }

    /**
     * @Description:用户的签到记录
     * @User:jysdhr
     * @param $user_id 用户ID
     * @param $ptid 平台ID
     * @param $start_time 筛选签到日期的开始时间
     * @param $end_time 筛选签到日期的结束时间
     * @return mixed 结果集
     */
    public function userSignRecord($user_id, $ptid, $start_time, $end_time)
    {
        return $this->SignRecord->where("userid=%d and ptid=%d and (signtime between '%s' and '%s') ",
            array($user_id, $ptid, $start_time, $end_time))->find(); // 不加数据库查询的缓存
    }

    /**
     * @Description:插入用户签到记录
     * @User:jysdhr
     * @param $user_data 用户签到数据
     * @return mixed 成功返回记录id 失败返回false
     */
    public function addUserSignRecord($user_data){
        return $this->SignRecord->add($user_data);
    }

    /**
     * @Description:用户签到记录总计
     * @User:jysdhr
     * @param $user_id 用户ID
     * @param $ptid 平台ID
     * @return 结果集
     */
    public function userSignDay($user_id,$ptid){
        return $this->SignDay->where("userid=%d and ptid=%d",
            array($user_id, $ptid))->find(); // 不加数据库查询的缓存
    }

    /**
     * @Description:新增用户signday记录
     * @User:jysdhr
     * @param $sign_data 签到信息
     * @return mixed
     */
    public function addUserSignDay($sign_data){
        return $this->SignDay->add($sign_data);
    }
    /**
     * @Description:更新用户的累计签到记录
     * @User:jysdhr
     * @param $user_id 用户ID
     * @param $ptid 平台ID
     * @param $signtotal 上一次累计签到记录
     * @return mixed 返回更新成功的行数
     */
    public function updateUserSignDay($user_id,$ptid,$signtotal){
        return $this->SignDay->where("userid=%d and ptid=%d", array(
            $user_id,
            $ptid
        ))->save(array(
            'signtotal' => $signtotal + 1,
            'updatetime' => date('Y-m-d H:i:s')
        ));
    }
}