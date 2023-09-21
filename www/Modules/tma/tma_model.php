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

class TMA
{
    private $redis;
    private $feed;
    
    private $ftp_dir = "/var/opt/tma/data";
    
    private $mpan_errors = array();
    private $mpan_data = false;

    public function __construct($redis, $feed)
    {
        $this->redis = $redis;
        $this->feed = $feed;
    }
    
    public function load() {
        $this->mpan_errors = array();

        // this will hold an associative array of MPANs and their data
        $mpan_data = array();

        // if rebuild clear processed files
        // if ($rebuild) {
            // file_put_contents("$output_dir/processed.log","");
        // }

        // load processed files (not needed)
        // $already_processed = get_processed_files($output_dir);

        $files = scandir($this->ftp_dir);
        for ($i=2; $i<count($files); $i++) {
            $filename = $files[$i];

            // check if file extension is .csv
            $ext = pathinfo($filename, PATHINFO_EXTENSION);
            if ($ext != 'csv') continue;

            // check if file has already been processed (not needed)
            // if (isset($already_processed[$filename])) continue;

            // process file
            $mpan_data = $this->process_file($filename, $mpan_data);

            // log processed file (not needed)
            // file_put_contents("$output_dir/processed.log",$filename."\n",FILE_APPEND);
        }

        // sort mpan data by timestamp
        foreach ($mpan_data as $mpan=>$data) {
            ksort($mpan_data[$mpan]);
        }
        
        // cache in redis and locally
        $this->redis->set("tmadata",json_encode($mpan_data));
        $this->mpan_data = $mpan_data;
        
        return $mpan_data;
    }
    
    public function mpan_list() {
        if (!$this->mpan_data) {
            $this->mpan_data = json_decode($this->redis->get("tmadata"),true);
            if (!$this->mpan_data) return array();
        }
        return array_keys($this->mpan_data);
    }
    
    private function process_file($filename, $mpan_data) {

        $date = new DateTime();
        $date->setTimezone(new DateTimeZone("UTC"));
        $date->setTime(0,0,0);

        $content = file_get_contents($this->ftp_dir."/".$filename);
        $lines = explode("\n",$content);
        for ($l=0; $l<count($lines); $l++) {
            $line = explode(",",trim($lines[$l]));

            // check if mpan contains letters
            $mpan = $line[0];
            if (preg_match('/[a-zA-Z]/', $mpan)) {
                $this->mpan_errors[$mpan] = 1;
                continue;
            }
            $mpan = (int) $mpan;
     
            if (count($line)==54) {
                $datestr = $line[5];
                $date_parts = explode("/",$datestr);
                if (count($date_parts)==3) {
                    $date->setDate($date_parts[2],$date_parts[1],$date_parts[0]);
                    $date->setTime(0,0,0);
                    
                    if ($line[4]=="AE" || $line[4]=="AI") {
                        // print $mpan." ".$line[4]." ".$line[5]."\n";
                        for ($hh=0; $hh<48; $hh++) {
                            $time = $date->getTimestamp() + $hh*1800;
                            
                            $index = ($hh)+6;
                            $value = trim($line[$index]);

                            $mpan_data[$mpan][$time] = $value;
                        }
                    } else {
                        // print $mpan." not AE\n";
                    }
                }
            }
            
            if (count($line)==99) {
                $datestr = $line[1];
                $date_parts = explode("/",$datestr);
                if (count($date_parts)==3) {
                    $date->setDate($date_parts[2],$date_parts[1],$date_parts[0]);
                    $date->setTime(0,0,0);
                                  
                    if ($line[2]=="AE" || $line[2]=="AI") {
                        // print $mpan." ".$line[2]." ".$line[1]."\n";
                        for ($hh=0; $hh<48; $hh+=2) {
                            $time = $date->getTimestamp() + $hh*1800;
                            
                            $index = ($hh)+3;
                            $value = trim($line[$index]);

                            $mpan_data[$mpan][$time] = $value;
                        }
                    } else {
                        // print $mpan." not AE\n";
                    }
                }
            }
        }

        return $mpan_data;
    }

    public function save($userid, $feedname, $mpan, $days=7) {

        // load from cache
        if (!$this->mpan_data) {
            $this->mpan_data = json_decode($this->redis->get("tmadata"),true);
            if (!$this->mpan_data) return false;
        }

        // Get tma feed id or create feed
        if (!$feedid = $this->feed->get_id($userid, $feedname)) {
            $result = $this->feed->create($userid, "energylocal", $feedname, 5, json_decode('{"interval":1800}'));
            if (!$result['success']) return $result;
            $feedid = $result['feedid'];
        }

        $npoints = 0;

        // if the mpan is in the mpan data
        if (isset($this->mpan_data[$mpan])) {
            // get the last time value for the feed
            $timevalue = $this->feed->get_timevalue($feedid);

            $data = array();
            
            // for each time value in the mpan data
            foreach ($this->mpan_data[$mpan] as $time=>$value) {
                // if rebuild is true or the time value is newer than the last time value
                if ($days=='all' || $time>($timevalue['time']-($days*24*3600))) {  
                    $data[] = array($time,$value);
                    $npoints ++;
                }
            }

            if (count($data)>0) {
                $this->feed->post_multiple($feedid,$data);
            }
        }
    }
}
