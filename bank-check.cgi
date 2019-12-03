#!/usr/local/bin/perl
use strict;
use utf8;
use CGI;
use CGI::Session;
use Encode;
use File::Copy;
use File::Basename;
use LWP::UserAgent;

my $cgi = new CGI;
my $session = new CGI::Session("driver:File", $cgi, {Directory=>'./session'});
my $header =$session->header(-type => 'text/html', -charset=> 'utf-8');
my $sid = $session->id();
my $title = $cgi->param('setButton');

print $header;

#my $seikyuFile = decode('utf8',$cgi->param('seikyuDataFile'));
#if ($seikyuFile) {
#	$seikyuFile = encode ('sjis', basename($seikyuFile));
#	my $fh = $cgi->upload('seikyuDataFile');
#	if ($fh) { copy ($fh, $seikyuFile) } else { die "Bill File Upload ERROR!!\n" }
#} else {
#	die "Bill File name ERROR!!\n"
#}

my $seikyuURL = decode('utf8',$cgi->param('seikyuDataURL'));
my $ua = LWP::UserAgent->new;
my $req = HTTP::Request->new(GET => $seikyuURL);
$req->authorization_basic($cgi->param('seikyuID'), $cgi->param('seikyuPass'));
my $res = $ua->request($req);

if ($res->is_success) {
	open (OUT,">","tmp/$sid-seikyu.dat");
    print OUT $res->content;
    close (OUT);
} else {
    print "URL access error.\n"; print $res->status_line; exit
}

my $bankFile = decode('utf8',$cgi->param('bankCSVFile'));
if ($bankFile) {
	$bankFile = encode ('utf8', basename($bankFile));
	my $fh = $cgi->upload('bankCSVFile');
	if ($fh) { copy ($fh, "tmp/$sid-bank.dat") } else { print "BANK CSV File Upload ERROR!!\n"; exit; }
} else {
	print "BANK CSV File name ERROR!!\n"; exit;
}

open(OUT,"perl bank-check.pl tmp/$sid-seikyu.dat tmp/$sid-bank.dat tmp/$sid-check.dat |") or die "bank-check.pl error!!";
while (<OUT>) { s/tmp\/$sid-seikyu.dat/$seikyuURL/; s/tmp\/$sid-bank.dat/$bankFile/; print }
close(OUT);
