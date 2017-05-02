<?php
namespace Home\Model;

use Think\Model;

class PlatformModel extends Model
{
    protected $tablePrefix;

    public function __construct()
    {
        parent::__construct();
        $this->tablePrefix = C('DB_PREFIX');
    }
    
    /**
     * [getPlatformCode 获取平台的codelist]
     * @ckhero
     * @DateTime 2017-03-15
     * @param    boolean    $type [true获取抽奖平台]
     * @return   [type]           [false获取非抽奖平台]
     */
    public function getPlatformCode($type=1)
    {
        
        if ($type == 1) {

            $eq = "=";
        } elseif ($type == 2) {

            $eq = "!=";
        }
        $list = $this->alias('p')
                     ->field('p.code')
                     ->join($this->tablePrefix."platform_config as pc on pc.PLATFORM_CODE = p.code")
                     ->where("pc.APP_MODE ".$eq." %d", 2)
                     ->cache(3600)
                     ->select();
        return array_column($list, 'code');
    }
}
