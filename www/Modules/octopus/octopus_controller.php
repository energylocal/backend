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

    if ($route->action == 'list') {
        if ($route->format == 'json') {
            return $octopus->user_list();
        } else {
            return view("Modules/octopus/octopus_list_view.php", array());
        }
    }

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

    if ($route->action == 'delete') {
        $route->format = "json";
        $userid = get('userid',true);
        return $octopus->user_remove($userid);
    }

    // -------------------------------------------------------------------------

    if ($route->action == 'status') {
        $route->format = "json";
        $clubid = get('clubid',true);
        return $club->account_data_status($clubid,$feed_class);
    }

    if ($route->action == 'fetch_data') {
        $route->format = "json";
        return $octopus->fetch_data(
            post('userid',true)
        );
    }

    // fetch octopus bills
    if ($route->action == 'fetch_bills') {
        $route->format = "json";
        return $octopus->fetch_bills(
            post('userid',true)
        );
    }
    
    return false;
}
