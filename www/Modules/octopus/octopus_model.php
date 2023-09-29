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

class OctopusAPI
{
    private $mysqli;
    private $feed;

    public function __construct($mysqli, $feed)
    {
        $this->mysqli = $mysqli;
        $this->feed = $feed;
    }
    
    public function user_list($clubid=false) {
        $clubid = (int) $clubid;
        
        // get all ocotpus_users link to club_accounts by userid and users table by id  |  WHERE club_accounts.clubid=$clubid
        $result = $this->mysqli->query("SELECT * FROM octopus_users INNER JOIN users ON octopus_users.userid=users.id INNER JOIN club_accounts ON users.id=club_accounts.userid");
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
        $result = $this->mysqli->query("SELECT * FROM octopus_users WHERE userid=$userid");
        if ($row = $result->fetch_object()) {
            return $row;
        } else {
            return false;
        }
    }
    
    public function user_add($userid,$mpan,$meter_serial,$octopus_apikey,$type) {
        // Validate input
        $userid = (int) $userid;

        // Default type
        $type = "consumption";

        // Validate input
        $result = $this->validate($mpan, $meter_serial, $octopus_apikey);
        if (!$result['success']) {
            return $result;
        }

        // Check if user already exists
        if ($this->user_exists($userid)) {
            return array("success"=>false,"message"=>"User already exists");
        }

        $stmt = $this->mysqli->prepare("INSERT INTO octopus_users (userid,mpan,meter_serial,octopus_apikey,type) VALUES (?,?,?,?,?)");
        $stmt->bind_param("issss",$userid,$mpan,$meter_serial,$octopus_apikey,$type);
        $result = $stmt->execute();
        $stmt->close();

        if ($result) {
            return array("success"=>true,"message"=>"User added");
        } else {
            return array("success"=>false,"message"=>"Error adding user");
        }
    }
    
    public function user_edit($userid,$mpan,$meter_serial,$octopus_apikey,$type) {
        // Validate input
        $userid = (int) $userid;

        // Validate input
        $result = $this->validate($mpan, $meter_serial, $octopus_apikey);
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
        $stmt = $this->mysqli->prepare("UPDATE octopus_users SET mpan=?, meter_serial=?, octopus_apikey=?, type=? WHERE userid=?");
        $stmt->bind_param("ssssi",$mpan,$meter_serial,$octopus_apikey,$type,$userid);
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

        $this->mysqli->query("DELETE FROM octopus_users WHERE userid=$userid");
        return array("success"=>true,"message"=>"User removed");
    }

    public function user_exists($userid) {
        $userid = (int) $userid;
        $result = $this->mysqli->query("SELECT userid FROM octopus_users WHERE userid=$userid");
        if ($row = $result->fetch_object()) {
            return true;
        } else {
            return false;
        }
    }
    
    // ---------------------------------------------------
    
    public function account_data_status($userid) {
        $userid = (int) $userid;

        if ($feedid = $this->feed->get_id($userid,"use_hh_octopus")) {
            if ($meta = $this->feed->get_meta($feedid)) {
                $days = $meta->npoints / 48;
                $updated = (time() - ($meta->start_time + ($meta->npoints*$meta->interval)))/86400;   
                return array('days'=>$days,'updated'=>$updated, 'feedid'=>$feedid);
            }
        }
        return false;
    }
    

