<?php
global $session;
if ($session["admin"]) {
    $menu["energylocal"] = array("name"=>_('EnergyLocal'),"href"=>"club/list", "order"=>1, "icon"=>"user", "l2"=>array());
    
    $menu["energylocal"]["l2"]['club'] = array(
        "name"=>_("Clubs"),
        "href"=>"club/list", 
        "order"=>1, 
        "icon"=>"format_list_bulleted"
    );

    $menu["energylocal"]["l2"]['app'] = array(
        "name"=>_("Dashboard"),
        "href"=>"club/app", 
        "order"=>0, 
        "icon"=>"format_list_bulleted"
    );
}
