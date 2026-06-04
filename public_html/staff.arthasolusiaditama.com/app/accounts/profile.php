<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../auth/auth.php';
require_once __DIR__ . '/../helpers/avatar.php';

if (empty($_SESSION['csrf_token'])) {
		$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

$currentUserId = (int)($_SESSION['user_id'] ?? 0);
if ($currentUserId <= 0) {
		die('Akses tidak valid.');
}

$requestedUserId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : $currentUserId;
if ($requestedUserId <= 0) {
		$requestedUserId = $currentUserId;
}

$profileNotice = '';
$profileUser = null;

try {
		$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
		$stmt->execute([$requestedUserId]);
		$profileUser = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
} catch (Throwable $profileFetchError) {
		$profileUser = null;
}

if (!$profileUser && $requestedUserId !== $currentUserId) {
		$profileNotice = 'Profil pengguna tidak ditemukan, menampilkan data Anda sendiri.';
		try {
				$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
				$stmt->execute([$currentUserId]);
				$profileUser = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
				$requestedUserId = $currentUserId;
		} catch (Throwable $fallbackFetchError) {
				$profileUser = null;
		}
}

if (!$profileUser) {
		die('Profil tidak ditemukan.');
}

$isOwnProfile = ($requestedUserId === $currentUserId);
$user = $profileUser;
$avatar_url = getAvatarUrl($user);

$upload_error = '';
$success_msg = '';

function validateProfileData(array $data): array {
		$errors = [];
		if (empty($data['full_name'])) {
				$errors[] = 'Nama tidak boleh kosong.';
		}
		if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
				$errors[] = 'Email tidak valid.';
		}
		return $errors;
}

function handleAvatarUpload(int $user_id, ?string $existing_avatar): ?string {
		$max_size = 2 * 1024 * 1024;
		$allowed_types = [
				'image/jpeg' => 'jpg',
				'image/png' => 'png',
				'image/webp' => 'webp'
		];
		$base_upload_dir = __DIR__ . '/../../storage/uploads/avatar/' . $user_id . '/';

		
		if (!isset($_FILES['avatar'])) {
				error_log('No avatar file in $_FILES');
				return $existing_avatar;
		}
		
		if ($_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
				error_log('Avatar upload error: ' . $_FILES['avatar']['error']);
				return $existing_avatar;
		}

		$file = $_FILES['avatar'];
		if ($file['size'] > $max_size) {
				throw new Exception('Ukuran file terlalu besar, maksimal 2MB.');
		}

		$file_info = finfo_open(FILEINFO_MIME_TYPE);
		$mime_type = finfo_file($file_info, $file['tmp_name']);
		finfo_close($file_info);

		if (!isset($allowed_types[$mime_type])) {
				throw new Exception('Tipe file tidak diperbolehkan.');
		}
		$extension = $allowed_types[$mime_type];

		$imginfo = @getimagesize($file['tmp_name']);
		if ($imginfo === false) {
				throw new Exception('File bukan gambar valid.');
		}

		if (!is_dir($base_upload_dir) && !mkdir($base_upload_dir, 0755, true) && !is_dir($base_upload_dir)) {
				throw new Exception('Gagal membuat folder upload.');
		}

		$filename = 'avatar-' . time() . '-' . bin2hex(random_bytes(6)) . '.' . $extension;
		$destination = $base_upload_dir . $filename;
		$relative_path = 'storage/uploads/avatar/' . $user_id . '/' . $filename;

		if (!move_uploaded_file($file['tmp_name'], $destination)) {
				throw new Exception('Gagal upload file.');
		}

		// Compress avatar image (max 400px for profile pictures)
		require_once __DIR__ . '/../helpers/image-compress.php';
		compressUploadedImage($destination, 400, 400, 80);

		if (!empty($existing_avatar)) {
				$normalized_old = ltrim($existing_avatar, './');
				if (preg_match('#^storage/uploads/avatar/\d+/avatar-\d+-[a-f0-9]{12}\.(jpg|jpeg|png|webp)$#i', $normalized_old)) {
						$old_path = __DIR__ . '/../../' . $normalized_old;
						if (file_exists($old_path)) {
								unlink($old_path);
						}
				}
		}

		return $relative_path;
}

