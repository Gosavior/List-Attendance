<?php
require_once __DIR__ . '../../config/database.php';
require_once __DIR__ . '../../auth/auth.php';

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$success_msg = '';
$error_msg = '';

$languages = [
    'id' => 'Bahasa Indonesia',
    'en' => 'English'
];
$themes = [
    'light' => 'Terang (Light)',
    'dark'  => 'Gelap (Dark)'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    $lang = $_POST['language'] ?? $user['language'];
    $theme = $_POST['theme'] ?? $user['theme'];

    if (!array_key_exists($lang, $languages) || !array_key_exists($theme, $themes)) {
        $error_msg = "Pilihan tidak valid.";
    } else {
        $stmt = $pdo->prepare("UPDATE users SET language = ?, theme = ? WHERE id = ?");
        if ($stmt->execute([$lang, $theme, $_SESSION['user_id']])) {
            $success_msg = "Pengaturan berhasil disimpan!";
            $_SESSION['language'] = $lang;
            $_SESSION['theme'] = $theme;
            $user['language'] = $lang;
            $user['theme'] = $theme;
        } else {
            $error_msg = "Gagal menyimpan pengaturan.";
        }
    }
}
$user_theme = (($user['theme'] ?? ($_SESSION['theme'] ?? 'light')) === 'dark') ? 'dark' : 'light';
?>
<!DOCTYPE html>
<html lang="id" class="<?= $user_theme === 'dark' ? 'dark' : '' ?>" data-theme="<?= $user_theme ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=5, user-scalable=yes">
  <meta name="theme-mode" content="<?= htmlspecialchars($user_theme) ?>" />
  <meta name="theme-color" content="<?= $user_theme === 'dark' ? '#0f172a' : '#ffffff' ?>" />
  <meta name="supported-color-schemes" content="<?= $user_theme === 'dark' ? 'dark' : 'light' ?>" />
  <meta name="color-scheme" content="<?= $user_theme === 'dark' ? 'dark' : 'light' ?>" />
  <meta name="apple-mobile-web-app-status-bar-style" content="<?= $user_theme === 'dark' ? 'black-translucent' : 'default' ?>" />
  <title>Pengaturan</title>
  <script>
    (function(){
      var theme = <?= json_encode($user_theme) ?>;
      var html = document.documentElement;
      html.setAttribute('data-theme', theme);
      if (theme === 'dark') {
        html.classList.add('dark');
      } else {
        html.classList.remove('dark');
      }
    })();
  </script>
  <link rel="stylesheet" href="./public/assets/css/style.css">
  <script src="./public/assets/js/toast.js"></script>
</head>
<body class="bg-white text-slate-800 dark:bg-slate-900 dark:text-white min-h-screen">
  <div class="max-w-xl mx-auto my-10 bg-white/80 dark:bg-slate-800/90 p-8 rounded-lg shadow-md">
    <h1 class="text-xl font-bold mb-6">Pengaturan Akun</h1>

    <?php if ($success_msg): ?>
      <script>document.addEventListener('DOMContentLoaded',function(){showToast(<?= json_encode($success_msg) ?>,'success');});</script>
    <?php endif; ?>
    <?php if ($error_msg): ?>
      <script>document.addEventListener('DOMContentLoaded',function(){showToast(<?= json_encode($error_msg) ?>,'error');});</script>
    <?php endif; ?>

    <form method="post" action="" class="space-y-6">
      <input type="hidden" name="save_settings" value="1">

      <div>
        <label for="language" class="block font-semibold mb-2">Bahasa</label>
        <select name="language" id="language" class="w-full border py-2 px-3 rounded bg-white dark:bg-slate-900 border-slate-300 dark:border-slate-700 text-slate-800 dark:text-white">
          <?php foreach ($languages as $key => $label): ?>
            <option value="<?= $key ?>" <?= ($user['language'] ?? 'id') === $key ? 'selected' : '' ?>><?= $label ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div>
        <label for="theme" class="block font-semibold mb-2">Tema</label>
        <select name="theme" id="theme" class="w-full border py-2 px-3 rounded bg-white dark:bg-slate-900 border-slate-300 dark:border-slate-700 text-slate-800 dark:text-white">
          <?php foreach ($themes as $key => $label): ?>
            <option value="<?= $key ?>" <?= ($user['theme'] ?? 'light') === $key ? 'selected' : '' ?>><?= $label ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-6 py-2 rounded transition">Simpan Pengaturan</button>
    </form>
  </div>
  <script>
    if (window.ThemeManager) {
      ThemeManager.syncWithServer('<?= htmlspecialchars($user['theme'] ?? 'light') ?>');
    }
    
    document.querySelector('form')?.addEventListener('submit', function() {
      const themeSelect = document.getElementById('theme');
      if (themeSelect && window.ThemeManager) {
        ThemeManager.syncWithServer(themeSelect.value);
      }
    });
  </script>
</body>
</html>