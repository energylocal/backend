<?php
global $session;
if ($session["write"]) {

    $menu["setup"]["l2"]['clubs'] = array("name"=>_('Clubs'),"href"=>"club/list", "order"=>13, "icon"=>"user");
}
