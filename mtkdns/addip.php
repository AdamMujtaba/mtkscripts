<?php
use PEAR2\Net\RouterOS;

require "PEAR2_Net_RouterOS-1.0.0b5.phar";

if($argc < 6) {
	echo "Usage: {$argv[0]} <host> <user> <pass> <address-list> <IP>\n";
	exit;
}

$ipaddr = trim($argv[5]);
$list = $argv[4];
$hostname = $argv[1];
$user = $argv[2];
$pass = $argv[3];

$c = new RouterOS\Client($hostname, $user, $pass);

$req = new RouterOS\Request("/ip/firewall/address-list/print");
$query = RouterOS\Query::where("address", $ipaddr)->andWhere("list", $list);
$req->setQuery($query);
$resp = $c->sendSync($req);
$oldid = -1;
$same = false;
foreach($resp as $r) {
	if($r->getType() === RouterOS\Response::TYPE_DATA) {
		$oldid = $r->getProperty(".id");
		if($r->getProperty("address") == $ipaddr)
			$same = true;
	}
}

if(!$same) {
	if($oldid > -1) {
		echo "Removing old $ipaddr...\n";
		$req = new RouterOS\Request("/ip/firewall/address-list/remove");
		$req->setArgument("numbers", $oldid);
		$c->sendSync($req);
	}
	
	echo "Adding $ipaddr...\n";
	$req = new RouterOS\Request("/ip/firewall/address-list/add");
	$req->setArgument("address", $ipaddr);
	$req->setArgument("list", $list);
	$req->setArgument("timeout", "01:00:00");
	$c->sendSync($req);
} else {
	echo "Resetting timeout on $ipaddr...\n";
	$req = new RouterOS\Request("/ip/firewall/address-list/set");
	$req->setArgument(".id", $oldid);
	$req->setArgument("timeout", "01:00:00");
	$c->sendSync($req);
}

$c->close();
