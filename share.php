<?php
/*
For each club
    Get all accounts
    For each account
        Get use_hh_octopus feed
        If feed exists
            Load feed
            Create shared generation feed
    Find oldest start time and most recent end time
    Calculate start positions
    Get number of feeds in aggregation
    Create buffer for each shared generation feed
    For each data point in time range
        Read all demand feeds, return null if no data point
        Calculate hydro share per user
        Itterate through each household subtracting hydro share
        Write allocated hydro to buffers for each user
    Write shared generation feeds
    Write club demand feed
*/


chdir("/var/www/energylocal");
require "Lib/load_emoncms.php";

require_once "Modules/club/club_model.php";
$club_class = new Club($mysqli, $user);

require_once "Modules/tariff/tariff_model.php";
$tariff = new Tariff($mysqli);

require_once "Modules/account/account_model.php";
$account_class = new Account($mysqli, $user, $tariff);

$helper = new FeedHelper($feed);

// get all clubs
$clubs = $club_class->list();
foreach ($clubs as $club) 
{
    echo $club->id . " " . $club->name . "\n";

    $ts = new FeedGroup($feed);

    // get club accounts
    $accounts = $account_class->list($club->id);

    $gen_feeds = array();
    $gen_buffer = array();
    $gen_buffer_start = array();

    // load demand feeds
    foreach ($accounts as $account) {
        echo "  " . $account->userid . " " . $account->username . "\n";
        if ($feedid = $feed->get_id($account->userid,"use_hh_octopus")) {
            $ts->load($feedid);

            // Create shared generation feed
            $gen_feeds[] = $helper->get_or_create($account->userid, "energylocal", "shared_gen_hh", 1800);
            $gen_buffer[] = "";
        }
    }

    // Find start and end time
    $range = $ts->find_start_end();

    // Recalculate only the last 7 days
    $range->start = $range->end - (3600*24*7);
   
    // Calculate start positions
    $ts->seek_to_time($range->start);

    // Get number of feeds in aggregation
    $feed_num = $ts->num();

    // For each time step
    $output = "";
    for ($time = $range->start; $time < $range->end; $time += 1800)
    {
        $use_hh = array();
        $users_to_share = 0;

        $club_use_hh = 0;
        for ($i=0; $i<$feed_num; $i++) {
            // Read value from feed
            $val = $ts->read($i);
            if ($val!=null) $club_use_hh += $val;
            $use_hh[] = $val;

            if ($val>0) $users_to_share++;
        }

        // Write to output
        $output .= pack("f",$club_use_hh);

        // -----------------------------------------
        // SHARING ALGORITHM
        // -----------------------------------------
        $hydro = 1;
        $spare_hydro = $hydro;
        $import_hh = $use_hh;

        while ($spare_hydro>0.0 && $users_to_share) 
        {
            // Calculate hydro share per user
            $hydro_share = $spare_hydro / $users_to_share;

            // Itterate through each household subtracting hydro share
            $spare_hydro = 0;
            $users_to_share = 0;
            for ($i=0; $i<$feed_num; $i++) {
                $balance = $import_hh[$i];
                
                if ($balance>0) {
                    $balance -= $hydro_share;
                    if ($balance<0) {
                        $remainder = $balance * -1;
                        $spare_hydro += $remainder;
                        $balance = 0;
                    } else {
                        $users_to_share++;
                    }
                }
                $import_hh[$i] = $balance;
            }
        }

        // Write allocated hydro to buffers for each user
        for ($i=0; $i<$feed_num; $i++) {
            $from_gen_hh = $use_hh[$i] - $import_hh[$i];

            // check overhead for this range and empty check?
            if ($time>=$ts->meta[$i]->start_time && $time<=$ts->meta[$i]->end_time) {
                if ($gen_buffer[$i]=="") $gen_buffer_start[$i] = $time;
                $gen_buffer[$i] .= pack("f",$from_gen_hh);
            }
        }
    }

    // Write shared generation feeds
    for ($i=0; $i<$feed_num; $i++) {
        $helper->write($gen_feeds[$i],$gen_buffer_start[$i],1800,$gen_buffer[$i]);
    }

    // Write club demand feed
    $feedid = $helper->get_or_create($club->userid, "energylocal", "club_demand_hh", 1800);
    $helper->write($feedid,$range->start,1800,$output);
}

