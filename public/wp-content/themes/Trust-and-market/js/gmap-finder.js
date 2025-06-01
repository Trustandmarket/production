/*
 * Google Maps: Latitude-Longitude Finder Tool
 * http://salman-w.blogspot.com/2009/03/latitude-longitude-finder-tool.html
 */
function loadmap() {

  if (document.querySelector(".gmap-finder-input-txt") !== undefined) {
    // initialize map
    var geocoder = new google.maps.Geocoder();
    google.maps.event.addDomListener(document.querySelector(".gmap-finder-input-txt"), "keypress", function() {
      geocoder.geocode({
        address: document.querySelector(".gmap-finder-input-txt-pays") === undefined ? document.querySelector(".gmap-finder-input-txt").value + ', ' + document.querySelector(".gmap-finder-input-txt-pays").value : document.querySelector(".gmap-finder-input-txt").value
      }, function(results, status) {
        if (status == google.maps.GeocoderStatus.OK) {
          var result = results[0];
          document.querySelector(".gmap-finder-input-auto-complite").innerText = result.formatted_address;
          $(".gmap-finder-input-auto-complite").slideDown(200);

        }
        // else if (status == google.maps.GeocoderStatus.ZERO_RESULTS) {
        //   alert("Sorry, geocoder API failed to locate the address.");
        // }
        // else {
        //   alert("Sorry, geocoder API failed with an error.");
        // }
      });
    });
    google.maps.event.addDomListener(document.querySelector("body"), "click", function() {
      document.querySelector(".gmap-finder-input-auto-complite").innerText = " ";
      $(".gmap-finder-input-auto-complite").slideUp(100);
    });
    google.maps.event.addDomListener(document.querySelector(".gmap-finder-input-auto-complite"), "click", function() {
      document.querySelector(".gmap-finder-input-txt").value = document.querySelector(".gmap-finder-input-auto-complite").innerText;
      document.querySelector(".gmap-finder-input-auto-complite").innerText = " ";
      $(".gmap-finder-input-auto-complite").slideUp(100);
    });
    google.maps.event.addDomListener(document.querySelector(".gmap-finder-input-txt"), "keydown", function(domEvent) {
      if (domEvent.which === 40 || domEvent.keyCode === 40) {
        domEvent.preventDefault();
        document.querySelector(".gmap-finder-input-txt").value = document.querySelector(".gmap-finder-input-auto-complite").innerText;
        document.querySelector(".gmap-finder-input-auto-complite").innerText = " ";
        $(".gmap-finder-input-auto-complite").slideUp(100);
      }
    });
  }
}
