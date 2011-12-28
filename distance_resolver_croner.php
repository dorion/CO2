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
  $lint = curl_init();

  $link = curl_init();
  $url = 'http://maps.googleapis.com/maps/api/distancematrix/json?origins='.
          $start_point['lat'] .','. $start_point['lng'] .'&destinations='. $end_point['lat'] .','. $end_point['lng']
          .'&units=metric&sensor=false';

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
      return $result['rows']['elements'][0]['status'];
    }
  }
  else {
    return $result['status'];
  }
}

function resolve_distance() {
  $link = connect_sql('co2');
  $sleep = 30;

  $sql = "SELECT c.cid, p.pid, p.latitude, p.longitude
          FROM conf AS c JOIN conf_part_trans AS cpt JOIN participant AS p
          WHERE c.cid = cpt.cid
            AND p.pid = cpt.pid
            AND (p.longitude IS NOT NULL OR p.latitude IS NOT NULL)
            AND (c.longitude IS NULL OR c.latitude IS NULL)
            LIMIT 10
          ";

  $result = mysql_query($sql, $link);
  while ($row = mysql_fetch_assoc($result)) {
    $confs[$row['cid']][$row['pid']] = array('lat' => $row['latitude'], 'lng' => $row['longitude']);
  }

  foreach($confs AS $cid => $participants) {
    $conf_location = array_shift($participants);
    $sql = "UPDATE conf SET latitude = ". $conf_location['lat'] ." longitude = ". $conf_location['lng'] ."WHERE cid = ". $cid;
    mysql_query($sql, $link);
    foreach($participants AS $pid => $participant) {
      $dist_time = distance_and_time_resolver($conf_location, array('lat' => $participant['lat'], 'lng' => $participant['lng']));
      if (is_array($dist_time)) {
        $sleep = 30;
        $sql = "UPDATE conf_part_trans
                SET distance = ". $dist_time['distance'] ." period = ". $dist_time['duration'] ."
                WHERE pid = ". $pid ." AND cid = ". $cid;
        mysql_query($sql, $link);
      }
      else {
        if ($dist_time === 'OVER_QUERY_LIMIT') {
          $sleep = 2 * $sleep;
          print("Sleeping " . $sleep ." seconds\n");
          sleep($sleep);
        }
        else {
          var_dump($dist_time);
        }
      }
    }
  }

  disconnect_sql(&$link);
}

resolve_distance();