class FeedGroup
{
    private $dir = "/var/opt/emoncms/phpfina/";
    private $feed;

    public $meta = array();
    private $npoints = array();
    private $data = array();
    private $pos = array();
    
    // constructor
    public function __construct($feed)
    {
        $this->feed = $feed;
    }

    // load feed into memory
    public function load($id)
    {
        // a. load meta
        $meta = $this->feed->get_meta($id);
        $this->meta[] = $meta;
        $this->npoints[] = $meta->npoints;

        // b. load data
        $fh = fopen($this->dir.$id.".dat", 'rb');
        $this->data[] = fread($fh, $meta->npoints*4);
        fclose($fh);
    }

    // return number of feeds
    public function num()
    {
        return count($this->meta);
    }

    // find start and end time
    public function find_start_end()
    {
        $start = 0;
        $end = 0;
        foreach ($this->meta as $meta) {
            if ($meta->npoints) {
                if ($meta->start_time < $start || $start==0) $start = $meta->start_time;
                if ($meta->end_time> $end) $end = $meta->end_time;
            }
        }
        $npoints = floor(($end - $start) / 1800);
        return (object) ['start' => $start, 'end' => $end, 'npoints' => $npoints];
    }

    // calculate positions at time
    public function seek_to_time($time)
    {
        $this->pos = array();
        foreach ($this->meta as $meta) {
            $pos = floor(($time - $meta->start_time) / $meta->interval);
            $this->pos[] = $pos;
            print "- $pos\n";
        }
    }

    // read 
    public function read($index) {
        $pos = $this->pos[$index];

        $val = null;
        if ($pos>=0 && $pos<$this->npoints[$index]) {
            $tmp = unpack("f",substr($this->data[$index],$pos*4,4));
            if (!is_nan($tmp[1])) {
                $val = $tmp[1];
            }
        }
        $this->pos[$index]++;
        return $val;
    }
}

class FeedHelper
{
    private $feed;
    private $dir = "/var/opt/emoncms/phpfina/";

    // constructor
    public function __construct($feed)
    {
        $this->feed = $feed;
    }

    // get feed, create if not exist
    public function get_or_create($userid, $tag, $name, $interval)
    {
        if (!$feedid = $this->feed->get_id($userid, $name)) {
            $options = (object) ['interval' => $interval];
            $result = $this->feed->create($userid, $tag, $name, 5, $options);
            if (!$result['success']) {
                echo json_encode($result) . "\n";
                return false;
            }
            $feedid = $result['feedid'];
        }
        return $feedid;
    }

    // write feed to disk
    public function write($id,$start,$interval,$data)
    {
        // if the feed already exists
        $meta = $this->feed->get_meta($id);
        print json_encode($meta) . "\n";

        // if start time not set, set start time
        if ($meta->start_time==0) {
            $this->write_meta($id,$start,$interval);
            $seek = 0;
        }
        // if data starts before feed start time
        // clear data and write new meta
        else if ($start<$meta->start_time) {
            $this->write_meta($id,$start,$interval);
            unlink($this->dir.$id.".dat"); // clear data
            $seek = 0;
        } else {
            // seek to start_time
            $seek = floor(($start - $meta->start_time) / $meta->interval);
        }

        // b. write data
        $fh = fopen($this->dir.$id.".dat", 'c+');
        fseek($fh, $seek*4);
        fwrite($fh, $data);
        fclose($fh);
    }

    public function write_meta($id,$start,$interval)
    {
        $fh = fopen($this->dir.$id.".meta", 'wb');
        fwrite($fh,pack("I",0));
        fwrite($fh,pack("I",0));
        fwrite($fh,pack("I",$interval));
        fwrite($fh,pack("I",$start));
        fclose($fh);
    }
}