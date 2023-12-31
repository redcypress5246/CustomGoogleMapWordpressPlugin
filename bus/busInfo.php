<?php

$apiUrl = 'https://api.usft.com/v1/Location';
$base64_credentials = $_GET['cred'];


// cURL options
$curlOptions = array(
    CURLOPT_URL => $apiUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => array(
        'Authorization: Basic ' . $base64_credentials,
        'Content-Type: application/json',
        'access-control-allow-headers: Content-Type',
        'access-control-allow-methods: GET, POST',
        'access-control-max-age: 3600',
    ),
);


// Initialize cURL session
$curl = curl_init();

// Set cURL options
curl_setopt_array($curl, $curlOptions);

// Execute cURL session and get the response
$response = curl_exec($curl);

// Check for cURL errors
if (curl_errno($curl)) {
    echo 'cURL Error: ' . curl_error($curl);
} else {
    // Process the API response (assuming it's in JSON format)
    $data = json_decode($response, true);
    echo $response;
}

// Close cURL session
curl_close($curl);