<?php
//DB handler
require_once('db.inc.php');

/**
 * Resolve the distance and the duration time with google distance matrix API
 * http://code.google.com/intl/hu-HU/apis/maps/documentation/distancematrix/
 *
 * @param array
 *  Coordinate of the start point
 * @param array
 *  Coordinate of the end point
 *
 * @return
 *  An array if success which include distance and time values and a string if unsuccessfull
 */
function distance_and_time_resolver($start_point, $end_point) {
  $url = 'http://maps.googleapis.com/maps/api/distancematrix/json'
          .'?origins='. $start_point['lat'] .','. $start_point['lng']
          .'&destinations='. $end_point['lat'] .','. $end_point['lng']
          .'&units=metric&sensor=false';

  $link = curl_init();
  curl_setopt($link, CURLOPT_URL, $url);
  curl_setopt($link, CURLOPT_RETURNTRANSFER, TRUE);

  $result = curl_exec($link);
  $http_code = curl_getinfo($link, CURLINFO_HTTP_CODE);

  $error_msg = curl_error($link);
  curl_close($link);

  if ($error_msg) {
    return $error_msg;
  }

  if ($http_code != '200') {
    return 'Http error: '. $http_code;
  }

  $result = json_decode($result, TRUE);

  if ($result['status'] === 'OK') {
    if ($result['rows'][0]['elements'][0]['status'] === 'OK') {
      return array('distance' => $result['rows'][0]['elements'][0]['distance']['value'], 'duration' => $result['rows'][0]['elements'][0]['duration']['value']);
    }
    else {
      return $result['rows'][0]['elements'][0]['status'];
    }
  }
  else {
    return $result['status'];
  }
}

function distance_and_time_db_cache($src, $dst, &$link) {
  $sql = "SELECT cpt.period, cpt.distance
          FROM conf AS c JOIN conf_part_trans AS cpt JOIN participant AS p
          WHERE c.cid = cpt.cid
                AND p.pid = cpt.pid
                AND (cpt.distance IS NOT NULL OR cpt.period IS NOT NULL)
                AND (
                    (p.latitude = ". $src['lat'] ."
                    AND p.longitude = ". $src['lng'] ."
                    AND c.latitude = ". $dst['lat'] ."
                    AND c.longitude = ". $dst['lng'] .")
                  OR
                    (p.latitude = ". $dst['lat'] ."
                    AND p.longitude = ". $dst['lng'] ."
                    AND c.latitude = ". $src['lat'] ."
                    AND c.longitude = ". $src['lng'] ."))";

  $result = mysql_query($sql, $link);
  $result = mysql_fetch_assoc($result);

  if($result) {
    return array('distance' => $result['distance'], 'duration' => $result['period']);
  }
  else {
    return FALSE;
  }
}

function resolve_distance() {
  $link = connect_sql('co2');
  $sleep = 30;

  $sql = "SELECT cpt.cid
          FROM conf_part_trans AS cpt
          WHERE cpt.period IS NULL OR cpt.distance IS NULL
          GROUP BY cpt.cid
          ";

  $result = mysql_query($sql, $link);
  $confs = array();
  while ($row = mysql_fetch_assoc($result)) {
    $confs[] = $row['cid'];
  }

  $participants = array();
  $conf_part = array();
  foreach($confs AS $cid) {
    $sql = "SELECT c.cid, p.pid, p.latitude, p.longitude
            FROM conf AS c JOIN conf_part_trans AS cpt JOIN participant AS p
            WHERE c.cid = cpt.cid
              AND p.pid = cpt.pid
              AND (p.longitude IS NOT NULL OR p.latitude IS NOT NULL)
              AND (cpt.distance IS NULL OR cpt.period IS NULL)
              AND c.cid = ". $cid ."
            ";

    $result = mysql_query($sql, $link);
    while ($row = mysql_fetch_assoc($result)) {
      $conf_part[$row['cid']][$row['pid']] = array('lat' => $row['latitude'], 'lng' => $row['longitude']);
    }
  }

  if(!empty($conf_part)) {
    foreach($conf_part AS $cid => $participants) {
      $conf_location = _array_shift(&$participants);
      $sql = "UPDATE conf SET latitude = ". $conf_location['lat'] .", longitude = ". $conf_location['lng'] ." WHERE cid = ". $cid;
      mysql_query($sql, $link);

      foreach($participants AS $pid => $participant) {
        $participant_location = array('lat' => $participant['lat'], 'lng' => $participant['lng']);
        if (!($dist_time = distance_and_time_db_cache($conf_location, $participant_location, &$link))) {
          $dist_time = distance_and_time_resolver($conf_location, $participant_location);
        }
        if (is_array($dist_time)) {
          $sleep = 30;
          $sql = "UPDATE conf_part_trans
                  SET distance = ". $dist_time['distance'] .", period = ". $dist_time['duration'] ."
                  WHERE pid = ". $pid ." AND cid = ". $cid;
          var_dump($sql);
          var_dump(mysql_query($sql, $link));
        }
        else {
          if ($dist_time === 'OVER_QUERY_LIMIT') {
    #        die($dist_time);
/*            $sleep = 2 * $sleep;
            print("Sleeping " . $sleep ." seconds\n");
            sleep($sleep);*/
          }
          else {
            var_dump($dist_time);
          }
        }
      }
    }
  }

  disconnect_sql(&$link);
}

function _array_shift(&$array) {
  $first_key = key($array);
  $first = $array[$first_key];
  unset($array[$first_key]);
  return $first;
}

resolve_distance();
