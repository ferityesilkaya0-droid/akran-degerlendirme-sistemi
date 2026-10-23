<?php
session_start();
require_once 'includes/db.php';

if (!isset($_SESSION['kullanici_id']) || $_SESSION['rol'] != 1) {
    header("Location: index.php");
    exit;
}
if (!empty($_SESSION['ilk_giris_gerekli'])) {
    header("Location: sifre_degistir.php");
    exit;
}

$oturum_id = (int)($_GET['id'] ?? 0);

$oturum_stmt = $db->prepare("SELECT o.*, k.ad_soyad as aday_adi, k.grup as aday_grup FROM oturumlar o JOIN kullanicilar k ON o.sunan_ogrenci_id = k.id WHERE o.id = ?");
$oturum_stmt->execute([$oturum_id]);
$oturum = $oturum_stmt->fetch(PDO::FETCH_ASSOC);

if (!$oturum) {
    header("Location: hoca_panel.php");
    exit;
}

$degerlendirenler_stmt = $db->prepare("SELECT d.*, k.ad_soyad as degerlendiren_adi FROM degerlendirmeler d JOIN kullanicilar k ON d.degerlendiren_id = k.id WHERE d.oturum_id = ? ORDER BY d.oz_degerlendirme_mi DESC, d.tarih ASC");
$degerlendirenler_stmt->execute([$oturum_id]);
$degerlendirenler = $degerlendirenler_stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Oturum Detayı | Akran Değerlendirme</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="sonuclar-panel">

<div class="panel-layout">
    <div class="mobile-overlay" onclick="toggleSidebar()"></div>
    <aside class="sidebar">
        <div class="sidebar-header">
            <div class="logo-area" style="font-size: 15px;">
                <svg viewBox="0 0 640 512" style="width: 25px; height: 25px;"><path d="M320 32c-8.1 0-16.1 1.4-23.7 4.1L15.8 137.4C6.3 140.9 0 149.9 0 160s6.3 19.1 15.8 22.6l57.4 20.3c-1.2 5.3-1.9 10.9-1.9 16.6L71.3 416c0 35.3 28.7 64 64 64H504.7c35.3 0 64-28.7 64-64l0-196.5c0-5.7-.7-11.3-1.9-16.6l10.2-3.6c29.1-10.3 29.1-51.5 0-61.8L343.7 36.1C336.1 33.4 328.1 32 320 32zM128 408c0-13.3 10.7-24 24-24H488c13.3 0 24 10.7 24 24c0 4.4-3.6 8-8 8H136c-4.4 0-8-3.6-8-8z"/></svg>
                <span>Yönetim Paneli</span>
            </div>
        </div>
        <ul class="sidebar-menu">
            <li><a href="hoca_panel.php">Değerlendirme Yönetimi</a></li>
            <li><a href="sonuclar.php">Rapor ve Sonuçlar</a></li>
            <li><a href="sifre_degistir.php">Şifre İşlemleri</a></li>
        </ul>
    </aside>

    <div class="main-area">
        <header class="topbar">
            <div class="topbar-left"><button class="menu-toggle" onclick="toggleSidebar()">☰</button></div>
            <div class="topbar-right">
                <div class="welcome-text" style="color: #888; margin-right: 20px;">Hoş Geldiniz, <b style="color:#fff;"><?= htmlspecialchars($_SESSION['ad_soyad']) ?></b></div>
                <a href="cikis.php" class="logout-btn">Çıkış Yap</a>
            </div>
        </header>

        <div class="content-area">
            <a href="hoca_panel.php" style="color:#28a745; text-decoration:none; display:inline-block; margin-bottom:20px;">&larr; Panele Dön</a>

            <div class="panel-box">
                <div class="panel-title">Oturum Detayı — <?= htmlspecialchars($oturum['aday_adi']) ?> <span style="color:#888; font-size:13px; font-weight:normal;">(Kayıt No: #<?= $oturum['id'] ?>)</span></div>
                <div class="stat-grid">
                    <div class="stat-box">
                        <div class="stat-value" style="font-size:20px;"><?= date('d.m.Y H:i', strtotime($oturum['tarih'])) ?></div>
                        <div class="stat-label">Başlama Zamanı</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-value" style="font-size:20px;"><?= $oturum['bitis_tarihi'] ? date('d.m.Y H:i', strtotime($oturum['bitis_tarihi'])) : '<span style="color:#888;">Kayıt yok</span>' ?></div>
                        <div class="stat-label">Bitiş Zamanı</div>
                    </div>
                </div>
                <div class="stat-grid">
                    <div class="stat-box">
                        <div class="stat-value"><?= htmlspecialchars($oturum['oturum_kodu']) ?></div>
                        <div class="stat-label">PIN Kodu</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-value"><?= htmlspecialchars($oturum['aday_grup']) ?></div>
                        <div class="stat-label">Grup</div>
                    </div>
                </div>

                <div class="panel-title" style="margin-top:20px;">Değerlendirenler (<?= count($degerlendirenler) ?>)</div>
                <div class="table-responsive yatay-scroll" style="max-height:400px;">
                    <table>
                        <tr><th>Ad Soyad</th><th>Tür</th><th>Tarih</th></tr>
                        <?php if (empty($degerlendirenler)): ?>
                            <tr><td colspan="3" style="color:#888; text-align:center;">Henüz değerlendirme yapılmamış.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($degerlendirenler as $d): ?>
                            <tr>
                                <td><?= htmlspecialchars($d['degerlendiren_adi']) ?></td>
                                <td><?= $d['oz_degerlendirme_mi'] ? '<span class="badge badge-success">Öz Değerlendirme</span>' : '<span class="badge" style="background:rgba(40,167,69,0.1); color:#28a745; border:1px solid #28a745;">Akran</span>' ?></td>
                                <td style="color:#888; font-size:12px;"><?= date('d.m.Y H:i', strtotime($d['tarih'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function toggleSidebar() {
        var sidebar = document.querySelector('.sidebar');
        sidebar.classList.toggle('collapsed');
        sidebar.classList.toggle('open');
        var overlay = document.querySelector('.mobile-overlay');
        if (overlay) overlay.classList.toggle('active');
    }
</script>
</body>
</html>