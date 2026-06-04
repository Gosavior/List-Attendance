<?php
function getAvatarUrl($user) {
    if (is_string($user)) {
        return getAvatarUrlFromPath($user, 0, '');
    }
    
    if (!$user || !is_array($user)) {
        return getDefaultAvatarUrl();
    }
    
    $uploadedAvatarUrl = checkUploadedAvatar($user);
    if ($uploadedAvatarUrl !== null) {
        return $uploadedAvatarUrl;
    }
    
    return getGenderBasedAvatarUrl($user);
}
function checkUploadedAvatar($user) {
    
    
    $userId = $user['id'] ?? 0;

    if (empty($user['avatar']) || !is_string($user['avatar'])) {
        
        if ($userId > 0) {
            $projectRoot = getProjectRoot();
            $baseUrl = getBaseUrl();
            $dir = $projectRoot . '/storage/uploads/avatar/' . $userId . '/';
            if (is_dir($dir)) {
                $files = glob($dir . 'avatar-*.{jpg,jpeg,png,gif,webp}', GLOB_BRACE);
                if (!empty($files)) {
                    usort($files, function($a, $b) {
                        return filemtime($b) - filemtime($a);
                    });
                        $rel = 'storage/uploads/avatar/' . $userId . '/' . basename($files[0]);
                        if (file_exists($files[0])) {
                            
                            return rtrim($baseUrl, '/') . '/serve_image.php?path=' . rawurlencode($rel);
                    }
                }
            }
        }

        return null;
    }
    
    $avatarPath = trim($user['avatar']);
    
    $baseUrl = getBaseUrl();
    $projectRoot = getProjectRoot();
    $avatarPath = ltrim($avatarPath, './');
    $uploadPattern = '/^avatar-\d+-[a-f0-9]+\.(jpg|jpeg|png|gif|webp)$/i';
    
    if (strpos($avatarPath, 'storage/uploads/avatar/') === 0) {
        $fullPath = $projectRoot . '/' . $avatarPath;
        
        if (file_exists($fullPath)) {
                return rtrim($baseUrl, '/') . '/serve_image.php?path=' . rawurlencode($avatarPath);
        }
        
        
        
        if ($userId > 0) {
            $dir = $projectRoot . '/storage/uploads/avatar/' . $userId . '/';
            if (is_dir($dir)) {
                $files = glob($dir . 'avatar-*.{jpg,jpeg,png,gif,webp}', GLOB_BRACE);
                if (!empty($files)) {
                    usort($files, function($a, $b) {
                        return filemtime($b) - filemtime($a);
                    });
                    $rel = 'storage/uploads/avatar/' . $userId . '/' . basename($files[0]);
                    if (file_exists($files[0])) {
                            return rtrim($baseUrl, '/') . '/serve_image.php?path=' . rawurlencode($rel);
                    }
                }
            }
        }
        
        return null;
    }
    
    if (preg_match($uploadPattern, $avatarPath)) {
        $userId = $user['id'] ?? 0;
        
        if ($userId > 0) {
            $storagePath = 'storage/uploads/avatar/' . $userId . '/' . $avatarPath;
            $fullPath = $projectRoot . '/' . $storagePath;
            
            if (file_exists($fullPath)) {
                    return rtrim($baseUrl, '/') . '/serve_image.php?path=' . rawurlencode($storagePath);
            }
        }
        
        return null;
    }
    
    if (strpos($avatarPath, 'uploads/') === 0) {
        $fullPath = $projectRoot . '/' . $avatarPath;
        
        if (file_exists($fullPath)) {
                return rtrim($baseUrl, '/') . '/serve_image.php?path=' . rawurlencode($avatarPath);
        }
    }
    
    return null;
}
function getGenderBasedAvatarUrl($user) {
    $baseUrl = getBaseUrl();
    $projectRoot = getProjectRoot();
    
    $gender = strtolower(trim($user['gender'] ?? ''));
    
    $genderMap = [
        'male' => 'avatar-male.png',
        'pria' => 'avatar-male.png',
        'laki-laki' => 'avatar-male.png',
        'l' => 'avatar-male.png',
        'm' => 'avatar-male.png',
        
        'female' => 'avatar-female.png',
        'wanita' => 'avatar-female.png',
        'perempuan' => 'avatar-female.png',
        'f' => 'avatar-female.png',
        'w' => 'avatar-female.png',
        
        'other' => 'avatar-default.png',
        'lainnya' => 'avatar-default.png',
        'tidak diketahui' => 'avatar-default.png',
        '' => 'avatar-default.png'
    ];
    
    $avatarFile = $genderMap[$gender] ?? 'avatar-default.png';
    $avatarFullPath = $projectRoot . '/public/assets/images/' . $avatarFile;
    
    if (!file_exists($avatarFullPath)) {
        $avatarFile = 'avatar-default.png';
    }
    
    return $baseUrl . '/public/assets/images/' . $avatarFile;
}
function getDefaultAvatarUrl() {
    $baseUrl = getBaseUrl();
    return $baseUrl . '/public/assets/images/avatar-default.png';
}
function getAvatarUrlFromPath($avatarPath, $userId = 0, $gender = '') {
    $user = [
        'avatar' => $avatarPath,
        'id' => $userId,
        'gender' => $gender
    ];
    return getAvatarUrl($user);
}
function getMaleAvatarUrl() {
    $baseUrl = getBaseUrl();
    $projectRoot = getProjectRoot();
    
    $avatarFile = 'avatar-male.png';
    $avatarPath = $projectRoot . '/public/assets/' . $avatarFile;
    
    if (!file_exists($avatarPath)) {
        $avatarFile = 'avatar-default.png';
    }
    
    return $baseUrl . '/public/assets/images/' . $avatarFile;
}
function getFemaleAvatarUrl() {
    $baseUrl = getBaseUrl();
    $projectRoot = getProjectRoot();
    
    $avatarFile = 'avatar-female.png';
    $avatarPath = $projectRoot . '/public/assets/images/' . $avatarFile;
    
    if (!file_exists($avatarPath)) {
        $avatarFile = 'avatar-default.png';
    }
    
    return $baseUrl . '/public/assets/images/' . $avatarFile;
}
function isValidUploadedAvatar($path) {
    if (empty($path)) {
        return false;
    }
    
    $path = trim($path);
    
    $pattern = '/^(?:storage\/uploads\/avatar\/\d+\/)?avatar-\d+-[a-f0-9]+\.(jpg|jpeg|png|gif|webp)$/i';
    
    return preg_match($pattern, $path) === 1;
}
function getBaseUrl() {
    
    if (defined('BASE_URL')) {
        return rtrim(BASE_URL, '/');
    }

    $isHttps = (
        (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') ||
        (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443) ||
        (!empty($_SERVER['REQUEST_SCHEME']) && strtolower((string)$_SERVER['REQUEST_SCHEME']) === 'https') ||
        (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && stripos((string)$_SERVER['HTTP_X_FORWARDED_PROTO'], 'https') !== false) ||
        (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && strtolower((string)$_SERVER['HTTP_X_FORWARDED_SSL']) === 'on') ||
        (!empty($_SERVER['HTTP_CF_VISITOR']) && stripos((string)$_SERVER['HTTP_CF_VISITOR'], '"scheme":"https"') !== false)
    );
    $protocol = $isHttps ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    
    
    return $protocol . $host;
}

function getProjectRoot() {
    return dirname(dirname(dirname(__FILE__)));
}
function debugAvatar($user) {
    $debug = [
        'input' => $user,
        'has_avatar_field' => !empty($user['avatar']),
        'avatar_field_value' => $user['avatar'] ?? null,
        'user_id' => $user['id'] ?? null,
        'gender' => $user['gender'] ?? null,
        'uploaded_avatar_url' => null,
        'final_avatar_url' => null,
        'is_valid_upload' => false,
        'file_exists' => false
    ];
    
    if (!empty($user['avatar'])) {
        $debug['is_valid_upload'] = isValidUploadedAvatar($user['avatar']);
        
        $projectRoot = getProjectRoot();
        $avatarPath = trim($user['avatar']);
        
        $possiblePaths = [];
        $possiblePaths[] = $projectRoot . '/' . $avatarPath;
        
        if (preg_match('/^avatar-\d+-[a-f0-9]+\.(jpg|jpeg|png|gif|webp)$/i', $avatarPath)) {
            $userId = $user['id'] ?? 0;
            if ($userId > 0) {
                $possiblePaths[] = $projectRoot . '/storage/uploads/avatar/' . $userId . '/' . $avatarPath;
            }
        }
        
        foreach ($possiblePaths as $path) {
            if (file_exists($path)) {
                $debug['file_exists'] = true;
                $debug['file_path'] = $path;
                break;
            }
        }
    }
    
    $debug['uploaded_avatar_url'] = checkUploadedAvatar($user);
    $debug['final_avatar_url'] = getAvatarUrl($user);
    
    return $debug;
}
?>