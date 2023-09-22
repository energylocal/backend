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

    // ----- Tariffs ------

    // List tariffs
    // /tariff/list.json?clubid=1 (returns json list of tariffs) (PUBLIC)
    // /tariff/list?clubid=1 (returns html view of tariffs)
    if ($route->action == 'list') {
        if ($route->format == "json") {
            $clubid =(int) get('clubid',true);
            return $tariff->list($clubid);

        } else if ($session['admin']) {
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
    // /tariff/create, POST BODY club=1&name=MyTariff, (returns json success or fail)
    if ($route->action == 'create' && $session['admin']) {
        $route->format = "json";
        return $tariff->create(
            post('club',true),
            post('name',true)
        );
    }

    // Delete tariff
    // /tariff/delete?id=1 (returns json success or fail)
    if ($route->action == 'delete' && $session['admin']) {
        $route->format = "json";
        $id = get('id',true);
        return $tariff->delete($id);
    }

    // ----- Periods ------

    // List tariff periods (PUBLIC)
    // /tariff/periods?id=1 (returns json list of periods)
    if ($route->action == 'periods') {
        $route->format = "json";
        $id = get('id',true);
        return $tariff->list_periods($id);
    }

    // Add period
    // /tariff/addperiod, POST BODY tariffid=1&name=MyPeriod&weekend=0&start=0&generator=15&import=20&color=#000 (returns json success or fail)
    if ($route->action == 'addperiod' && $session['admin']) {
        $route->format = "json";
        return $tariff->add_period(
            post('tariffid',true),
            post('name',true),
            post('weekend',true),
            post('start',true),
            post('generator',true),
            post('import',true),
            post('color',true)
        );
    }

    // Delete period
    // /tariff/deleteperiod?tariffid=1&index=0 (returns json success or fail)
    if ($route->action == 'deleteperiod' && $session['admin']) {
        $route->format = "json";
        return $tariff->delete_period(
            get('tariffid',true),
            get('index',true)
        );
    }

    // Save period
    // /tariff/saveperiod, POST BODY tariffid=1&index=0&name=MyPeriod&weekend=0&start=0&generator=15&import=20&color=#000 (returns json success or fail)
    if ($route->action == 'saveperiod' && $session['admin']) {
        $route->format = "json";
        return $tariff->save_period(
            post('tariffid',true),
            post('index',true),
            post('name',true),
            post('weekend',true),
            post('start',true),
            post('generator',true),
            post('import',true),
            post('color',true)
        );
    }
    
    return false;
}
