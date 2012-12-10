<?php
/**
 * @file Database functions
 */

/**
 * Connected to the database
 */
function connect_sql($server) {
  $sql_servers  = array(
                    'cdr' =>  array(
                                'host' => 'localhost',
                                'mysql_user' => 'cdr',
                                'mysql_passwd' => 'Gj88PbFB8Ynx944f',
                                'db_name' => 'cdr',
                              ),
                    'co2' =>  array(
                                'host' => 'localhost',
                                'mysql_user' => 'co2',
                                'mysql_passwd' => 'd4XP545bMZrtnZ4B',
                                'db_name' => 'co2',
                              ),
                    'iir2' => array(
                                'host' => 'db2.voip.niif.hu',
                                'mysql_user' => 'iirdevelop',
                                'mysql_passwd' => 'T3UsxS6K',
                                'db_name' => 'iir2',
                              ),
                  );

  $link = mysql_connect($sql_servers[$server]['host'], $sql_servers[$server]['mysql_user'], $sql_servers[$server]['mysql_passwd']);
  if (!$link) {
    die('Could not connect: ' . mysql_error());
  }

  if(!mysql_select_db($sql_servers[$server]['db_name'], $link)) {
    die('Could not chanage database: ' . mysql_error());
  }

  return $link;
}

function disconnect_sql(&$link) {
  mysql_close($link);
}
