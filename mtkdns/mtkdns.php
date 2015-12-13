<?php
use PEAR2\Net\RouterOS;

require "PEAR2_Net_RouterOS-1.0.0b5.phar";

if($argc < 6) {
	echo "Usage: {$argv[0]} <host> <user> <pass> <address-list> <dns>\n";
	exit;
}

$dnshost = $argv[5];
$list = $argv[4];
$hostname = $argv[1];
$user = $argv[2];
$pass = $argv[3];


$dnsip = array();
$dnsrecord = dns_get_record($dnshost, DNS_A);

foreach($dnsrecord as $p) {
	$dnsip[] = $p["ip"];
}

$c = new RouterOS\Client($hostname, $user, $pass);

$req = new RouterOS\Request("/ip/firewall/address-list/print");
$query = RouterOS\Query::where("list", $list)->andWhere("comment", $dnshost);
$req->setQuery($query);
$resp = $c->sendSync($req);

$todelete = array();
$toadd = $dnsip;

foreach($resp as $r) {
	if($r->getType() === RouterOS\Response::TYPE_DATA) {
		$pos = array_search($r->getProperty("address"), $toadd);
		if ($pos === FALSE) {
			$todelete[] = $r->getProperty(".id");
		} else {
			unset($toadd[$pos]);
		}
	}
}

foreach($toadd as $ip) {
	echo "Adding $ip...\n";
	$req = new RouterOS\Request("/ip/firewall/address-list/add");
	$req->setArgument("address", $ip);
	$req->setArgument("comment", $dnshost);
	$req->setArgument("list", $list);
	$c->sendSync($req);
}

foreach($todelete as $id) {
	echo "Removing $id...\n";
	$req = new RouterOS\Request("/ip/firewall/address-list/remove");
	$req->setArgument("numbers", $id);
	$c->sendSync($req);
}

$c->close();
