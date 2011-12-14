<?php
/**
 * @file Filtered the CDR recordy by varios parameters, then transfer them into the temp table
 */

//DB handling
require_once('db.inc.php');

function filter($id = 0) {
  $db = connect_sql('cdr');
  $cdr = 'CDR';//table name
  $gk_name = 'NIIF-GK';
  $min_duration = 300;//duration in secound
  $start_time = '2009-01-01 0:00:00';

  $sql = "SELECT
            ID, confid, connect_time, duration, calling_stationid, callerip, called_stationid, calledip
          FROM ". $cdr ." 
          WHERE
            gk_name = '". $gk_name ."' AND duration >= ". $min_duration ." AND ID > ". $id ." AND connect_time >= '". $start_time ."' LIMIT 10000";
  $result = mysql_query($sql, $db);

  disconnect_sql($db);

  return $result;
}

function record_inserter() {
  $rows = filter();

  $link = connect_sql('co2');
  while ($row = mysql_fetch_assoc($rows)) {
    $sql = "INSERT INTO temp_log".
            " (conf_id, start_datetime, duration, caller_GDS, called_GDS, caller_IP, called_IP)".
            " VALUES ('".
              $row['confid'] ."', '".
              $row['connect_time']. "', '".
              $row['duration'] ."', '".
              $row['calling_stationid'] ."', '".
              $row['called_stationid']."', '".
              $row['callerip'] ."', '".
              $row['calledip'] ."')";

    mysql_query($sql, $link);
  }
  disconnect_sql(&$link);
}
