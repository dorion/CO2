<?php
/**
 * @file Filtered the CDR recordy by varios parameters, then transfer them into the temp table
 */

/**
 * Connected to the CDR database
 */
function connect_cdr() {
  $host = 'localhost';
  $mysql_user = 'CDR';
  $mysql_passwd = '';
  $db_name = 'CDR;'

  $link = mysql_connect($host, $mysql_user, $mysql_passwd);
  if (!$link) {
    die('Could not connect: ' . mysql_error());
  }

  if(!mysql_select_db($db_name, $link)) {
    die('Could not chanage database: ' . mysql_error());
  }

  return $link;
}

function disconnect_cdr() {
  mysql_close($link);
}

function filter() {
  $db = connect_cdr();
  $cdr = 'CDR';//table name
  $gk_name = 'NIIF-GK';
  $min_duration = 300;//duration in secound

  $sql = "SELECT
            ID, duration, connect_time, callerip, calledip
          FROM ". $cdr ." 
          WHERE
            gk_name = '". $gk_name ."' AND duration >= ". $min_duration ."";
  $result = mysql_query($sql, $db);

  disconnect_cdr($db);

  return $result;
}

function record_inserter($record) {
  $sql = "INSERT INTO temp_log VALUES ()";
}
