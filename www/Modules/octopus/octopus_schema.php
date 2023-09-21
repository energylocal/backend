<?php

$schema['octopus_users'] = array(
    'userid' => array('type' => 'int(11)'),
    'mpan' => array('type' => 'varchar(32)', "default"=>""),
    'meter_serial' => array('type' => 'varchar(11)', "default"=>""),
    'octopus_apikey' => array('type' => 'varchar(32)', "default"=>""),
    'type' => array('type' => 'varchar(32)', "default"=>"")
);