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

function club_controller()
{
    global $mysqli, $session, $route, $settings, $redis;

    require_once "Modules/club/club_model.php";
    $club = new Club($mysqli);

    // API
    // List all clubs, Public
    // /club/list.json (returns json list of clubs)
    // /club/list (returns html list of clubs)
    if ($route->action == 'list') {
        if ($route->format == "json") {
            return $club->list();
        } else if ($session['admin']) {
            return view("Modules/club/club_admin_view.php", array());
        }
    }

    // Create a new club, admin only
    // /club/create.json (returns json success and clubid or fail)
    if ($route->action == 'create' && $session['admin']) {
        $route->format = "json";
        $name = get('name', true);
        return $club->create($name);
    }

    // Delete club, admin only
    // /club/delete.json?id=1 (returns json success or fail)
    if ($route->action == 'delete' && $session['admin']) {
        $route->format = "json";
        $id = get('id', true);
        return $club->delete($id);
    }

    // EnergyLocal app
    if ($route->action == 'app') {

        $userid = $session['userid'];

        $club = 'bethesda';
        $club_settings = array(
            "bethesda" => array(
                "club_id" => 1,
                "name" => "Bethesda",
                "generator" => "hydro",
                "generator_color" => "#29aae3",
                "export_color" => "#a5e7ff",
                "languages" => array("cy", "en"),
                "generation_feed" => 1,
                "consumption_feed" => 1645,
                "generation_forecast_feed" => 2057,
                "consumption_forecast_feed" => 2058,
                "unitprice_comparison" => 0.3310,
                "gen_scale" => 1.0
            )
        );

        global $translation;
        $translation = array();
        $session['feeds'] = array(
            // hub_use
            // meter_power
            // use_hh_est
            // gen_hh
        );

        require_once "Modules/tariff/tariff_model.php";
        $tariff_class = new Tariff($mysqli);

        $tariffid = $tariff_class->get_user_tariff($userid);
        $tariffs = $tariff_class->list_periods($tariffid);
        $tariffs_table = $tariff_class->getTariffsTable($tariffs);

        require "Modules/feed/feed_model.php";
        $feed = new Feed($mysqli,$redis,$settings['feed']);

        require "Modules/data/account_data_model.php";
        $account_data = new AccountData($feed, $club, $tariff_class);

        return view("Modules/club/app/client_view.php", array(
            'session' => $session,
            'club' => $club,
            'club_settings' => $club_settings[$club],
            'tariffs_table' => $tariffs_table,
            'tariffs' => $tariffs,
            'available_reports' => $account_data->get_available_reports($userid)
        ));
    }
    return false;
}

function t($s)
{
    return $s;
}

function translate($s, $lang)
{
    global $translation;

    if (isset($translation->$lang) && isset($translation->$lang->$s)) {
        return $translation->$lang->$s;
    } else {
        return $s;
    }
}

function datetime_strtotime($str)
{
    $date = new DateTime($str);
    $date->setTimezone(new DateTimeZone("Europe/London"));
    return $date->getTimestamp();
}
