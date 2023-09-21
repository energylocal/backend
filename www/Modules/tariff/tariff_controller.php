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

function tariff_controller()
{
    global $mysqli, $redis, $user, $path, $session, $route , $settings;

    require_once "Modules/club/club_model.php";
    $club = new Club($mysqli,$redis,$user);

    require_once "Modules/tariff/tariff_model.php";
    $tariff = new Tariff($mysqli);

    // List tariffs
    if ($route->action == 'list') {
        if ($route->format == "json") {
            $clubid =(int) get('clubid',true);
            return $tariff->list($clubid);
        } else {
            $clubid = get('clubid',false);
            if (!$club_info = $club->get($clubid)) {
                return "Club not found";
            }

            return view("Modules/tariff/tariffs_view.php", array(
                "clubid"=>$clubid,
                "club_name"=>$club_info->name
            ));
        }
    }

    // Add a new tariff
    if ($route->action == 'create') {
        $route->format = "json";
        
        return $tariff->create(
            post('club',true),
            post('name',true)
        );
    }

    // Delete tariff
    if ($route->action == 'delete') {
        $route->format = "json";
        $id = get('id',true);
        return $tariff->delete($id);
    }

    // List tariff periods
    if ($route->action == 'periods') {
        $route->format = "json";
        $id = get('id',true);
        return $tariff->list_periods($id);
    }

    // Add period
    if ($route->action == 'addperiod') {
        $route->format = "json";
        return $tariff->add_period(
            post('tariffid',true),
            post('name',true),
            post('start',true),
            post('end',true),
            post('generator',true),
            post('import',true),
            post('color',true)
        );
    }

    // Delete period
    if ($route->action == 'deleteperiod') {
        $route->format = "json";
        return $tariff->delete_period(
            get('tariffid',true),
            get('index',true)
        );
    }

    // Save period
    if ($route->action == 'saveperiod') {
        $route->format = "json";
        return $tariff->save_period(
            post('tariffid',true),
            post('index',true),
            post('name',true),
            post('start',true),
            post('end',true),
            post('generator',true),
            post('import',true),
            post('color',true)
        );
    }
    
    return false;
}
