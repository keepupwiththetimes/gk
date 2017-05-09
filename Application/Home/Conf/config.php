<?php
return array(
    'DEFAULT_THEME' => 'Default',
    'TMPL_ACTION_ERROR' => 'Public:error', // 默认错误跳转对应的模板文件
    //语言包开启
    'LANG_SWITCH_ON' => true,
    'LANG_AUTO_DETECT' => true, // 自动侦测语言 开启多语言功能后有效
    'LANG_LIST' => 'zh-cn', // 允许切换的语言列表 用逗号分隔
    'VAR_LANGUAGE' => 'l', // 默认语言切换变量
    'ZHCJ_LUCKDRAW_USER_TOTAL_KEY'=>'cmb_luckydraw_user_total',//招行微信转盘用户总人数redis对应KEY
    'ZHSQCJ_LUCKDRAW_USER_TOTAL_KEY'=>'cmb_luckydraw_user_total_qq',//招行QQ转盘用户总人数redis对应KEY
    'SIGN_SEND_TOZH_SPECIAL_PLATFORM_CODE_ARR' => [//签到用来处理特殊处理的平台CODE
        'ZHCJ','ZHSQCJ',
    ],
    'LOGIN_SEND_TOZH_SPECIAL_PLATFORM_CODE_ARR' => [//登录给招行发登录手机号的平台CODE
        'ZHWZ','ZHCJ','ZHSQCJ','ZHSQ'
    ],
    'ZHCJ_LUCKDRAW_USER_TOTAL_TODAY_KEY'=>'cmb_luckydraw_today_user_total',//招行微信转盘用户当日总人数redis对应KEY
    'ZHSQCJ_LUCKDRAW_USER_TOTAL_TODAY_KEY'=>'cmb_luckydraw_today_user_total_qq',//招行QQ转盘用户当日总人数redis对应KEY
    'CHANNEL_OPENID_KEY'=>'channel_openid',//渠道传过来的openid KEY（渠道openid）
    //test环境
    'ZHSQCJ_CHECKIN_TESTURL' => 'http://pointbonustest.dev.cmbchina.com/IMSPActivities/qqCheckIn/index?openid=',//招行qq抽奖同步签到记录URL
    'ZHCJ_CHECKIN_TESTURL' => 'http://pointbonustest.dev.cmbchina.com/IMSPActivities/checkIn/index?openid=',//招行qq抽奖同步签到记录URL
    'ZHCJ_SEND_TEL_TESTURL' => 'http://pointbonustest.dev.cmbchina.com/IMSPActivities/checkIn/savePhone',
    'ZHWZ_SEND_TEL_TESTURL' => 'http://pointbonustest.dev.cmbchina.com/IMSPActivities/checkIn/savePhone',
    'ZHSQ_SEND_TEL_TESTURL' => 'http://pointbonustest.dev.cmbchina.com/IMSPActivities/qqCheckIn/savePhone',
    'ZHSQCJ_SEND_TEL_TESTURL' => 'http://pointbonustest.dev.cmbchina.com/IMSPActivities/qqCheckIn/savePhone',
    //正式
    'ZHCJ_CHECKIN_URL' => 'https://pointbonus.cmbchina.com/IMSPActivities/checkIn/index?openid=',//招行wx抽奖同步签到记录URL
    'ZHSQCJ_CHECKIN_URL' => 'https://pointbonus.cmbchina.com/IMSPActivities/qqCheckIn/index?openid=',//招行qq抽奖同步签到记录URL



);