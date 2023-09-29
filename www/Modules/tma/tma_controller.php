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

// TMA data source controller
function tma_controller()
{
    global $mysqli, $redis, $session, $route, $settings;

    require "Modules/feed/feed_model.php";
    $feed = new Feed($mysqli,$redis,$settings['feed']);

    require_once "Modules/tma/tma_model.php";
    $tma_class = new TMA($mysqli, $redis, $feed);

    // Admin access required
    if (!$session['admin']) return array('content'=>false, 'message'=>'Admin access required');

    // List all accounts
    // /tma/list.json
    // /tma/list
    if ($route->action == 'list') {
        if ($route->format == 'json') {
            return $tma_class->user_list();
        } else {
            return view("Modules/tma/tma_list_view.php", array());
        }
    }

    // Add a new account
    // /tma/add.json?userid=1&mpan=1234567890
    if ($route->action == 'add') {
        $route->format = "json";
        return $tma_class->user_add(
            post('userid',true),
            post('mpan',true),
            "consumption"
        );
    }

    // Remove an account
    // /tma/delete.json?userid=1
    if ($route->action == 'delete') {
        $route->format = "json";
        $userid = get('userid',true);
        return $tma_class->user_remove($userid);
    }
    
    // Load from ftp to cache
    // /tma/load_from_ftp.json
    if ($route->action == 'load_from_ftp') {
        $route->format = "json";
        return $tma_class->load_from_ftp();
    }

    // Get available MPANs
    // /tma/get_available_mpan.json
    if ($route->action == 'mpan_list') {
        $route->format = "json";
        return $tma_class->mpan_list();
    }

    // Load user data to feed
    // /tma/fetch_data.json?userid=1
    if ($route->action == 'fetch_data') {
        $route->format = "json";
        $userid = get('userid',true);
        return $tma_class->load_data_to_feed($userid,"all");
    }




    if ($route->action == 'save') {
        $route->format = "json";

        return $tma_class->save(
            483,
            "hydro_hh",
            1300060574529,
            'all'
        );
    }
}