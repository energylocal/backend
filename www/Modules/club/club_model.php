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
}
