<?php
// 应用入口文件
// 检测PHP环境
if(version_compare(PHP_VERSION,'5.3.0','<'))  die('require PHP > 5.3.0 !');

// 开启调试模式 建议开发阶段开启 部署阶段注释或者设为false
define('APP_DEBUG' , true);

define('DEBUG_ON_LOCALHOST_OR_201TESTINGSERVER' , TRUE); //在本机测试或者在测试服务器时为TRUE
//define('DEBUG_ON_LOCALHOST_OR_201TESTINGSERVER' , FALSE); //在正式服务器运行时，必须设为FALSE

//版本号（为解决七牛静态文件缓存问题） 设置以后修改public目录下home下的defalut目录
define('EDITION_NUM' , '');

define('REQUEST_SCHEME',$_SERVER['REQUEST_SCHEME']); //HTTP  OR HTTPS
define('ROOT_PATH','/wxzspfse');
if ( DEBUG_ON_LOCALHOST_OR_201TESTINGSERVER == TRUE) {
	define('HOST_URL', REQUEST_SCHEME.'://uat.zwmedia.com.cn');
	define ('RESOURCE_PATH', HOST_URL . ROOT_PATH );
	define('WEB_URL', HOST_URL . ROOT_PATH . '/') ;
	
	///////招行测试环境URL   2016-07-04 shenzihao
	//define('ZH_URL', 'http://oauthuat.cc.cmbchina.com/OauthPortal/wechat/oauth?oauth_id=qep3eg6&callback_uri=http%3a%2f%2fm.hdtmobile.com%2fwxzhzspf&scope=snsapi_base');
	define('ONE_COIN_URL','http://test.zwmedia.com.cn/wxzspfse/');
}
else {
	define('HOST_URL', REQUEST_SCHEME.'://m.zwmedia.com.cn');
	define ('RESOURCE_PATH', 'https://cdn.zwmedia.com.cn');  //使用CDN
	define('WEB_URL', HOST_URL . ROOT_PATH . '/');
	///////招行生产环境URL   2016-07-04 shenzihao
	//define('ZH_URL', 'http://oauth.cc.cmbchina.com/OauthPortal/wechat/oauth?oauth_id=75cade02&callback_uri=http%3a%2f%2fm.zwmedia.com.cn%2fwxzhzspf&scope=snsapi_base');
	define('ONE_COIN_URL','http://51qun.la/wxzspfse/');
}

if(preg_match('/(zwmedia\.com\.cn)|(51qun\.la)/', $_SERVER['HTTP_HOST'])){
	define('online_redis_server',true);  //可以用redis
}else{
	define('online_redis_server',false);
}
define('PRODUCT_RANK', true );  //true 开启针对个人的商品排名系统

// 定义应用目录
define('APP_PATH','./Application/');

define('ADMIN_MOBILE_PHONE' , '15699763679'); ////用在一元购和抽奖平台：发送短信给管理员(Aaron)的手机，告诉他某张优惠券已经发完

// 引入ThinkPHP入口文件
require './ThinkPHP/ThinkPHP.php';

// 亲^_^ 后面不需要任何代码了 就是如此简单
