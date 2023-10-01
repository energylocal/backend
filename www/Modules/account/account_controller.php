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
    $club = new Club($mysqli, $user);

    require_once "Modules/tariff/tariff_model.php";
    $tariff = new Tariff($mysqli);

    require_once "Modules/account/account_model.php";
    $account = new Account($mysqli,$user,$tariff);

    // Admin access required
    if (!$session['admin']) return array('content'=>false, 'message'=>'Admin access required');

    // Return list of club accounts
    // /account/list.json?clubid=1 (returns json list of accounts)
    // /account/list?clubid=1 (returns html view)
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

    // Add a new account
    // url: /account/add.json
    // post body: clubid=1&username=abc&password=123&email=abc@abc
    // return: json success or fail
    if ($route->action == 'add') {
        if ($route->format == 'json') {
            return $account->add_account(
                post('clubid',true),
                post('username',true),
                post('password',true),
                post('email',true)
            );
        } else {
            // Account view html
            // url: /account/add?clubid=1
            // return: html view
            $clubid = get('clubid',true);
            $club_name = $club->get_name($clubid);

            return view("Modules/account/account_view.php", array(
                "mode"=>"add",
                "userid"=>0,
                "clubid"=>$clubid,
                "club_name"=>$club_name
            ));
        }
    }

    // Edit account
    // url: /account/edit.json
    // post body: clubid=1&username=abc&email=abc@abc
    // return: json success or fail
    if ($route->action == 'edit' && $route->format == 'json') {
        return $account->edit_account(
            post('userid',true),
            post('username',true),
            post('email',true)
        );
    }

    // Account view/edit
    // url: /account/view?userid=1
    // return: html view 
    if ($route->action == 'view' || $route->action == 'edit') {
        $userid = get('userid',true);
        
        $clubid = $account->get_club_id($userid);
        $club_name = $club->get_name($clubid);
        
        return view("Modules/account/account_view.php", array(
            "mode"=>"edit",
            "userid"=>$userid,
            "clubid"=>$clubid,
            "club_name"=>$club_name
        ));
    }
    
    // Get account info
    // url: /account/get.json?userid=1
    // return: json account info
    if ($route->action == 'get') {
        $route->format = "json";
        $userid = get('userid',true);
        return $account->get($userid);
    }
    
    return false;
}
