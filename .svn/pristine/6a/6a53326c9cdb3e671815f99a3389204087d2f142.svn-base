<?php 
namespace Vendor\GeoIp;

require_once 'vendor/autoload.php';
use GeoIp2\Database\Reader;

class GeoIp
{
	private $cityDataDir, $ch;
	public function __construct($ch = 'zh-CN')
	{

		$this->cityDataDir = "/var/www/html/ltb_static_files/geoData/GeoLite2-City.mmdb";
		$this->ch = empty($ch) ? 'zh-CN' : $ch;
	}
    
    /**
     * [city 获取城市名字]
     * @ckhero
     * @DateTime 2017-02-08
     * @param    string     $ip [description]
     * @return   [type]         [description]
     */
	public function city($ip = '8.8.8.8')
	{
        $reader = new Reader($this->cityDataDir);
        $record = $reader->city($ip);
        
        $data = array();
        //geoip里面读取
        $data['province'] = $record->mostSpecificSubdivision->names[$this->ch];   //省的名字
        $data['city'] = $record->city->names[$this->ch];   //城市名字
        $data['type'] = 'geoip';
        //由于同一个IP地址很有可能很快被访问，所以缓存10分钟
//        $this->redis->hset('Ip:'.$ip, $data);
//        $this->redis->expire('Ip:'.$ip, 600);
        //$data['postal'] = $record->postal->code;

        return $data;
	}
}
 ?>
