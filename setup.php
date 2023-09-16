<?php
chdir("/var/www/energylocal");
require "Lib/load_emoncms.php";

// Reset application
$mysqli->query("TRUNCATE TABLE `users`");
$mysqli->query("TRUNCATE TABLE `feeds`");

$mysqli->query("TRUNCATE TABLE `club`");
$mysqli->query("TRUNCATE TABLE `club_accounts`");
$mysqli->query("TRUNCATE TABLE `tariffs`");
$mysqli->query("TRUNCATE TABLE `tariff_periods`");
$mysqli->query("TRUNCATE TABLE `user_tariffs`");

// Create admin user
$result = $user->register("admin","admin","admin@energylocal.co.uk","Europe/London");
print json_encode($result)."\n";

require_once "Modules/club/club_model.php";
$club_class = new Club($mysqli, $redis, $user);

require_once "Modules/club/tariff_model.php";
$tariff_class = new Tariff($mysqli);

// Load settings object
require "Modules/club/settings.php";

$club_ids = array();

// Create clubs
foreach ($club_settings as $club_name=>$club) {
    echo "- creating club: $club_name\n";
    $result = $club_class->create($club['name']);
    $club_ids[$result['id']] = $club_name;
}

// Create tariffs
$clubs = $club_class->list();
foreach ($clubs as  $club) {
    print "Club: ".$club->id." ".$club->name."\n";
    $club_tag = $club_ids[$club->id];
    $tariff_history = $club_settings[$club_tag]['tariff_history'];

    $n = 1;
    foreach ($tariff_history as $tariff) {
        // Create tariff
        $result = $tariff_class->create($club->id,"Tariff $n");
        $tariffid = $result['id'];
        print "Tariff $n: $tariffid\n";

        // Set created time to tariff history start
        $mysqli->query("UPDATE tariffs SET created='".$tariff['start']."' WHERE id='$tariffid'");

        // Add tariff periods
        $tariff_periods = $tariff['tariffs'];
        foreach ($tariff_periods as $period) {
            // Convert start and end to decimal
            $tmp = explode(":",$period['start']);
            $start = $tmp[0] + ($tmp[1]/60);
            $tmp = explode(":",$period['end']);
            $end = $tmp[0] + ($tmp[1]/60);

            // Add tariff period
            $tariff_class->add_period($tariffid,$period['name'],$start,$end,$period['generator'],$period['import'],$period['color']);
        }
        $n++;
    }
}