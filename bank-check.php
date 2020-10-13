<?php
ini_set( 'display_errors', 0 );
require_once("../schedule/const/const.inc");
require_once("../schedule/func.inc");
require_once("../schedule/const/login_func.inc");
require_once("../schedule/const/token.php");
//$result = check_user($db, "1");
$token_id = set_token();

require_once("./hosei.inc");

$errArray = array();
$errFlag = 0;

//$debug_member_no = '001126';

$db->beginTransaction();
try {

$year0 = $_GET["y"];
$month0 = $_GET["m"];
$mlength = $_GET["l"];
$match_off = $_GET["moff"];

if (is_null($year) == true || $year == "") {
	$year = $_GET["y"];
}
if (is_null($month) == true || $month == "") {
	$month = $_GET["m"];
}
if ((is_null($year) == true || $year == "") || (is_null($month) == true || $month == "")) {
	throw new Exception('年月が不明です。');
}

$mcount = $month0 - 3 + ($year0-2016)*12;
if ($mcount<=0) $mcount+=12;
if ($mcount<2) {
	throw new Exception('2016年6月以降を指定してください。');
}

$year = $year0;
$month = $month0;

// Bank CSV file read
$year = $year0;
$month = $month0;

ini_set("auto_detect_line_endings", true);

mb_regex_encoding("UTF-8");
$bankData = array();
for ($i=0;$i<$mcount-1;$i++,$month--) {
 	if ($month<1) { $year--; $month=12; }
	$month2 = $month;
	if ($month<10) $month2 = "0".$month;
	$bankcsvname = "./data/".$year.$month2.".csv";
	$fp = fopen ($bankcsvname, "r");
	if ($fp){
	    if (flock($fp, LOCK_SH)){
			$bankData0 = array();
	        while (!feof($fp)) {
	            $buffer = preg_replace ( '@\p{Cc}@u', '', mb_convert_encoding(fgets($fp),'utf8', 'sjis') );
	            if (ctype_digit(substr($buffer ,0,8))) {
		            list($bankTrDate,$bankTrID,$bankTrAmount,,,$bankTrName) = explode(",",$buffer);
								$bankTrName = trim($bankTrName);
								if (substr($bankTrDate,0,6)!="$year$month2") continue;
		            if ($bankTrName=='') continue;
		            if ($bankTrAmount==0) continue;
		            $bankData0["bankTrDate"] = $bankTrDate;
		            $bankData0["bankTrAmount"] = $bankTrAmount;
		            $bankData0["mId"] = $i; $bankData0["year"] = $year; $bankData0["month"] = $month;
								$bankData[$i][$bankTrName][] = $bankData0;
//		            $log1 .= $bankTrDate."-1-".$bankTrID."-2-".$bankTrAmount."-3-".$bankTrData."-4-".$bankTrName."<br>\n";
				}
	        }
	        flock($fp, LOCK_UN);
	    }else{
	        throw new Exception('銀行CSVファイルロックエラー');
	    }
	}
	fclose (fp);
}

$stmt = $db->prepare("SELECT member_no, furikomisha_name FROM tbl_furikomisha");
$stmt->execute();
$furikomisha_array = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ( $furikomisha_array  as $item ) { 
	$array = mb_split(':',$item['member_no']);
	foreach($array as $member_no) {
if ($debug_member_no && $member_no != $debug_member_no) continue;
		$furikomisha_list[$member_no][] = $item['furikomisha_name'];
}}

$year = $year0;
$month = $month0;

$cond_name = "";
if (isset($_POST["cond_name"])) {
	$cond_name = trim($_POST["cond_name"]);
}
/*
	$sql = "SELECT * FROM tbl_statement where seikyu_year=? and seikyu_month=?";
	$stmt = $db->prepare($sql);
	$stmt->bindParam(1, $tmp_year);
	$stmt->bindParam(2, $tmp_month);
	$tmp_year = $year;
	$tmp_month = $month;
	$stmt->execute();
	$statement_array = $stmt->fetchAll(PDO::FETCH_BOTH);
	if (count($statement_array) < 1) {
		$message = '<br>対象年月の明細書を保存してから部門別合計を算出してください。<br>';
		$message .= '<a href="./save_statement.php?y='.$year.'&m='.$month.'">'.$year.'年'.$month.'月の明細書を保存する</a>';
    throw new Exception($message);
	}
*/

// 生徒情報（受講している教室と科目情報を含む）を取得
$student_list = array();
$param_array = array();
$value_array = array();
array_push($param_array, "tbl_member.kind = ?");
array_push($value_array, "3");
if ($cond_name != "") {
// 検索時
	array_push($param_array," tbl_member.name like concat('%',?,'%') ");
	// 20150817 修正
	//array_push($value_array,str_replace(" " , "　" , $cond_name));
	array_push($value_array,$cond_name);
}
// 20150816 ふりがなの50音順にソートする
$order_array = array("tbl_member.furigana asc");

if ($debug_member_no)	array_push($param_array, "no='$debug_member_no'");

// 20151230 授業料表示のため変更
//$member_list = get_simple_member_list($db, $param_array, $value_array, $order_array);
// 20160416 振込者追加のため変更
//$member_list = get_member_list($db, $param_array, $value_array, $order_array);
//var_dump($member_list);
$cmd = 
		"SELECT
				tbl_member.no as no,
				tbl_member.id as id,
				tbl_member.name as name,
				tbl_member.furigana as furigana,
				tbl_member.sei as sei,
				tbl_member.mei as mei,
				tbl_member.kind as kind,
				tbl_member.grade as grade,
				tbl_member.membership_fee as membership_fee,
				tbl_member.cid as cid,
				tbl_member.sheet_id as sheet_id,
				tbl_member.del_flag as del_flag,
				tbl_member.tax_flag as tax_flag
		 FROM tbl_member";
$cmd .= " where (tbl_member.del_flag = '0' or tbl_member.del_flag = '1' or tbl_member.del_flag = '2')";
if(count($param_array) > 0){
	$cmd .= " and " . join(" and ",$param_array);
}
if(count($order_array) > 0){
	$cmd .= "	order by " . join(" , ",$order_array);
} else {
	$cmd .= "	order by tbl_member.furigana";
}
$stmt = $db->prepare($cmd);
$stmt->execute($value_array);
$member_array = $stmt->fetchAll(PDO::FETCH_ASSOC);
$member_list = array();
foreach ( $member_array as $row ) {
	$param_array = array("tbl_fee.member_no = ?");
	$value_array = array($row["no"]);
	$row["fee_list"] = get_fee_list($db, $param_array, $value_array);
	$member_list[$row["no"]] = $row;
}

if (count($member_list) == 0) {
	$errFlag = 1;
	throw new Exception('生徒が見つかりませんでした。');
}


$furikomisha_count = array();
$student_list = array();

$sql = "SELECT * FROM tbl_statement";
$stmt = $db->prepare($sql);
$stmt->execute();
$ret = $stmt->fetchAll(PDO::FETCH_BOTH);
$statement_array = array();
foreach($ret as $item) {
	$tmp_year = $item["seikyu_year"];
	$tmp_month = $item["seikyu_month"];
	$tmp_member_no = $item["member_no"];
	$statement_array[$tmp_year][$tmp_month][$tmp_member_no] = $item;
}


// その他から売上外請求追加を取得
$sql = "SELECT * FROM tbl_others WHERE kind=8";
$stmt = $db->prepare($sql);
$stmt->execute();
$ret = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($ret as $item) {
	$kabarai_hosei[$item['member_no']][$item['year']][$item['month']][$item['charge']] = $item['price'];
}


foreach ($member_list as $member_no => $member) {

	$tmp_student = array();

	$tmp_student["no"] = $member['no'];
	$tmp_student['name'] = $member['name'];
	$tmp_student['furigana'] = $member['furigana'];

	$furikomisha_list0 = $furikomisha_list[$member['no']];
	foreach ($furikomisha_list0 as $furikomisha_name) { $furikomisha_count[$furikomisha_name]++; }
	
	$year = $year0;
	$month = $month0;

	for ($i=0;$i<$mcount;$i++,$month--) {
	
	 	if ($month<1) { $year--; $month=12; }
		$year1[$i] = $year;
		$month1[$i] = $month;
	
		$tmp_student["furikomi"][$i] = "";
		$tmp_student["furikomi0"][$i] = "";
		$tmp_student["furikomi1"][$i] = 0;
		if ( array_key_exists( $i, $bankData ) ) {
			foreach ($furikomisha_list0 as $furikomisha_name) { 
			$bankData0 = $bankData[$i][$furikomisha_name];
			if ( $bankData0 ) {
				foreach ($bankData0 as $item) {
					$tmp_student["furikomi"][$i] .= "<span>".number_format(-$item["bankTrAmount"])."</span><p class=tooltip>".substr($item["bankTrDate"],0,4)."/".substr($item["bankTrDate"],4,2)."/".substr($item["bankTrDate"],6,2)."</p><br>";
					$tmp_student["furikomi0"][$i] .= number_format(-$item["bankTrAmount"]);
					$tmp_student["furikomi1"][$i] += -$item["bankTrAmount"];
				}
			}}
		} else {
			$tmp_student["furikomi"][$i] = "no data";
			$tmp_student["furikomi0"][$i] = "no data";
		}
		
		if ($JieiFurikomiHosei[$member['name']][$year][$month]) {
			$tmp_student["furikomi"][$i] .= "<span>".number_format($JieiFurikomiHosei[$member['name']][$year][$month])."</span><p class=tooltip>自営分補正</p><br>";
			$tmp_student["furikomi0"][$i] .= "+".number_format($JieiFurikomiHosei[$member['name']][$year][$month]);
			$tmp_student["furikomi1"][$i] += $JieiFurikomiHosei[$member['name']][$year][$month];
		}
		
		if ($kabarai_hosei[$member['no']][$year][$month]) {
			$tmp_student["kabarai_hosei"][$i] = $kabarai_hosei[$member['no']][$year][$month];
		}
		
		if ($statement_array[$year][$month][$member_no]) {
			$tmp_student["last_total_price"][$i] = $statement_array[$year][$month][$member_no]["grand_total_price"];
			$tmp_student["others_price_no_charge"][$i] = $statement_array[$year][$month][$member_no]["no_charge"];
		} else {
			// テキスト代（模試代含む）
			$param_array = array("tbl_buying_textbook.member_no=?", "tbl_buying_textbook.year=?", "tbl_buying_textbook.month=?");
			$value_array = array($member_no, $year, $month);
			$order_array = array("tbl_buying_textbook.input_year", "tbl_buying_textbook.input_month", "tbl_buying_textbook.input_day", "tbl_buying_textbook.buying_no");
			$buying_textbook_list = get_buying_textbook_list($db, $param_array, $value_array, $order_array);
			$textbook_total_price = 0;
			foreach ($buying_textbook_list as $buying)	$textbook_total_price = $textbook_total_price + $buying["price"];
			$tmp_student["last_total_price"][$i] = $textbook_total_price;

			$param_array = array("tbl_others.member_no=?", "tbl_others.year=?", "tbl_others.month=?", "tbl_others.kind!=8");
			$value_array = array($member_no, $year, $month);
			$order_array = array();
			$others_list = get_others_list($db, $param_array, $value_array, $order_array);
			$tmp_student["others_price_no_charge"][$i] = 0;
			foreach ($others_list as $key => $others) {
				$others_price = (int)str_replace(",","",$others["price"]);
//			if ($member['tax_flag']  == "1" && $others["tax_flag"] == null) { $others_price = $others_price + floor($others_price * get_cons_tax_rate($year,$month)); }
				if ($others["charge"] == 1)
					$tmp_student["last_total_price"][$i] += $others_price;
				else
					$tmp_student["others_price_no_charge"][$i] += $others_price;				
			}
		}
		
		if ($seikyu_hosei[$member['name']][$year][$month]) {
			$tmp_student["last_total_price"][$i] += $seikyu_hosei[$member['name']][$year][$month];
		}
	}
	
	$seikyu_total = 0;
	$furikomi_total = 0;
	for ($i=$mcount-1;$i>=0;$i--) {
		$seikyu_total += $tmp_student["last_total_price"][$i];
		$seikyu_total += $tmp_student["others_price_no_charge"][$i];
		$seikyu_total -= $kabarai_hosei[$member['no']][$year1[$i]][$month1[$i]][1];
		if ( array_key_exists( $i, $bankData ) ) {
			foreach ($furikomisha_list0 as $furikomisha_name) { 
			$bankData0 = $bankData[$i][$furikomisha_name];
			if ( $bankData0 )
				foreach ($bankData0 as $item) { $furikomi_total += $item["bankTrAmount"]; };
			}
		}
		$tmp_student["furikomi_total"][$i] = $furikomi_total;
		$tmp_student["seikyu_total"][$i] = $seikyu_total;
		$tmp_student["seikyu_total0"][$i] = $seikyu_total;
		$tmp_student["last_total_price1"][$i] = $tmp_student["last_total_price"][$i];
	}
	if (($seikyu_total == 0) && ($furikomi_total == 0)) {continue;}
		
	$student_list[] = $tmp_student;
}

$student_list2 = array();
$student_list1 = array_reverse($student_list);

foreach ($furikomisha_count as $furikomisha_name=>$item) {
	$fc = 0;
	foreach ($student_list1 as $item1)
		if (in_array($furikomisha_name,$furikomisha_list[$item1["no"]])) $fc++;
	$furikomisha_count[$furikomisha_name] = $fc;
}

foreach ($student_list1 as $item) {
	$fc= $furikomisha_count[$furikomisha_list[$item["no"]][0]];
	if (!isset($fc) || ($fc == 1)) {
		$item["furikomisha_count"] = 1;
		$hosei_total = 0;
		for ($i=$mcount-1;$i>=0;$i--) {
			if ($JieiFurikomiHosei[$item['name']][$year1[$i]][$month1[$i]])
				$hosei_total += $JieiFurikomiHosei[$item['name']][$year1[$i]][$month1[$i]];
			if ($kabarai_hosei[$item['no']][$year1[$i]][$month1[$i]][2])
				$hosei_total += $kabarai_hosei[$item['no']][$year1[$i]][$month1[$i]][2];
			$item["furikomi_total"][$i] -= $hosei_total;
		}
		if ($item["seikyu_total"][$mlength]  != $item["seikyu_total"][0] ||
				$item["furikomi_total"][$mlength]!= $item["furikomi_total"][0] ||
				$item["seikyu_total"][$mlength]  != $item["furikomi_total"][$mlength])
			$student_list2[] = $item;
	} elseif ($fc > 1 ) {
		for ($i=0;$i<$mcount;$i++) $seikyu_totals[$i] = 0;
		for ($i=0;$i<$mcount;$i++) $last_total_price1s[$i] = 0;
		for ($i=0;$i<$mcount;$i++) $furikomi_hoseis[$i] = 0;
		$fc0 = $fc;
		foreach ($student_list1 as $item1)
			if ($furikomisha_list[$item1["no"]][0] == $furikomisha_list[$item["no"]][0]) {
				for ($i=0;$i<$mcount;$i++) {
					$seikyu_totals[$i] += $item1["seikyu_total"][$i];
					$last_total_price1s[$i] += $item1["last_total_price"][$i];
					if ($JieiFurikomiHosei[$item1['name']][$year1[$i]][$month1[$i]])
						$furikomi_hoseis[$i] += $JieiFurikomiHosei[$item1['name']][$year1[$i]][$month1[$i]];
					if ($kabarai_hosei[$item1['no']][$year1[$i]][$month1[$i]][2])
						$furikomi_hoseis[$i] += $kabarai_hosei[$item1['no']][$year1[$i]][$month1[$i]][2];
				}
			}
		foreach ($furikomisha_list[$item["no"]] as $furikomisha_name) { $furikomisha_count[$furikomisha_name] = 0; }
		foreach ($student_list1 as $item1)
			if ($furikomisha_list[$item1["no"]][0] == $furikomisha_list[$item["no"]][0]) {
				$fc0--;
				if ($fc0==0) $item1["furikomisha_count"] = $fc;
				else         $item1["furikomisha_count"] = 0;
				$item1["seikyu_total"] = $seikyu_totals;
				$item1["last_total_price1"] = $last_total_price1s;
				$hosei_total = 0;
				for ($i=$mcount-1;$i>=0;$i--) {
					$hosei_total += $furikomi_hoseis[$i];
					$item1["furikomi_total"][$i] -= $hosei_total;
				}
				$student_list2[] = $item1;
			}
	}
}

	$year = $year0;
	$month = $month0;
if (!$debug_member_no) {
	$bankData1 = array();
	for ($i=0;$i<$mcount;$i++)
		if ( array_key_exists( $i, $bankData ) )
			foreach ($bankData[$i] as $name => $bankData0) {
				$bankData1[$name][] = $bankData0;
			}
	foreach ($bankData1 as $name => $bankData2)
 		if ( !array_key_exists( $name, $furikomisha_count ) ) {
			$furikomisha_index = array_search($name, array_column($furikomisha_array,'furikomisha_name'));
			if ($furikomisha_index !== false && $furikomisha_array[$furikomisha_index]['no'] == '' ) continue;
			$tmp_student = array();
			$tmp_student["name"] = "―不明―";
			$tmp_student["furikomisha_name"] = $name;
			for ($i=0;$i<$mcount;$i++) $furikomi_totals[$i]= 0;
			foreach ($bankData2 as $bankData3 )
			foreach ($bankData3 as $item) {
				$tmp_student["furikomi"][$item["mId"]] .= "<span>".number_format(-$item["bankTrAmount"])."</span><p class=tooltip>".substr($item["bankTrDate"],0,4)."/".substr($item["bankTrDate"],4,2)."/".substr($item["bankTrDate"],6,2)."</p><br>";
				$tmp_student["furikomi0"][$item["mId"]] .= number_format(-$item["bankTrAmount"]);
				$tmp_student["furikomi1"][$item["mId"]] += -$item["bankTrAmount"];
				$furikomi_totals[$item["mId"]] += $item["bankTrAmount"];
				$tmp_hosei = $SeitofumeiFurikomiHosei[$name][$item['year']][$item['month']];
				if ($tmp_hosei) {
					$tmp_student["furikomi"][$item["mId"]] .= "<span>".number_format($tmp_hosei)."</span><p class=tooltip>*生徒不明特別補正</p><br>";
					$tmp_student["furikomi0"][$item["mId"]] .= "+".number_format($tmp_hosei);
					$tmp_student["furikomi1"][$item["mId"]] += $tmp_hosei;
					$furikomi_totals[$item["mId"]] -= $tmp_hosei;
				}
			}
			for ($i=$mcount-2;$i>=0;$i--) $furikomi_totals[$i] += $furikomi_totals[$i+1];
			$tmp_student["furikomi_total"] = $furikomi_totals;
			$tmp_student["furikomisha_count"] = 1;
			$student_list2[] = $tmp_student;
		}
}

	// 表示期間ALL0生徒削除
	foreach ($student_list2 as $key=>$item) {
		$item1 = 0;
		foreach ($item["seikyu_total"] as $item1) if ($item1) break;
		if ($item1) continue;
		$item1 = 0;
		foreach ($item["furikomi_total"] as $item1) if ($item1) break;
		if ($item1) continue;
		unset ($student_list2[$key]);
	}

	// 未払い金データベース登録
	$sql = "DELETE FROM tbl_mibarai WHERE year=? AND month=?";
	$stmt = $db->prepare($sql);
	$stmt->execute(array($year0, $month0));
	$members="";
	foreach ($student_list2 as $item) {
		$fc=$item["furikomisha_count"];
		if ($fc==1)    { $members = $item["no"]; } 
		elseif ($fc>1) { $members = $item["no"].','.$members; }
		else { if ($members) { $members = $item["no"].','.$members; } else { $members=$item["no"]; } continue; }
		$mibarai = $item["seikyu_total"][1]-$item["furikomi_total"][0];
		if ($mibarai>0) {
			$sql = "INSERT INTO tbl_mibarai VALUES (?,?,?,?, now(), now())";
			$stmt = $db->prepare($sql);
			$stmt->execute(array($members, $year0, $month0, $mibarai));
		}
		$members = "";
	}
	$db->commit();


function fwritesjis( $fp, $s) {
	fwrite ($fp, mb_convert_encoding($s,'sjis','utf8'));
}
$csvfname = "tmp".time().".csv";

$fp = fopen ("./tmp/".$csvfname, "w");
if ($fp){
	if (flock($fp, LOCK_EX)){
	fwritesjis( $fp, "請求振込一覧\n" );
	fwritesjis( $fp, $year0."年".$month0."月\n" );
	fwritesjis( $fp, '"No.",' );
	fwritesjis( $fp, '"生徒名",' );
	fwritesjis( $fp, '"振込者名",' );
	for ($i=$mcount-1;$i>=0;$i--) {
		if ($i>$mlength) { continue; }
		if ($i!=$mlength) {
			fwritesjis( $fp, '"'.$year1[$i].'年'.$month1[$i].'月入金",' );
			fwritesjis( $fp, '"'.$year1[$i].'年'.$month1[$i].'月未払金",' );
			fwritesjis( $fp, '"'.$year1[$i].'年'.$month1[$i].'月売上",' );
			fwritesjis( $fp, '"'.$year1[$i].'年'.$month1[$i].'月売上外請求追加",' );
			fwritesjis( $fp, '"'.$year1[$i].'年'.$month1[$i].'月請求",' );
			fwritesjis( $fp, '"'.$year1[$i].'年'.$month1[$i].'月振込・会計調整",' );
		}
		fwritesjis( $fp, '"'.$year1[$i].'年'.$month1[$i].'月売掛金残",' );
	}
	fwritesjis( $fp, "\n" );
	$lineno = 1;
	for ($i=$mcount-1;$i>=0;$i--) { $fTotal[$i]=0; $kTotal[$i]=0; $sTotal[$i]=0; $nTotal[$i]=0; $uTotal[$i]=0; $mTotal[$i]=0; $uriageTotal[$i]=0; };
	foreach (array_reverse($student_list2) as $item) {
		$fc=$item["furikomisha_count"];
		fwritesjis( $fp, $lineno++."," );
		fwritesjis( $fp, '"'.$item["name"].'",' );
		if ($item["furikomisha_name"]) { 
			$name=$item["furikomisha_name"]; 
		} else if ($furikomisha_list[$item["no"]]) {
			$comma= ''; $name=''; foreach ($furikomisha_list[$item["no"]] as $furikomisha_name) { $name .= $comma.$furikomisha_name; $comma='、'; }
		} else {
			$name='--未登録--';
		}
		fwritesjis( $fp, '"'.$name.'",' );
		for ($i=$mcount-1;$i>=0;$i--) {
			if ($i>$mlength) { continue; }
			if ($i!=$mlength) {
				if ($fc>=1) {
					// 入金
					$s = $item["furikomi0"][$i];
					fwritesjis( $fp, '"'.$s.'",' );
					$fTotal[$i] -= $item["furikomi1"][$i];
					// 未払い
					fwritesjis( $fp, '"'.number_format($item["seikyu_total"][$i+1]-$item["furikomi_total"][$i+1]+$item["furikomi1"][$i]).'",' );
					$mTotal[$i] += $item["seikyu_total"][$i+1]-$item["furikomi_total"][$i+1]+$item["furikomi1"][$i];
				} else {
					fwritesjis( $fp, '"上に含まれる",' );
					fwritesjis( $fp, '"上に含まれる",' );
				}
				// 売上
				fwritesjis( $fp, '"'.number_format($item["seikyu_total0"][$i]-$item["seikyu_total0"][$i+1]).'",' );
				$uriageTotal[$i] += $item["seikyu_total0"][$i]-$item["seikyu_total0"][$i+1];
				// 売上外請求追加
				fwritesjis( $fp, '"'.number_format($item["kabarai_hosei"][$i][1]).'",' );
				$kTotal[$i] += $item["kabarai_hosei"][$i][1];
				// 請求
				fwritesjis( $fp, '"'.number_format($item["last_total_price"][$i]).'",' );
				$sTotal[$i] += $item["last_total_price"][$i];
				// 振込・会計調整金
				fwritesjis( $fp, '"'.number_format($item["kabarai_hosei"][$i][2]).'",' );
				$nTotal[$i] += $item["kabarai_hosei"][$i][2];
			}
			// 月末売掛金残
			if ($fc>=1) {
				fwritesjis( $fp, '"'.number_format($item["seikyu_total"][$i]-$item["furikomi_total"][$i]).'",' );
				$uTotal[$i] += $item["seikyu_total"][$i]-$item["furikomi_total"][$i];
			} else {
				fwritesjis( $fp, '"上に含まれる",' );
			}
		}
		fwritesjis( $fp, "\n" );
	}
	fwritesjis( $fp, '"合計"," "," ",' );
	for ($i=$mcount-1;$i>=0;$i--) {
		if ($i>$mlength) { continue; }
		if ($i!=$mlength) {
			fwritesjis( $fp, '"'.number_format(-$fTotal[$i]).'",');
			fwritesjis( $fp, '"'.number_format($mTotal[$i]).'",');
			fwritesjis( $fp, '"'.number_format($uriageTotal[$i]).'",');
			fwritesjis( $fp, '"'.number_format($kTotal[$i]).'",');
			fwritesjis( $fp, '"'.number_format($sTotal[$i]).'",' );
			fwritesjis( $fp, '"'.number_format($nTotal[$i]).'",' );
		}
		fwritesjis( $fp, '"'.number_format($uTotal[$i]).'",' );
	}
	fwritesjis( $fp, "\n" );
	sleep(2);
	fclose ($fp);
	} else {
		throw new Exception('一時ファイルオープンエラー');
}
} else {
	throw new Exception('一時ファイルオープンエラー');
}

	$sql = "SELECT tbl_member.no FROM tbl_member ".
			"WHERE tbl_member.kind = 3 ".
			"AND tbl_member.del_flag = 0 ".
			"AND tbl_member.name <> '体験生徒' ";
	$stmt = $db->prepare($sql);
	$stmt->execute();
	$zaiseki_list = $stmt->fetchAll(PDO::FETCH_NUM);

	$year = $year0;
	$month = $month0;

} catch (Exception $e) {
	$db->rollback();
	// 処理を中断するほどの致命的なエラー
	array_push($errArray, $e->getMessage());
}


