<?php
 

 
function asa_get_reports_upload_dir() {
    $baseDir = dirname(__DIR__, 2); 
    $uploadsDir = $baseDir . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'reports' . DIRECTORY_SEPARATOR;
    return $uploadsDir;
}

 
function asa_read_report_index($file, $lockType = LOCK_SH) {
    $handle = null;
    $data = ['items' => [], 'handle' => null];
    
    if (file_exists($file)) {
        $handle = fopen($file, 'r+');
        if ($handle && flock($handle, $lockType)) {
            $content = stream_get_contents($handle);
            $json = json_decode($content, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($json)) {
                $data['items'] = $json;
            }
            $data['handle'] = $handle;
        }
    }
    
    return $data;
}

 
function asa_count_reports_for_year($items, $year) {
    $count = 0;
    foreach ($items as $item) {
        if (isset($item['report_year']) && (int)$item['report_year'] === (int)$year) {
            $count++;
        }
    }
    return $count;
}

 
function asa_build_report_number($year, $sequence) {
    $yearShort = substr((string)$year, -2);
    $sequenceStr = str_pad((string)$sequence, 4, '0', STR_PAD_LEFT);
    
    return [
        'year' => $year,
        'year_short' => $yearShort,
        'sequence' => $sequence,
        'sequence_padded' => $sequenceStr,
        'full' => sprintf('%s-%s', $sequenceStr, $yearShort),
        'compact' => sprintf('%s%s', $sequenceStr, $yearShort),
    ];
}

 
function asa_write_report_index($handle, $file, $items) {
    if (!$handle) {
        return false;
    }
    
    rewind($handle);
    ftruncate($handle, 0);
    
    $json = json_encode($items, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    $written = fwrite($handle, $json);
    
    return $written !== false;
}

 
function asa_release_report_index($handle) {
    if ($handle) {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}
?>
