#!/usr/local/bin/perl
use CGI;
use CGI::Session;

$cgi = new CGI;
$session = new CGI::Session("driver:File", $cgi, {Directory=>'./session'});
$sid =  $session->id();

print "Content-Type: application/download\n";
print "Content-Disposition: attachment; filename=\"seikyu_list.csv\"\n\n";

open (FILE, "< tmp/$sid-check.dat");
while (<FILE>) { print; }
close(FILE);