function formatIndoDate(?string $date): string {
		return $date ? date('d F Y', strtotime($date)) : '-';
}

function sidebarBgRole(?string $role): string {
		switch ($role) {
				case 'administrator':
				case 'direktur':
						return 'background: linear-gradient(135deg,#1e3a8a 80%,#2563eb 100%);';
				case 'technician_manager':
						return 'background: linear-gradient(135deg,#166534 80%,#22c55e 100%);';
				case 'sales':
						return 'background: linear-gradient(135deg,#b45309 80%,#fde68a 100%);';
				case 'technician':
						return 'background: linear-gradient(135deg,#6d28d9 80%,#a78bfa 100%);';
				default:
						return 'background: linear-gradient(135deg,#e0e7ef 80%,#f1f5f9 100%);';
		}
}

$isAdmin = in_array(($_SESSION['role'] ?? ''), ['administrator', 'direktur']);
$canEditProfile = $isOwnProfile || $isAdmin;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_profile_modal'])) {
		if (!$canEditProfile) {
				$upload_error = 'Anda tidak memiliki izin untuk mengubah profil ini.';
				$profileNotice = $upload_error;
		} elseif (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
				$upload_error = 'Token tidak valid.';
		} else {
				$full_name = trim($_POST['full_name'] ?? '');
				$email = trim($_POST['email'] ?? '');
				$phone = trim($_POST['phone'] ?? '');
				$address = trim($_POST['address'] ?? '');
				$birth_date = $_POST['birth_date'] ?? null;
				$gender = $_POST['gender'] ?? '';
				$linkedin = trim($_POST['linkedin'] ?? '');

				$errors = validateProfileData([
						'full_name' => $full_name,
						'email' => $email
				]);

				if ($errors) {
						$upload_error = implode(' ', $errors);
				} else {
						$stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id != ?');
						$stmt->execute([$email, $requestedUserId]);
						if ($stmt->fetch()) {
								$upload_error = 'Email sudah digunakan oleh pengguna lain.';
						} else {
								try {
										$pdo->beginTransaction();
										$avatar_path = handleAvatarUpload($requestedUserId, $user['avatar'] ?? null);

										$stmt = $pdo->prepare('UPDATE users SET full_name = ?, email = ?, phone = ?, address = ?, birth_date = ?, gender = ?, linkedin = ?, avatar = ? WHERE id = ?');
										$stmt->execute([
												$full_name,
												$email,
												$phone,
												$address,
												$birth_date,
												$gender,
												$linkedin,
												$avatar_path,
												$requestedUserId
										]);

										if ($isOwnProfile) {
												$_SESSION['full_name'] = $full_name;
												$_SESSION['email'] = $email;
										}

										$pdo->commit();

										$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
										$stmt->execute([$requestedUserId]);
										$user = $stmt->fetch(PDO::FETCH_ASSOC);
										$avatar_url = getAvatarUrl($user);

										$success_msg = 'Profil berhasil diperbarui!';
								} catch (Exception $e) {
										$pdo->rollBack();
										error_log('Profile update error: ' . $e->getMessage());
										$upload_error = 'Gagal memperbarui profil: ' . $e->getMessage();
								}
						}
				}
		}
}

$showEditModal = $canEditProfile && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_profile_modal']);

if (
		!isset($_GET['page']) ||
		(isset($_SERVER['SCRIPT_FILENAME']) && basename($_SERVER['SCRIPT_FILENAME']) === 'profile.php')
) {
		include_once __DIR__ . '/../includes/header.php';
}

