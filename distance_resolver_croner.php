<?php
//DB handler
require_once('db.inc.php');
require_once('CO2.conf.php');

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
      return array(
        'distance' => $result['rows'][0]['elements'][0]['distance']['value'],
        'duration' => $result['rows'][0]['elements'][0]['duration']['value'],
      );
    }
    else {
      return $result['rows'][0]['elements'][0]['status'];
    }
  }
  else {
    return $result['status'];
  }
}

function distance_and_time_db_cache($src, $dst, &$db_link) {
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

  $result = mysql_query($sql, $db_link);
  $result = mysql_fetch_assoc($result);

  if($result) {
    return array('distance' => $result['distance'], 'duration' => $result['period']);
  }
  else {
    return FALSE;
  }
}

function coord_distance($start, $end) {
  $delta_lat = $end['lat'] - $start['lat'];
  $delta_lon = $end['lng'] - $start['lng'];

  $earth_radius = 6372797.0; //in meter

  $alpha  = $delta_lat / 2;
  $beta   = $delta_lon / 2;
  $a      = sin(deg2rad($alpha)) * sin(deg2rad($alpha)) + cos(deg2rad($start['lat'])) * cos(deg2rad($end['lat'])) * sin(deg2rad($beta)) * sin(deg2rad($beta));
  $c      = asin(min(1, sqrt($a)));
  $distance = 2 * $earth_radius * $c;
  $distance = round($distance);

  return $distance; //in meter
}

function resolve_distance() {
  $db_link = connect_sql('co2');

  $sql = "SELECT cpt.cid
          FROM conf_part_trans AS cpt
          WHERE cpt.period IS NULL OR cpt.distance IS NULL
          GROUP BY cpt.cid
          ";

  $result = mysql_query($sql, $db_link);
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
    $result = mysql_query($sql, $db_link);
    while ($row = mysql_fetch_assoc($result)) {
      $conf_part[$row['cid']][$row['pid']] = array('lat' => $row['latitude'], 'lng' => $row['longitude']);
    }
  }

  if(!empty($conf_part)) {
    foreach($conf_part AS $cid => $participants) {
      //set up conference location by a random participant
      $conf_location = conf_location(&$participants, $cid, &$db_link);

      foreach($participants AS $pid => $participant) {
        $participant_location = array('lat' => $participant['lat'], 'lng' => $participant['lng']);

        if ($participant_location['lat'] == $conf_location['lng'] AND $participant_location['lng'] == $conf_location['lng']) {
         $dist_time = array('distance' => 0, 'duration' => 0);
        }
        else {
          if (!($dist_time = distance_and_time_db_cache($conf_location, $participant_location, &$db_link))) {
            $dist_time = distance_and_time_resolver($conf_location, $participant_location);

            if ($dist_time === 'ZERO_RESULTS') {
              $dist_time = absolute_distance($conf_location, $participant_location);
            }
          }
        }

        if (is_array($dist_time)) {
          $sql = "UPDATE conf_part_trans
                  SET distance = ". $dist_time['distance'] .", period = ". $dist_time['duration'] ."
                  WHERE pid = ". $pid ." AND cid = ". $cid;
          print $sql ."\n";
          mysql_query($sql, $db_link);
        }
        else {
          var_dump($dist_time);
        }
      }
    }
  }

  disconnect_sql(&$db_link);
}

function conf_location(&$participants, $cid, &$db_link) {
  $key = array_rand($participants, 1);

  $sql = "UPDATE conf SET latitude = ". $participants[$key]['lat'] .", longitude = ". $participants[$key]['lng'] ." WHERE cid = ". $cid;
  mysql_query($sql, $db_link);

  $conf_location = $participants[$key];
  unset($participants[$key]);

  return $conf_location;
}

function absolute_distance($start, $end) {
  $dist = coord_distance($start, $end);
  $time = round($dist / CO2_AVERAGE_SPEED_AEROPLANE);

  if (is_float($dist) AND !empty($time)) {
    return array('distance' => $dist, 'duration' => $time);
  }
  else {
    return 'I can not calculate distance and duration!';
  }
}

function _array_shift(&$array) {
  $first_key = key($array);
  $first = $array[$first_key];
  unset($array[$first_key]);
  return $first;
}

resolve_distance();