ob_start();
print<<<HTML00a
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<meta http-equiv="Pragma" content="no-cache">
<meta http-equiv="Cache-Control" content="no-cache">
<meta http-equiv="Expires" content="0">
<meta name="robots" content="noindex,nofollow">
<script type="text/javascript" src="./script/calender.js"></script>
<script type = "text/javascript">
</script>
<style>
table{  
    width:100px;  
    table-layout:fixed;  
}  
</style>
</head>
<body>
<table border="0" cellspacing="0" cellpadding="0">
<tr>
<td>
<div id="table1">
<table border=1 cellpadding=5>
<tr>
	<th width=40>No.</th>
	<th width=100 id="marker">生徒名</th>
	<th width=100>振込者名</th>
HTML00a;
for ($i=$mcount-1;$i>=0;$i--) {
if ($i>$mlength) { continue; }
if ($i!=$mlength) {
	echo "	<th width=80 >$year1[$i]/$month1[$i]<br>入金</th>";
	echo "	<th width=80 >$year1[$i]/$month1[$i]<br>未払金</th>";
	echo "	<th width=80 >$year1[$i]/$month1[$i]<br>前月請求-未払金</th>";
	echo "	<th width=80 >$year1[$i]/$month1[$i]<br>売上</th>";
	echo "	<th width=80 >$year1[$i]/$month1[$i]<br>売上外請求追加</th>";
	echo "	<th width=80 >$year1[$i]/$month1[$i]<br>請求</th>";
	echo "	<th width=80 >$year1[$i]/$month1[$i]<br>振込・会計調整</th>";
}
echo "	<th width=80 >$year1[$i]/$month1[$i]<br>売掛金残</th>";
}
print<<<HTML00c
</tr>
</table></div>
</td>
<td>&nbsp;&nbsp;&nbsp;&nbsp;</td>
</tr>
</table>
</body></html>
HTML00c;