?>
<style>
.profile-sidebar {
	min-height: 340px;
	width: 220px;
	display: flex;
	flex-direction: column;
	align-items: center;
	padding: 28px 0;
	border-radius: 10px;
	margin-right: 28px;
	box-shadow: 0 2px 8px #38bdf825;
}
.profile-sidebar .avatar {
	width: 88px;
	height: 88px;
	border-radius: 50%;
	object-fit: cover;
	background: #fff;
	border: 3px solid #e5e7eb;
	margin-bottom: 1rem;
	box-shadow: 0 1px 10px #bae6fd55;
}
.profile-sidebar .name {
	color: #fff;
	font-size: 1.15rem;
	font-weight: bold;
	margin-bottom: 0.5rem;
	text-align: center;
	text-shadow: 0 1px 4px #0002;
}
.profile-sidebar .username {
	color: #e0e7ef;
	font-size: 0.97rem;
	margin-bottom: 0.5rem;
	text-align: center;
	text-shadow: 0 1px 2px #0002;
}
.profile-sidebar .role {
	display: inline-block;
	background: #f1f5f9;
	color: #334155;
	font-size: 0.93rem;
	font-weight: 600;
	border-radius: 12px;
	padding: 4px 16px;
	margin-bottom: 0.5rem;
	text-align: center;
	letter-spacing: 0.5px;
}
.profile-about-card {
	background: #fff;
	border-radius: 10px;
	box-shadow: 0 2px 8px #0001;
	padding: 32px 28px 20px 28px;
	min-width: 300px;
	flex: 1;
}
.profile-about-list .row {
	display: flex;
	padding: 8px 0;
	border-bottom: 1px solid #f1f1f1;
	font-size: 1rem;
}
.profile-about-list .row:last-child {
	border-bottom: none;
}
.profile-about-list .label {
	width: 130px;
	color: #6b7280;
}
.profile-about-list .value {
	flex: 1;
	color: #212121;
	word-break: break-word;
}
.profile-about-list .value a {
	font-size: 0.95rem;
	color: #2563eb;
	text-decoration: underline;
}
.profile-about-list .value a:hover {
	color: #1e3a8a;
}
.profile-action-btns {
	margin-top: 1.5rem;
	display: flex;
	gap: 0.5rem;
}
.profile-action-btns a {
	padding: 0.55rem 1.2rem;
	border-radius: 8px;
	font-weight: 600;
	font-size: 0.97rem;
	text-decoration: none;
	transition: background 0.15s ease;
}
.profile-action-btns .edit {
	background: #2563eb;
	color: #fff;
}
.profile-action-btns .edit:hover {
	background: #1d4ed8;
}
.dark .profile-about-card {
	background: #0f172a;
	color: #fff;
}
.dark .profile-about-list .row {
	border-bottom: 1px solid #1f2937;
}
.dark .profile-about-list .label {
	color: #cbd5e1;
}
.dark .profile-about-list .value {
	color: #e5e7eb;
}
.dark .profile-about-list .value a {
	color: #93c5fd;
}
.dark .profile-about-list .value a:hover {
	color: #bfdbfe;
}
.dark .profile-sidebar {
	background: linear-gradient(135deg, #0f172a 80%, #1e3a8a 100%) !important;
}
.dark .profile-sidebar .role {
	background: #1f2937;
	color: #e2e8f0;
}
@media (max-width: 900px) {
	.profile-layout-main {
		flex-direction: column;
	}
	.profile-sidebar {
		margin-right: 0;
		margin-bottom: 18px;
		width: 100%;
	}
}
@media (max-width: 768px) {
	.profile-sidebar {
		min-height: auto;
		padding: 20px 0;
	}
	.profile-sidebar .avatar {
		width: 80px;
		height: 80px;
	}
	.profile-sidebar .name {
		font-size: 1.1rem;
	}
	.profile-sidebar .username {
		font-size: 0.95rem;
	}
	.profile-sidebar .role {
		font-size: 0.9rem;
		padding: 3px 14px;
	}
	.profile-about-card {
		padding: 24px 20px 16px 20px;
		min-width: auto;
	}
	.profile-about-list .row {
		flex-direction: column;
		padding: 6px 0;
		font-size: 0.95rem;
	}
	.profile-about-list .label {
		width: auto;
		margin-bottom: 4px;
	}
	.profile-action-btns {
		margin-top: 1.2rem;
		flex-direction: column;
		gap: 0.8rem;
	}
	.profile-action-btns a {
		padding: 0.5rem 1rem;
		font-size: 0.95rem;
		text-align: center;
		width: 100%;
	}
}
@media (max-width: 480px) {
	.profile-sidebar {
		padding: 16px 0;
	}
	.profile-sidebar .avatar {
		width: 70px;
		height: 70px;
	}
	.profile-sidebar .name {
		font-size: 1rem;
	}
	.profile-sidebar .username {
		font-size: 0.9rem;
	}
	.profile-sidebar .role {
		font-size: 0.85rem;
		padding: 2px 12px;
	}
	.profile-about-card {
		padding: 20px 16px;
	}
}
.notice-card {
	border-radius: 10px;
	padding: 14px 16px;
	font-size: 0.92rem;
}
</style>

<div class="w-full max-w-5xl mx-auto">
	<?php if (!$isOwnProfile): ?>
		<div class="notice-card mb-4 border border-blue-200 bg-blue-50 text-slate-700 dark:border-blue-500/40 dark:bg-blue-500/10 dark:text-blue-100">
			Anda sedang melihat profil milik <span class="font-semibold text-blue-700 dark:text-blue-200"><?= htmlspecialchars($user['full_name'] ?? ($user['username'] ?? 'Pengguna')) ?></span>.<?= $isAdmin ? '' : ' Perubahan hanya dapat dilakukan oleh pemilik akun.' ?>
		</div>
	<?php endif; ?>
	<?php if ($profileNotice !== ''): ?>
		<div class="notice-card mb-4 border border-amber-300 bg-amber-50 text-amber-800 dark:border-amber-500/50 dark:bg-amber-500/10 dark:text-amber-100">
			<?= htmlspecialchars($profileNotice) ?>
		</div>
	<?php endif; ?>

	<div class="flex profile-layout-main">
		<aside class="profile-sidebar" style="<?= sidebarBgRole($user['role'] ?? '') ?>">
			<img src="<?= htmlspecialchars($avatar_url . ((strpos($avatar_url, '?') === false) ? '?' : '&') . 'v=' . time(), ENT_QUOTES) ?>" alt="Avatar" class="avatar shadow" id="profileAvatar" style="cursor:pointer" title="Lihat avatar lebih besar">
			<div class="name"><?= htmlspecialchars($user['full_name'] ?? '-') ?></div>
			<div class="username">@<?= htmlspecialchars($user['username'] ?? '-') ?></div>
			<div class="role"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $user['role'] ?? '-'))) ?></div>
		</aside>
		<section class="profile-about-card">
			<div style="font-size:1.4rem;font-weight:500;margin-bottom:20px;letter-spacing:-1px;">Informasi Karyawan</div>
			<div class="profile-about-list">
				<div class="row">
					<div class="label">Email</div>
					<div class="value"><?= htmlspecialchars($user['email'] ?? '-') ?></div>
				</div>
				<div class="row">
					<div class="label">No. HP</div>
					<div class="value"><?= htmlspecialchars($user['phone'] ?? '-') ?></div>
				</div>
				<div class="row">
					<div class="label">Alamat</div>
					<div class="value"><?= nl2br(htmlspecialchars($user['address'] ?? '-')) ?></div>
				</div>
				<div class="row">
					<div class="label">Tanggal Lahir</div>
					<div class="value"><?= formatIndoDate($user['birth_date'] ?? null) ?></div>
				</div>
				<div class="row">
					<div class="label">Jenis Kelamin</div>
					<div class="value"><?= $user['gender'] ? ($user['gender'] === 'male' ? 'Laki-laki' : 'Perempuan') : '-' ?></div>
				</div>
				<div class="row">
					<div class="label">Tanggal Join</div>
					<div class="value"><?= formatIndoDate($user['created_at'] ?? null) ?></div>
				</div>
				<div class="row">
					<div class="label">LinkedIn</div>
					<div class="value">
						<?php if (!empty($user['linkedin'])): ?>
							<a href="<?= htmlspecialchars($user['linkedin']) ?>" target="_blank" rel="noopener"><?= htmlspecialchars($user['linkedin']) ?></a>
						<?php else: ?>
							<span class="text-gray-400" style="font-size:0.95rem;">-</span>
						<?php endif; ?>
					</div>
				</div>
				<div class="row">
					<div class="label">Divisi</div>
					<div class="value">
						<?php
						$userDivisions = [];
						try {
							$stmtDiv = $pdo->prepare("
								SELECT d.id, d.name, d.color
								FROM user_divisions ud
								JOIN divisions d ON ud.division_id = d.id
								WHERE ud.user_id = ? AND d.is_active = 1
								ORDER BY d.name ASC
							");
							$stmtDiv->execute([$requestedUserId]);
							$userDivisions = $stmtDiv->fetchAll(PDO::FETCH_ASSOC);
						} catch (Throwable $e) {
							$userDivisions = [];
						}
						if (!empty($userDivisions)):
							foreach ($userDivisions as $udiv): ?>
								<a href="dashboard.php?page=divisions" style="display:inline-flex;align-items:center;gap:5px;padding:3px 12px;border-radius:8px;font-size:0.85rem;font-weight:600;text-decoration:none;margin-right:6px;margin-bottom:4px;background:<?= htmlspecialchars($udiv['color']) ?>15;color:<?= htmlspecialchars($udiv['color']) ?>;border:1px solid <?= htmlspecialchars($udiv['color']) ?>30;">
									<span style="width:6px;height:6px;border-radius:50%;background:<?= htmlspecialchars($udiv['color']) ?>;"></span>
									<?= htmlspecialchars($udiv['name']) ?>
								</a>
							<?php endforeach;
						else: ?>
							<span class="text-gray-400" style="font-size:0.95rem;">Belum ada divisi</span>
						<?php endif; ?>
					</div>
				</div>
			</div>
			<?php if ($canEditProfile): ?>
				<div class="profile-action-btns">
					<a href="#" class="edit" id="openEditProfileModal">Edit Profil</a>
				</div>
			<?php endif; ?>
		</section>
	</div>
</div>

<div id="avatarViewModal" style="position:fixed; inset:0; display:none; align-items:center; justify-content:center; background:rgba(0,0,0,0.6); z-index:99999;" onclick="this.style.display='none';document.body.style.overflow='';">
	<img id="avatarViewImg" src="<?= htmlspecialchars($avatar_url . ((strpos($avatar_url, '?') === false) ? '?' : '&') . 'v=' . time(), ENT_QUOTES) ?>" alt="Avatar Besar" style="max-width:90vw; max-height:90vh; border-radius:12px; box-shadow:0 10px 40px rgba(0,0,0,.5);" onclick="event.stopPropagation();">
	<button type="button" aria-label="Tutup" style="position:absolute; top:16px; right:16px; font-size:2rem; color:#fff; background:transparent; border:none; cursor:pointer;" onclick="document.getElementById('avatarViewModal').style.display='none'; document.body.style.overflow='';">&times;</button>
</div>

<?php if ($canEditProfile): ?>
<div id="editProfileModal" style="position:fixed; inset:0; z-index:99999; display:none; background:rgba(0,0,0,0.4); align-items:center; justify-content:center;">
	<div class="modal-content" style="background:#fff; border-radius:18px; box-shadow:0 8px 32px #0002; max-width:700px; width:97vw; margin:16px; position:relative; overflow:hidden; display:flex; flex-direction:column; max-height:96vh;" onclick="event.stopPropagation();">
		<button id="closeEditProfileModal" style="position:absolute; top:18px; right:18px; font-size:1.7rem; color:#aaa; background:none; border:none; cursor:pointer; z-index:2; transition:.2s;" title="Tutup" onmouseover="this.style.color='#222'" onmouseout="this.style.color='#aaa'">&times;</button>
		<div style="padding:36px 28px 20px 28px; flex:1; overflow:hidden; display:flex; flex-direction:column;">
			<h2 style="font-size:1.35rem; font-weight:bold; margin-bottom:22px; color:#334155; text-align:center;">Edit Profil</h2>
			<div id="modalAlertContainer"></div>
			<div class="modal-form-scroll" style="flex:1 1 auto; min-height:0; overflow-y:auto;">
				<form id="editProfileForm" method="post" enctype="multipart/form-data" autocomplete="off" action="<?= htmlspecialchars($_SERVER['REQUEST_URI']) ?>">
					<input type="hidden" name="edit_profile_modal" value="1">
					<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
					<div style="display:flex; flex-direction:column; align-items:center; gap:15px; margin-bottom:22px;">
						<img src="<?= $avatar_url ?>" id="modalPreviewAvatar" style="width:88px;height:88px;border-radius:50%;object-fit:cover;background:#f3f4f6;border:2px solid #e5e7eb;box-shadow:0 1px 8px #0002;" alt="Preview Avatar">
						<label for="avatarInput" style="display:inline-block;position:relative;">
							<span style="display:inline-block;background:#f1f5f9;color:#2563eb;border:1.5px solid #2563eb;border-radius:6px;padding:6px 18px;cursor:pointer;font-size:1rem;font-weight:500;transition:.15s;margin-top:2px;">Pilih Foto</span>
							<input id="avatarInput" type="file" name="avatar" accept="image/*" onchange="previewAvatar(event)" style="display:none;">
						</label>
						<span id="avatarFilename" style="font-size:.96rem; color:#666;"></span>
					</div>
					<div style="display:flex; flex-wrap:wrap; gap:18px 2%; margin-bottom:14px;">
						<div style="flex:1 1 280px; min-width:160px;">
							<label style="font-weight:600; margin-bottom:5px; display:block;">Nama Lengkap</label>
							<input type="text" name="full_name" required value="<?= htmlspecialchars($user['full_name'] ?? '') ?>" style="width:100%;padding:10px 12px;border:1.5px solid #e5e7eb;border-radius:7px;margin-bottom:0;font-size:1.05rem;">
						</div>
						<div style="flex:2 1 380px; min-width:220px;">
							<label style="font-weight:600; margin-bottom:5px; display:block;">Email</label>
							<input type="email" name="email" required value="<?= htmlspecialchars($user['email'] ?? '') ?>" style="width:100%;padding:10px 12px;border:1.5px solid #e5e7eb;border-radius:7px;margin-bottom:0;font-size:1.05rem;">
						</div>
					</div>
					<div style="display:flex; flex-wrap:wrap; gap:18px 2%; margin-bottom:14px;">
						<div style="flex:1 1 220px; min-width:140px;">
							<label style="font-weight:600; margin-bottom:5px; display:block;">No. HP</label>
							<input type="text" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" style="width:100%;padding:10px 12px;border:1.5px solid #e5e7eb;border-radius:7px;margin-bottom:0;font-size:1.05rem;">
						</div>
						<div style="flex:1 1 220px; min-width:140px;">
							<label style="font-weight:600; margin-bottom:5px; display:block;">Tanggal Lahir</label>
							<input type="date" name="birth_date" value="<?= htmlspecialchars($user['birth_date'] ?? '') ?>" style="width:100%;padding:10px 12px;border:1.5px solid #e5e7eb;border-radius:7px;margin-bottom:0;font-size:1.05rem;">
						</div>
					</div>
					<div style="display:flex; flex-wrap:wrap; gap:18px 2%; margin-bottom:14px;">
						<div style="flex:1 1 220px; min-width:140px;">
							<label style="font-weight:600; margin-bottom:5px; display:block;">Jenis Kelamin</label>
							<select name="gender" style="width:100%;padding:10px 12px;border:1.5px solid #e5e7eb;border-radius:7px;font-size:1.05rem;">
								<option value="">- Pilih -</option>
								<option value="male" <?= ($user['gender'] ?? '') === 'male' ? 'selected' : '' ?>>Laki-laki</option>
								<option value="female" <?= ($user['gender'] ?? '') === 'female' ? 'selected' : '' ?>>Perempuan</option>
							</select>
						</div>
						<div style="flex:1 1 220px; min-width:140px;">
							<label style="font-weight:600; margin-bottom:5px; display:block;">LinkedIn</label>
							<input type="text" name="linkedin" value="<?= htmlspecialchars($user['linkedin'] ?? '') ?>" style="width:100%;padding:10px 12px;border:1.5px solid #e5e7eb;border-radius:7px;margin-bottom:0;font-size:1.05rem;">
						</div>
					</div>
					<div style="margin-bottom:24px;">
						<label style="font-weight:600; margin-bottom:5px; display:block;">Alamat</label>
						<textarea name="address" rows="2" style="width:100%;padding:10px 12px;border:1.5px solid #e5e7eb;border-radius:7px;font-size:1.05rem;"><?= htmlspecialchars($user['address'] ?? '') ?></textarea>
					</div>
					<div style="padding-top:6px; display:flex; justify-content:end;">
						<button type="submit" style="background:#2563eb;color:#fff;font-weight:600;padding:10px 32px;border-radius:7px;border:none;cursor:pointer;transition:.2s;font-size:1.08rem;">Simpan Perubahan</button>
					</div>
				</form>
			</div>
		</div>
	</div>
</div>
<style>
.modal-form-scroll {
	scrollbar-width: thin;
	scrollbar-color: #cbd5e1 #f1f5f9;
	max-height: 60vh;
}
.modal-form-scroll::-webkit-scrollbar {
	width: 7px;
}
.modal-form-scroll::-webkit-scrollbar-thumb {
	background: #cbd5e1;
	border-radius: 7px;
}
.modal-form-scroll::-webkit-scrollbar-track {
	background: #f1f5f9;
}

html[data-theme="dark"] #editProfileModal {
	background: rgba(0, 0, 0, 0.6) !important;
}
html[data-theme="dark"] #editProfileModal .modal-content {
	background: #1e293b !important;
	color: #e2e8f0 !important;
	box-shadow: 0 8px 32px rgba(0, 0, 0, 0.5) !important;
}
html[data-theme="dark"] #editProfileModal h2 {
	color: #f1f5f9 !important;
}
html[data-theme="dark"] #editProfileModal label {
	color: #cbd5e1 !important;
}
html[data-theme="dark"] #editProfileModal input,
html[data-theme="dark"] #editProfileModal select,
html[data-theme="dark"] #editProfileModal textarea {
	background-color: #0f172a !important;
	color: #f1f5f9 !important;
	border-color: #475569 !important;
}
html[data-theme="dark"] #editProfileModal input::placeholder,
html[data-theme="dark"] #editProfileModal textarea::placeholder {
	color: #94a3b8 !important;
}
html[data-theme="dark"] #editProfileModal #closeEditProfileModal {
	color: #94a3b8 !important;
}
html[data-theme="dark"] #editProfileModal #closeEditProfileModal:hover {
	color: #e2e8f0 !important;
}
html[data-theme="dark"] #editProfileModal #modalPreviewAvatar {
	border-color: #475569 !important;
	background: #0f172a !important;
}
html[data-theme="dark"] #editProfileModal #avatarFilename {
	color: #94a3b8 !important;
}
html[data-theme="dark"] .modal-form-scroll {
	scrollbar-color: #475569 #1e293b;
}
html[data-theme="dark"] .modal-form-scroll::-webkit-scrollbar-thumb {
	background: #475569;
}
html[data-theme="dark"] .modal-form-scroll::-webkit-scrollbar-track {
	background: #1e293b;
}

