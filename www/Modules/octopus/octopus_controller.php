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
    $club = new Club($mysqli,$redis,$user);


    if ($route->action == 'status') {
        $route->format = "json";
        $clubid = get('clubid',true);

        require "Modules/feed/feed_model.php";
        $feed_class = new Feed($mysqli,$redis,$settings['feed']);
        return $club->account_data_status($clubid,$feed_class);
    }

    if ($route->action == 'fetch_data') {
        $route->format = "json";
        
        require "Modules/feed/feed_model.php";
        $feed = new Feed($mysqli,$redis,$settings['feed']);
        require "Modules/octopus/octopus_model.php";
        $octopus = new OctopusAPI($feed);

        return $octopus->fetch_data(
            post('userid',true),
            post('mpan',true),
            post('meter_serial',true),
            post('octopus_apikey',true)
        );
    }

    // fetch octopus bills
    if ($route->action == 'fetch_bills') {
        $route->format = "json";
        
        require "Modules/feed/feed_model.php";
        $feed = new Feed($mysqli,$redis,$settings['feed']);
        require "Modules/octopus/octopus_model.php";
        $octopus = new OctopusAPI($feed);

        return $octopus->fetch_bills(
            post('userid',true),
            post('mpan',true),
            post('meter_serial',true),
            post('octopus_apikey',true)
        );
    }
    
    return false;
}
