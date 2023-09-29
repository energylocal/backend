<?php
global $session;

if ($session['admin']) {
    $menu["energylocal"]["l2"]['octopus'] = array(
        "name"=>_("Octopus"),
        "href"=>"octopus/list", 
        "order"=>2, 
        "icon"=>"format_list_bulleted"
    );
}