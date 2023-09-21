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

function account_controller()
{
    global $mysqli, $redis, $user, $path, $session, $route , $settings;

    require_once "Modules/club/club_model.php";
    $club = new Club($mysqli,$redis,$user);

    require_once "Modules/account/account_model.php";
    $account = new Account($mysqli,$redis,$user);

    require_once "Modules/tariff/tariff_model.php";
    $tariff = new Tariff($mysqli);
    
    // Linked users
    
    if ($session['admin']) {

        if ($route->action == 'list') {
            if ($route->format == 'json') {
                $clubid = get('clubid',false);
                return $account->list($clubid);
            } else {

                $clubid = get('clubid',false);
                if (!$club_info = $club->get($clubid)) {
                    return "Club not found";
                }

                return view("Modules/account/account_list_view.php", array(
                    "clubid"=>$clubid, 
                    "club_name"=>$club_info->name
                ));
            }
        }

        if ($route->action == 'view') {
            $userid = get('userid',true);
            
            $clubid = $account->get_club_id($userid);
            $club_name = $club->get_name($clubid);
            
            return view("Modules/account/account_view.php", array(
                "userid"=>$userid,
                "clubid"=>$clubid,
                "club_name"=>$club_name
            ));
        }
        
        if ($route->action == 'get') {
            $route->format = "json";
            $userid = get('userid',true);
            return $account->get($userid);
        }

        if ($route->action == 'add'){
            $route->format = "json";
            return $club->add_account(
                post('id',true),
                post('username',true),
                post('password',true),
                post('email',true),
                post('mpan',true),
                post('cad_serial',true),
                post('octopus_apikey',true),
                post('meter_serial',true)
            );
        }

        if ($route->action == 'edit') {
            $route->format = "json";
            return $club->edit_account(
                post('id',true),
                post('userid',true),
                post('username',true),
                post('email',true),
                post('mpan',true),
                post('cad_serial',true),
                post('octopus_apikey',true),
                post('meter_serial',true)
            );
        }

        // Set users current tariff
        if ($route->subaction == 'set_tariff') {
            $route->format = "json";
            return $club->set_user_tariff(
                get('userid',true),
                get('tariffid',true)
            );
        }

        if ($route->action == 'data-status') {
            $route->format = "json";
            $clubid = get('clubid',true);

            require "Modules/feed/feed_model.php";
            $feed_class = new Feed($mysqli,$redis,$settings['feed']);
            return $club->account_data_status($clubid,$feed_class);
        }
    }
    
    return false;
}
