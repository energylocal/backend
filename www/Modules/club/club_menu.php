<?php
global $session;
if ($session["admin"]) {
    $menu["energylocal"] = array("name"=>_('EnergyLocal'),"href"=>"club/list", "order"=>1, "icon"=>"user");
}
