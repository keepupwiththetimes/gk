
//购买界面\
    // $('.buy-reduce').click(function(){
    //     var numOBJ = parseInt($('.buy-count').html());
    //     if(numOBJ == 1){
    //         $('.buy-count').html(1);
    //     }else{
    //         numOBJ = numOBJ -1;
    //         $('.buy-count').html(numOBJ);
    //     }
    // });
    // $('.buy-plus').click(function(){
    //     var numOBJ = parseInt($('.buy-count').html());
    //     numOBJ= numOBJ + 1;
    //     $('.buy-count').html(numOBJ);
    // });
    //付款操作
    var isWeixin = true;
//默认微信支付
    if (isWeixin){
        $('.pay-money-alipay-check').hide();
        $('.pay-money-weixin-check').show();
    }
//选择微信支付
    $('.pay-money-weixin-pay').click(function(){
        isWeixin = true;
        $('.pay-money-weixin-check').show();
        $('.pay-money-alipay-check').hide();
    });
//选择支付宝支付
    $('.pay-money-alipay-pay').click(function(){
        isWeixin = false;
        $('.pay-money-weixin-check').hide();
        $('.pay-money-alipay-check').show();
    });
	//关闭提示窗1
	$(".point").bind("touchstart",function (){
		var time = setTimeout(function (){
	      $(".point").hide();
	    },500);

        if(reloadJ){

            location.reload();
        }
	});
	
	//关闭提示窗2
	$(".point2").bind("touchstart",function (){
		var time2 = setTimeout(function (){
	      $(".point2").hide();
	      $(".point-container2").hide();
	    },500);

        if(reloadJ){

            location.reload();
        }
	});
	
	//点击button弹框消失
	$(".point-container2 .point-button").bind("touchstart",function (){
		var time2 = setTimeout(function (){
	      $(".point2").hide();
	      $(".point-container2").hide();
	    },500);

        
	});
	
//	var timer = setTimeout(function (){
//		$(".point").hide();
//	},3000);
//弹框
function showMsg(msg,reload){
    if(reload){

        reloadJ = true;
    }else{

        reloadJ = false
    }
	$('#msgInfo').html(msg);
	$('#point').show();
}
//关闭登出
$('#close-logout').bind("touchstart",function(){
		$('#container-loginout').hide();
	});

