<?php
 

function socketNotify($userIds = null, $type = 'general', $message = '') {
    $socketUrl = 'http://localhost:3001/notify';

    $payload = [
        'type'    => $type,
        'message' => $message,
    ];

    if (is_array($userIds) && count($userIds) > 0) {
        $payload['userIds'] = array_map('intval', $userIds);
    }

    $json = json_encode($payload);

    
    if (function_exists('curl_init')) {
        $ch = curl_init($socketUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $json,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Content-Length: ' . strlen($json)],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 3,        
            CURLOPT_CONNECTTIMEOUT => 2,
        ]);
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) {
            error_log("[socketNotify] cURL error: {$err}");
            return false;
        }
        return $httpCode === 200;
    }

    
    $ctx = stream_context_create([
        'http' => [
            'method'  => 'POST',
            'header'  => "Content-Type: application/json\r\nContent-Length: " . strlen($json) . "\r\n",
            'content' => $json,
            'timeout' => 3,
        ],
    ]);

    $result = @file_get_contents($socketUrl, false, $ctx);
    if ($result === false) {
        error_log("[socketNotify] file_get_contents failed for {$socketUrl}");
        return false;
    }
    return true;
}
