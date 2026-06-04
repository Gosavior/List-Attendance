<?php
require_once __DIR__ . '/../auth/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/avatar.php';
require_once __DIR__ . '/../helpers/audit-log.php';


if (!in_array($_SESSION['role'], ['administrator', 'direktur'])) {
    http_response_code(403); exit('Akses ditolak.');
}


function handleAvatarUpload($user_id, $existing_avatar = null) {
    
    $base_upload_dir = __DIR__ . '/../../storage/uploads/avatar/' . $user_id . '/';
    $base_avatar_path = 'storage/uploads/avatar/' . $user_id . '/';

    if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
        return $existing_avatar;
    }
    
    $file = $_FILES['avatar'];
    $max_size = 2 * 1024 * 1024; 
    $allowed_types = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp'
    ];
    
    if ($file['size'] > $max_size) {
        throw new Exception('Ukuran file maksimal 2MB.');
    }
    
    $file_info = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($file_info, $file['tmp_name']);
    finfo_close($file_info);
    
    if (!isset($allowed_types[$mime_type])) {
        throw new Exception('Tipe file tidak diperbolehkan.');
    }
    
    $imginfo = @getimagesize($file['tmp_name']);
    if ($imginfo === false) {
        throw new Exception('File bukan gambar valid.');
    }
    
    if (!is_dir($base_upload_dir)) {
        if (!mkdir($base_upload_dir, 0755, true)) {
            throw new Exception('Gagal membuat folder upload.');
        }
    }
    
    $filename = 'avatar-' . time() . '-' . bin2hex(random_bytes(6)) . '.' . $allowed_types[$mime_type];
    $destination = $base_upload_dir . $filename;
    $relative_path = $base_avatar_path . $filename;
    
    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new Exception('Gagal upload file.');
    }
    
    // Compress avatar image (max 400px for profile pictures)
    require_once __DIR__ . '/../helpers/image-compress.php';
    compressUploadedImage($destination, 400, 400, 80);
    
    
    if ($existing_avatar && !empty($existing_avatar)) {
        $old_path = $_SERVER['DOCUMENT_ROOT'] . '/' . $existing_avatar;
        if (file_exists($old_path) && is_file($old_path)) {
            unlink($old_path);
        }
    }
    
    return $relative_path;
}


$id        = isset($_POST['id']) ? intval($_POST['id']) : 0;
$full_name = trim($_POST['full_name'] ?? '');
$username  = trim($_POST['username'] ?? '');
$email     = trim($_POST['email'] ?? '');
$gender    = trim($_POST['gender'] ?? '');
$role      = trim($_POST['role'] ?? '');
$is_active = isset($_POST['is_active']) ? intval($_POST['is_active']) : 1;


$phone     = trim($_POST['phone'] ?? '') ?: null;
$address   = trim($_POST['address'] ?? '') ?: null;
$birth_date= !empty($_POST['birth_date']) ? $_POST['birth_date'] : null;
$linkedin  = trim($_POST['linkedin'] ?? '') ?: null;
$gender    = $gender ?: null;

if ($full_name == '' || $username == '' || $email == '' || $role == '') {
    exit("Field wajib tidak boleh kosong.");
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    exit("Email tidak valid.");
}


if ($id) {
    $cek = $pdo->prepare("SELECT id FROM users WHERE (username=? OR email=?) AND id<>?");
    $cek->execute([$username, $email, $id]);
} else {
    $cek = $pdo->prepare("SELECT id FROM users WHERE username=? OR email=?");
    $cek->execute([$username, $email]);
}
if ($cek->fetch()) exit("Username atau email sudah digunakan.");


try {
    if ($id) {
        
        $stmt0 = $pdo->prepare("SELECT avatar FROM users WHERE id=?");
        $stmt0->execute([$id]);
        $old = $stmt0->fetch(PDO::FETCH_ASSOC);
        $old_avatar = $old['avatar'] ?? null;
        try {
            $avatar_path = handleAvatarUpload($id, $old_avatar);
        } catch (Exception $e) {
            exit($e->getMessage());
        }
        
        $params = [$full_name, $username, $email, $gender, $role, $is_active, $phone, $address, $birth_date, $linkedin, $id];
        $sql = "UPDATE users SET full_name=?, username=?, email=?, gender=?, role=?, is_active=?, phone=?, address=?, birth_date=?, linkedin=?";
        if ($avatar_path) {
            $sql .= ", avatar=?";
            $params = [$full_name, $username, $email, $gender, $role, $is_active, $phone, $address, $birth_date, $linkedin, $avatar_path, $id];
        }
        $sql .= " WHERE id=?";
        $pdo->prepare($sql)->execute($params);
        auditLog($pdo, 'edit_user', [
            'target_type' => 'user',
            'target_id' => (int)$id,
            'target_user_id' => (int)$id,
            'details' => ['full_name' => $full_name, 'username' => $username, 'role' => $role, 'is_active' => $is_active]
        ]);
        exit("OK");
    } else {
        
        $password = trim($_POST['password'] ?? '');
        if (strlen($password) < 6) exit("Password minimal 6 karakter.");
        $hashpass = password_hash($password, PASSWORD_DEFAULT);
        $sql = "INSERT INTO users (full_name, username, email, gender, role, is_active, phone, address, birth_date, linkedin, avatar, password, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        $pdo->prepare($sql)->execute([
            $full_name, $username, $email, $gender, $role, $is_active,
            $phone, $address, $birth_date, $linkedin, null, $hashpass
        ]);
        $new_id = $pdo->lastInsertId();
        $avatar_path = null;
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            try {
                $avatar_path = handleAvatarUpload($new_id);
                $pdo->prepare("UPDATE users SET avatar=? WHERE id=?")->execute([$avatar_path, $new_id]);
            } catch (Exception $e) {
                
            }
        }
        auditLog($pdo, 'create_user', [
            'target_type' => 'user',
            'target_id' => (int)$new_id,
            'target_user_id' => (int)$new_id,
            'details' => ['full_name' => $full_name, 'username' => $username, 'role' => $role]
        ]);
        exit("OK");
    }
} catch (Exception $e) {
    exit("Gagal menyimpan akun: " . $e->getMessage());
}
?>