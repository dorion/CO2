<?php

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
  curl_setopt($link, CURLOPT_URL, 'http://maps.googleapis.com/maps/api/distancematrix/json?origins='.
    $start_point['lat'],$start_point['lng'] .'&destinations='. $end_point['lat'],$end_point['lng'])
    .'&units=metric&sensor=false';
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
    if ($result['rows']['elements'][0]['status'] === 'OK') {
      return array($distance, $time);
    }
    else {
      return $result['rows']['elements'][0]['status'];
    }
  }
  else {
    return $result['status'];
  }
}
