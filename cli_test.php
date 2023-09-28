<?php

chdir("/var/www/energylocal");
require "Lib/load_emoncms.php";

require_once "Modules/club/club_model.php";
$club = new Club($mysqli,$redis,$user);

require_once "Modules/tariff/tariff_model.php";
$tariff = new Tariff($mysqli);

require_once "Modules/account/account_model.php";
$account = new Account($mysqli,$user,$tariff);

$result = $club->list();
print json_encode($result,JSON_PRETTY_PRINT)."\n";

$clubid = 1;
// $result = $club->account_list($clubid);
// print json_encode($result,JSON_PRETTY_PRINT)."\n";

// $tariffid = $tariff->create($clubid,"Bethesda");
// print "Tariff id: $tariffid\n";

$result = $tariff->list(1);
print json_encode($result,JSON_PRETTY_PRINT)."\n";

// $tariff->delete(2);

$result = $tariff->list_periods(1);
print json_encode($result,JSON_PRETTY_PRINT)."\n";


// $tariff->add_period(1,"morning","08:00","12:00",12.0,20.0,"#ff0000");
