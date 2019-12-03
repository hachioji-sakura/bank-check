#!/usr/local/bin/perl
use strict;
use CGI;
use CGI::Session;
use Encode;
use utf8;
binmode STDIN, ':encoding(utf8)';
binmode STDOUT,':encoding(utf8)';

my $billFile = $ARGV[0];
my $bankFile = $ARGV[1];
my $checkFile = $ARGV[2];
my ($date,$price,$bankDataPeriod);
my $seikyu_month;
my @seikyu_dataitem = (
	'氏名','振込者名','授業時間（時間）','授業金額-小計（円）','分割払い-小計（円）','入会金','月会費',
	'テキスト代-小計（円）','その他項目-小計（円）','合計金額','消費税','総合計金額','授業料',
	'振込','差額'
	);
my %billSum = ();
my @seikyu;
my $seikyu_count=-2;
my $i=-1;
my $firstBillDate = '9999年99月99日';
my $lastBillDate  = '0000年00月00日';

open (FILE,"<:utf8","$billFile") or die "bill file open error!!\n";
while (<FILE>) {
	chomp();
	if (/(\d+年\d+月)/) { $seikyu_month = $1; next }
	if (/<tr>/) { $seikyu_count++; $i=-1; next }
	if (/<td.*>(.*)<\/td>/) {
		$i++;
		$seikyu[$seikyu_count]{$seikyu_dataitem[$i]}=$1;
		if ($seikyu_dataitem[$i] eq '総合計金額') {
  			$price=$1; $price=~s/,//g;
			$billSum{$seikyu[$seikyu_count]{'振込者名'}}+=$price+0;
		}
	}
}
close (FILE);

my %bankSum = ();
my (@bankTrDate,@bankTrId,@bankTrAmount,@bankTrName);

$i = 0;
open (FILE,"< $bankFile") or die "bank file open error!!\n";
while ($_=decode('SJIS',<FILE>)) {
	chomp();
	if (/日付指定：(.*)/) { $bankDataPeriod = $1 }
	if (/(\d+),(\d+),(\d+),([^,]*),([^,]*),([^,]*)/) {
		$date = $1; $bankTrId[$i] = $2; $bankTrAmount[$i] = $3; $bankTrName[$i] = $6;
		if ($bankTrAmount[$i]) {
			$date =~ /(\d{4})(\d{2})(\d{2})/;
			$bankTrDate[$i] = "$1/$2/$3";
			$bankSum{$bankTrName[$i]} += $bankTrAmount[$i];
			$i++;
		}
	}
}
close (FILE);


my $diff;
my $seikyu_list_new = '';
my $matchList = '';
my $unmatchList1 = '';
my $unmatchList2 = '';
my $matchCount = 0;
my $unmatchCount0 = 0;
my $unmatchCount1 = 0;
my $unmatchCount2 = 0;

open (OUT,">:encoding(sjis)","$checkFile");
print OUT "\"請求一覧\"\n";
print OUT "\"$seikyu_month\"\n";
print OUT "\"氏名\",\"振込者名\",\"授業時間（時間）\",\"授業金額-小計（円）\",\"分割払い-小計（円）\",\"入会金\",\"月会費\",\"テキスト代-小計（円）\",\"その他項目-小計（円）\",\"合計金額\",\"消費税\",\"総合計金額\",\"授業料\",\"振込\",\"差額\"\n";

for ($i=0;$i<=$seikyu_count;$i++) {
	$seikyu_list_new.="\t<tr>\n";
	$diff = $bankSum{$seikyu[$i]{'振込者名'}} - $billSum{$seikyu[$i]{'振込者名'}};
	for (my $j=0;$j<=$#seikyu_dataitem;$j++) {
		$seikyu_list_new.="\t\t<td>";
		if ($seikyu_dataitem[$j] eq '振込') {
			if ($bankSum{$seikyu[$i]{'振込者名'}}==0) { $seikyu_list_new.='0'; print OUT '0,' }
			elsif ($diff==0)                          { $seikyu_list_new.='1'; print OUT '1,' }
			else                                      { $seikyu_list_new.='2'; print OUT '2,' }
		} elsif ($seikyu_dataitem[$j] eq '差額') {
			if ($diff<0) { $seikyu_list_new.="<span style=\"color:red\">" }
			my $num=$diff;
			$num=~s/(\d{1,3})(?=(?:\d{3})+(?!\d))/$1,/g;
			$seikyu_list_new.=$num; print OUT "\"$num\",";
			if ($diff<0) { $seikyu_list_new.="</span>" }
		} else {
			$seikyu_list_new.=$seikyu[$i]{$seikyu_dataitem[$j]};
			print OUT "\"$seikyu[$i]{$seikyu_dataitem[$j]}\",";
		}
		$seikyu_list_new.="</td>\n";
	}
	$seikyu_list_new.="\t</tr>\n"; print OUT "\n";
}
close (OUT);

