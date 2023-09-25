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

class Account
{
    private $mysqli;
    private $user;
    private $tariff;

    public function __construct($mysqli,$user,$tariff)
    {
        $this->mysqli = $mysqli;
        $this->user = $user;
        $this->tariff = $tariff;
        $this->log = new EmonLogger(__FILE__);
    }
    
    // Return a list of accounts linked to a club
    public function list($id) {
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
            if ($user_tariff = $this->tariff->get_user_tariff($account->userid)) {
                $tariff = $this->tariff->get_tariff($user_tariff);
                $account->tariff_id = $tariff->id;
                $account->tariff_name = $tariff->name;
            } else {
                $account->tariff_id = false;
                $account->tariff_name = "";
            }
        }

        return $accounts;
    }
    
    public function get($userid) {
        $userid = (int) $userid;

        $result = $this->mysqli->query("SELECT userid FROM club_accounts WHERE userid='$userid'");
        if (!$account = $result->fetch_object()) {
            return false;
        }
        
        $user = $this->get_user($account->userid);
        $account->username = $user->username;
        $account->email = $user->email;

        // Add tariff details
        if ($user_tariff = $this->tariff->get_user_tariff($account->userid)) {
            $tariff = $this->tariff->get_tariff($user_tariff);
            $account->tariff_id = $tariff->id;
            $account->tariff_name = $tariff->name;
        } else {
            $account->tariff_id = false;
            $account->tariff_name = "";
        }

        return $account;
    }

    /*
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
    }*/

    // Add a user to a club
    public function add_account($clubid,$username,$password,$email) {
        $clubid = (int) $clubid;

        // Check if club exists
        if (!$this->club_exists($clubid)) {
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
        if ($this->exists_account($clubid,$userid)) {
            return array("success"=>false,"message"=>"User already in club");
        }

        // Add user to club
        $stmt = $this->mysqli->prepare("INSERT INTO club_accounts (clubid,userid) VALUES (?,?)");
        $stmt->bind_param("ii",$clubid,$userid);
        $stmt->execute();
        $stmt->close();
        return array("success"=>true, "userid"=>$userid);
    }

    // Edit a user in a club
    public function edit_account($userid,$username,$email) {
        $userid = (int) $userid;

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

        // Edit user in club
        /*
        $stmt = $this->mysqli->prepare("UPDATE club_accounts SET ... WHERE clubid=? AND userid=?");
        $stmt->bind_param("ii",$id,$userid);
        $stmt->execute();
        $stmt->close();
        */
        return array("success"=>true);
    }

    // check if club exists
    public function club_exists($clubid) {
        $clubid = (int) $clubid;
        $result = $this->mysqli->query("SELECT id FROM club WHERE id=$clubid");
        if ($row = $result->fetch_object()) {
            return true;
        } else {
            return false;
        }
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

        $result = $this->mysqli->query("SELECT userid FROM club_accounts WHERE clubid=$id AND userid=$userid");
        if ($row = $result->fetch_object()) {
            return true;
        } else {
            return false;
        }
    }

    // check if user exists in users table
    public function user_exists($userid) {
        $userid = (int) $userid;
        $result = $this->mysqli->query("SELECT id FROM users WHERE id=$userid");
        if ($row = $result->fetch_object()) {
            return true;
        } else {
            return false;
        }
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
    
    // Get clubid of user
    public function get_club_id($userid) {
        $userid = (int) $userid;
        $result = $this->mysqli->query("SELECT clubid FROM club_accounts WHERE userid='$userid'");
        if ($row = $result->fetch_object()) {
            return $row->clubid;
        } 
        return false;
    }
}
