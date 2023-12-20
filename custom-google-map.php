<?php
/*
Plugin Name: Custom Google Map
Description: Custom Google Map for Nola Beer Bus use [CustomGoogleMap] shortcode.
Version: 1.0
Author: Lisa Thompson (Built By Redcypress)
*/

function custom_google_map_shortcode()
{
    $googleApiKey = get_option('custom_google_map_api_key');
    $showBus = get_option('stop_showing_bus', false);
    $plugin_url = plugins_url('/', __FILE__);
    $busImage = $plugin_url . 'images/bus.png';
    $breweryImage = $plugin_url . 'images/brewery.png';
    $busPHP = $plugin_url . 'bus/busInfo.php';
    $busCSS = $plugin_url . 'style.css';

$gpsApiKey = get_option('custom_google_map_gps_api_key');
$gpsApiSecret = get_option('custom_google_map_gps_api_secret');

$credentials = $gpsApiKey . ':' . $gpsApiSecret;
$base64_credentials = base64_encode($credentials);


    ob_start();
    ?>

    <div id="business-hours-message"></div>

    <div id="map" style="height: 500px; width: 100%;"></div>
    
    <link rel="stylesheet" href="<?=$busCSS?>">
    <script>
        function calculateAverageCoordinates(locations) {
            if (locations.length === 0) {
                return { lat: 0, lng: 0 }; // Default to center of the world if no locations
            }

            const sumLat = locations.reduce((acc, location) => acc + parseFloat(location.lat), 0);
            const sumLng = locations.reduce((acc, location) => acc + parseFloat(location.lng), 0);

            const averageLat = sumLat / locations.length;
            const averageLng = sumLng / locations.length;

            return { lat: averageLat, lng: averageLng };
        }
        function initMap() {
            const locations = <?php echo json_encode(get_option('custom_google_map_locations', array())); ?>;
            const businessHours = <?php echo json_encode(get_option('business_hours', array())); ?>;
            const route1Locations = <?php echo json_encode(get_option('route1Locations', array())); ?>;
            const route2Locations = <?php echo json_encode(get_option('route2Locations', array())); ?>;

            const route1LocationArray = getLatLngArray(route1Locations);
            route1LocationArray.push(route1LocationArray[0]);

             snapPolylineToRoads(route1LocationArray)
            .then(snappedRoute1Locations => {
                // Create a polyline and set its path to the snapped coordinates
                const snappedPolyline1 = new google.maps.Polyline({
                    path: snappedRoute1Locations,
                    geodesic: true,
                    strokeColor: '#4AB596',
                    strokeOpacity: 1.0,
                    strokeWeight: 4,
                });

                // Set the map for the snapped polyline
                snappedPolyline1.setMap(map);
            })
            .catch(error => {
                console.error('Error snapping polyline to roads:', error);
            });

            const route2LocationArray = getLatLngArray(route2Locations);
            route2LocationArray.push(route2LocationArray[0]);
            snapPolylineToRoads(route2LocationArray)
                .then(snappedRoute2Locations => {
                    // Create a polyline and set its path to the snapped coordinates
                    const snappedPolyline2 = new google.maps.Polyline({
                        path: snappedRoute2Locations,
                        geodesic: true,
                        strokeColor: '#FFAD23',
                        strokeOpacity: 1.0,
                        strokeWeight: 4,
                    });

                    // Set the map for the snapped polyline
                    snappedPolyline2.setMap(map);
                })
                .catch(error => {
                    console.error('Error snapping polyline to roads:', error);
                });


            const mapOptions = {
                zoom: 13,
                styles: [
                    {
                        featureType: "all",
                        elementType: "all",
                        stylers: [
                            { saturation: -100 } // <-- THIS
                        ]
                    },
                    {
                        featureType: 'poi',
                        stylers: [{ visibility: 'off' }],
                    }, 
                    {
                        featureType: 'administrative',
                        stylers: [{ visibility: 'off' }],
                    },
                ],
            };
            const map = new google.maps.Map(document.getElementById('map'), mapOptions);
            map.setCenter( { lat: 29.972354888916016, lng: -90.083869934082031});
            let allMarkerLocations = [];
            if (locations && Object.keys(locations).length) {
                const bounds = new google.maps.LatLngBounds();
				for(var key in locations) {
					if (locations.hasOwnProperty(key)) {
						var marker = new google.maps.Marker({
							position: { lat: parseFloat(locations[key].lat), lng: parseFloat(locations[key].lng)},
							map: map,
							title: locations[key].title,
                            label: {
                                text: locations[key].title,
                                fontSize: "12px",
                                fontWeight: "bold"
                            },
                            icon: '<?php echo esc_url($breweryImage) ?>'
						});

                        allMarkerLocations.push({ lat: parseFloat(locations[key].lat), lng: parseFloat(locations[key].lng)});

                        (function(marker, location) {
                            const infowindow = new google.maps.InfoWindow({
                                content: "<h4>" + location.title + "</h4> " + location.address,
                            });

                            marker.addListener('click', function () {
                                infowindow.open(map, marker);
                            });
                        })(marker, locations[key]);
						bounds.extend(marker.getPosition());
					}
				}
                const averageCoordinates = calculateAverageCoordinates(allMarkerLocations);
                
                map.setCenter(averageCoordinates);
                //map.fitBounds(bounds);
            };

            // Check business hours and display message
            const currentDay = new Date().toLocaleDateString('en-US', { weekday: 'long' });
            const currentTime = new Date().toLocaleTimeString('en-US', { hour: 'numeric', minute: 'numeric', hour12: true });

            var BusMarker = new google.maps.Marker({
                    position: { lat: 29.972354888916016, lng: -90.083869934082031},
                    map: map,
                    zIndex:99999999,
                    icon: '<?php echo esc_url($busImage) ?>'
                });
                
            var disableBusView = <?php echo get_option('stop_showing_bus', false) ? 'true' : 'false'; ?>;

            if (disableBusView || isOutsideBusinessHours(currentDay, currentTime, businessHours)) {
                BusMarker.setMap(null);
                document.getElementById('business-hours-message').innerHTML = 'The Nola Beer Bus is currently not running.';
            }
            else{
                const refreshButton = document.createElement('button');
                refreshButton.setAttribute("id", "refreshButton");
                refreshButton.textContent = 'Refresh Bus Location';
                refreshButton.addEventListener('click', function() {
                    fetchData(true);
                });
                document.getElementById('map').before(refreshButton);


                // Fetch data during business hours
                fetchData(true);
                // Set interval to fetch data every 30 seconds
               
                setInterval(fetchData, 30000);
               
            }

            function isOutsideBusinessHours(day, time, businessHours) {
                day = day.toLowerCase();
                if (businessHours.hasOwnProperty(day)) {
                    if( businessHours[day]['start'] === ""){
                        return true;
                    }

                    let startTime = businessHours[day]['start'];
                    let endTime = businessHours[day]['end'];
                    let currentDate = new Date(); 

                    startDate = new Date(currentDate.getTime());
                    startDate.setHours(startTime.split(":")[0]);
                    startDate.setMinutes(startTime.split(":")[1]);

                    endDate = new Date(currentDate.getTime());
                    endDate.setHours(endTime.split(":")[0]);
                    endDate.setMinutes(endTime.split(":")[1]);

                    return currentDate < startDate || endDate < currentDate     
                }
                return true;
            }           

            function fetchData(firstload = false) {
                BusMarker.setMap(null);
                document.getElementById('business-hours-message').innerHTML = '';


                fetch('<?=$busPHP?>?cred=<?=esc_attr($base64_credentials);?>&timestamp=' + new Date().getTime())
                .then(response => response.json())
                .then(data => {
                    // Process the API response (in 'data' variable)
                     BusMarker = new google.maps.Marker({
                        position: { lat: data[0]["Latitude"], lng: data[0]["Longitude"]},
                        map: map,
                        zIndex:99999999,
                        icon: '<?php echo esc_url($busImage) ?>'
                    });
                    if(firstload){
                        map.setCenter(  { lat: data[0]["Latitude"], lng: data[0]["Longitude"]});
                    }

                })
                .catch(error => {
                    // Handle errors
                    document.getElementById('business-hours-message').innerHTML = 'Error Getting Bus Location.';

                    console.error('Fetch API Error:', error);
                    return false;
                });
                

            }
        }

        function snapPolylineToRoads(route1LocationArray) {
            return new Promise((resolve, reject) => {
                // Check if route1Locations is an array
                if (!Array.isArray(route1LocationArray)) {
                    reject(new Error('Invalid route1Locations data.'));
                    return;
                }

                const service = new google.maps.DirectionsService();
                const waypoints = route1LocationArray.map(coord => ({
                    location: new google.maps.LatLng(coord.lat, coord.lng),
                    stopover: true,
                }));

                const request = {
                    origin: waypoints[0].location,
                    destination: waypoints[waypoints.length - 1].location,
                    waypoints: waypoints.slice(1, -1),
                    travelMode: google.maps.TravelMode.DRIVING,
                };

                service.route(request, (result, status) => {
                    if (status === google.maps.DirectionsStatus.OK) {
                        const snappedCoordinates = result.routes[0].overview_path.map(point => ({
                            lat: point.lat(),
                            lng: point.lng(),
                        }));
                        resolve(snappedCoordinates);
                    } else {
                        reject(new Error('Directions request failed'));
                    }
                });
            });
        }

        function getLatLngArray(locations) {
        return Object.keys(locations).map(key => {
            return { lat: parseFloat(locations[key].lat), lng: parseFloat(locations[key].lng) };
        });
    }
    </script>

    <script src="https://maps.googleapis.com/maps/api/js?key=<?php echo esc_attr($googleApiKey); ?>&callback=initMap" async
            defer></script>
    <?php
    return ob_get_clean();
}