$output = ob_get_contents();
ob_end_clean(); 
$fp = fopen('bank-check0.html', 'w');
fwrite($fp, $output);
fclose($fp);


ob_start();
print<<<HTML01a
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<meta http-equiv="Pragma" content="no-cache">
<meta http-equiv="Cache-Control" content="no-cache">
<meta http-equiv="Expires" content="0">
<meta name="robots" content="noindex,nofollow">
<script type="text/javascript" src="./script/calender.js"></script>
<script type = "text/javascript">
function tbScr(x) {scrollTo(x,0);}
</script>
<style>
table{  
    width:100px;  
    table-layout:fixed;  
}  
</style>
</head>
<body  style="margin-left: 0;">
<table border="0" cellspacing="0" cellpadding="0">
<tr>
<td>
<div id="table1">
HTML01a;

echo "<table border=1 cellpadding=5>";
echo "<tr>";
echo "	<th width=100>振込者名</th>";
for ($i=$mcount-1;$i>=0;$i--) {
if ($i>$mlength) { continue; }
if ($i!=$mlength) {
	echo "	<th width=80 >$year1[$i]/$month1[$i]<br>入金</th>";
	echo "	<th width=80 >$year1[$i]/$month1[$i]<br>未払金</th>";
	echo "	<th width=80 >$year1[$i]/$month1[$i]<br>前月請求-未払金</th>";
	echo "	<th width=80 >$year1[$i]/$month1[$i]<br>売上</th>";
	echo "	<th width=80 >$year1[$i]/$month1[$i]<br>売上外請求追加</th>";
	echo "	<th width=80 >$year1[$i]/$month1[$i]<br>請求</th>";
	echo "	<th width=80 >$year1[$i]/$month1[$i]<br>振込・会計調整</th>";
}
echo "	<th width=80 >$year1[$i]/$month1[$i]<br>売掛金残</th>";
}
print<<<HTML01c
<th width=20>&nbsp;&nbsp;&nbsp;&nbsp;</th>
</tr>
</table></div>
</td>
</tr>
</table>
</body></html>
HTML01c;

