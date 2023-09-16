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

class Club
{
    private $mysqli;
    private $redis;
    private $user;

    public function __construct($mysqli,$redis,$user)
    {
        $this->mysqli = $mysqli;
        $this->redis = $redis;
        $this->user = $user;
        $this->log = new EmonLogger(__FILE__);
    }

    // Check if a club exists by id
    public function exists($id) {
        $stmt = $this->mysqli->prepare("SELECT id FROM club WHERE id=?");
        $stmt->bind_param("i",$id);
        $stmt->execute();
        $stmt->store_result();
        $num_rows = $stmt->num_rows;
        $stmt->close();
        if ($num_rows>0) return true; else return false;
    }

    // Check if a club exists by name
    public function exists_name($name) {
        $stmt = $this->mysqli->prepare("SELECT id FROM club WHERE name=?");
        $stmt->bind_param("s",$name);
        $stmt->execute();
        $stmt->store_result();
        $num_rows = $stmt->num_rows;
        $stmt->close();
        if ($num_rows>0) return true; else return false;
    }

    // Get club name from id
    public function get_name($id) {
        $stmt = $this->mysqli->prepare("SELECT name FROM club WHERE id=?");
        $stmt->bind_param("i",$id);
        $stmt->execute();
        $stmt->bind_result($name);
        $stmt->fetch();
        $stmt->close();
        return $name;
    }

    // Create a new club
    public function create($name) {
        $time = time();
        // Alphanumeric and spaces only
        if (!ctype_alnum(str_replace(" ","",$name))) {
            return array("success"=>false,"message"=>"Club name must be alphanumeric");
        }
        // Check if club name already exists
        if ($this->exists_name($name)) {
            return array("success"=>false,"message"=>"Club name already exists");
        }
        $stmt = $this->mysqli->prepare("INSERT INTO club (name,created) VALUES (?,?)");
        $stmt->bind_param("si",$name,$time);
        $stmt->execute();
        $stmt->close();
        return array("success"=>true,"id"=>$this->mysqli->insert_id);
    }

    // Delete a club
    public function delete($id) {
        // Check if club exists
        if (!$this->exists($id)) {
            return array("success"=>false,"message"=>"Club does not exist");
        }
        $stmt = $this->mysqli->prepare("DELETE FROM club WHERE id=?");
        $stmt->bind_param("i",$id);
        $stmt->execute();
        $stmt->close();
        return array("success"=>true);
    }

    // Return a list of clubs
    public function list() {
        $result = $this->mysqli->query("SELECT * FROM club ORDER BY created ASC");
        $clubs = array();
        while ($row = $result->fetch_object()) {
            // Convert unix timestamp to date Europe/London
            $date = new DateTime();
            $date->setTimezone(new DateTimeZone("Europe/London"));
            $date->setTimestamp($row->created);
            // Format date 14th Jan 2014
            $row->created = $date->format('jS M Y');
            $clubs[] = $row;
        }
        return $clubs;
    }

    public function get($id) {
        $id = (int) $id;
        $result = $this->mysqli->query("SELECT * FROM club WHERE id=$id");
        return $result->fetch_object();
    }
    
    // Return a list of accounts linked to a club
    public function account_list($id) {
        $id = (int) $id;

        $result = $this->mysqli->query("SELECT userid FROM club_accounts WHERE clubid=$id");
        $accounts = array();
        while ($row = $result->fetch_object()) {
            $accounts[] = $row;
        }

        // Add user details
        foreach ($accounts as &$account) {
            $user = $this->get_user($account->userid);
            $account->username = $user->username;
            $account->email = $user->email;

            // Add tariff details
            if ($user_tariff = $this->get_user_tariff($account->userid)) {
                $tariff = $this->get_tariff($user_tariff);
                $account->tariff_id = $tariff->id;
                $account->tariff_name = $tariff->name;
            } else {
                $account->tariff_id = false;
                $account->tariff_name = "";
            }
        }

        return $accounts;
    }

    public function account_data_status($id,$feed_class) {
        $id = (int) $id;
        $result = $this->mysqli->query("SELECT userid FROM club_accounts WHERE clubid=$id");
        $accounts = array();
        while ($row = $result->fetch_object()) {
            $userid = $row->userid;

            $row = array(                
                'octopus'=>array('days'=>0,'updated'=>0)                 
            );

            if ($feedid = $feed_class->get_id($userid,"use_hh_octopus")) {
                if ($meta = $feed_class->get_meta($feedid)) {
                    $row['octopus']['days'] = $meta->npoints / 48;
                    $row['octopus']['updated'] = (time() - ($meta->start_time + ($meta->npoints*$meta->interval)))/86400;   
                    $row['octopus']['feedid'] = $feedid;
                }
            }

            $accounts[$userid] = $row;
        }
        return $accounts;
    }

