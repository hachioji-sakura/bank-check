#!/usr/local/bin/perl
use CGI;
use File::Copy;

my $q = new CGI;

my $year = $q->param('y');
my $month = $q->param('m');
if ( $month <10 ) {  $month = "0".($month+0) ; }

my $newfile = "./data/$year$month.csv";
my $fh = $q->upload('upfile');
copy ($fh, "$newfile");
undef $q;

print "Content-type: text/html\n\n";
print <<HTML;

<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html lang="ja">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
	<title></title>
</head>
<body>
<h3>請求振込管理システム</h3>
<h3>銀行ＣＳＶファイルアップロード</h3>
<br>
アップロード完了しました。<br>
<br>
<input type="button" value="戻る" onclick="history.back()"></body>
</html>
HTML

