<?php
// ダウンロードさせるファイル名
if(isset($_GET['f'])) {
$fname = $_GET['f'];
$year = $_GET['y'];
$month = $_GET['m'];
if ($month<10) $month = "0".$month;
$downname = "seikyu_furikomi".$year.$month.".csv" ;
// ヘッダ
header("Content-Type: application/octet-stream");
// ダイアログボックスに表示するファイル名
header("Content-Disposition: attachment; filename=$downname");
// 対象ファイルを出力する。
readfile("./tmp/".$fname);
}
exit;
?>
