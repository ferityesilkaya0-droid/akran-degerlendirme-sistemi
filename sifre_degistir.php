<?php
session_start();
require_once 'includes/db.php';

// Sistemde giriş yapmayan kimse bu sayfaya giremez
if (!isset($_SESSION['kullanici_id'])) {
    header("Location: index.php");
    exit;
}

$mesaj = "";
$kullanici_id = $_SESSION['kullanici_id'];
$rol = $_SESSION['rol']; // 1: Hoca, 0: Öğrenci
$grup = isset($_SESSION['grup']) ? $_SESSION['grup'] : '';

if ($rol == 0) {
    $ogrenci_ip = $_SERVER['REMOTE_ADDR'];
    $izin_verilen_ip = ($ogrenci_ip == '127.0.0.1' || $ogrenci_ip == '::1' || strpos($ogrenci_ip, '192.168.') === 0);
    if (!$izin_verilen_ip) {
        die("<div style='background:#0b0b0b; color:#dc3545; height:100vh; display:flex; justify-content:center; align-items:center; text-align:center; font-family:sans-serif;'><h2>GÜVENLİK İHLALİ!<br><span style='color:#aaa; font-size:16px;'>Sisteme sadece kampüs ağından erişilebilir. (IP: " . htmlspecialchars($ogrenci_ip) . ")</span></h2></div>");
    }
}

