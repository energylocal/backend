<?php
/*
   All Emoncms code is released under the GNU Affero General Public License.
   See COPYRIGHT.txt and LICENSE.txt.

   ---------------------------------------------------------------------
   Emoncms - open source energy visualisation
   Part of the OpenEnergyMonitor project:
   http://openenergymonitor.org

$schema['tariffs'] = array(
    'id' => array('type' => 'int(11)', 'Null'=>false, 'Key'=>'PRI', 'Extra'=>'auto_increment'),
    'clubid' => array('type' => 'int(11)'),
    'name' => array('type' => 'varchar(32)', "default"=>""),
    'created' => array('type' => 'int(10)'),
    'first_assigned' => array('type' => 'int(10)'),
    'last_assigned' => array('type' => 'int(10)')
);

$schema['tariff_periods'] = array(
    'id' => array('type' => 'int(11)'),
    'name' => array('type' => 'varchar(16)'),
    'start' => array('type' => 'varchar(16)'),
    'end' => array('type' => 'varchar(16)'),
    'generator' => array('type' => 'float'),
    'import' => array('type' => 'float'),
    'color' => array('type' => 'varchar(32)')
);

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

            // Find when tariff was first assigned
            $result = $this->mysqli->query("SELECT `start` FROM user_tariffs WHERE tariffid='$row->id' ORDER BY `start` ASC LIMIT 1");
            $row2 = $result->fetch_object();
            if ($row2) $row->first_assigned = date("jS F Y",$row2->start);
            else $row->first_assigned = "";


            $tariffs[] = $row;
        }
        return $tariffs;
    }

    // Create a new tariff
    public function create($clubid,$name) {
        $time = time();
        $stmt = $this->mysqli->prepare("INSERT INTO tariffs (clubid,name,created) VALUES (?,?,?)");
        $stmt->bind_param("isi",$clubid,$name,$time);
        $stmt->execute();
        $stmt->close();
        return array("success"=>true, "id"=>$this->mysqli->insert_id);
    }

    // Delete a tariff
    public function delete($tariffid) {
        $stmt = $this->mysqli->prepare("DELETE FROM tariffs WHERE id=?");
        $stmt->bind_param("i",$tariffid);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();
        if ($affected==0) return array("success"=>false);
        else return array("success"=>true);
    }

    // List tariff periods belonging to tariff from tariff_periods table
    public function list_periods($tariffid) {
        $result = $this->mysqli->query("SELECT * FROM tariff_periods WHERE tariffid='$tariffid' ORDER BY `index` ASC");
        $periods = array();
        while ($row = $result->fetch_object()) {
            $row->tariffid = (int) $row->tariffid;
            $row->index = (int) $row->index;
            $periods[] = $row;
        }
        return $periods;
    }

    // Add a tariff period to a tariff
    public function add_period($tariffid,$name,$start,$end,$generator,$import,$color) {

        // Check number of periods in this tariff
        $result = $this->mysqli->query("SELECT COUNT(*) AS count FROM tariff_periods WHERE tariffid='$tariffid'");
        $row = $result->fetch_object();
        $index = $row->count;

        $stmt = $this->mysqli->prepare("INSERT INTO tariff_periods (tariffid,`index`,name,start,end,generator,import,color) VALUES (?,?,?,?,?,?,?,?)");
        $stmt->bind_param("iisssdds",$tariffid,$index,$name,$start,$end,$generator,$import,$color);

        $stmt->execute();
        $stmt->close();
        return array("success"=>true);
    }
    
    // Delete a tariff period
    public function delete_period($tariffid,$index) {
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

    // Save period
    public function save_period($tariffid,$index,$name,$start,$end,$generator,$import,$color) {
        $stmt = $this->mysqli->prepare("UPDATE tariff_periods SET name=?, start=?, end=?, generator=?, import=?, color=? WHERE tariffid=? AND `index`=?");
        $stmt->bind_param("sssddsii",$name,$start,$end,$generator,$import,$color,$tariffid,$index);
        $stmt->execute();
        $stmt->close();
        return array("success"=>true);
    }
}
