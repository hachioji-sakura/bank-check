<?php
// �_�E�����[�h������t�@�C����
if(isset($_GET['f'])) {
$fname = $_GET['f'];
$year = $_GET['y'];
$month = $_GET['m'];
if ($month<10) $month = "0".$month;
$downname = "seikyu_furikomi".$year.$month.".csv" ;
// �w�b�_
header("Content-Type: application/octet-stream");
// �_�C�A���O�{�b�N�X�ɕ\������t�@�C����
header("Content-Disposition: attachment; filename=$downname");
// �Ώۃt�@�C�����o�͂���B
readfile("./tmp/".$fname);
}
exit;
?>
