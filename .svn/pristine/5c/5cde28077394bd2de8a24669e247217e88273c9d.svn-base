<?php 
//分支
if($_GET['type'] == 'return') {
    
    $str = $_GET['payresult'];
	$str = $_SERVER['REQUEST_SCHEME']."://".$_SERVER['HTTP_HOST']."/wxzspfse/index.php?m=Home&c=Notify&a=payReturn&payType=cmbc&payresult=".str_replace('+','%2B',$str);
	header("location:$str");
} else {

	$str = $_GET['payresult'];
	$str = $_SERVER['REQUEST_SCHEME']."://".$_SERVER['HTTP_HOST']."/wxzspfse/index.php?m=Home&c=Notify&a=notify&payType=cmbc&payresult=".str_replace('+','%2B',$str);
	header("location:$str");
}
 ?>