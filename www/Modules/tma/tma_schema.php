<?php

$schema['tma_users'] = array(
    'userid' => array('type' => 'int(11)'),
    'mpan' => array('type' => 'varchar(32)', "default"=>""),
    'type' => array('type' => 'varchar(32)', "default"=>"") // consumption, solar, wind, hydro, AD
);