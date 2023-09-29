<?php
global $session;

if ($session['admin']) {
    $menu["energylocal"]["l2"]['tma'] = array(
        "name"=>_("TMA"),
        "href"=>"tma/list", 
        "order"=>2, 
        "icon"=>"format_list_bulleted"
    );
}