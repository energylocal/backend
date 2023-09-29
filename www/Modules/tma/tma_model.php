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
    private $mysqli;
    private $redis;
    private $feed;
    
    private $ftp_dir = "/var/opt/tma/data";
    
    private $mpan_errors = array();
    private $mpan_data = false;

    public function __construct($mysqli, $redis, $feed)
    {
        $this->mysqli = $mysqli;
        $this->redis = $redis;
        $this->feed = $feed;
    }

    public function user_list($clubid=false) {
        $clubid = (int) $clubid;
        
        // get all ocotpus_users link to club_accounts by userid and users table by id  |  WHERE club_accounts.clubid=$clubid
        $result = $this->mysqli->query("SELECT * FROM tma_users INNER JOIN users ON tma_users.userid=users.id INNER JOIN club_accounts ON users.id=club_accounts.userid");
        $accounts = array();
        while ($row = $result->fetch_object()) {
            // Get data status
            if ($status = $this->account_data_status($row->userid)) {
                $row->days = $status['days'];
                $row->updated = $status['updated'];
                $row->feedid = $status['feedid'];
            }

            $accounts[] = $row;
        }
        return $accounts;
    }

    public function user_get($userid) {
        $userid = (int) $userid;
        $result = $this->mysqli->query("SELECT * FROM tma_users WHERE userid=$userid");
        if ($row = $result->fetch_object()) {
            return $row;
        } else {
            return false;
        }
    }

    public function user_add($userid,$mpan,$type) {
        // Validate input
        $userid = (int) $userid;

        // Default type
        $type = "consumption";

        // Validate input
        $result = $this->validate($mpan);
        if (!$result['success']) {
            return $result;
        }

        // Check if user already exists
        if ($this->user_exists($userid)) {
            return array("success"=>false,"message"=>"User already exists");
        }

        $stmt = $this->mysqli->prepare("INSERT INTO tma_users (userid,mpan,type) VALUES (?,?,?)");
        $stmt->bind_param("iss",$userid,$mpan,$type);
        $result = $stmt->execute();
        $stmt->close();

        if ($result) {
            return array("success"=>true,"message"=>"User added");
        } else {
            return array("success"=>false,"message"=>"Error adding user");
        }
    }
    
    public function user_edit($userid,$mpan,$type) {
        // Validate input
        $userid = (int) $userid;

        // Validate input
        $result = $this->validate($mpan);
        if (!$result['success']) {
            return $result;
        }

        // Default type
        $type = "consumption";

        // Check if user already exists
        if (!$this->user_exists($userid)) {
            return array("success"=>false,"message"=>"User does not exist");
        }
        
        // Update user
        $stmt = $this->mysqli->prepare("UPDATE tma_users SET mpan=?, type=? WHERE userid=?");
        $stmt->bind_param("ssi",$mpan,$type,$userid);
        $result = $stmt->execute();
        $stmt->close();

        if ($result) {
            return array("success"=>true,"message"=>"User updated");
        } else {
            return array("success"=>false,"message"=>"Error updating user");
        }
    }
    
    public function user_remove($userid) {
        // Validate input
        $userid = (int) $userid;

        // Check if user already exists
        if (!$this->user_exists($userid)) {
            return array("success"=>false,"message"=>"User does not exist");
        }

        $this->mysqli->query("DELETE FROM tma_users WHERE userid=$userid");
        return array("success"=>true,"message"=>"User removed");
    }

    public function user_exists($userid) {
        $userid = (int) $userid;
        $result = $this->mysqli->query("SELECT userid FROM tma_users WHERE userid=$userid");
        if ($row = $result->fetch_object()) {
            return true;
        } else {
            return false;
        }
    }

    // -------------------------------------------------------------------------

    public function account_data_status($userid) {
        $userid = (int) $userid;

        if ($feedid = $this->feed->get_id($userid,"gen_hh_tma")) {
            if ($meta = $this->feed->get_meta($feedid)) {
                $days = $meta->npoints / 48;
                $updated = (time() - ($meta->start_time + ($meta->npoints*$meta->interval)))/86400;   
                return array('days'=>$days,'updated'=>$updated, 'feedid'=>$feedid);
            }
        }
        return false;
    }
    
    public function load_from_ftp() {
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
        
        return array("success"=>true,"message"=>"Loaded from ftp", "mpan_errors"=>$this->mpan_errors);
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

    public function save_data($userid, $days=7) {

        // Load user entry, get mpan
        $userid = (int) $userid;
        if (!$result = $this->user_get($userid)) {
            return array("success" => false, "message" => "User does not exist");
        }
        $mpan = $result->mpan;

        // load from cache
        if (!$this->mpan_data) {
            $this->mpan_data = json_decode($this->redis->get("tmadata"),true);
            if (!$this->mpan_data) return false;
        }

        // Get tma feed id or create feed
        if (!$feedid = $this->feed->get_id($userid, "gen_hh_tma")) {
            $result = $this->feed->create($userid, "energylocal", "gen_hh_tma", 5, json_decode('{"interval":1800}'));
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
                return array("success" => true, "message" => "Saved $npoints points");
            }
        }

        return array("success" => false);
    }

    private function validate($mpan) {

        // Validate MPAN
        if (strlen("$mpan") != 13) {
            return array("success" => false, "message" => "Invalid MPAN, must be 13 digits $mpan");
        }
        if ($mpan != (int)$mpan) {
            return array("success" => false, "message" => "Invalid MPAN, must be numeric $mpan");
        }

        return array("success" => true, "message" => "Validated");
    }
}
