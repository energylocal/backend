<?php

$schema['club_accounts'] = array(
    'clubid' => array('type' => 'int(11)', 'Null'=>false, 'Key'=>'PRI'),
    'userid' => array('type' => 'int(11)', 'Null'=>false, 'Key'=>'PRI'),
    'mpan' => array('type' => 'varchar(32)', "default"=>""),
    'cad_serial' => array('type' => 'varchar(11)', "default"=>""),
    'meter_serial' => array('type' => 'varchar(11)', "default"=>""),
    'octopus_apikey' => array('type' => 'varchar(32)', "default"=>"")
);