// Register the shortcode with the name 'CustomGoogleMap'
add_shortcode('CustomGoogleMap', 'custom_google_map_shortcode');

// Add settings menu
function custom_google_map_menu()
{
    add_menu_page(
        'Custom Google Map Settings',
        'Google Map Settings',
        'manage_options',
        'custom-google-map-settings',
        'custom_google_map_settings_page',
        'dashicons-location', // You can change the icon as needed
        80 // Adjust the position to your preference
    );
}
add_action('admin_menu', 'custom_google_map_menu');

// Register settings
function custom_google_map_settings()
{
    register_setting('custom_google_map_settings_group', 'custom_google_map_api_key');
    register_setting('custom_google_map_settings_group', 'custom_google_map_gps_api_key');
    register_setting('custom_google_map_settings_group', 'custom_google_map_gps_api_secret');

    register_setting('custom_google_map_settings_group', 'custom_google_map_locations');
    register_setting('custom_google_map_settings_group', 'business_hours', 'sanitize_business_hours');
    register_setting('custom_google_map_settings_group', 'route1Locations', 'sanitize_route_locations');
    register_setting('custom_google_map_settings_group', 'route2Locations', 'sanitize_route_locations');
    register_setting('custom_google_map_settings_group', 'stop_showing_bus');

}
add_action('admin_init', 'custom_google_map_settings');

