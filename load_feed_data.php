<?php
chdir("/var/www/energylocal");
require "Lib/load_emoncms.php";
require "/opt/emoncms/modules/sync/lib/phpfina.php";

require_once "Modules/feed/feed_model.php";
$feed_class = new Feed($mysqli,$redis,$settings["feed"]);

$local_datadir = "/var/opt/emoncms/phpfina/";
$remote_server = "https://dashboard.energylocal.org.uk";

// get all users
$result = $mysqli->query("SELECT * FROM users WHERE id>1");
while ($row = $result->fetch_object()) {
    $userid = $row->id;
    print $userid." ".$row->username."\n";

    // get feed id
    $remote_id = (int) file_get_contents("https://dashboard.energylocal.org.uk/feed/getid.json?name=use_hh_octopus&apikey=".$row->apikey_read);
    if ($remote_id==0) {
        echo "No remote feed id found\n";
        continue;
    }

    if (!$local_id = $feed->get_id($userid,"use_hh_octopus")) {
        $result2 = $feed->create($userid,"energylocal","use_hh_octopus",5,json_decode('{"interval":1800}'));
        if (!$result2['success']) { echo json_encode($result2)."\n"; die; }
        $local_id = $result2['feedid'];
    }

    phpfina_download($local_datadir,$local_id,$remote_server,$remote_id,$row->apikey_write);
}