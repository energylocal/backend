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
    require "Modules/feed/feed_model.php";
    $feed = new Feed($mysqli,$redis,$settings['feed']);

    require_once "Modules/tma/tma_model.php";
    $tma_class = new TMA($redis, $feed);

    if ($route->subaction == 'load') {
        $route->format = "json";
        $tma_class->load();
        return $tma_class->mpan_list();
    }
    
    if ($route->subaction == 'list') {
        $route->format = "json";
        return $tma_class->mpan_list();  
    }

    if ($route->subaction == 'save') {
        $route->format = "json";

        return $tma_class->save(
            483,
            "hydro_hh",
            1300060574529,
            'all'
        );
    }
}