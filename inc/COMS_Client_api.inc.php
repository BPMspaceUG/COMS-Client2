<?php
function coms_client_api($data) {
    global $coms_url;
    global $coms_token;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_URL, $coms_url);
    
    $headers = array();
    $headers[] = 'Cookie: token='.$coms_token;
    $data = json_encode($data);
    if ($data) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        $headers[] = 'Content-Type: application/json';
        $headers[] = 'Content-Length: ' . strlen($data);
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    return curl_exec($ch);
}

function liam_client_api($data) {
    global $liam_url;
    global $liam_token;
    //echo "----------------------Request----------------------------<br>";
    //echo "<pre>".var_export($data, true)."</pre>";
    //var_dump($url);
    //var_dump($token);
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_URL, $liam_url);
    curl_setopt($ch, CURLOPT_VERBOSE, true);
    $headers = array();
    //JWT token for Authentication
    /************** change following line **********************/
    $headers[] = 'Cookie: token=' . $liam_token;
    if ($data) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        $headers[] = 'Content-Type: application/json';
        $headers[] = 'Content-Length: ' . strlen($data);
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $verbose = fopen('php://temp', 'w+');
    curl_setopt($ch, CURLOPT_STDERR, $verbose);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    $result = curl_exec($ch);
    // debug Info
    if ($result === FALSE) printf("cUrl error (#%d): %s<br>\n", curl_errno($ch), htmlspecialchars(curl_error($ch)));
    rewind($verbose);
    $verboseLog = stream_get_contents($verbose);
    //echo "Verbose information:\n<pre>", htmlspecialchars($verboseLog), "</pre>\n";
    //echo "----------------------Response----------------------------<br>";
    //echo "<pre>".var_export($result, true)."</pre>";
    return $result;
}