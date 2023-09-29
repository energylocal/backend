<?php

/*
    All Emoncms code is released under the GNU Affero General Public License.
    See COPYRIGHT.txt and LICENSE.txt.

    ---------------------------------------------------------------------
    Emoncms - open source energy visualisation
    Part of the OpenEnergyMonitor project:
    http://openenergymonitor.org

*/

// no direct access
defined('EMONCMS_EXEC') or die('Restricted access');

function octopus_controller()
{
    global $mysqli, $redis, $user, $path, $session, $route , $settings;

    require_once "Modules/club/club_model.php";
    $club = new Club($mysqli,$user);

    require "Modules/feed/feed_model.php";
    $feed = new Feed($mysqli,$redis,$settings['feed']);

    require_once "Modules/octopus/octopus_model.php";
    $octopus = new OctopusAPI($mysqli, $feed);

    // Admin access required
    if (!$session['admin']) return array('content'=>false, 'message'=>'Admin access required');

    // Return list of octopus accounts
    // /octopus/list.json (returns json list of accounts)
    // /octopus/list (returns html view)
    if ($route->action == 'list') {
        if ($route->format == 'json') {
            return $octopus->user_list();
        } else {
            return view("Modules/octopus/octopus_list_view.php", array());
        }
    }

    // Add a new octopus account
    // /octopus/add.json?userid=1&mpan=1234567890&meter_serial=1234567890&octopus_apikey=1234567890
    // returns json success or fail
    if ($route->action == 'add') {
        $route->format = "json";

        return $octopus->user_add(
            post('userid',true),
            post('mpan',true),
            post('meter_serial',true),
            post('octopus_apikey',true),
            "consumption"
        );
    }

    // Remove an octopus account
    // /octopus/delete.json?userid=1 (returns json success or fail)
    if ($route->action == 'delete') {
        $route->format = "json";
        $userid = get('userid',true);
        return $octopus->user_remove($userid);
    }

    // Load octopus data from octopus API and save to user feed
    // /octopus/fetch_data.json?userid=1 (returns json success or fail, number of datapoints loaded)
    if ($route->action == 'fetch_data') {
        $route->format = "json";
        return $octopus->fetch_data(
            post('userid',true)
        );
    }
    
    return false;
}
