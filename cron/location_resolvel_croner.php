<?php
/**
 * @file Location resolvers
 */

//DB handler
require_once('db.inc.php');

class LocationResolver
{
  static function resolver() {
    $link = connect_sql('co2');
    $location = NULL;

    $sql = "SELECT * FROM participant WHERE latitude IS NULL OR longitude IS NULL";
    $result = mysql_query($sql, $link);

    while ($participant = mysql_fetch_assoc($result)) {
      $location = $this->locationByGDS($participant['GDS']);

      if (!is_array($location)) {
        $location = $this->locationByIP($participant['IP']);
      }

      if (is_array($location)) {
        $sql = "UPDATE participant SET latitude = ". $location['lat'] .", longitude = ". $location['lng'] ." WHERE pid =". $participant['pid'];
        mysql_query($sql, $link);
      }
    }

    disconnect_sql(&$link);
  }

  /**
   * Location resolver by GDS number
   *
   * @param char
   *  GDS number
   *
   * @return
   *  If succes array which containing the coordonations, or a error string if not.
   */
  public function locationByGDS($GDS) {
    //resolvable GDS number must start with double zero
    if ($GDS[0] === '0' AND $GDS[1] === '0') {
      if ($GDS[2] === '3' AND $GDS[3] === '6') {
        $location = $this->ownLocationByGDS($GDS);

        if (!empty($location) AND !empty($location['lat']) OR !empty($location['lat'])) {
          return $location;
        }
        else {
          return array('lat' => 47.1624940, 'lng' => 19.5033040);//Hungary
        }
      }
      else {
        $gds[0] = array(
          "7"   => array('lat' => 61.524010,  'lng' => 105.3187560),//Russia
          "1"   => array('lat' => 37.090240,  'lng' => -95.7128910),//USA
        );

        $gds[1] = array(
          "61"  => array('lat' => 47.5162310, 'lng' => 14.5500720),//Austria
          "55"  => array('lat' => -14.2350040,  'lng' => -51.925280),//Brazil
          "41"  => array('lat' => 46.8181880,  'lng' => 8.227511999999999),//Switzerland
          "49"  => array('lat' => 38.91083250,  'lng' => -75.52766989999999),//Germany
          "45"  => array('lat' => 56.263920,  'lng' => 9.5017850),//Denmark
          "34"  => array('lat' => -19.18342290,  'lng' => -40.30886260),//Spain
          "30"  => array('lat' => 39.0742080,  'lng' => 21.8243120),//Greece
          "39"  => array('lat' => 41.871940,  'lng' => 12.567380),//Italy
          "31"  => array('lat' => 53.13550910,  'lng' => -57.66043640),//Netherlands
          "47"  => array('lat' => -57.66043640,  'lng' => 8.468945999999999),//Norway
          "64"  => array('lat' => -40.9005570,  'lng' => 174.8859710),//Newzealand
          "48"  => array('lat' => 51.9194380,  'lng' => 19.1451360),//Poland
          "46"  => array('lat' => 60.12816100000001,  'lng' => 18.6435010),//Sweden
          "44"  => array('lat' => 55.3780510,  'lng' => -3.4359730),//United Kingdom
        );

        $gds[2] = array(
          "351" => array('lat' => 39.39987199999999,  'lng' => -8.2244540),//Portugal
          "353" => array('lat' => 53.412910,  'lng' => -8.243890),//Ireland
          "354" => array('lat' => 64.96305099999999,  'lng' => -19.0208350),//Iceland
          "357" => array('lat' => 35.1264130,  'lng' => 33.4298590),//Cyprus
          "370" => array('lat' => 55.1694380,  'lng' => 23.8812750),//Lithuania
          "385" => array('lat' => 45.10,  'lng' => 15.20),//Croatia
          "386" => array('lat' => 46.1512410,  'lng' => 14.9954630),//Slovenia
          "420" => array('lat' => 49.81749199999999,  'lng' => 15.4729620),//Czech Republic
          "972" => array('lat' => 31.0460510,  'lng' => 34.8516120),//Israel
        );

        $prefix = '';
        for ($i = 2; $i <= 4; $i++) {
          $prefix .= $GDS[$i];
          if (array_key_exists($prefix, $gds[$i-2])) {
            return $gds[$i-2][$prefix];
          }
        }
      }
    }

    return 'This is not an resolvable GDS number';
  }

  /**
   * IP address - location resolver
   *
   * @param char
   *  Reconstituted DNS name or IP address
   *
   * @return
   *  If succes array which containing the coordonations, or a error string if not.
   */
  public function locationByIP($fqdn_or_ip) {
    $link = curl_init();
    $url = 'http://freegeoip.net/json/'. $fqdn_or_ip;
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
    return array('lat' => $result['latitude'], 'lng' => $result['longitude']);
  }

  public function ownLocationByGDS($GDS) {
      $GDS = substr($GDS, 2);
      //Hungarian GDS location resolving
      $sql = 'SELECT
                cim.lat,
                cim.lng
              FROM enum
                INNER JOIN szammezo
                  ON (enum.szammezo_id = szammezo.id)
                INNER JOIN socket
                  ON (enum.socket_id = socket.id)
                INNER JOIN eszkoz
                  ON (socket.eszkoz_id = eszkoz.id)
                INNER JOIN cim
                  ON (eszkoz.cim_id = cim.id)
              WHERE szammezo.tol = \''. $GDS .'\'';

      $link = connect_sql('iir2');
      $result = mysql_query($sql, $link);
      disconnect_sql($link);

      return mysql_fetch_assoc($result);
  }
}

LocationResolver::resolver();
