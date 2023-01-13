$(document).ready(function() {

    $(document).on('click', '#btn_map, #btn_map_checkout_shipping, #btn_map_checkout_billing', function (){

        let gmapController = (function () {
            return initialize();
        })();
    });

    $('.mapArea').removeAttr('style').css(
        {
            overflow: 'hidden',
            position: 'absolute',
            left: 0,
            right:0,
            top: 0,
            bottom: 0
        }
    );

    // Toggle Navbar
    $(document).on('click', '#btn_navbar_toggle', function () {

        $('#main_nav').toggle(700);
    });
});

function initialize() {

    let map = null;
    let address = 'My Address';

    function displayPosition(pos) {
        let coords = pos.coords;
        $("#lat").text(coords.latitude);
        $("#long").text(coords.longitude);
        $("#accuracy").text(coords.accuracy);
        addMap(coords);

        // Add marker for location
        addMarker(coords);
    }

    function addMap(location) {
        // Create a lat/lng object
        let pos = new google.maps.LatLng(location.latitude, location.longitude);

        // Create map options
        let mapOptions = {
            center: pos,
            zoom: 17,
            mapTypeId: google.maps.MapTypeId.ROADMAP
        };

        // Create new google map
        map = new google.maps.Map(document.getElementById("map"), mapOptions);
    }

    function addMarker(coords) {

        let lat = coords.latitude;
        let lng = coords.longitude;
        let geocoder = new google.maps.Geocoder();
        let latLng = {lat, lng};
        let infowindow = '';
        geocoder.geocode({
            latLng: latLng
        }, function(responses) {
            infowindow = new google.maps.InfoWindow({
                content: responses[0].formatted_address,
            });
        });

        // Create a new marker
        coords.marker = new google.maps.Marker( {
            position: new google.maps.LatLng(coords.latitude, coords.longitude),
            draggable: true,
            map: map,
            animation: google.maps.Animation.DROP,
            title: address
        });

        coords.marker.addListener("click", () => {

            infowindow.open({
                anchor: coords.marker,
                map,
                shouldFocus: false,
            });
        });

        // Add marker to the map
        coords.marker.setMap(map);

        google.maps.event.addListener(coords.marker, 'dragend', function () {
            geocodePosition(coords.marker.getPosition());
        });
    }

    function geocodePosition(pos){
        let geocoder = new google.maps.Geocoder();
        geocoder.geocode
        ({
                latLng: pos
            },
            function(results, status)
            {
                if (status == google.maps.GeocoderStatus.OK)
                {
                    let string = results[0].formatted_address;
                    let result = string.replaceAll(',','\n');

                    $("#mapSearchInput").val(results[0].formatted_address);
                    $("#mapErrorMsg").hide(100);
                    $('#address_line_1').val(result);
                }
                else
                {
                    $("#mapErrorMsg").html('Cannot determine address at this location.'+status).show(100);
                }
            }
        );
    }

    function displayError(msg) {
        $("#errorArea").removeClass("d-none");
        $("#errorArea").html(msg);
    }

    geoController.getCurrentPosition(displayPosition, displayError);
};