// Şifre Güncelleme Algoritması
if (isset($_POST['sifre_guncelle'])) {
    csrf_dogrula();
    $eski_sifre = $_POST['eski_sifre'];
    $yeni_sifre = $_POST['yeni_sifre'];
    $yeni_sifre_tekrar = $_POST['yeni_sifre_tekrar'];

    $sorgu = $db->prepare("SELECT sifre, ogr_no FROM kullanicilar WHERE id = ?");
    $sorgu->execute([$kullanici_id]);
    $kullanici = $sorgu->fetch(PDO::FETCH_ASSOC);

    if (strlen($yeni_sifre) < 6) {
        $mesaj = "<div class='alert-error'>Yeni şifre en az 6 karakter olmalıdır!</div>";
    } elseif ($yeni_sifre !== $yeni_sifre_tekrar) {
        $mesaj = "<div class='alert-error'>Yeni şifre ile tekrarı birbirini tutmuyor!</div>";
    } elseif ($yeni_sifre === $kullanici['ogr_no']) {
        $mesaj = "<div class='alert-error'>Yeni şifreniz, kullanıcı numaranızla aynı olamaz. Lütfen farklı bir şifre belirleyin.</div>";
    } elseif (!password_verify($eski_sifre, $kullanici['sifre'])) {
        $mesaj = "<div class='alert-error'>Mevcut şifrenizi hatalı girdiniz!</div>";
    } else {
        $yeni_hash = password_hash($yeni_sifre, PASSWORD_BCRYPT);
        $db->prepare("UPDATE kullanicilar SET sifre = ? WHERE id = ?")->execute([$yeni_hash, $kullanici_id]);

        // GÜVENLİK: Şifre değişti, oturumu imha et ve tekrar girmesini sağla
        session_destroy();
        header("Location: index.php?sifre_basarili=1");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Şifre İşlemleri | Akran Değerlendirme</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body>

    <div class="panel-layout">
        <!-- MOBİLDE ARKA PLANI KARARTAN KATMAN -->
        <div class="mobile-overlay" onclick="toggleSidebar()"></div>

        <!-- DİNAMİK SOL MENÜ (SIDEBAR) -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="logo-area" style="font-size: 15px;">
                    <svg viewBox="0 0 640 512" style="width: 25px; height: 25px;">
                        <path d="M320 32c-8.1 0-16.1 1.4-23.7 4.1L15.8 137.4C6.3 140.9 0 149.9 0 160s6.3 19.1 15.8 22.6l57.4 20.3c-1.2 5.3-1.9 10.9-1.9 16.6L71.3 416c0 35.3 28.7 64 64 64H504.7c35.3 0 64-28.7 64-64l0-196.5c0-5.7-.7-11.3-1.9-16.6l10.2-3.6c29.1-10.3 29.1-51.5 0-61.8L343.7 36.1C336.1 33.4 328.1 32 320 32zM128 408c0-13.3 10.7-24 24-24H488c13.3 0 24 10.7 24 24c0 4.4-3.6 8-8 8H136c-4.4 0-8-3.6-8-8z" />
                    </svg>
                    <span><?= $rol == 1 ? 'Yönetim Paneli' : 'Öğrenci Paneli' ?></span>
                </div>
            </div>
            <ul class="sidebar-menu">
                <?php if ($rol == 1): ?>
                    <li><a href="hoca_panel.php">Değerlendirme Yönetimi</a></li>
                    <li><a href="sonuclar.php">Rapor ve Sonuçlar</a></li>
                    <li><a href="sifre_degistir.php" class="active">Şifre İşlemleri</a></li>
                <?php else: ?>
                    <li><a href="ogrenci_panel.php">Canlı Değerlendirme</a></li>
                    <li><a href="sonuclar.php">Kişisel Sonuçlarım</a></li>
                    <li><a href="sifre_degistir.php" class="active">Şifre İşlemleri</a></li>
                <?php endif; ?>
            </ul>
        </aside>

        <!-- SAĞ TARAF (ANA İÇERİK) -->
        <div class="main-area">
            <!-- TOPBAR -->
            <header class="topbar">
                <div class="topbar-left">
                    <button class="menu-toggle" onclick="toggleSidebar()">☰</button>
                </div>
                <div class="topbar-right">
                    <div class="welcome-text" style="color: #888; margin-right: 20px;">
                        Hoş Geldin, <b style="color:#fff;"><?= $_SESSION['ad_soyad'] ?></b>
                        <?= $rol == 0 ? "<span style='font-size: 12px;'>[$grup]</span>" : "" ?>
                    </div>
                    <a href="cikis.php" class="logout-btn">Çıkış Yap</a>
                </div>
            </header>

            <div class="content-area">
                <?= $mesaj ?>

                <div style="max-width: 500px; margin: 0 auto;">
                    <div class="panel-box">
                        <div class="panel-title" style="border:none; text-align: center; color: #28a745;">Hesap Güvenliği</div>
                        <?php if (!empty($_SESSION['sifre_degisim_zorunlu'])): ?>
                            <div class="alert-error" style="margin-bottom: 20px;">İlk girişinizi yaptınız. Devam edebilmek için şifrenizi değiştirmeniz zorunludur.</div>
                        <?php elseif ($rol == 1): ?>
                            <p style="color: #bbb; margin-bottom: 25px; text-align: center;">Hesabınızın güvenliği için lütfen şifrenizi düzenli olarak güncelleyiniz.</p>
                        <?php else: ?>
                            <p style="color: #bbb; margin-bottom: 25px; text-align: center;">İlk giriş şifreniz okul numaranızdır. Hesabınızın güvenliği için lütfen şifrenizi güncelleyiniz.</p>
                        <?php endif; ?>

                        <form action="" method="POST">
                            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                            <div class="form-group">
                                <label>Mevcut Şifre</label>
                                <input type="password" name="eski_sifre" required placeholder="Şu an kullandığınız şifre">
                            </div>
                            <div class="form-group">
                                <label>Yeni Şifre (En az 6 karakter)</label>
                                <input type="password" name="yeni_sifre" required placeholder="Yeni belirleyeceğiniz şifre">
                            </div>
                            <div class="form-group">
                                <label>Yeni Şifre (Tekrar)</label>
                                <input type="password" name="yeni_sifre_tekrar" required placeholder="Yeni şifrenizi tekrar giriniz">
                            </div>
                            <button type="submit" name="sifre_guncelle" class="btn-neon" style="margin-top: 10px;">Şifremi Güncelle</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function toggleSidebar() {
            var sidebar = document.querySelector('.sidebar');
            sidebar.classList.toggle('collapsed'); // Masaüstü için daraltır
            sidebar.classList.toggle('open'); // Mobil için açar

            var overlay = document.querySelector('.mobile-overlay');
            if (overlay) overlay.classList.toggle('active');
        }
    </script>

</body>

</html>