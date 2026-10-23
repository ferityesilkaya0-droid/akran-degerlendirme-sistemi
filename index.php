<?php
session_start();
require_once 'includes/db.php';

if (isset($_SESSION['kullanici_id'])) {
    header("Location: " . ($_SESSION['rol'] == 1 ? "hoca_panel.php" : "ogrenci_panel.php"));
    exit;
}

$hata = "";
$kullanici_adi_value = "";

if (isset($_POST['giris_yap'])) {
    csrf_dogrula();

    if (isset($_SESSION['giris_kilit']) && time() < $_SESSION['giris_kilit']) {
        $kalan = $_SESSION['giris_kilit'] - time();
        $hata = "Çok fazla yanlış deneme yaptınız. Lütfen <span id='geriSayim' class='countdown-text' data-kalan='$kalan'>$kalan</span> saniye sonra tekrar deneyin.";
    } else {
        $kullanici_adi = trim($_POST['kullanici_adi']);
        $kullanici_adi_value = htmlspecialchars($kullanici_adi);
        $sifre = $_POST['sifre'];

        $sql = "SELECT * FROM kullanicilar WHERE ogr_no = :kadi1 LIMIT 1";
        $sorgu = $db->prepare($sql);
        $sorgu->execute(['kadi1' => $kullanici_adi]);
        $kullanici = $sorgu->fetch(PDO::FETCH_ASSOC);

        if ($kullanici && password_verify($sifre, $kullanici['sifre'])) {
            unset($_SESSION['giris_deneme'], $_SESSION['giris_kilit']);
            session_regenerate_id(true);
            $_SESSION['kullanici_id'] = $kullanici['id'];
            $_SESSION['ad_soyad'] = $kullanici['ad_soyad'];
            $_SESSION['rol'] = $kullanici['rol'];
            $_SESSION['grup'] = $kullanici['grup'];

            // İlk giriş kontrolü: şifre hâlâ öğrenci numarasıyla aynıysa değişim zorunlu
            if (password_verify($kullanici['ogr_no'], $kullanici['sifre'])) {
                $_SESSION['sifre_degisim_zorunlu'] = true;
                header("Location: sifre_degistir.php");
                exit;
            }

            header("Location: " . ($kullanici['rol'] == 1 ? "hoca_panel.php" : "ogrenci_panel.php"));
            exit;
        } else {
            $_SESSION['giris_deneme'] = ($_SESSION['giris_deneme'] ?? 0) + 1;
            if ($_SESSION['giris_deneme'] >= 5) {
                $_SESSION['giris_kilit'] = time() + 60;
                $hata = "5 kez yanlış giriş denediniz. 60 saniye bekleyin.";
            } else {
                $hata = "Sistemde böyle bir kayıt bulunamadı veya parola hatalı!";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sisteme Giriş</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="auth-body">

    <nav class="auth-nav">
        <a href="https://www.marmara.edu.tr" target="_blank" class="logo-area">
            <!-- Asla Bozulmayan Akademik Kep İkonu (SVG) -->
            <svg viewBox="0 0 640 512" xmlns="http://www.w3.org/2000/svg">
                <path d="M320 32c-8.1 0-16.1 1.4-23.7 4.1L15.8 137.4C6.3 140.9 0 149.9 0 160s6.3 19.1 15.8 22.6l57.4 20.3c-1.2 5.3-1.9 10.9-1.9 16.6L71.3 416c0 35.3 28.7 64 64 64H504.7c35.3 0 64-28.7 64-64l0-196.5c0-5.7-.7-11.3-1.9-16.6l10.2-3.6c29.1-10.3 29.1-51.5 0-61.8L343.7 36.1C336.1 33.4 328.1 32 320 32zM128 408c0-13.3 10.7-24 24-24H488c13.3 0 24 10.7 24 24c0 4.4-3.6 8-8 8H136c-4.4 0-8-3.6-8-8zm24-88c-17.7 0-32-14.3-32-32l0-64.8L296.3 285c15.1 5.4 32.2 5.4 47.3 0L520 223.2l0 64.8c0 17.7-14.3 32-32 32H152z"/>
            </svg>
            <span>Marmara BYS</span>
        </a>
    </nav>

    <div class="main-content">
        <div class="auth-container">
            <h2 style="text-align: center; margin-bottom: 25px; font-weight: 600; letter-spacing: 1px;">Oturum Aç</h2>
            
            <?php if(isset($_GET['sifre_basarili'])): ?>
                <div class="alert-success">Şifreniz güncellendi. Yeni şifrenizle giriş yapın.</div>
            <?php endif; ?>
            <?php if($hata != ""): ?><div class="alert-error"><?= $hata ?></div><?php endif; ?>
            
            <form action="" method="POST">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <div class="form-group">
                    <label>Öğrenci Numarası</label>
                    <input type="text" name="kullanici_adi" value="<?= $kullanici_adi_value ?>" autocomplete="off" required>
                </div>
                <div class="form-group">
                    <label>Parola</label>
                    <input type="password" name="sifre" required>
                </div>
                <button type="submit" name="giris_yap" class="btn-neon">GİRİŞ YAP</button>
                <div style="text-align: center;">
                    <a onclick="alert('Güvenlik İlkeleri: İlk şifreniz Öğrenci Numaranızdır. Eğer şifrenizi değiştirdiyseniz ve hatırlamıyorsanız, lütfen Öğretim Görevlisine başvurunuz.');" class="text-link">Şifrenizi mi unuttunuz?</a>
                </div>
            </form>
        </div>
    </div>

    <footer>
        &copy; 2026 Ferit Yeşilkaya - Tüm Hakları Saklıdır. | BÖTE Mikroöğretim Modülü
    </footer>

    <script>
        var geriSayimEl = document.getElementById('geriSayim');
        if (geriSayimEl) {
            var kalan = parseInt(geriSayimEl.getAttribute('data-kalan'), 10);
            var interval = setInterval(function() {
                kalan--;
                if (kalan <= 0) {
                    clearInterval(interval);
                    location.reload();
                } else {
                    geriSayimEl.textContent = kalan;
                }
            }, 1000);
        }
    </script>
</body>
</html>