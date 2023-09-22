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

class Tariff
{
    private $mysqli;

    public function __construct($mysqli)
    {
        $this->mysqli = $mysqli;
    }

    // List tariffs belonging to a club from tariffs table
    public function list($clubid) {
        $clubid = (int) $clubid;

        // Get most recent tariff for all users (only return most recent for each user)
        $result = $this->mysqli->query("SELECT userid,tariffid,`start` FROM user_tariffs WHERE `start` IN (SELECT MAX(`start`) FROM user_tariffs GROUP BY userid)");
        $active_user_count = array();
        while ($row = $result->fetch_object()) {
            if (!isset($active_user_count[$row->tariffid])) {
                $active_user_count[$row->tariffid] = 0;
            }
            $active_user_count[$row->tariffid]++;
        }
        
        $result = $this->mysqli->query("SELECT * FROM tariffs WHERE clubid='$clubid'");
        $tariffs = array();
        while ($row = $result->fetch_object()) {
            // convert created to date 12th September 2013
            $row->created = date("jS F Y",$row->created);

            if (isset($active_user_count[$row->id])) {
                $row->active_users = $active_user_count[$row->id];
            } else {
                $row->active_users = 0;
            }
            
            $row->first_assigned = $this->first_assigned($row->id);

            $tariffs[] = $row;
        }
        return $tariffs;
    }

    // Create a new tariff
    public function create($clubid,$name) {
        $clubid = (int) $clubid;
        if ($name=="") return array("success"=>false, "message"=>"Tariff name cannot be empty");

        // a-z A-Z 0-9 whitespace and - _ . only
        if (preg_match('/[^a-zA-Z0-9\s\-_\.]/', $name)) {
            return array("success"=>false, "message"=>"Tariff name can only contain a-z A-Z 0-9 whitespace and - _ .");
        }
        
        $time = time();
        $stmt = $this->mysqli->prepare("INSERT INTO tariffs (clubid,name,created) VALUES (?,?,?)");
        $stmt->bind_param("isi",$clubid,$name,$time);
        $stmt->execute();
        $stmt->close();
        return array("success"=>true, "id"=>$this->mysqli->insert_id);
    }

    // Delete a tariff
    public function delete($tariffid) {
        $tariffid = (int) $tariffid;

        // Only allow tariff to be deleted if it has never been assigned
        $first_assigned = $this->first_assigned($tariffid);
        if ($first_assigned!="") return array("success"=>false, "message"=>"Tariff has been assigned to users");
        
        $stmt = $this->mysqli->prepare("DELETE FROM tariffs WHERE id=?");
        $stmt->bind_param("i",$tariffid);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();
        if ($affected==0) return array("success"=>false);

        // Delete all tariff periods
        $this->mysqli->query("DELETE FROM tariff_periods WHERE tariffid='$tariffid'");

        // Delete all user tariffs (can only delete unassigned tariffs)
        // $this->mysqli->query("DELETE FROM user_tariffs WHERE tariffid='$tariffid'");

        return array("success"=>true);
    }

    // List tariff periods belonging to tariff from tariff_periods table
    public function list_periods($tariffid) {
        $tariffid = (int) $tariffid;
        
        $result = $this->mysqli->query("SELECT * FROM tariff_periods WHERE tariffid='$tariffid' ORDER BY `index` ASC");
        $periods = array();
        while ($row = $result->fetch_object()) {
            $row->tariffid = (int) $row->tariffid;
            $row->index = (int) $row->index;
            $row->weekend = (int) $row->weekend;
            $row->start = (float) $row->start / 10;
            $periods[] = $row;
        }
        return $periods;
    }