$output = ob_get_contents();
ob_end_clean(); 
$fp = fopen('bank-check1.html', 'w');
fwrite($fp, $output);
fclose($fp);


ob_start();
print<<<HTML02a
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<meta http-equiv="Pragma" content="no-cache">
<meta http-equiv="Cache-Control" content="no-cache">
<meta http-equiv="Expires" content="0">
<meta name="robots" content="noindex,nofollow">
<script type="text/javascript" src="./script/calender.js"></script>
<script type = "text/javascript">
function tbScr(y) {
  scrollTo(0,y);
}
function filter( mibarai, zaiseki){
	var ele_row=document.getElementsByName('row');
	var ele_lineno=document.getElementsByName('lineno');
	var ele_mibarai=document.getElementsByName('mibarai');
	var i,j=0,k=0,rowspan=0,dispflag0=false,dispflag1=false;
	for (i=0;i<ele_row.length;i++) {
		if (rowspan>0) {
			rowspan--;
		} else if (mibarai && ele_mibarai[k].innerHTML==0) { 
			dispflag0=false;
			rowspan=ele_mibarai[k].rowSpan-1;
			k++;
		} else {
			dispflag0=true;
			rowspan=ele_mibarai[k].rowSpan-1;
			k++;
		}
		if (zaiseki && ele_row[i].cells[1].getAttribute("name")=="zaiseki0") {
			dispflag1=false;
		} else {
			dispflag1=true;
		}
		if (dispflag0 && dispflag1) {
			ele_row[i].style.display="";
			if (j%2) { ele_row[i].style.backgroundColor="#ffffff"; } else { ele_row[i].style.backgroundColor="#eeffee"; }
			ele_lineno[i].innerHTML=j+1;
			j++;
		} else {
			ele_row[i].style.display="none";
		}
	}
}
</script>
<style>
#table1 div {
  position: relative;
}