@media (min-width: 700px) {
	.modal-form-scroll {
		max-height: 65vh;
	}
}
@media (max-width: 600px) {
	.modal-content {
		max-width: 99vw !important;
	}
	.modal-form-scroll {
		max-height: 60vh;
	}
}
</style>
<?php endif; ?>

<script>
if (typeof window.previewAvatar === 'undefined') {
	window.previewAvatar = function (event) {
		const file = event.target.files?.[0];
		if (!file) {
			return;
		}

		const filenameElement = document.getElementById('avatarFilename');
		if (filenameElement) {
			filenameElement.textContent = file.name;
		}

		const reader = new FileReader();
		reader.onload = function (ev) {
			const previewElement = document.getElementById('modalPreviewAvatar');
			if (previewElement) {
				previewElement.src = String(ev.target?.result || '');
			}
		};
		reader.readAsDataURL(file);
	};
}

<?php if ($canEditProfile): ?>
function initProfileModal() {
	const modal = document.getElementById('editProfileModal');
	const alertContainer = document.getElementById('modalAlertContainer');
	const openBtn = document.getElementById('openEditProfileModal');
	const closeBtn = document.getElementById('closeEditProfileModal');

	function showModal() {
		if (modal) {
			modal.style.display = 'flex';
			document.body.style.overflow = 'hidden';
		}
	}

	function hideModal() {
		if (modal) {
			modal.style.display = 'none';
			document.body.style.overflow = '';
			if (alertContainer) {
				alertContainer.innerHTML = '';
			}
			const filenameElement = document.getElementById('avatarFilename');
			if (filenameElement) {
				filenameElement.textContent = '';
			}
		}
	}

	if (openBtn) {
		openBtn.onclick = null;
		openBtn.addEventListener('click', function (e) {
			e.preventDefault();
			showModal();
		});
	}

	if (closeBtn) {
		closeBtn.onclick = null;
		closeBtn.addEventListener('click', function (e) {
			e.preventDefault();
			hideModal();
		});
	}

	if (modal) {
		modal.onclick = function (e) {
			if (e.target === modal) {
				hideModal();
			}
		};
	}

	<?php if ($showEditModal && ($upload_error !== '' || $success_msg !== '')): ?>
	if (alertContainer) {
		const errorMsg = '<?= addslashes($upload_error) ?>';
		const successMsg = '<?= addslashes($success_msg) ?>';
		
		if (errorMsg && errorMsg.trim() !== '') {
			if(typeof showToast==='function') showToast(<?= json_encode($upload_error) ?>,'error');
		} else if (successMsg && successMsg.trim() !== '') {
			if(typeof showToast==='function') showToast(<?= json_encode($success_msg) ?>,'success');
			updateAllAvatars('<?= $avatar_url ?>');
		}
		showModal();
	}
	<?php endif; ?>
}

