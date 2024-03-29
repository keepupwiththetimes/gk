<?php
namespace Home\Model;

use Think\Model;

class PtUserModel extends Model
{
    private $telNumLimit;
    protected $tablePrefix;

    public function __construct()
    {
        parent::__construct();
        $this->tablePrefix = C('DB_PREFIX');
        $this->telNumLimit = 10;
    }
    
    /**
     * [checkTelNum 检查电话号码限制]
     * @ckhero
     * @DateTime 2017-03-01
     * @param    [type]     $tel     [description]
     * @param    boolean    $checked [description]
     * @return   [type]              [description]
     */
    public function checkTelNum($platform_id, $tel, $checked = true)
    {

        if (empty($tel)) {

            return false;
        }
        //$platform_id = $_SESSION[$_SESSION['PLATFORM_CODE']]['platform_id'];
        if ($checked) {

            // $num = $this->alias("u")
            //             ->join($this->tablePrefix."pt_user_extra as ue on ue.uid = u.id")
            //             ->where("u.telephone = '%s' and u.ptid = %d and ue.is_check_tel = 1", $tel, $platform_id)
            //             ->count();
            $num = $this->where("telephone = '%s' and ptid = %d and is_check_tel = 1", $tel, $platform_id)
                        ->count();
        } else {

            $num = $this->where("telephone = '%s' and ptid = %d", $tel, $platform_id)
                        ->count();
        }
        if ($num >= $this->telNumLimit) {

            return false;
        } else {

            return true;
        }
    }
    /**
     * [updateTelCheckStatus 更新  用户的电话号码检查状态]
     * @gk
     * @2017/5/9
     * @param  [int] $uid [用户id]
     * @return [type]      [成功返回对应数据记录id，失败返回false]
     */
    public function updateTelCheckStatus($uid)
    {   
        if(empty($uid)){
            return false;
        }
        $data['id'] = $uid;
        $data['is_check_tel'] = 1;
        return $this->save($data);
    }
}