.tooltip {
  display: none;
  position: absolute;
  padding: 16px;
  background: #333;
  color: #fff;
}

span:hover + p.tooltip {
  display: block;
}
table{  
    width:100px;  
    table-layout:fixed;  
}  
.nowrap{  
    white-space:nowrap;  
    overflow:hidden;  
}
</style>
</head>
<body style="margin-top: 0;">
HTML02a;

	if (count($errArray) > 0) {
		foreach( $errArray as $error) {
			echo "<font color=red size=3>".$error."</font><br><br>";
		}
	echo "<a href=\"../schedule/menu.php\">メニューへ戻る</a><br>";
	exit();
	}
echo "<div id=table1>";
echo "<table border=1 cellpadding=5>";
$flag=0; $lineno=1;
foreach (array_reverse($student_list2) as $item) {
	$fc=$item["furikomisha_count"];
	$rowspan=""; if ($fc>1) { $rowspan="rowspan=\"$fc\""; }
	if (array_search(array($item["no"]),$zaiseki_list)!==false) { $zaiseki_flag=''; } else { $zaiseki_flag="name='zaiseki0'"; }
	if ($flag) { echo "<tr name=row bgcolor=#ffffff>"; } else { echo "<tr name=row bgcolor=#f0fff0>"; }; $flag=1-$flag;
	echo "<td name=lineno width=40>".$lineno++."</td>";
	echo "<td $zaiseki_flag width=100> <div class=nowrap title=\"".$item["name"]."\">".$item["name"]."</div></td>";
	echo "<td width=100>";
	if ($item["furikomisha_name"]) { echo $item["furikomisha_name"]; } else 
	if ($furikomisha_list[$item["no"]]) { $br=''; foreach ($furikomisha_list[$item["no"]] as $furikomisha_name) { echo $br; echo $furikomisha_name; $br='<br>'; }} else {
		echo '--未登録--';
	}
	echo "</td>";
	if ($fc>0)	for ($i=$mcount-1;$i>=0;$i--)	$off_flag[$i]=0;
	for ($i=$mcount-1;$i>=0;$i--) {
		if ($i>$mlength) { continue; }
		if ($i!=$mlength) {
			// 入金
			if($fc>0) { echo "<td width=80 align=right bgcolor=#ddffdd $rowspan>".($off_flag[$i+1]?'':$item["furikomi"][$i])."</td>"; }
			if ($i==0) { $name_mibarai="name=mibarai"; } else { $name_mibarai=""; };
			if ($fc>0 && $match_off)	$off_flag[$i]=($item["last_total_price1"][$i]+$item["furikomi1"][$i-1]==0)?1:0;
			// 未払い
			if($fc>0) { echo "<td $name_mibarai width=80 align=right bgcolor=#ffdddd $rowspan>".number_format($item["seikyu_total"][$i+1]-$item["furikomi_total"][$i+1]+$item["furikomi1"][$i])."</td>"; }
			if($fc>0) { echo "<td $name_mibarai width=80 align=right $rowspan>".number_format($item["furikomi"][$i]-($item["seikyu_total"][$i+1]-$item["furikomi_total"][$i+1]+$item["furikomi1"][$i]))."</td>"; }
			// 売上
			echo "<td width=80 align=right bgcolor=#ddddff>".($off_flag[$i]?'':number_format($item["seikyu_total0"][$i]-$item["seikyu_total0"][$i+1]))."</td>";
			// 売上外請求追加
			echo "<td width=80 align=right>".number_format($item["kabarai_hosei"][$i][1])."</td>";
			// 請求
			echo "<td width=80>".number_format($item["last_total_price"][$i])."</td>";
			// 振込・会計調整金
			echo "<td width=80 align=right>".(0?'':number_format($item["kabarai_hosei"][$i][2]))."</td>";
		}
		// 月末売掛金残
		if($fc>0) { echo "<td width=80 align=right $rowspan>".
		($off_flag[$i]?number_format($item["seikyu_total"][$i]-$item["last_total_price1"][$i]-$item["furikomi_total"][$i]):number_format($item["seikyu_total"][$i]-$item["furikomi_total"][$i])).
//		($off_flag[$i]?"<BR>{$item['seikyu_total'][$i]}-{$item['last_total_price1'][$i]}-{$item['furikomi_total'][$i]}":"<BR>{$item['seikyu_total'][$i]}-{$item['furikomi_total'][$i]}").
		"</td>"; }
	}
	echo "</tr>\n";
}
	echo "<tr><td> </td><td> </td><td> </td>";
