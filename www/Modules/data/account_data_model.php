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

class AccountData
{
    private $feed;
    private $club;
    private $tariff;
    private $log;

    public function __construct($feed, $club, $tariff)
    {
        $this->feed = $feed;
        $this->club = $club;
        $this->tariff = $tariff;
        $this->log = new EmonLogger(__FILE__);
    }

    public function daily_summary($userid,$start,$end) 
    {
        $userid = (int) $userid;
        $start = (int) $start;
        $end = (int) $end;

        // Load tariff history for user
        $tariff_history = $this->tariff->get_user_tariff_history($userid);

        // Load tariff bands for each tariff
        foreach ($tariff_history as $tariff) {
            $tariff->bands = $this->tariff->list_periods($tariff->tariffid);
        }

        // Check if user has consumption feed
        // Load half hourly data between start and end times
        if (!$use_feedid = $this->feed->get_id($userid,"use_hh_octopus")) {
            return array("success"=>false, "message"=>"Missing consumption feed");
        }
        $use_data = $this->feed->get_data($use_feedid,$start,$end,1800,0,"Europe/London","notime",false,0,0,false,-1);
        
        // Check if user has generation feed
        // Load generation data between start and end times
        if (!$gen_feedid = $this->feed->get_id($userid,"gen_hh")) {
            // No generation feed, create empty array
            $gen_data = array();
            for ($i=0; $i<count($use_data); $i++) $gen_data[$i] = 0;
        } else {
            $gen_data = $this->feed->get_data($gen_feedid,$start,$end,1800,0,"Europe/London","notime",false,0,0,false,-1);
        }

        $date = new DateTime();
        $date->setTimezone(new DateTimeZone("Europe/London")); 
        $date->setTimestamp($start);

        $year = (int) $date->format("Y");
        $month = (int) $date->format("m");
        $day = (int) $date->format("d");
        $hour = (int) $date->format("H");

        $period_allocation = array();
        $daily = array();

        // Keys to sum
        $categories = array("demand","generation","import","generation_cost","import_cost","cost");

        $n=0;
        for ($time=$start; $time<=$end; $time+=1800) {
            $date->setTimestamp($time);

            $last_year = $year;
            $last_month = $month;
            $last_day = $day;

            $year = (int) $date->format("Y");
            $month = (int) $date->format("m");
            $day = (int) $date->format("d");
            $hour = (int) $date->format("H");
            
            // Slice data by day
            $slice = false;  
            if ($last_day!=$day) $slice = true;
            if ($time==$end) $slice = true;

            if ($slice) {
                // roll back to last day
                $date->setDate($last_year,$last_month,$last_day);
                $date->setTime(0,0,0);

                // date_str is used in output to identify the day
                $Ymd = $date->format("Y-m-d");

                $daily[$Ymd] = array();
                $daily[$Ymd]['time'] = $date->getTimestamp();

                // 1. Initialise
                $totals = array();
                foreach ($categories as $key) {
                    // these will hold daily totals per band
                    $daily[$Ymd][$key] = array();
                    // these will hold daily totals for all bands
                    $totals[$key] = 0;
                }

                // 2. Breakdown by tariff band
                foreach ($period_allocation as $name=>$breakdown) {
                    // Allocate by key
                    foreach ($categories as $key) {
                        $totals[$key] += $breakdown[$key];
                        $daily[$Ymd][$key][$name] = $this->fixed($breakdown[$key],3);
                    }
                }

                // 3. Calculate totals
                foreach ($totals as $key=>$value) {
                    $daily[$Ymd][$key]['total'] = $this->fixed($totals[$key],3);
                }
                
                $period_allocation = array();
            }
            
            // Get use and generation for this time
            // calculate import
            $use = $use_data[$n];
            if ($use==null) $use = 0;
            
            $gen = $gen_data[$n];
            if ($gen==null) $gen = 0;

            if ($gen>$use) $gen = $use;
            $import = $use - $gen;

            // Get tariff bands for this time
            $bands = $this->get_tariff_bands($tariff_history,$time);
            $band = $this->get_tariff_band($bands,$hour);

            // initialise period allocation
            if (!isset($period_allocation[$band->name])) {
                $period_allocation[$band->name] = array();
                foreach ($categories as $key) {
                    $period_allocation[$band->name][$key] = 0;
                }
            }

            // add to period allocation, kwh
            $period_allocation[$band->name]['demand'] += $use;
            $period_allocation[$band->name]['generation'] += $gen;
            $period_allocation[$band->name]['import'] += $import;

            // add to period allocation, costs
            $period_allocation[$band->name]['generation_cost'] += $gen*$band->generator*0.01;
            $period_allocation[$band->name]['import_cost'] += $import*$band->import*0.01;
            $period_allocation[$band->name]['cost'] += ($gen*$band->generator*0.01) + ($import*$band->import*0.01);

            $n++; // increment data index
        }
        
        return $daily;
    }

    public function monthly_summary($userid) 
    {

    }

    public function custom_summary($userid) 
    {

    }

    // Get tariff bands for a given time
    public function get_tariff_bands($tariff_history,$time) {
        $bands = array();
        foreach ($tariff_history as $tariff) {
            if ($time>=$tariff->start) {
                $bands = $tariff->bands;
            }
        }
        return $bands;
    }

    // Get tariff band for a given hour
    public function get_tariff_band($bands,$hour) {

        // Work out which tariff period this hour falls into
        for ($i=0; $i<count($bands); $i++) {
            $start = (float) $bands[$i]->start;

            // calculate end
            $next = $i+1;
            if ($next==count($bands)) $next=0;
            $end = (float) $bands[$next]->start;

            // if start is less than end then period is within a day
            if ($start<$end) {
                if ($hour>=$start && $hour<$end) {
                    return $bands[$i];
                }
            // if start is greater than end then period is over midnight
            } else if ($end<$start) {
                if ($hour>=$start || $hour<$end) {
                    return $bands[$i];
                }
            // if start is equal to end then period is 24 hours
            // flat rate tariff
            } else if ($start==$end) {
                return $bands[$i];
            }
        }
        return false;
    }

    public function fixed($value,$dp) {
        return number_format($value,$dp)*1;
    }
}