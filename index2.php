<?php

class Location
{

    function haversineDistance($latitudeFrom, $longitudeFrom, $latitudeTo, $longitudeTo)
    {
        $earthRadius = 6371;

        $latFrom = deg2rad($latitudeFrom);
        $lonFrom = deg2rad($longitudeFrom);
        $latTo = deg2rad($latitudeTo);
        $lonTo = deg2rad($longitudeTo);

        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;

        $a = sin($latDelta / 2) * sin($latDelta / 2) +
            cos($latFrom) * cos($latTo) *
            sin($lonDelta / 2) * sin($lonDelta / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    function getClosest($policeStations, $userLatitude, $userLongitude)
    {
        $closestStation = null;
        $shortestDistance = PHP_FLOAT_MAX;

        foreach ($policeStations as $station) {
            $distance = $this->haversineDistance(
                $userLatitude,
                $userLongitude,
                $station['latitude'],
                $station['longitude']
            );

            if ($distance < $shortestDistance) {
                $shortestDistance = $distance;
                $closestStation = $station;
            }
        }

        return ["shortest" => $shortestDistance, "police" => $closestStation];
    }
}


$policeStations = [
    ['name' => 'Polsek A', 'latitude' => -0.5113080822042986, 'longitude' => 101.54126644134523],
    ['name' => 'Polsek B', 'latitude' => -0.5192685566111522, 'longitude' => 101.54362678527832],
];
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $requestBody = file_get_contents('php://input');
    $userLocation = json_decode($requestBody, true);

    $userLatitude = $userLocation['latitude'];
    $userLongitude = $userLocation['longitude'];
    $a = (new Location)->getClosest($policeStations, $userLatitude, $userLongitude);
    header("Content-Type: Application/json");
    echo json_encode($a);
    exit;
}


?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporkan Insiden</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
</head>

<body>
    <div id="map" style="height: 500px;"></div>
    <button id="submit">Laporkan Insiden</button>

    <script>
        const curposition = {
            lat: null,
            lang: null
        }
        const map = L.map('map').setView([-6.200000, 106.816666], 13);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: 'police service'
        }).addTo(map);

        let incidentMarker;

        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition((position) => {
                const {
                    latitude,
                    longitude
                } = position.coords;
                curposition.lat = latitude
                curposition.lang = longitude
                map.setView([latitude, longitude], 17);
                incidentMarker = L.marker([latitude, longitude]).addTo(map);
                incidentMarker.bindPopup(`Lokasi Anda: ${latitude}, ${longitude}`).openPopup();
            }, (error) => {
                alert("Gagal mendapatkan lokasi Anda: " + error.message);
            });
        } else {
            alert("Geolocation tidak didukung oleh browser ini.");
        }

        map.on('click', (e) => {
            const {
                lat,
                lng
            } = e.latlng;
            if (incidentMarker) {
                incidentMarker.setLatLng([lat, lng]);
                curposition.lat = lat
                curposition.lang = lng
            } else {
                incidentMarker = L.marker([lat, lng]).addTo(map);
                curposition.lat = lat
                curposition.lang = lng
            }
            incidentMarker.bindPopup(`Lokasi Insiden: ${lat}, ${lng}`).openPopup();
            incidentMarker.lat = lat;
            incidentMarker.lng = lng;
        });
        let p

        document.getElementById('submit').addEventListener('click', async () => {

            const reponse = await fetch("/index2.php", {
                "method": "POST",
                "body": JSON.stringify({
                    latitude: curposition.lat,
                    longitude: curposition.lang
                })


            })
            const json = await reponse.json();

            console.log(json);
            const police = {
                latitude: json.police.latitude,
                longitude: json.police.longitude
            }



            p = L.marker([police.latitude, police.longitude]).addTo(map).bindPopup("police terdekat").openPopup()

            // if (incidentMarker) {
            //     location.href = "https://www.google.com/maps?q=" + curposition.lat + "," + curposition.lang

            // } else {
            //     alert('Pilih lokasi insiden di peta terlebih dahulu!');
            // }
        });
    </script>

</body>

</html>