for ($i=$mcount-1;$i>=0;$i--) {
	if ($i>$mlength) { continue; }
	if ($i!=$mlength) {
		echo "<td align=right>".number_format(-$fTotal[$i])."</td>";
		echo "<td align=right>".number_format($mTotal[$i])."</td>";
		echo "<td align=right>".number_format($uriageTotal[$i])."</td>";
		echo "<td align=right>".number_format($kTotal[$i])."</td>";
		echo "<td align=right>".number_format($sTotal[$i])."</td>";
		echo "<td align=right>".number_format($nTotal[$i])."</td>";
	}
	echo "<td align=right>".number_format($uTotal[$i])."</td>";
}
print<<<HTML02b
	</tr>
</table>
</div>
<br><br>
</body></html>
HTML02b;

$output = ob_get_contents();
ob_end_clean(); 
$fp = fopen('bank-check2.html', 'w');
fwrite($fp, $output);
fclose($fp);

ob_start();
print<<<HTML03a
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<meta http-equiv="Pragma" content="no-cache">
<meta http-equiv="Cache-Control" content="no-cache">
<meta http-equiv="Expires" content="0">
<meta name="robots" content="noindex,nofollow">
<script type="text/javascript" src="./script/calender.js"></script>
<script type = "text/javascript">
window.onscroll = function() {
    var scrollLeft =
        document.documentElement.scrollLeft || // IE、Firefox、Opera
        document.body.scrollLeft;              // Chrome、Safari
    var scrollTop =
        document.documentElement.scrollTop || // IE、Firefox、Opera
        document.body.scrollTop;              // Chrome、Safari
    parent.topframe.tbScr(scrollLeft);
    parent.leftframe.tbScr(scrollTop);
}
function filter( mibarai, zaiseki){
	var ele_row=document.getElementsByName('row');
	var ele_lineno=document.getElementsByName('lineno');
	var ele_mibarai=document.getElementsByName('mibarai');
	var i,j=0,k=0,rowspan=0,dispflag0=false,dispflag1=false;
	for (i=0;i<ele_row.length;i++) {
		if (rowspan>0) {
			rowspan--;
		} else if (mibarai && ele_mibarai[k].innerHTML==0) { 
			dispflag0=false;
			rowspan=ele_mibarai[k].rowSpan-1;
			k++;
		} else {
			dispflag0=true;
			rowspan=ele_mibarai[k].rowSpan-1;
			k++;
		}
		if (zaiseki && ele_row[i].cells[0].getAttribute("name")=="zaiseki0") {
			dispflag1=false;
		}else {
			dispflag1=true;
		}
		if (dispflag0 && dispflag1) {
			ele_row[i].style.display="";
			if (j%2) { ele_row[i].style.backgroundColor="#ffffff"; } else { ele_row[i].style.backgroundColor="#eeffee"; }
			j++;
		} else {
			ele_row[i].style.display="none";
		}
	}
}
</script>
<style>
#table1 div {
  position: relative;
}

