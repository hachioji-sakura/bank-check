<?php

$xpos = $_GET["x"];
$ypos = $_GET["y"];

?>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<meta name="robots" content="noindex,nofollow">
<script type="text/javascript" src="./script/calender.js"></script>
<script type = "text/javascript">
<?php echo 'function download(){document.location='.'"'.'./download.php?f='.$csvfname.'&y='.$year0.'&m='.$month0.'";}' ?>
function tbScr(x) {
  scrollTo(x,0);
}
</script>
<style>
</style>
</head>
<FRAMESET rows="50,<?=$ypos-50?>,*" frameborder="0">
<FRAME name='headframe' src='bank-check4.html' scrolling='no' style="overflow:hidden;">
<FRAMESET cols="<?=$xpos?>,*" frameborder="0">
<FRAME name='frame0' src='bank-check0.html' scrolling='no' style="overflow:hidden;">
<FRAME name='topframe' src='bank-check1.html' scrolling='no' style="overflow:hidden;">
</FRAMESET>
<FRAMESET cols="<?=$xpos?>,*" frameborder="0">
<FRAME name='leftframe' src='bank-check2.html' scrolling='no' style="overflow:hidden;">
<FRAME name='bottomframe' src='bank-check3.html'>
</FRAMESET>
</FRAMESET>
<NOFRAMES>
<BODY>
フレームに対応しているブラウザでご覧ください
</BODY>
</NOFRAMES>
</FRAMESET>
</HTML>
</html>