    // Add a tariff period to a tariff
    public function add_period($tariffid,$name,$weekend,$start,$generator,$import,$color) {
        $tariffid = (int) $tariffid;
        $weekend = (int) $weekend;
        $start = (int) ($start * 10);
        $generator = (float) $generator;
        $import = (float) $import;

        // a-z A-Z 0-9 whitespace and - _ . only
        if (preg_match('/[^a-zA-Z0-9\s\-_\.]/', $name)) {
            return array("success"=>false, "message"=>"Tariff period name can only contain a-z A-Z 0-9 whitespace and - _ .");
        }

        // validate color #000 or #000000
        if (!preg_match('/^#[a-fA-F0-9]{3}([a-fA-F0-9]{3})?$/', $color)) {
            return array("success"=>false, "message"=>"Tariff period color is invalid");
        }

        // Only allow tariff periods to be added if it has never been assigned
        $first_assigned = $this->first_assigned($tariffid);
        if ($first_assigned!="") return array("success"=>false, "message"=>"Tariff has been assigned to users");

        // Check number of periods in this tariff
        $result = $this->mysqli->query("SELECT COUNT(*) AS count FROM tariff_periods WHERE tariffid='$tariffid'");
        $row = $result->fetch_object();
        $index = $row->count;

        $stmt = $this->mysqli->prepare("INSERT INTO tariff_periods (tariffid,`index`,name,weekend,start,generator,import,color) VALUES (?,?,?,?,?,?,?,?)");
        $stmt->bind_param("iisiidds",$tariffid,$index,$name,$weekend,$start,$generator,$import,$color);

        $stmt->execute();
        $stmt->close();
        return array("success"=>true);
    }

    // Save period
    public function save_period($tariffid,$index,$name,$weekend,$start,$generator,$import,$color) {
        $tariffid = (int) $tariffid;
        $index = (int) $index;
        $weekend = (int) $weekend;
        $start = (int) ($start * 10);
        $generator = (float) $generator;
        $import = (float) $import;

        // a-z A-Z 0-9 whitespace and - _ . only
        if (preg_match('/[^a-zA-Z0-9\s\-_\.]/', $name)) {
            return array("success"=>false, "message"=>"Tariff period name can only contain a-z A-Z 0-9 whitespace and - _ .");
        }

        // validate color #000 or #000000
        if (!preg_match('/^#[a-fA-F0-9]{3}([a-fA-F0-9]{3})?$/', $color)) {
            return array("success"=>false, "message"=>"Tariff period color is invalid");
        }

        // Only allow tariff periods to be added if it has never been assigned
        $first_assigned = $this->first_assigned($tariffid);
        if ($first_assigned!="") return array("success"=>false, "message"=>"Tariff has been assigned to users");

        // Validate index
        $result = $this->mysqli->query("SELECT COUNT(*) AS count FROM tariff_periods WHERE tariffid='$tariffid'");
        $row = $result->fetch_object();
        if ($index<0 || $index>=$row->count) return array("success"=>false, "message"=>"Invalid index");

        $stmt = $this->mysqli->prepare("UPDATE tariff_periods SET name=?, weekend=?, start=?, generator=?, import=?, color=? WHERE tariffid=? AND `index`=?");
        $stmt->bind_param("siiddsii",$name,$weekend,$start,$generator,$import,$color,$tariffid,$index);
        $stmt->execute();
        $stmt->close();
        return array("success"=>true);
    }

    // Delete a tariff period
    public function delete_period($tariffid,$index) {
        $tariffid = (int) $tariffid;
        $index = (int) $index;

        // Only allow tariff periods to be added if it has never been assigned
        $first_assigned = $this->first_assigned($tariffid);
        if ($first_assigned!="") return array("success"=>false, "message"=>"Tariff has been assigned to users");
        
        $stmt = $this->mysqli->prepare("DELETE FROM tariff_periods WHERE tariffid=? AND `index`=?");
        $stmt->bind_param("ii",$tariffid,$index);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();
        if ($affected==0) return array("success"=>false);

        // Re-index tariff periods
        $result = $this->mysqli->query("SELECT * FROM tariff_periods WHERE tariffid='$tariffid' ORDER BY `index` ASC");
        $index = 0;
        while ($row = $result->fetch_object()) {
            $this->mysqli->query("UPDATE tariff_periods SET `index`='$index' WHERE `index`='$row->index'");
            $index++;
        }

        return array("success"=>true);
    }

    // Find when tariff was first assigned
    public function first_assigned($tariffid) {
        $tariffid = (int) $tariffid;

        $result = $this->mysqli->query("SELECT `start` FROM user_tariffs WHERE tariffid='$tariffid' ORDER BY `start` ASC LIMIT 1");
        $row = $result->fetch_object();
        if ($row) return date("jS F Y",$row->start);
        return "";
    }
}
