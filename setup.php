<?php

// Load settings object to import from
require "settings.php";

// Load in users
$users = json_decode(file_get_contents("users.json"));

// Switch to energylocal emoncms install
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

$mysqli->query("TRUNCATE TABLE `octopus_users`");
$mysqli->query("TRUNCATE TABLE `tma_users`");

$user_class = $user;
$user = false;

// Create admin user
$result = $user_class->register("admin","admin","admin@energylocal.co.uk","Europe/London");
print json_encode($result)."\n";

require_once "Modules/club/club_model.php";
$club_class = new Club($mysqli,$redis,$user_class);

require_once "Modules/tariff/tariff_model.php";
$tariff_class = new Tariff($mysqli);

require_once "Modules/account/account_model.php";
$account_class = new Account($mysqli,$user_class,$tariff_class);

require_once "Modules/octopus/octopus_model.php";
$octopus = new OctopusAPI($mysqli, $feed);



$club_ids = array();
$original_clubs_id = array();

// Create clubs
foreach ($club_settings as $club_name=>$club) {
    echo "- creating club: $club_name\n";
    $result = $club_class->create($club['name']);
    $club_ids[$result['id']] = $club_name;
    $original_clubs_id[$club['club_id']] = $result['id'];
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
            $tariff_class->add_period($tariffid,$period['name'],0,$start,$period['generator'],$period['import'],$period['color']);
        }
        $n++;
    }
}

// Add club accounts
foreach ($users as $user) {
    $password = generate_secure_key(16);

    if (isset($original_clubs_id[$user->clubs_id])) {
        $clubid = $original_clubs_id[$user->clubs_id];
        // print $user->username." ".$clubid."\n";

        $result = $account_class->add_account($clubid,$user->username,$password,$user->email);
        if ($result['success']) {
            $userid = $result['userid'];

            $octopus->user_add(
                $userid,
                $user->mpan,
                $user->meter_serial,
                $user->octopus_apikey,
                "consumption"
            );

            // Set apikey_read
            $mysqli->query("UPDATE users SET apikey_read='$user->apikey_read' WHERE id='$userid'");
            // Set apikey_write
            $mysqli->query("UPDATE users SET apikey_write='$user->apikey_write' WHERE id='$userid'");
        }
    }
}

$clubs = $club_class->list();

// Assign tariffs to users
foreach ($clubs as  $club) {
    print "Club: ".$club->id." ".$club->name."\n";

    // Get club tariffs
    $club_tariffs = $tariff_class->list($club->id);

    // echo json_encode($club_tariffs, JSON_PRETTY_PRINT)."\n";

    // Get club accounts
    $accounts = $account_class->list($club->id);

    foreach ($accounts as $account) {
        
        foreach ($club_tariffs as $tariff) {
        
            // print "Tariff: ".$tariff->id." ".$tariff->created."\n";
    
            // convert 1st January 2010 to unix timestamp
            $date = new DateTime($tariff->created);
            $date->setTime(0,0,0);
            $date->setTimezone(new DateTimeZone('Europe/London'));
            $start = $date->getTimestamp();

            $tariff_class->set_user_tariff($account->userid,$tariff->id,$start);
        }
    }
}
