<?php
/**
 * @file select conf and participant from the log messages
 */

//DB handler
require_once('db.inc.php');

function selector() {
  $link = connect_sql(co2);

  VCR_cleaner(&$link);
  create_mcu_conference(&$link);
 # create_point_to_point_conference(&$link);

  disconnect_sql(&$link);
}

function mcu_conf_selector() {
  //TODO: Move this constants to the config file
  $mcu_gds = array('003610030', '003610040', '00365511');
  $mcu_ip = array('195.111.192.30', '195.111.192.29', '193.225.95.130');

  $sql_orig = '(';
  $sql = $sql_orig;

  foreach($mcu_gds AS $key => $mcu) {
    if ($key !== 0) {
      $sql .= "OR ";
    }
    $sql .= "caller_GDS LIKE '". $mcu ."%' OR called_GDS LIKE '". $mcu ."%' ";
  }

  $key = NULL;
  foreach($mcu_ip AS $key => $mcu) {
    if ($sql !== $sql_orig) {
      $sql .= "OR ";
    }
    $sql .= "caller_IP = '". $mcu ."' OR called_IP = '". $mcu ."' ";
  }

  return $sql .')';

  return " (called_GDS LIKE '". $mcu_gds[0] ."%' OR called_GDS LIKE '". $mcu_gds[1] ."%' OR called_GDS LIKE '". $mcu_gds[2] ."%' OR called_IP = '". $mcu_ip[0] ."' OR called_IP = '". $mcu_ip[1] ."' OR called_IP = '". $mcu_ip[2] ."')";


}

function create_mcu_conference(&$link) {
  $sql = "SELECT * FROM temp_log WHERE ". mcu_conf_selector();// ." AND start_datetime >= '2010-01-01 0:00:00'";

  $result = mysql_query($sql, $link);

  while ($row = mysql_fetch_assoc($result)) {
    $date = explode(' ', $row['start_datetime']);
    $starttime = strtotime($row['start_datetime']);

    $rows[$date[0] .'_'. $row['called_GDS']][] = array(
      'id' => $row['ID'],
      'gds' => $row['caller_GDS'],
      'ip' => $row['caller_IP'],
      'start_time' => $starttime,
      'end_time' => $starttime + $row['duration'],
    );
  }

  $conf_duration = NULL;
  $conf_start_datetime = NULL;
  foreach($rows AS $conference_name => $conference_participants) {
    if (count($conference_participants) > 1) {
      $start = NULL;
      $end = NULL;

      foreach($conference_participants AS $participant) {
        if ($start === NULL OR $start > $participant['start_time']) {
          $start = $participant['start_time'];
        }

        if ($end === NULL OR $end < $participant['end_time']) {
          $end = $participant['end_time'];
        }
        var_dump($participant);
        $parties[] = create_participant($participant['ID'], $participant['caller_GDS'], $participant['caller_IP'], &$link);
      }

      $conf_duration = $end - $start;
    }
  }

  
  return;
}

function create_point_to_point_conference(&$link) {
  $select_sql = 'SELECT * FROM temp_log WHERE NOT '. mcu_conf_selector();// ." LIMIT 10";
  $result = mysql_query($select_sql, $link);

  while ($row = mysql_fetch_assoc($result)) {
    $parties[] = create_participant($row['ID'], $row['caller_GDS'], $row['caller_IP'], &$link);
    $parties[] = create_participant($row['ID'], $row['called_GDS'], $row['called_IP'], &$link);

    $insert_sql = "INSERT INTO conf (start_datetime , duration) VALUES ('". $row['start_datetime'] ."' , '". $row['duration'] ."')";
    $result = mysql_query($insert_sql, $link);
    $conf_id = mysql_insert_id($link);
  }

}

function conf_participants($conf_id, $parties, &$link) {
  foreach($parties as $participant) {
    $sql = "INSERT INTO conf_part_trans (cid, pid) VALUES (". $conf_id .", ". $participant .")";
    mysql_query($sql, $link);
  }
}

function create_participant($temp_log_id, $gds, $ip, &$link) {
  //TODO: move attribs to conf file
  //gatekeeper IPs
  $exception_ips = array('195.111.192.3', '195.111.192.5');

  if (in_array($ip, $exception_ips)) {
    temp_table_cleaner($temp_log_id, $link);
    return FALSE;
  }
  //check if participant is exists in db
  $sql = "SELECT pid FROM participant WHERE GDS = '". $gds ."' OR IP = '". $ip ."'";

  $party = mysql_fetch_assoc(mysql_query($sql, $link));
  if (!empty($party)) {
    return $party['pid'];
  }
  else {
    $sql = "INSERT INTO participant (GDS, IP) VALUES ('". $gds ."', '". $ip ."')";

    if (!mysql_query($sql, $link)) {
      return FALSE;
    }
//    temp_table_cleaner($temp_log_id, &$link)
    return mysql_insert_id($link);
  }
}

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
    $sql .= "caller_GDS LIKE '". $gds ."%' OR called_GDS LIKE '". $gds ."%' ";
  }

  $key = NULL;
  foreach($vcr_ip AS $key => $ip) {
    if ($sql !== $sql_orig) {
      $sql .= "OR ";
    }
    $sql .= "caller_IP = '". $ip ."' OR called_IP = '". $ip ."' ";
  }

  return mysql_query($sql, $link);
}