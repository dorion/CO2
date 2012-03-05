<script type="text/javascript"
  src="http://maps.googleapis.com/maps/api/js?key=AIzaSyBjBQ3ho3wTYIDgxSa8g_3ryCpNfrSAn0U&sensor=false">
</script>
<style type="text/css">
  #map {
    height: 480px;
    width: 800px;
    border: solid thin #333;
    margin-top: 20px;
  }
</style>

<script>
  var destinationIcon = "http://chart.apis.google.com/chart?chst=d_map_pin_letter&chld=D|FF0000|000000";
  var originIcon = "http://chart.apis.google.com/chart?chst=d_map_pin_letter&chld=O|FFFF00|000000";
  var directionsDisplay;
  var directionsService = new google.maps.DirectionsService();
  var map;
  var markers = [];

  function initialize() {
    directionsDisplay = new google.maps.DirectionsRenderer();
    var myOptions = {
      zoom:7,
      mapTypeId: google.maps.MapTypeId.ROADMAP,
      center: new google.maps.LatLng(47.139293, 19.127197),
    }

    map = new google.maps.Map(document.getElementById("map"), myOptions);
    geocoder = new google.maps.Geocoder();
  }

  function route(start, end, i) {
    var rendererOptions = {
      routeIndex: i,
      suppressMarkers: true,
    };

    var request = {
      travelMode: google.maps.TravelMode.DRIVING,
      origin:start,
      destination:end,
    };

    var directionsDisplay = new google.maps.DirectionsRenderer(rendererOptions);
    directionsDisplay.setMap(map);

    directionsService.route(request, function(result, status) {
      if (status == google.maps.DirectionsStatus.OK) {
        directionsDisplay.setDirections(result);
        var conferenceLocation  = [result.routes[0].legs[0].start_location, result.routes[0].legs[0].start_address];
        var participantLocation = [result.routes[0].legs[0].end_location, result.routes[0].legs[0].end_address];

        addMarker(conferenceLocation[0], conferenceLocation[1], false);
        addMarker(participantLocation[0], participantLocation[1], true);
      }
    });
  }

  function addMarker(location, address, isDestination) {
    var icon;

    if (isDestination) {
      icon = destinationIcon;
      zindex = 10000;
      title = '';
    }
    else {
      icon = originIcon;
      zindex = 100000;
      text = 'Conference place';
    }
    var marker = new google.maps.Marker({
          map: map,
          position: location,
          icon: icon,
          zIndex: zindex,
          title: text,
        });

    var infowindow = new google.maps.InfoWindow({
      content: address,
    });

    google.maps.event.addListener(marker, 'click', function () {
      infowindow.open(map, this);
    });
  }

  function generateRoutes() {
    initialize();
    var origins = <?php print($origins); ?>;
    var destinations = <?php print($destinations); ?>;
    var i;

    for (i = 0; i < origins.length; i++) {
      route(origins[i], destinations[i], i);
    }
  }

  google.maps.event.addDomListener(window, 'load', generateRoutes);
</script>
<div id="map"></div>