    public function fetch_data($userid)
    {
        $userid = (int) $userid;
        if (!$result = $this->user_get($userid)) {
            return array("success" => false, "message" => "User does not exist");
        }
        $mpan = $result->mpan;
        $meter_serial = $result->meter_serial;
        $octopus_apikey = $result->octopus_apikey;

        // Get octopus feed id or create feed
        if (!$feedid = $this->feed->get_id($userid, "use_hh_octopus")) {
            $result = $this->feed->create($userid, "energylocal", "use_hh_octopus", 5, json_decode('{"interval":1800}'));
            if (!$result['success']) return $result;
            $feedid = $result['feedid'];
        }

        // Step 2: Fetch feed meta data to find last data point time and value
        $meta = $this->feed->get_meta($feedid);

        $params = array(
            "page" => 1,
            "order_by" => "period",
            "page_size" => 25000
        );

        // If feed has data then set period_from to last data point time
        if ($meta->npoints > 0) {
            $end_time = $meta->start_time + ($meta->interval * $meta->npoints);
            $date = new DateTime();
            $date->setTimestamp($end_time);
            $params["period_from"] = $date->format("c");
        }

        // Step 3: Request history from Octopus
        $reply = $this->http_request("GET", "https://api.octopus.energy/v1/electricity-meter-points/" . $mpan . "/meters/" . $meter_serial . "/consumption/", $params, $octopus_apikey);

        $data = json_decode($reply);
        if ($data == null || !isset($data->results)) {
            return array("success" => false, "message" => "Empty response from Octopus, invalid apikey? $reply");
        } else {
            $dp_received = count($data->results);
            if (!$dp_received) {
                return array("success" => false, "message" => "No data received");
            }

            // Step 4: Process history into data array for emoncms
            $series = array();
            foreach ($data->results as $i) {
                $time = strtotime($i->interval_start);
                $value = $i->consumption;
                $series[] = array($time, $value);
            }
            $result = $this->feed->post_multiple($feedid, $series);
            if (!$result['success']) {
                return $result;
            } else {
                return array("success" => true, "message" => "Downloaded $dp_received datapoints");
            }

        }
        return array("success" => false, "message" => "Unknown error");
    }

    public function fetch_bills($userid) {

        $userid = (int) $userid;
        if (!$result = $this->user_get($userid)) {
            return array("success" => false, "message" => "User does not exist");
        }
        $mpan = $result->mpan;
        $meter_serial = $result->meter_serial;
        $octopus_apikey = $result->octopus_apikey;

        $params = array(
            "page" => 1,
            "order_by" => "period",
            "page_size" => 25000
        );

        // Step 3: Request history from Octopus
        $reply = $this->http_request("GET", "https://api.octopus.energy/v1/electricity-meter-points/" . $mpan . "/meters/" . $meter_serial . "/consumption/", $params, $octopus_apikey);

        // Fetch octopus user bill
        $reply = $this->http_request("GET", "https://api.octopus.energy/v1/accounts/AGILE-18-02-21-A/bills/", $params, $octopus_apikey);
        

    }

    private function http_request($method,$url,$data,$apikey) {

        $options = array();
        $urlencoded = http_build_query($data);
        
        if ($method=="GET") { 
            $url = "$url?$urlencoded";
        } else if ($method=="POST") {
            $options[CURLOPT_POST] = 1;
            $options[CURLOPT_POSTFIELDS] = $data;
        }
        
        $options[CURLOPT_URL] = $url;
        $options[CURLOPT_RETURNTRANSFER] = 1;
        $options[CURLOPT_CONNECTTIMEOUT] = 2;
        $options[CURLOPT_TIMEOUT] = 5;
    
        if ($apikey) {
            $options[CURLOPT_HTTPAUTH] = CURLAUTH_BASIC;
            $options[CURLOPT_USERPWD] = $apikey;
        }
    
        $curl = curl_init();
        curl_setopt_array($curl,$options);
        $resp = curl_exec($curl);
        curl_close($curl);
        return $resp;
    }

    private function validate($mpan, $meter_serial, $octopus_apikey) {

        // Validate MPAN
        if (strlen("$mpan") != 13) {
            return array("success" => false, "message" => "Invalid MPAN, must be 13 digits $mpan");
        }
        if ($mpan != (int)$mpan) {
            return array("success" => false, "message" => "Invalid MPAN, must be numeric $mpan");
        }
        // Validate meter serial
        if (strlen("$meter_serial") != 10) {
            return array("success" => false, "message" => "Invalid meter serial");
        }
        // Validate octopus apikey
        if (strlen("$octopus_apikey") != 32 || strpos($octopus_apikey, "sk_live_") != 0) {
            return array("success" => false, "message" => "Invalid octopus apikey");
        }

        return array("success" => true, "message" => "Validated");
    }
}