if (document.readyState === 'loading') {
	document.addEventListener('DOMContentLoaded', initProfileModal);
} else {
	initProfileModal();
}

setTimeout(initProfileModal, 100);
<?php endif; ?>

function updateAllAvatars(newAvatarUrl) {
	const timestamp = Date.now();
	const avatarUrlWithTimestamp = newAvatarUrl + '?' + timestamp;

	const profileAvatar = document.getElementById('profileAvatar');
	if (profileAvatar) {
		profileAvatar.src = avatarUrlWithTimestamp;
	}

	const sidebarAvatars = document.querySelectorAll('#mainSidebar img[alt="Avatar"]');
	sidebarAvatars.forEach(function (avatar) {
		avatar.src = avatarUrlWithTimestamp;
	});

	const headerAvatars = document.querySelectorAll('header img[alt="Avatar"]');
	headerAvatars.forEach(function (avatar) {
		avatar.src = avatarUrlWithTimestamp;
	});

	const avatarViewImg = document.getElementById('avatarViewImg');
	if (avatarViewImg) {
		avatarViewImg.src = avatarUrlWithTimestamp;
	}

	const modalPreviewAvatar = document.getElementById('modalPreviewAvatar');
	if (modalPreviewAvatar) {
		modalPreviewAvatar.src = avatarUrlWithTimestamp;
	}
}

window.updateAllAvatars = updateAllAvatars;
</script>
<script>
(function () {
	const avatarTrigger = document.getElementById('profileAvatar');
	const avatarModal = document.getElementById('avatarViewModal');
	const avatarModalImg = document.getElementById('avatarViewImg');
	if (avatarTrigger && avatarModal && avatarModalImg) {
		avatarTrigger.addEventListener('click', function () {
			avatarModalImg.src = this.src;
			avatarModal.style.display = 'flex';
			document.body.style.overflow = 'hidden';
		});
		document.addEventListener('keydown', function (e) {
			if (e.key === 'Escape' && avatarModal.style.display === 'flex') {
				avatarModal.style.display = 'none';
				document.body.style.overflow = '';
			}
		});
	}
})();
</script>

<?php
if (
		!isset($_GET['page']) ||
		(isset($_SERVER['SCRIPT_FILENAME']) && basename($_SERVER['SCRIPT_FILENAME']) === 'profile.php')
) {
		include_once __DIR__ . '/../includes/footer.php';
}
?>

