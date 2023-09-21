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

// The data controller is used to access processed data for users and clubs
// including daily summaries, monthly summaries and custom periods
// club generation and aggregated consumption data is also available
function data_controller()
{

    // Get feed data
    if ($route->action == 'data') {

    }

    // Daily consumption, time of use and use of generation data for a user
    // returns multiple days between start and end
    if ($route->action == 'daily') {
        $route->format = "json";

        $start = get('start',true);
        $end = get('end',true);

        // get midnight of today
        // using datetime
        /*
        $date = new DateTime();
        $date->setTime(0,0,0);
        $end = $date->getTimestamp();

        // get midnight of 7 days ago
        $date->modify('-7 days');
        $start = $date->getTimestamp();
        */

        // Set based on session user
        $userid = 2;

        // Find consumption feed id
        require "Modules/feed/feed_model.php";
        $feed = new Feed($mysqli,$redis,$settings['feed']);

        require "Modules/data/account_data_model.php";
        $account_data = new AccountData($feed, $club, $tariff);

        return $account_data->daily_summary($userid,$start,$end);
    }

    // Monthly consumption, time of use and use of generation data for a user
    // returns multiple months between start and end
    if ($route->action == 'monthly') {    
        
    }

    // Custom consumption, time of use and use of generation data for a user
    // returns summary results for a custom period
    if ($route->action == 'custom') {    
        
    }
}