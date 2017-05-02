var isWeixin = true;
if(isWeixin) {
	$('.pay-money-alipay-check').hide();
	$('.pay-money-weixin-check').show()
}
$('.pay-money-weixin-pay').click(function() {
	isWeixin = true;
	$('.pay-money-weixin-check').show();
	$('.pay-money-alipay-check').hide()
});
$('.pay-money-alipay-pay').click(function() {
	isWeixin = false;
	$('.pay-money-weixin-check').hide();
	$('.pay-money-alipay-check').show()
});
$(".point").bind('touchstart',function() {
	var time = setTimeout(function() {
		$(".point").fadeOut(300);
		$("#msyh").hide();
		isSure = true;
	}, 500);

	if(reloadJ) {
		location.reload()
	}
});


//点击button弹框消失
$(".point-container .point-button").bind("touchstart",function (){
	var time2 = setTimeout(function (){
      $(".point").hide();
    },500);
});

function showMsg(msg, reload) {
	$("#msgInfo").html(msg)
	$(".point").fadeIn(200);
	if(reload) {
		reloadJ = true
	} else {
		reloadJ = false
	}
}
function showMsg1(message, reload) {
	$(".alertInfo").html(message);
	$("#alertTips-box").show()
	if(reload) {
		reloadJ = true
	} else {
		reloadJ = false
	}
}
$('#close-logout').bind("touchstart", function() {
	$('#container-loginout').hide()
});
