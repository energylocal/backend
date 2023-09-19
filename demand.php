<?php
chdir("/var/www/energylocal");
require "Lib/load_emoncms.php";

require_once "Modules/club/club_model.php";
$club_class = new Club($mysqli, $redis, $user);

require_once "Modules/feed/feed_model.php";
$feed_class = new Feed($mysqli, $redis, $settings["feed"]);

// get all clubs
$clubs = $club_class->list();
foreach ($clubs as $club) {
    echo $club->id . " " . $club->name . "\n";

    // get club accounts
    $accounts = $club_class->account_list($club->id);

    
    $start = 10000000000;
    $end = 0;

    // list all users
    foreach ($accounts as $account) {
        $userid = $account->userid;
        echo "  " . $userid . " " . $account->username . "\n";

        // get demand feed id
        if ($feedid = $feed_class->get_id($userid,"use_hh_octopus")) {
            $meta = $feed_class->get_meta($feedid);
            if ($meta->npoints) {
                if ($meta->start_time < $start) $start = $meta->start_time;
                if ($meta->end_time> $end) $end = $meta->end_time;
            }
        }
    }

    if ($start==10000000000) $start = 0;

    

}
