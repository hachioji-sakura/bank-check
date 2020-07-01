<?php
ini_set( 'display_errors', 0 );
require_once("../schedule/const/const.inc");
require_once("../schedule/func.inc");
require_once("../schedule/const/login_func.inc");
$result = check_user($db, "1");

$errFlag = 0;
$errArray = array();

$year = $_POST['y'];
$month = $_POST['m'];
if ( $month <10 ) {  $month = "0".($month+0) ; }

$newfile = "./data/$year$month.csv";

?>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF8">
<meta name="robots" content="noindex,nofollow">
<title>事務システム</title>
<script type="text/javascript" src="./script/calender.js"></script>
<script type = "text/javascript">
<!--
function blankCheck() {
    if (document.FORM1.y.value == '') {
    	alert ("年を指定して下さい。");
        return false;
    }
    if (document.FORM1.m.value == '') {
    	alert ("月を指定して下さい。");
        return false;
    }
    if (document.FORM1.m.value <1 || document.FORM1.m.value >12) {
    	alert ("月を正しく指定して下さい。");
        return false;
    }
    if (document.FORM1.upfile.value == '') {
    	alert ("ファイルを指定して下さい。");
        return false;
    }
    return true;
}
// -->
</script>
<link rel="stylesheet" type="text/css" href="./script/style.css">
</head>
<body>

<div id="header">
	事務システム 
</div>


<div id="content" align="left">

<h3>銀行ＣＳＶファイルアップロード</h3>

<a href="../schedule/menu.php">メニューへ戻る</a><br><br>

<?php
	if (count($errArray) > 0) {
		foreach( $errArray as $error) {
?>
			<font color="red" size="5"><?= $error ?></font><br>
<?php
		}
?>
	<br>
<?php
	}
	
if ($_POST['sub'] != 'アップロード実行') {
	
?>
<br>
１ヶ月分のＣＳＶデータファイルを指定してください。<br><br>
<form action="./bank-upload.php" name=FORM1 method="post" enctype="multipart/form-data" onsubmit="return blankCheck()">
<input type=text name="y" size="4" maxlength="4">年<input type=text name="m" size="2" maxlength="2">月ゆうちょ銀行明細書<br><br>
<input type=file name="upfile" size="60"><br><br>
<input type=submit name=sub value="アップロード実行">
</form>
<?php

} else {

	if (is_uploaded_file($_FILES["upfile"]["tmp_name"])) {
		if (move_uploaded_file($_FILES["upfile"]["tmp_name"], $newfile)) {
			chmod($newfile, 0644);
			echo $_FILES["upfile"]["name"] . "をアップロードしました。";
			echo '<br><br>';
		} else {
			echo "ファイルをアップロードできません。";
		}
	} else {
		echo "ファイルが選択されていません。";
	}
	
}
	
?>