.tooltip {
  display: none;
  position: absolute;
  padding: 16px;
  background: #333;
  color: #fff;
}

span:hover + p.tooltip {
  display: block;
}
table{  
    width:100px;  
    table-layout:fixed;  
}  
</style>
</head>
<body style="margin-top: 0;margin-left: 0;">
HTML03a;

	if (count($errArray) > 0) {
		foreach( $errArray as $error) {
			echo "<font color=red size=3>".$error."</font><br><br>";
		}
	echo "<a href=\"../schedule/menu.php\">メニューへ戻る</a><br>";
	exit();
	}
echo "<div id=table1>";
echo "<table border=1 cellpadding=5>";
$flag=0; $lineno=1;
foreach (array_reverse($student_list2) as $item) {
	$fc=$item["furikomisha_count"];
	$rowspan=""; if ($fc>1) { $rowspan="rowspan=\"$fc\""; }
	if (array_search(array($item["no"]),$zaiseki_list)!==false) { $zaiseki_flag=''; } else { $zaiseki_flag="name='zaiseki0'"; }
	if ($flag) { echo "<tr name=row bgcolor=#ffffff>"; } else { echo "<tr name=row bgcolor=#f0fff0>"; }; $flag=1-$flag;
	echo "<td $zaiseki_flag width=100>";
	if ($item["furikomisha_name"]) { echo $item["furikomisha_name"]; } else 
	if ($furikomisha_list[$item["no"]]) { $br=''; foreach ($furikomisha_list[$item["no"]] as $furikomisha_name) { echo $br; echo $furikomisha_name; $br='<br>'; }} else {
		echo '--未登録--';
	}
	echo "</td>";
	if ($fc>0)	for ($i=$mcount-1;$i>=0;$i--)	$off_flag[$i]=0;
	for ($i=$mcount-1;$i>=0;$i--) {
		if ($i>$mlength) { continue; }
		if ($i!=$mlength) {
			// 入金
			if($fc>0) { echo "<td width=80 align=right bgcolor=#ddffdd $rowspan>".($off_flag[$i+1]?'':$item["furikomi"][$i])."</td>"; }
			if ($i==0) { $name_mibarai="name=mibarai"; } else { $name_mibarai=""; };
			if ($fc>0 && $match_off)	$off_flag[$i]=($item["last_total_price1"][$i]+$item["furikomi1"][$i-1]==0)?1:0;
			// 未払い
			if($fc>0) { echo "<td $name_mibarai width=80 align=right bgcolor=#ffdddd $rowspan>".number_format($item["seikyu_total"][$i+1]-$item["furikomi_total"][$i+1]+$item["furikomi1"][$i]).
//					"<br>{$item["seikyu_total"][$i+1]}-{$item["furikomi_total"][$i]}".
					"</td>"; }
			if($fc>0) { echo "<td $name_mibarai width=80 align=right $rowspan>".number_format($item["furikomi"][$i]-($item["seikyu_total"][$i+1]-$item["furikomi_total"][$i+1]+$item["furikomi1"][$i]))."</td>"; }
			// 売上
			echo "<td width=80 align=right bgcolor=#ddddff>".($off_flag[$i]?'':number_format($item["seikyu_total0"][$i]-$item["seikyu_total0"][$i+1]))."</td>";
			// 売上外請求追加
			echo "<td width=80 align=right>".number_format($item["kabarai_hosei"][$i][1])."</td>";
			echo "<td width=80 >".number_format($item["last_total_price"][$i])."</td>";
			// 振込・会計調整金
			echo "<td width=80 align=right>".(0?'':number_format($item["kabarai_hosei"][$i][2]))."</td>";
		}
		// 月末売掛金残
//		if($fc>0) { echo "<td width=80 align=right $rowspan>".($off_flag[$i]?number_format($item["seikyu_total"][$i]-$item["last_total_price1"][$i]-$item["furikomi_total"][$i]):number_format($item["seikyu_total"][$i]-$item["furikomi_total"][$i]))."</td>"; }
		if($fc>0) { echo "<td width=80 align=right $rowspan>".
		($off_flag[$i]?number_format($item["seikyu_total"][$i]-$item["last_total_price1"][$i]-$item["furikomi_total"][$i]):number_format($item["seikyu_total"][$i]-$item["furikomi_total"][$i])).
//		($off_flag[$i]?"<BR>{$item['seikyu_total'][$i]}-{$item['last_total_price1'][$i]}-{$item['furikomi_total'][$i]}":"<BR>{$item['seikyu_total'][$i]}-{$item['furikomi_total'][$i]}").
		"</td>"; }
	}
	echo "</tr>\n";
}
	echo "<tr><td> </td>";