foreach my $key (sort keys(%billSum)) {
	if ( $bankSum{$key} == $billSum{$key} ) {
		$matchCount++;
		for ( $i=0; $i<=$seikyu_count; $i++ ) {
			if ($seikyu[$i]{'振込者名'} eq $key) {
				$matchList .= "$seikyu[$i]{'振込者名'}（$seikyu[$i]{'氏名'}）<br>\n";
				last;
			}
		}
		for ( ; $i<=$seikyu_count; $i++ ) {
			if ($seikyu[$i]{'振込者名'} eq $key) {
				$matchList .= "　　請求：　$seikyu[$i]{'総合計金額'}<br>\n";
			}
		}
		for ( $i=0; $i<=$#bankTrId; $i++ ) {
			if ($bankTrName[$i] eq $key) {
				$matchList .= "　　振込：　$bankTrDate[$i],　$bankTrAmount[$i],　$bankTrId[$i]<br>\n";
			}
		}
	} else {
		if ($bankSum{$key}) { $unmatchCount1++ } else { $unmatchCount0++ }
		for ( $i=0; $i<=$seikyu_count; $i++ ) {
			if ($seikyu[$i]{'振込者名'} eq $key) {
				$diff = $bankSum{$key} - $billSum{$key};
				$unmatchList1 .= "$seikyu[$i]{'振込者名'}（$seikyu[$i]{'氏名'}）、　$diff<br>\n";
				last;
			}
		}
		for ( ; $i<=$seikyu_count; $i++ ) {
			if ($seikyu[$i]{'振込者名'} eq $key) {
				$unmatchList1 .= "　　請求：　$seikyu[$i]{'総合計金額'}<br>\n";
			}
		}
		for ( $i=0; $i<=$#bankTrId; $i++ ) {
			if ($bankTrName[$i] eq $key) {
				$unmatchList1 .= "　　振込：　$bankTrDate[$i],　$bankTrAmount[$i],　$bankTrId[$i]<br>\n";
			}
		}
		if ($bankSum{$key} == 0) {
				$unmatchList1 .= "　　振込：　0<br>\n";
		}
	}
	$bankSum{$key} = 0;
}

foreach my $key (sort keys(%bankSum)) {
	if ( $bankSum{$key} ) {
		$unmatchCount2++;
		$unmatchList2 .= "<tr>\n";
		for ( $i=0; $i<=$#bankTrId; $i++ ) {
			if ($bankTrName[$i] eq $key) {
				$unmatchList2 .= "\t<td>$bankTrName[$i]</td><td>$bankTrDate[$i]</td><td>$bankTrAmount[$i]</td><td>$bankTrId[$i]</td>\n";
			}
		$unmatchList2 .= "</tr>\n";
		}
	}
}

my $unmatchCount = $unmatchCount0 + $unmatchCount1 + $unmatchCount2;
$billFile = decode('sjis',$billFile);
$bankFile = decode('sjis',$bankFile);

my $seikyuCount = $seikyu_count+1;
my $furikomiCount = $#bankTrId+1;

print <<HTML_OUTPUT;
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html lang="ja">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
	<title></title>
</head>
<body>
<h3>請求・振込照合結果</h3><br>
請求データ：　$billFile<br>
　　請求期間：　　$seikyu_month<br>
銀行CSVファイル：　　$bankFile<br>
　　入出金期間：　　$bankDataPeriod<br>
請求件数：　　$seikyuCount<br>
振込件数：　　$furikomiCount<br>
整合件数：　　$matchCount<br>
不整合件数：　　$unmatchCount<br>
　　振込なし：　　$unmatchCount0<br>
　　請求額・振込額不一致：　　$unmatchCount1<br>
　　請求にない振込：　　$unmatchCount2<br>
<br><br>
<h3>生徒の月謝計算 - 生徒一覧</h3>
<h3>$seikyu_month</h3>
<form action="bank-check-download.cgi" method="get"><input type="submit" value="CSVダウンロード"></form>
<table border="1" cellpadding="5">
<tr>
	<th>氏名</th>
	<th>振込者名</th>
	<th>授業時間（時間）</th>
	<th>授業金額-小計（円）</th>
	<th>分割払い-小計（円）</th>
	<th>入会金</th>
	<th>月会費</th>
	<th>テキスト代-小計（円）</th>
	<th>その他項目-小計（円）</th>
	<th>合計金額</th>
	<th>消費税</th>
	<th>総合計金額</th>
	<th>授業料</th>
	<th>振込<br>0:振込なし<br>1:振込あり<br>2:差額あり</th>
	<th>差額</th>
</tr>
$seikyu_list_new
</table>
<br><br>
<h3>請求にない振込：</h3>
<table border="1" cellpadding="5">
<tr>
	<th>振込者名</th>
	<th>取引日</th>
	<th>受入金額（円）</th>
	<th>入出金明細ＩＤ</th>
</tr>
$unmatchList2
</table>
<br><br>
</body>
</html>
HTML_OUTPUT
