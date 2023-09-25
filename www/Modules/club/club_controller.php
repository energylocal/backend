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
    global $mysqli, $session, $route;

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
        $name = get('name',true);
        return $club->create($name);
    }

    // Delete club, admin only
    // /club/delete.json?id=1 (returns json success or fail)
    if ($route->action == 'delete' && $session['admin']) {
        $route->format = "json";
        $id = get('id',true);
        return $club->delete($id);
    }
    
    return false;
}