for ($i=$mcount-1;$i>=0;$i--) {
	if ($i>$mlength) { continue; }
	if ($i!=$mlength) {
		echo "<td align=right>".number_format(-$fTotal[$i])."</td>";
		echo "<td align=right>".number_format($mTotal[$i])."</td>";
		echo "<td align=right>".number_format($uriageTotal[$i])."</td>";
		echo "<td align=right>".number_format($kTotal[$i])."</td>";
		echo "<td align=right>".number_format($sTotal[$i])."</td>";
		echo "<td align=right>".number_format($nTotal[$i])."</td>";
	}
	echo "<td align=right>".number_format($uTotal[$i])."</td>";
}
print<<<HTML03b
	</tr>
</table>
</div>
</body></html>
HTML03b;

$output = ob_get_contents();
ob_end_clean(); 
$fp = fopen('bank-check3.html', 'w');
fwrite($fp, $output);
fclose($fp);

ob_start();
print<<<HTML04a
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<meta http-equiv="Pragma" content="no-cache">
<meta http-equiv="Cache-Control" content="no-cache">
<meta http-equiv="Expires" content="0">
<meta name="robots" content="noindex,nofollow">
<script type="text/javascript" src="./script/calender.js"></script>
<script type = "text/javascript">
var mibaFilter=false,zaisFilter=false;
function download(){document.location="./download.php?f=$csvfname&y=$year0&m=$month0";}
function mibarai0(obj) { mibaFilter=obj.checked; parent.leftframe.filter( mibaFilter, zaisFilter ); parent.bottomframe.filter( mibaFilter, zaisFilter ); }
function zaiseki0(obj) { zaisFilter=obj.checked; parent.leftframe.filter( mibaFilter, zaisFilter ); parent.bottomframe.filter( mibaFilter, zaisFilter ); }
</script>
<style>
table{  
    width:100px;  
    table-layout:fixed;  
}  
</style>
</head>
<body>
<table border="0" cellspacing="0" cellpadding="0">
<tr><th width=150>請求振込一覧</th>
<th width=200>{$year1[$mlength-1]}年{$month1[$mlength-1]}月～<br>{$year0}年{$month0}月</th>
<td width=300><input type=checkbox onclick="mibarai0(this)">未払金０の生徒を表示しない<br>
<input type=checkbox onclick="zaiseki0(this)">在籍生徒のみ表示</td>
<td width=150><a href="../schedule/tokusoku.php?y={$year0}&m={$month0}" target="_top">督促へ</a><br><a href="../schedule/menu.php" target="_top">メニューへ戻る</a></td>
<td width=150><input type=button value="&nbsp;CSVダウンロード&nbsp;" onclick="download();"></td>
<td><input type=button value="&nbsp;売上入金一致非表示&nbsp;" onclick="top.location.href='./bank-check.php?y=$year0&m=$month0&l=$mlength&moff=1'"></td>
</tr>
</table>
</body>
HTML04a;

$output = ob_get_contents();
ob_end_clean(); 
$fp = fopen('bank-check4.html', 'w');
fwrite($fp, $output);
fclose($fp);


?>

<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<meta http-equiv="Pragma" content="no-cache">
<meta http-equiv="Cache-Control" content="no-cache">
<meta http-equiv="Expires" content="0">
<meta name="robots" content="noindex,nofollow">
<TITLE></TITLE>
</head>

<?php
	if (count($errArray) > 0) {
		foreach( $errArray as $error) {
?>
			<font color="red" size="3"><?= $error ?></font><br><br>
<?php
		}
?>
	<a href="../schedule/menu.php">メニューへ戻る</a>
	<br>
<?php
	exit();
	}

print<<<HTML00a1
<script type = "text/javascript">
function check(){
var xpos,ypos;
xpos = document.getElementById('c3').offsetLeft+7;
ypos = document.getElementById('r4').offsetTop-1;
document.location="./bank-check0.php?x="+xpos+"&y="+ypos;
}
</script>
<style>
table{  
    width:100px;  
    table-layout:fixed;  
}  
</style>
</head>
<body onload="check()">
<table border="0" cellspacing="0" cellpadding="0">
<tr><th width=150>請求振込一覧</th>
<th width=200>{$year1[$mlength-1]}年{$month1[$mlength-1]}月～<br>{$year0}年{$month0}月</th>
<td width=300><input type=checkbox onclick="mibarai0(this)">未払金０の生徒を表示しない<br>
<input type=checkbox onclick="zaiseki0(this)">在籍生徒のみ表示</td>
<td width=150><a href="../schedule/tokusoku.php?y={$year0}&m={$month0}" target="_top">督促へ</a><br><a href="../schedule/menu.php" target="_top">メニューへ戻る</a></td>
<td width=150><input type=button value="&nbsp;CSVダウンロード&nbsp;" onclick="download();"></td>
<td><input type=button value="&nbsp;売上入金一致非表示&nbsp;" onclick="top.location.href='./bank-check.php?y=$year0&m=$month0&l=$mlength&moff=1'"></td>
</tr>
</table>
<table border="0" cellspacing="0" cellpadding="0">
<tr>
<td>
<div id="table1">
<table border=1 cellpadding=5>
<tr>
	<th width=40 id=c1>No.</th>
	<th width=100 id=c2>生徒名</th>
	<th width=100 id=c3>振込者名</th>
HTML00a1;
for ($i=$mcount-1;$i>=0;$i--) {
if ($i>$mlength) { continue; }
if ($i!=$mlength) {
	echo "	<th width=80 >$year1[$i]/$month1[$i]<br>入金</th>";
	echo "	<th width=80 >$year1[$i]/$month1[$i]<br>未払金</th>";
	echo "	<th width=80 >$year1[$i]/$month1[$i]<br>前月請求-未払金</th>";
	echo "	<th width=80 >$year1[$i]/$month1[$i]<br>売上</th>";
	echo "	<th width=80 >$year1[$i]/$month1[$i]<br>売上外請求追加</th>";
	echo "	<th width=80 >$year1[$i]/$month1[$i]<br>請求</th>";
	echo "	<th width=80 >$year1[$i]/$month1[$i]<br>振込・会計調整</th>";
}
echo "	<th width=80 >$year1[$i]/$month1[$i]<br>売掛金残</th>";
}
print<<<HTML00c1
</tr>
</table></div>
</td>
<td>&nbsp;&nbsp;&nbsp;&nbsp;</td>
</tr>
</table>
<div id=r4></div>
</body></html>
HTML00c1;

?>