    // Add a user to a club
    public function add_account($id,$username,$password,$email,$mpan,$cad_serial,$octopus_apikey,$meter_serial) {
        $id = (int) $id;

        // Check if club exists
        if (!$this->exists($id)) {
            return array("success"=>false,"message"=>"Club does not exist");
        }
        // Register user using user model
        $result = $this->user->register($username,$password,$email,"Europe/London");
        if (!$result['success']) {
            return $result;
        }
        $userid = $result['userid'];

        // Check if user exists
        if (!$this->exists_user($userid)) {
            return array("success"=>false,"message"=>"User does not exist");
        }
        // Check if user is already in club
        if ($this->exists_account($id,$userid)) {
            return array("success"=>false,"message"=>"User already in club");
        }

        // Add user to club
        $stmt = $this->mysqli->prepare("INSERT INTO club_accounts (clubid,userid,mpan,cad_serial,octopus_apikey,meter_serial) VALUES (?,?,?,?,?,?)");
        $stmt->bind_param("iissss",$id,$userid,$mpan,$cad_serial,$octopus_apikey,$meter_serial);
        $stmt->execute();
        $stmt->close();
        return array("success"=>true, "userid"=>$userid);
    }

    // Edit a user in a club
    public function edit_account($id,$userid,$username,$email,$mpan,$cad_serial,$octopus_apikey,$meter_serial) {
        $id = (int) $id;
        $userid = (int) $userid;

        // Check if club exists
        if (!$this->exists($id)) {
            return array("success"=>false,"message"=>"Club does not exist");
        }
        // Check if user exists
        if (!$this->exists_user($userid)) {
            return array("success"=>false,"message"=>"User does not exist");
        }

        // Update username in user table
        if ($this->user->get_username($userid)!= $username) {
            $result = $this->user->change_username($userid,$username);
            if (!$result['success']) {
                return $result;
            }
        }

        // Update email in user table
        if ($this->user->get_email($userid)!= $email) {
            $result = $this->user->change_email($userid,$email);
            if (!$result['success']) {
                return $result;
            }
        }

        // Check if user is already in club
        if (!$this->exists_account($id,$userid)) {
            return array("success"=>false,"message"=>"User is not in club");
        }
        // Edit user in club
        $stmt = $this->mysqli->prepare("UPDATE club_accounts SET mpan=?, cad_serial=?, octopus_apikey=?, meter_serial=? WHERE clubid=? AND userid=?");
        $stmt->bind_param("ssssii",$mpan,$cad_serial,$octopus_apikey,$meter_serial,$id,$userid);
        $stmt->execute();
        $stmt->close();
        return array("success"=>true);
    }
    
    // Check if a user exists in user table
    public function exists_user($userid) {
        $userid = (int) $userid;
        $stmt = $this->mysqli->prepare("SELECT id FROM users WHERE id=?");
        $stmt->bind_param("i",$userid);
        $stmt->execute();
        $stmt->store_result();
        $num_rows = $stmt->num_rows;
        $stmt->close();
        if ($num_rows==0) {
            return false;
        }
        return true;
    }

    // Check if a user is in a club
    public function exists_account($id,$userid) {
        $id = (int) $id;
        $userid = (int) $userid;
        $stmt = $this->mysqli->prepare("SELECT userid FROM club_accounts WHERE clubid=? AND userid=?");
        $stmt->bind_param("ii",$id,$userid);
        $stmt->execute();
        $stmt->store_result();
        $num_rows = $stmt->num_rows;
        $stmt->close();
        if ($num_rows==0) {
            return false;
        }
        return true;
    }

    // Fetch user details from user table
    public function get_user($userid) {
        $userid = (int) $userid;
        $stmt = $this->mysqli->prepare("SELECT * FROM users WHERE id=?");
        $stmt->bind_param("i",$userid);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_object();
        $stmt->close();
        return $row;
    }

    // -------------------

    // Add tariff to user
    public function set_user_tariff($userid,$tariffid) {
        $userid = (int) $userid;
        $tariffid = (int) $tariffid;
        $start = time();

        // Check if user exists
        if (!$this->exists_user($userid)) {
            return array("success"=>false,"message"=>"User does not exist");
        }

        // Check if tariff exists
        if (!$tariff = $this->get_tariff($tariffid)) {
            return array("success"=>false,"message"=>"Tariff does not exist");
        }
        
        // Get most recent tariff
        $result = $this->mysqli->query("SELECT tariffid,`start` FROM user_tariffs WHERE userid=$userid ORDER BY start DESC LIMIT 1");
        if ($row = $result->fetch_object()) {
            // Check if tariff is already set
            if ($row->tariffid==$tariffid) {
                return array("success"=>false,"message"=>"Tariff already set");
            }
        }

        // check if tariff is already set to start in the future (this should never happen)
        if ($row->start>$start) {
            return array("success"=>false,"message"=>"Tariff already set to start in the future");
        }

        // Add tariff to user
        $stmt = $this->mysqli->prepare("INSERT INTO user_tariffs (userid,tariffid,start) VALUES (?,?,?)");
        $stmt->bind_param("iii",$userid,$tariffid,$start);
        $stmt->execute();
        $stmt->close();

        return array("success"=>true);
    }

    // Get user tariff
    public function get_user_tariff($userid) {
        $userid = (int) $userid;
        $result = $this->mysqli->query("SELECT tariffid FROM user_tariffs WHERE userid=$userid ORDER BY start DESC LIMIT 1");
        if ($row = $result->fetch_object()) {
            return $row->tariffid;
        } else {
            return false;
        }
    }

    // Check if a tariff exists
    public function get_tariff($tariffid) {
        $tariffid = (int) $tariffid;
        $result = $this->mysqli->query("SELECT * FROM tariffs WHERE id=$tariffid");
        return $result->fetch_object();
    }
}