// Create settings page
function custom_google_map_settings_page()
{
    $locations = get_option('custom_google_map_locations', array());
    $stopShowingBus = get_option('stop_showing_bus', false);

    ?>
    <div class="wrap">
        <h2>Custom Google Map Settings</h2>
        <form method="post" action="options.php">
            <?php settings_fields('custom_google_map_settings_group'); ?>
            <?php do_settings_sections('custom_google_map_settings_group'); ?>
            <?php wp_nonce_field('custom_google_map_nonce', 'custom_google_map_nonce'); ?>
            <table class="form-table">
            <tr valign="top">
                    <td>
                        <h3>Stop Showing Bus:</h3>
                        <label>
                            <input type="checkbox" name="stop_showing_bus" value="1" <?php checked($stopShowingBus); ?> />
                            Stop Showing Bus
                        </label>
                    </td>
                </tr>
                <tr valign="top">
                    <td>
                        <h3>Locations:</h3>
                        <div id="locations-container">
                            <?php foreach ($locations as $index => $location): ?>
                                <div class="location-row">
                                    <input type="text" name="custom_google_map_locations[<?php echo $index; ?>][title]"
                                           placeholder="Title" value="<?php echo esc_attr($location['title']); ?>" />
                                    <input type="text" name="custom_google_map_locations[<?php echo $index; ?>][address]"
                                           placeholder="address" value="<?php echo esc_attr($location['address']); ?>" />
                                    <input type="text" name="custom_google_map_locations[<?php echo $index; ?>][lat]"
                                           placeholder="Latitude" value="<?php echo esc_attr($location['lat']); ?>" />
                                    <input type="text" name="custom_google_map_locations[<?php echo $index; ?>][lng]"
                                           placeholder="Longitude" value="<?php echo esc_attr($location['lng']); ?>" />
                                    <button type="button" class="remove-location">Remove</button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" id="add-location">Add Location</button>
                    </td>
                </tr>
                <tr valign="top">
                   
                    <td>
                        <h3>Business Hours:</h3>
                        <div id="business-hours-container">
                            <?php
                            $days_of_week = array('Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday');
                            foreach ($days_of_week as $day) {
                                ?>
                                <div class="business-hours-row">
                                    <label><?php echo $day; ?>:</label>
                                    <input type="text" name="business_hours[<?php echo strtolower($day); ?>][start]" placeholder="Start Time" value="<?php echo esc_attr(get_option('business_hours', array())[strtolower($day)]['start']); ?>"/>
                                    <input type="text" name="business_hours[<?php echo strtolower($day); ?>][end]" placeholder="End Time" value="<?php echo esc_attr(get_option('business_hours', array())[strtolower($day)]['end']); ?>"/>
                                </div>
                                <?php
                            }
                            ?>
                        </div>
                    </td>
                </tr>
                <tr valign="top">
                    <td>
                        <h3>Route 1 Locations:</h3>
                        <div id="route-locations-container">
                            <?php
                            $route1Locations = get_option('route1Locations', array());
                            foreach ($route1Locations as $index => $location) :
                            ?>
                                <div class="route-location-row">
                                    <select  class="location-dropdown" id="location1-dropdown-<?=$index;?>" data-index="<?php echo $index; ?>"  name="route1Locations[<?php echo $index; ?>][location]" placeholder="Select Location">
                                        <?php
                                        $locations = get_option('custom_google_map_locations', array());
                                        foreach ($locations as $locIndex => $loc) :
                                        ?>
                                            <option value="<?php echo esc_attr($loc['title']); ?>" <?php selected($location['location'], $loc['title']); ?>>
                                                <?php echo esc_html($loc['title']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <input type="hidden" name="route1Locations[<?php echo $index; ?>][lat]" value="<?php echo esc_attr($location['lat']); ?>" />
                                    <input type="hidden" name="route1Locations[<?php echo $index; ?>][lng]" value="<?php echo esc_attr($location['lng']); ?>" />
                                    <button type="button" class="remove-route-location">Remove</button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" id="add-route-location">Add Location</button>
                    </td>
                </tr>
                <tr valign="top">
                    <td>
                        <h3>Route 2 Locations:</h3>
                        <div id="route2-locations-container">
                            <?php
                            $route2Locations = get_option('route2Locations', array());
                            foreach ($route2Locations as $index => $location) :
                            ?>
                                <div class="route-location2-row">
                                    <select class="location2-dropdown" id="location2-dropdown-<?=$index;?>" data-index="<?php echo $index; ?>"  name="route2Locations[<?php echo $index; ?>][location]" placeholder="Select Location">
                                        <?php
                                        foreach ($locations as $locIndex => $loc) :
                                        ?>
                                            <option value="<?php echo esc_attr($loc['title']); ?>" <?php selected($location['location'], $loc['title']); ?>>
                                                <?php echo esc_html($loc['title']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <input type="hidden" name="route2Locations[<?php echo $index; ?>][lat]" value="<?php echo esc_attr($location['lat']); ?>" />
                                    <input type="hidden" name="route2Locations[<?php echo $index; ?>][lng]" value="<?php echo esc_attr($location['lng']); ?>" />
                                    <button type="button" class="remove-route-location">Remove</button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" id="add-route2-location">Add Location</button>
                    </td>
                </tr>
                <tr valign="top">
                    <td>
                        <h3>Google Maps API Key:</h3>    
                        <input type="text" name="custom_google_map_api_key"
                               value="<?php echo esc_attr(get_option('custom_google_map_api_key')); ?>" />
                    </td>
                </tr>
                <tr valign="top">
                    <td>

                        <h3>GPS Maps API Key:</h3>    
                        <input type="text" name="custom_google_map_gps_api_key"
                               value="<?php echo esc_attr(get_option('custom_google_map_gps_api_key')); ?>" />
                        <h3>GPS Maps API Secret:</h3>    
                        <input type="text" name="custom_google_map_gps_api_secret"
                               value="<?php echo esc_attr(get_option('custom_google_map_gps_api_secret')); ?>" />
                    
                            </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const locationsContainer = document.getElementById('locations-container');
            const addLocationButton = document.getElementById('add-location');
            const routeLocationsContainer = document.getElementById('route-locations-container');
            const addRouteLocationButton = document.getElementById('add-route-location');
            const route1Locations = <?php echo json_encode(get_option('route1Locations', array())); ?>;
            const route2LocationsContainer = document.getElementById('route2-locations-container');
            const addRoute2LocationButton = document.getElementById('add-route2-location');
            const route2Locations = <?php echo json_encode(get_option('route2Locations', array())); ?>;
            const locations = <?php echo json_encode(get_option('custom_google_map_locations', array())); ?>; 

            const route1Selects = document.getElementsByClassName("location-dropdown");
            const route2Selects = document.getElementsByClassName("location2-dropdown");



            function createLocationRow() {
                const index = Date.now();
                const row = document.createElement('div');
                row.classList.add('location-row');
                row.innerHTML = `
                        <input type="text" name="custom_google_map_locations[${index}][title]" placeholder="Title" />
                        <input type="text" name="custom_google_map_locations[${index}][address]" placeholder="Address" />
                        <input type="text" name="custom_google_map_locations[${index}][lat]" placeholder="Latitude" />
                        <input type="text" name="custom_google_map_locations[${index}][lng]" placeholder="Longitude" />
                        <button type="button" class="remove-location">Remove</button>
                    `;
                locationsContainer.appendChild(row);
            }

            addLocationButton.addEventListener('click', createLocationRow);

            locationsContainer.addEventListener('click', function (event) {
                if (event.target.classList.contains('remove-location')) {
                    event.target.closest('.location-row').remove();
                }
            });

            function createRouteLocationRow(index, routeLocations) {
                const row = document.createElement('div');
                row.classList.add('route-location-row');

                if (routeLocations && typeof routeLocations === 'object') {
                    // Convert the object to an array of locations
                    const locationsArray = Object.keys(routeLocations).map(key => routeLocations[key]);

                    row.innerHTML = `
                        <select class="location-dropdown" id="location1-dropdown-${index}" data-index="${index}" name="route1Locations[${index}][location]" placeholder="Select Location">
                            ${locationsArray.map((location, locIndex) => `
                                <option value="${location.title}" data-lat="${location.lat}" data-lng="${location.lng}">
                                    ${location.title}
                                </option>
                            `).join('')}
                        </select>
                        <input type="hidden" name="route1Locations[${index}][lat]" value="${locationsArray[0].lat}" />
                        <input type="hidden" name="route1Locations[${index}][lng]" value="${locationsArray[0].lng}" />
                        <button type="button" class="remove-route-location">Remove</button>
                    `;
                } else {
                    row.innerHTML = 'Error: routeLocations is not an object';
                }

                routeLocationsContainer.appendChild(row);

                // Add event listener to update hidden fields when dropdown changes
                const locationDropdown = row.querySelector('.location-dropdown');
                locationDropdown.addEventListener('change', function () {
                    const selectedLocationValue = locationDropdown.value;
                    let selectedLocationKey;
                        for(let key in routeLocations) {
                            if(routeLocations[key].title === selectedLocationValue){
                                selectedLocationKey = key;
                                break;
                            }
                        }
                    const selectedLocation = routeLocations[selectedLocationKey];
                    const latInput = row.querySelector('input[name^="route1Locations"][name$="[lat]"]');
                    const lngInput = row.querySelector('input[name^="route1Locations"][name$="[lng]"]');

                    if (selectedLocation) {
                        latInput.value = selectedLocation.lat;
                        lngInput.value = selectedLocation.lng;
                    }
                });
            }

            addRouteLocationButton.addEventListener('click', function () {
                const newIndex = Date.now();
                createRouteLocationRow(newIndex, <?php echo json_encode(get_option('custom_google_map_locations', array())); ?>);
            });
            
            routeLocationsContainer.addEventListener('click', function (event) {
                if (event.target.classList.contains('remove-route-location')) {
                    event.target.closest('.route-location-row').remove();
                }
            });


            function createRoute2LocationRow(index, routeLocations) {
                const row = document.createElement('div');
                row.classList.add('route-location2-row');

                if (routeLocations && typeof routeLocations === 'object') {
                    // Convert the object to an array of locations
                    const locationsArray = Object.keys(routeLocations).map(key => routeLocations[key]);

                    row.innerHTML = `
                        <select  class="location2-dropdown"  name="route2Locations[${index}][location]" placeholder="Select Location">
                            ${locationsArray.map((location, locIndex) => `
                                <option value="${location.title}" ${locIndex === 0 ? 'selected' : ''}>
                                    ${location.title}
                                </option>
                            `).join('')}
                        </select>
                        <input type="hidden" name="route2Locations[${index}][lat]" value="${locationsArray[0].lat}" />
                        <input type="hidden" name="route2Locations[${index}][lng]" value="${locationsArray[0].lng}" />
                        <button type="button" class="remove-route-location">Remove</button>
                    `;
                } else {
                    row.innerHTML = 'Error: routeLocations is not an object';
                }

                route2LocationsContainer.appendChild(row);

                // Add even t listener to update hidden fields when dropdown changes
                const locationDropdown = row.querySelector('.location2-dropdown');
                locationDropdown.addEventListener('change', function () {
                    const selectedLocationValue = locationDropdown.value;
                    let selectedLocationKey;
                        for(let key in routeLocations) {
                            if(routeLocations[key].title === selectedLocationValue){
                                selectedLocationKey = key;
                                break;
                            }
                        }
                    const selectedLocation = routeLocations[selectedLocationKey];
                    const latInput = row.querySelector('input[name^="route2Locations"][name$="[lat]"]');
                    const lngInput = row.querySelector('input[name^="route2Locations"][name$="[lng]"]');

                    if (selectedLocation) {
                        latInput.value = selectedLocation.lat;
                        lngInput.value = selectedLocation.lng;
                    }
                });

            }

            addRoute2LocationButton.addEventListener('click', function () {
                const newIndex = Date.now();
                createRoute2LocationRow(newIndex, <?php echo json_encode(get_option('custom_google_map_locations', array())); ?>);
            });

            route2LocationsContainer.addEventListener('click', function (event) {
                if (event.target.classList.contains('remove-route-location')) {
                    event.target.closest('.route-location2-row').remove();
                }
            });


            for(var i = 0; i < route1Selects.length; i++) {
                (function(index) {
                    route1Selects[index].addEventListener("change", function() {
                        const selectedLocationValue = this.value;
                        const thisIndex =  this.getAttribute('data-index');
                        let selectedLocationKey;
                            for(let key in locations) {
                                if(locations[key].title === selectedLocationValue){
                                    selectedLocationKey = key;
                                    break;
                                }
                            }
                        const selectedLocation = locations[selectedLocationKey];
                        document.getElementsByName('route1Locations['+thisIndex+'][lat]')[0].value = selectedLocation.lat;
;                        document.getElementsByName('route1Locations['+thisIndex+'][lng]')[0].value = selectedLocation.lng;

                    })
                })(i);
            }

            for(var i = 0; i < route2Selects.length; i++) {
                (function(index) {
                    route2Selects[index].addEventListener("change", function() {
                        const selectedLocationValue = this.value;
                        const thisIndex =  this.getAttribute('data-index');
                        let selectedLocationKey;
                            for(let key in locations) {
                                if(locations[key].title === selectedLocationValue){
                                    selectedLocationKey = key;
                                    break;
                                }
                            }
                        const selectedLocation = locations[selectedLocationKey];
                        document.getElementsByName('route2Locations['+thisIndex+'][lat]')[0].value = selectedLocation.lat;
;                        document.getElementsByName('route2Locations['+thisIndex+'][lng]')[0].value = selectedLocation.lng;

                    })
                })(i);
            }

        });
    </script>
    <?php
}

// Sanitize business hours
function sanitize_business_hours($input)
{
    // Ensure the input is an array
    $output = is_array($input) ? $input : array();

    // Sanitize each day's input
    foreach ($input as $day => $values) {
        $output[$day]['start'] = sanitize_text_field($values['start']);
        $output[$day]['end'] = sanitize_text_field($values['end']);
    }

    return $output;
}

function sanitize_route_locations($input)
{
    // Ensure the input is an array
    $output = is_array($input) ? $input : array();

    // Sanitize each location's input
    foreach ($input as $index => $location) {
        $output[$index]['lat'] = sanitize_text_field($location['lat']);
        $output[$index]['lng'] = sanitize_text_field($location['lng']);
    }

    return $output;
}
?>