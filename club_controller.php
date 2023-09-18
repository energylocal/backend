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
    global $mysqli, $redis, $user, $path, $session, $route , $settings;

    require_once "Modules/club/club_model.php";
    $club = new Club($mysqli,$redis,$user);

    require_once "Modules/club/tariff_model.php";
    $tariff = new Tariff($mysqli);
    
    // Linked users
    
    if ($session['admin']) {

        // API

        if ($route->action == 'create') {
            $route->format = "json";
            $name = get('name',true);
            return $club->create($name);
        }

        if ($route->action == 'delete') {
            $route->format = "json";
            $id = get('id',true);
            return $club->delete($id);
        }

        if ($route->action == 'list') {
            if ($route->format == 'json') {
                return $club->list();
            } else {
                return view("Modules/club/list_view.php");
            }
        }

        if ($route->action == 'account') {
            if ($route->subaction == 'list'){
                $route->format = "json";
                $id = get('id',true);
                return $club->account_list($id);
            }

            if ($route->subaction == 'data-status'){
                $route->format = "json";
                $id = get('id',true);

                require "Modules/feed/feed_model.php";
                $feed_class = new Feed($mysqli,$redis,$settings['feed']);
                return $club->account_data_status($id,$feed_class);
            }       

            if ($route->subaction == 'add'){
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

            if ($route->subaction == 'edit') {
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

            if ($route->subaction == 'fetch_octopus_data') {
                $route->format = "json";
                
                require "Modules/feed/feed_model.php";
                $feed = new Feed($mysqli,$redis,$settings['feed']);
                require "Modules/club/octopus_model.php";
                $octopus = new OctopusAPI($feed);

                return $octopus->fetch_data(
                    post('userid',true),
                    post('mpan',true),
                    post('meter_serial',true),
                    post('octopus_apikey',true)
                );
            }

            // fetch octopus bills
            if ($route->subaction == 'fetch_octopus_bills') {
                $route->format = "json";
                
                require "Modules/feed/feed_model.php";
                $feed = new Feed($mysqli,$redis,$settings['feed']);
                require "Modules/club/octopus_model.php";
                $octopus = new OctopusAPI($feed);

                return $octopus->fetch_bills(
                    post('userid',true),
                    post('mpan',true),
                    post('meter_serial',true),
                    post('octopus_apikey',true)
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

            // Daily consumption, time of use and use of generation data for a user
            // returns multiple days between start and end
            if ($route->subaction == 'daily') {
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

                require "Modules/club/account_data_model.php";
                $account_data = new AccountData($feed, $club, $tariff);

                return $account_data->daily_summary($userid,$start,$end);
            }

            // Monthly consumption, time of use and use of generation data for a user
            // returns multiple months between start and end
            if ($route->subaction == 'monthly') {    
                
            }

            // Custom consumption, time of use and use of generation data for a user
            // returns summary results for a custom period
            if ($route->subaction == 'custom') {    
                
            }
        }

        if ($route->action == 'tariff') {
            // Add a new tariff
            if ($route->subaction == 'create') {
                $route->format = "json";
                
                return $tariff->create(
                    post('club',true),
                    post('name',true)
                );
            }

            // Delete tariff
            if ($route->subaction == 'delete') {
                $route->format = "json";
                $id = get('id',true);
                return $tariff->delete($id);
            }

            // List tariffs
            if ($route->subaction == 'list') {
                $route->format = "json";
                $clubid =(int) get('clubid',true);
                return $tariff->list($clubid);
            }

            // List tariff periods
            if ($route->subaction == 'periods') {
                $route->format = "json";
                $id = get('id',true);
                return $tariff->list_periods($id);
            }

            // Add period
            if ($route->subaction == 'addperiod') {
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
            if ($route->subaction == 'deleteperiod') {
                $route->format = "json";
                return $tariff->delete_period(
                    get('tariffid',true),
                    get('index',true)
                );
            }

            // Save period
            if ($route->subaction == 'saveperiod') {
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
        }

        // View
    
        if ($route->action == '') {
            return view("Modules/club/club_view.php", array());
        }

        if ($route->action == 'accounts') {
            $clubid = get('clubid',false);
            if (!$club_info = $club->get($clubid)) {
                return "Club not found";
            }

            return view("Modules/club/accounts_view.php", array(
                "clubid"=>$clubid, 
                "club_name"=>$club_info->name
            ));
        }

        if ($route->action == 'tariffs') {
            $clubid = get('clubid',false);
            if (!$club_info = $club->get($clubid)) {
                return "Club not found";
            }

            return view("Modules/club/tariffs_view.php", array(
                "clubid"=>$clubid,
                "club_name"=>$club_info->name
            ));
        }
    }
    
    return false;
}
