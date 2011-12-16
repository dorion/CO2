<?php
/**
 * @file select conf and participant from the log messages
 */

//DB handler
require_once('db.inc.php');

function selector() {
  $link = connect_sql(co2);

  var_dump(VCR_cleaner(&$link));
#  create_mcu_conference();
#  create_point_to_point_conference();

  disconnect_sql(&$link);
}

function mcu_conf_selector() {
  //TODO: Move this constants to the config file
  $mcu_gds = array('003610030', '003610040', '00365511');
  $mcu_ip = array('195.111.192.30', '195.111.192.29', '193.225.95.130');

  return " (called_GDS LIKE '". $mcu_gds[0] ."%' OR called_GDS LIKE '". $mcu_gds[1] ."%' OR called_GDS LIKE '". $mcu_gds[2] ."%' OR called_IP = '". $mcu_ip[0] ."' OR called_IP = '". $mcu_ip[1] ."' OR called_IP = '". $mcu_ip[2] ."')";


}

function create_mcu_conference() {
  $sql = "SELECT * FROM temp_log WHERE". mcu_conf_selector();

  $result = mysql_query($sql, $link);

  while ($row = mysql_fetch_assoc($result)) {
    var_dump($row);
  }

  return $sql;
}

function create_point_to_point_conference() {
  $link = connect_sql(co2);
  $select_sql = 'SELECT * FROM templ_log WHERE NOT'. mcu_conf_selector();
var_dump($select_sql);
  $result = mysql_query($select_sql, $link);

  while ($row = mysql_fetch_assoc($result)) {
    var_dump($row);
  }

/*  $insert_sql = "INSERT INTO conf (start_datetime , duration , latitude , longitude) VALUES ( NULL , NULL , NULL , NULL)";
  $result = mysql_query($insert_sql, $link);*/
  disconnect_sql(&$link);
}

/*function create_participant() {
  
}*/

function temp_table_cleaner($id, &$db_link) {
  $sql = "DELETE FROM templ_log WHERE ID = ". $id;
  return mysql_query($sql, $db_link);
}

function VCR_cleaner(&$link) {
  //TODO: move this const to conf
  $vcr_gds = array("003610042", "003610040");
  $vcr_ip = array("195.111.192.28");

  $sql_orig = "DELETE FROM temp_log WHERE ";
  $sql = $sql_orig;

  foreach($vcr_gds AS $key => $gds) {
    if ($key !== 0) {
      $sql .= "OR ";
    }
    $sql .= "caller_GDS = '". $gds ."%' OR called_GDS LIKE '". $gds ."%' ";
  }

  $key = NULL;
  foreach($vcr_ip AS $key => $ip) {
    if ($sql !== $sql_orig) {
      $sql .= "OR ";
    }
    $sql .= "caller_IP = '". $ip ."' OR called_IP LIKE '". $ip ."' ";
  }

  return mysql_query($sql, $link);
}
