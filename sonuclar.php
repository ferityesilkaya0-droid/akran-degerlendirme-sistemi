<?php
session_start();
require_once 'includes/db.php';

if (!isset($_SESSION['kullanici_id'])) { header("Location: index.php"); exit; }
if (!empty($_SESSION['sifre_degisim_zorunlu'])) { header("Location: sifre_degistir.php"); exit; }

$kullanici_id = $_SESSION['kullanici_id'];
$rol = $_SESSION['rol'];
$grup = isset($_SESSION['grup']) ? $_SESSION['grup'] : '';

// BONUS: Katılım listesini CSV (Excel'de doğrudan açılır) olarak dışa aktarma
if ($rol == 1 && isset($_GET['disa_aktar']) && $_GET['disa_aktar'] == 'katilim') {
    $veri = $db->query("SELECT k.ad_soyad, k.grup,
        (SELECT COUNT(d.id) FROM degerlendirmeler d JOIN oturumlar o2 ON d.oturum_id = o2.id WHERE d.degerlendiren_id = k.id AND d.oz_degerlendirme_mi = 0 AND o2.durum = 0) as akran_sayisi,
        (SELECT COUNT(o.id) FROM oturumlar o JOIN kullanicilar k2 ON o.sunan_ogrenci_id = k2.id WHERE o.durum = 0 AND k2.grup = k.grup AND o.sunan_ogrenci_id != k.id) as gereken_otomatik,
        (SELECT hedef_gorev_sayisi FROM grup_ayarlari WHERE grup = k.grup) as hedef_kota,
        (SELECT COUNT(d.id) FROM degerlendirmeler d JOIN oturumlar o2 ON d.oturum_id = o2.id WHERE d.degerlendiren_id = k.id AND d.oz_degerlendirme_mi = 1 AND o2.durum = 0) as oz_sayisi
        FROM kullanicilar k WHERE k.rol = 0 ORDER BY k.grup, k.ad_soyad")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($veri as &$satir_ref) {
        $satir_ref['gereken_sayisi'] = ($satir_ref['hedef_kota'] > 0) ? max(0, $satir_ref['hedef_kota'] - 1) : $satir_ref['gereken_otomatik'];
    }
    unset($satir_ref);

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="katilim_raporu.csv"');
    echo "\xEF\xBB\xBF";
    $cikti = fopen('php://output', 'w');
    fputcsv($cikti, ['Ad Soyad', 'Grup', 'Tamamlanan', 'Gereken', 'Katılım Oranı (%)', 'Öz Değerlendirme'], ';');
    foreach ($veri as $satir) {
        $oran = $satir['gereken_sayisi'] > 0 ? round(($satir['akran_sayisi'] / $satir['gereken_sayisi']) * 100) : 100;
        fputcsv($cikti, [
            $satir['ad_soyad'], $satir['grup'], $satir['akran_sayisi'], $satir['gereken_sayisi'],
            $oran, $satir['oz_sayisi'] > 0 ? 'Yaptı' : 'Yapmadı'
        ], ';');
    }
        fclose($cikti);
    exit;
}

if ($rol == 1 && isset($_GET['disa_aktar']) && $_GET['disa_aktar'] == 'geribildirim') {
    $veri = $db->query("SELECT k2.ad_soyad as aday, k2.grup as aday_grup, k1.ad_soyad as degerlendiren, d.oz_degerlendirme_mi, d.guclu_yonler, d.eksik_yonler, d.oneri_metni, d.tarih
        FROM degerlendirmeler d
        JOIN oturumlar o ON d.oturum_id = o.id
        JOIN kullanicilar k2 ON o.sunan_ogrenci_id = k2.id
        JOIN kullanicilar k1 ON d.degerlendiren_id = k1.id
        WHERE o.durum = 0
        ORDER BY k2.ad_soyad, d.tarih")->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="geri_bildirim_raporu.csv"');
    echo "\xEF\xBB\xBF";
    $cikti = fopen('php://output', 'w');
    fputcsv($cikti, ['Aday', 'Grup', 'Değerlendiren', 'Tür', 'Güçlü Yönler', 'Eksik Yönler', 'Öneri', 'Tarih'], ';');
    foreach ($veri as $satir) {
        fputcsv($cikti, [
            $satir['aday'], $satir['aday_grup'], $satir['degerlendiren'],
            $satir['oz_degerlendirme_mi'] ? 'Öz Değerlendirme' : 'Akran',
            $satir['guclu_yonler'], $satir['eksik_yonler'], $satir['oneri_metni'],
            $satir['tarih']
        ], ';');
    }
    fclose($cikti);
    exit;
}

$tumGrafikler = [];
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rapor ve Sonuçlar | Akran Değerlendirme</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="sonuclar-panel">

<div class="panel-layout">
    <div class="mobile-overlay" onclick="toggleSidebar()"></div>
    <aside class="sidebar">
        <div class="sidebar-header">
            <div class="logo-area" style="font-size: 15px;">
                <svg viewBox="0 0 640 512" style="width: 25px; height: 25px;"><path d="M320 32c-8.1 0-16.1 1.4-23.7 4.1L15.8 137.4C6.3 140.9 0 149.9 0 160s6.3 19.1 15.8 22.6l57.4 20.3c-1.2 5.3-1.9 10.9-1.9 16.6L71.3 416c0 35.3 28.7 64 64 64H504.7c35.3 0 64-28.7 64-64l0-196.5c0-5.7-.7-11.3-1.9-16.6l10.2-3.6c29.1-10.3 29.1-51.5 0-61.8L343.7 36.1C336.1 33.4 328.1 32 320 32zM128 408c0-13.3 10.7-24 24-24H488c13.3 0 24 10.7 24 24c0 4.4-3.6 8-8 8H136c-4.4 0-8-3.6-8-8z"/></svg>
                <span><?= $rol == 1 ? 'Yönetim Paneli' : 'Öğrenci Paneli' ?></span>
            </div>
        </div>
        <ul class="sidebar-menu">
            <?php if ($rol == 1): ?>
                <li><a href="hoca_panel.php">Değerlendirme Yönetimi</a></li>
                <li><a href="sonuclar.php" class="active">Rapor ve Sonuçlar</a></li>
                <li><a href="sifre_degistir.php">Şifre İşlemleri</a></li>
            <?php else: ?>
                <li><a href="ogrenci_panel.php">Canlı Değerlendirme</a></li>
                <li><a href="sonuclar.php" class="active">Kişisel Sonuçlarım</a></li>
                <li><a href="sifre_degistir.php">Şifre İşlemleri</a></li>
            <?php endif; ?>
        </ul>
    </aside>

    <div class="main-area">
        <header class="topbar">
            <div class="topbar-left"><button class="menu-toggle" onclick="toggleSidebar()">☰</button></div>
            <div class="topbar-right">
                <div class="welcome-text" style="color: #888; margin-right: 20px;">Hoş Geldin, <b style="color:#fff;"><?= htmlspecialchars($_SESSION['ad_soyad']) ?></b></div>
                <a href="cikis.php" class="logout-btn">Çıkış Yap</a>
            </div>
        </header>

        <div class="content-area">
            <?php if ($rol == 1): ?>

                <div class="main-tabs">
                    <button class="main-tab-btn active" onclick="openMainTab(event, 'katilim_tab')" id="defaultOpen">Katılım İstatistikleri</button>
                    <button class="main-tab-btn" onclick="openMainTab(event, 'sabah_rapor')">Sabah Raporları</button>
                    <button class="main-tab-btn" onclick="openMainTab(event, 'ogle_rapor')">Öğle Raporları</button>
                </div>

                <div id="katilim_tab" class="tab-content active">
                    <div class="panel-box">
                        <div class="panel-title" style="display:flex; justify-content:space-between; align-items:center; flex-wrap: wrap; gap: 10px;">
                            <span>Sınıf Katılım Notları (Akran Görevi)</span>
                            <span style="display:flex; gap:8px; flex-wrap:wrap;">
                                <a href="sonuclar.php?disa_aktar=katilim" style="font-size:12px; background:#28a745; color:#000; padding:8px 14px; border-radius:6px; text-decoration:none; font-weight:bold;">⬇ Katılım CSV</a>
                                <a href="sonuclar.php?disa_aktar=geribildirim" style="font-size:12px; background:#4da6ff; color:#000; padding:8px 14px; border-radius:6px; text-decoration:none; font-weight:bold;">⬇ Geri Bildirimler CSV</a>
                            </span>
                        </div>
                        <div class="table-responsive yatay-scroll" style="max-height: 600px;">
                            <table>
                                <tr><th>Öğrenci Adı</th><th>Grup</th><th>Katılım Notu</th><th>Katılım Oranı</th><th>Öz Değerlendirme</th></tr>
                                <?php
                                $ogrenciler = $db->query("SELECT id, ad_soyad, grup, 
                                    (SELECT COUNT(d.id) FROM degerlendirmeler d JOIN oturumlar o2 ON d.oturum_id = o2.id WHERE d.degerlendiren_id = kullanicilar.id AND d.oz_degerlendirme_mi = 0 AND o2.durum = 0) as akran_sayisi,
                                    (SELECT COUNT(d.id) FROM degerlendirmeler d JOIN oturumlar o2 ON d.oturum_id = o2.id WHERE d.degerlendiren_id = kullanicilar.id AND d.oz_degerlendirme_mi = 1 AND o2.durum = 0) as oz_sayisi,
                                    (SELECT COUNT(o.id) FROM oturumlar o JOIN kullanicilar k2 ON o.sunan_ogrenci_id = k2.id WHERE o.durum = 0 AND k2.grup = kullanicilar.grup AND o.sunan_ogrenci_id != kullanicilar.id) as gereken_otomatik,
                                    (SELECT hedef_gorev_sayisi FROM grup_ayarlari WHERE grup = kullanicilar.grup) as hedef_kota
                                    FROM kullanicilar WHERE rol = 0 ORDER BY grup, ad_soyad")->fetchAll(PDO::FETCH_ASSOC);
                                foreach ($ogrenciler as &$ogr) {
                                    $ogr['gereken_sayisi'] = ($ogr['hedef_kota'] > 0) ? max(0, $ogr['hedef_kota'] - 1) : $ogr['gereken_otomatik'];
                                }
                                unset($ogr);
                                foreach ($ogrenciler as $ogr):
                                    $oran = $ogr['gereken_sayisi'] > 0 ? round(($ogr['akran_sayisi'] / $ogr['gereken_sayisi']) * 100) : 100;
                                ?>
                                <tr>
                                    <td><?= htmlspecialchars($ogr['ad_soyad']) ?></td><td><?= htmlspecialchars($ogr['grup']) ?></td>
                                    <td><span class="badge badge-success"><?= $ogr['akran_sayisi'] ?> / <?= $ogr['gereken_sayisi'] ?></span></td>
                                    <td><b style="color:<?= $oran >= 100 ? '#28a745' : ($oran >= 50 ? '#e0a800' : '#dc3545') ?>;">%<?= $oran ?></b></td>
                                    <td><?= $ogr['oz_sayisi'] > 0 ? '<span class="badge badge-success">Yaptı</span>' : '<span class="badge badge-danger">Yapmadı</span>' ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </table>
                        </div>
                    </div>
                </div>

                <div id="sabah_rapor" class="tab-content" style="display:none;">
                    <div class="panel-box">
                        <div class="panel-title">Sabah Grubu Aday Detayları</div>
                        <?php
                        $sabah_oturumlar = $db->query("SELECT o.id, o.tarih, k.ad_soyad, k.grup FROM oturumlar o JOIN kullanicilar k ON o.sunan_ogrenci_id = k.id WHERE o.durum = 0 AND k.grup LIKE '%Sabah%' ORDER BY o.id DESC")->fetchAll(PDO::FETCH_ASSOC);
                        if (!$sabah_oturumlar) echo '<div style="color:#aaa; text-align:center;">Kapatılmış oturum bulunmuyor.</div>';
                        foreach ($sabah_oturumlar as $oturum):
                            $yorumlar = $db->prepare("SELECT d.id, d.guclu_yonler, d.eksik_yonler, d.oneri_metni, d.oz_degerlendirme_mi, k.ad_soyad FROM degerlendirmeler d JOIN kullanicilar k ON d.degerlendiren_id = k.id WHERE d.oturum_id = ?"); $yorumlar->execute([$oturum['id']]); $yorumListesi = $yorumlar->fetchAll(PDO::FETCH_ASSOC);

                            $aday_ort_stmt = $db->prepare("SELECT kr.id as kriter_id, kr.kriter_metni, IFNULL(AVG(p.puan),0) as ort FROM kriterler kr LEFT JOIN (SELECT p2.kriter_id, p2.puan FROM puanlar p2 JOIN degerlendirmeler d2 ON p2.degerlendirme_id = d2.id WHERE d2.oturum_id = ? AND d2.oz_degerlendirme_mi = 0) p ON kr.id = p.kriter_id GROUP BY kr.id");
                            $aday_ort_stmt->execute([$oturum['id']]);
                            $aday_ort = $aday_ort_stmt->fetchAll(PDO::FETCH_ASSOC);
                            $grafik_id = "grafik_oturum_" . $oturum['id'];
                            if (!empty($aday_ort)) {
                                $tumGrafikler[] = [
                                    'id' => $grafik_id,
                                    'labels' => array_map(fn($p) => mb_strimwidth($p['kriter_metni'], 0, 35, '...'), $aday_ort),
                                    'data' => array_map(fn($p) => round((float)$p['ort'], 2), $aday_ort)
                                ];
                            }
                        ?>
                        <div class="accordion-item">
                            <div class="accordion-header" onclick="toggleAccordion(this)">
                                <span>Aday: <b style="font-size: 16px; margin-left: 5px;"><?= htmlspecialchars($oturum['ad_soyad']) ?></b> <span style="font-size:11px; color:#666; font-weight:normal;">(<?= date('d.m.Y H:i', strtotime($oturum['tarih'])) ?>)</span></span>
                                <span style="font-size:12px; color:#aaa;"><?= count($yorumListesi) ?> Geri Bildirim ▼</span>
                            </div>
                            <div class="accordion-body">
                                <?php if (!empty($aday_ort)): ?>
                                <div class="aday-grafik-kutu">
                                    <canvas id="<?= $grafik_id ?>" height="70"></canvas>
                                </div>
                                <?php endif; ?>
                                <div class="feedback-scroll">
                                    <div class="feedback-grid">
                                        <?php foreach ($yorumListesi as $y): ?>
                                            <div class="feedback-card <?= $y['oz_degerlendirme_mi'] ? 'oz' : '' ?>">
                                                <div class="feedback-header">
                                                    <?= $y['oz_degerlendirme_mi'] ? '🌟 ÖZ DEĞERLENDİRME' : '👤 Değerlendiren: ' . htmlspecialchars($y['ad_soyad']) ?>
                                                </div>
                                                <div class="feedback-body">
                                                    <div class="points-box">
                                                        <?php
                                                        $puanlar = $db->prepare("SELECT kr.kriter_metni, p.puan FROM puanlar p JOIN kriterler kr ON p.kriter_id = kr.id WHERE p.degerlendirme_id = ?"); $puanlar->execute([$y['id']]);
                                                        foreach ($puanlar as $puan) { echo "<span>" . htmlspecialchars($puan['kriter_metni']) . ": <b style='color:#28a745;'>" . (int)$puan['puan'] . "/5</b></span>"; }
                                                        ?>
                                                    </div>
                                                    <div class="text-data"><strong>Güçlü Yönler:</strong><br><?= nl2br(htmlspecialchars($y['guclu_yonler'])) ?></div>
                                                    <div class="text-data"><strong>Eksik Yönler:</strong><br><?= nl2br(htmlspecialchars($y['eksik_yonler'])) ?></div>
                                                    <div class="text-data"><strong>Öneri:</strong><br><?= nl2br(htmlspecialchars($y['oneri_metni'])) ?></div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div id="ogle_rapor" class="tab-content" style="display:none;">
                    <div class="panel-box">
                        <div class="panel-title">Öğle Grubu Aday Detayları</div>
                        <?php
                        $ogle_oturumlar = $db->query("SELECT o.id, o.tarih, k.ad_soyad, k.grup FROM oturumlar o JOIN kullanicilar k ON o.sunan_ogrenci_id = k.id WHERE o.durum = 0 AND (k.grup LIKE '%Öğle%' OR k.grup LIKE '%Ogle%' OR k.grup LIKE '%öğle%') ORDER BY o.id DESC")->fetchAll(PDO::FETCH_ASSOC);
                        if (!$ogle_oturumlar) echo '<div style="color:#aaa; text-align:center;">Kapatılmış oturum bulunmuyor.</div>';
                        foreach ($ogle_oturumlar as $oturum):
                            $yorumlar = $db->prepare("SELECT d.id, d.guclu_yonler, d.eksik_yonler, d.oneri_metni, d.oz_degerlendirme_mi, k.ad_soyad FROM degerlendirmeler d JOIN kullanicilar k ON d.degerlendiren_id = k.id WHERE d.oturum_id = ?"); $yorumlar->execute([$oturum['id']]); $yorumListesi = $yorumlar->fetchAll(PDO::FETCH_ASSOC);

                            $aday_ort_stmt = $db->prepare("SELECT kr.id as kriter_id, kr.kriter_metni, IFNULL(AVG(p.puan),0) as ort FROM kriterler kr LEFT JOIN (SELECT p2.kriter_id, p2.puan FROM puanlar p2 JOIN degerlendirmeler d2 ON p2.degerlendirme_id = d2.id WHERE d2.oturum_id = ? AND d2.oz_degerlendirme_mi = 0) p ON kr.id = p.kriter_id GROUP BY kr.id");
                            $aday_ort_stmt->execute([$oturum['id']]);
                            $aday_ort = $aday_ort_stmt->fetchAll(PDO::FETCH_ASSOC);
                            $grafik_id = "grafik_oturum_" . $oturum['id'];
                            if (!empty($aday_ort)) {
                                $tumGrafikler[] = [
                                    'id' => $grafik_id,
                                    'labels' => array_map(fn($p) => mb_strimwidth($p['kriter_metni'], 0, 35, '...'), $aday_ort),
                                    'data' => array_map(fn($p) => round((float)$p['ort'], 2), $aday_ort)
                                ];
                            }
                        ?>
                        <div class="accordion-item">
                            <div class="accordion-header" onclick="toggleAccordion(this)">
                                <span>Aday: <b style="font-size: 16px; margin-left: 5px;"><?= htmlspecialchars($oturum['ad_soyad']) ?></b> <span style="font-size:11px; color:#666; font-weight:normal;">(<?= date('d.m.Y H:i', strtotime($oturum['tarih'])) ?>)</span></span>
                                <span style="font-size:12px; color:#aaa;"><?= count($yorumListesi) ?> Geri Bildirim ▼</span>
                            </div>
                            <div class="accordion-body">
                                <?php if (!empty($aday_ort)): ?>
                                <div class="aday-grafik-kutu">
                                    <canvas id="<?= $grafik_id ?>" height="70"></canvas>
                                </div>
                                <?php endif; ?>
                                <div class="feedback-scroll">
                                    <div class="feedback-grid">
                                        <?php foreach ($yorumListesi as $y): ?>
                                            <div class="feedback-card <?= $y['oz_degerlendirme_mi'] ? 'oz' : '' ?>">
                                                <div class="feedback-header">
                                                    <?= $y['oz_degerlendirme_mi'] ? '🌟 ÖZ DEĞERLENDİRME' : '👤 Değerlendiren: ' . htmlspecialchars($y['ad_soyad']) ?>
                                                </div>
                                                <div class="feedback-body">
                                                    <div class="points-box">
                                                        <?php
                                                        $puanlar = $db->prepare("SELECT kr.kriter_metni, p.puan FROM puanlar p JOIN kriterler kr ON p.kriter_id = kr.id WHERE p.degerlendirme_id = ?"); $puanlar->execute([$y['id']]);
                                                        foreach ($puanlar as $puan) { echo "<span>" . htmlspecialchars($puan['kriter_metni']) . ": <b style='color:#28a745;'>" . (int)$puan['puan'] . "/5</b></span>"; }
                                                        ?>
                                                    </div>
                                                    <div class="text-data"><strong>Güçlü:</strong><br><?= nl2br(htmlspecialchars($y['guclu_yonler'])) ?></div>
                                                    <div class="text-data"><strong>Eksik:</strong><br><?= nl2br(htmlspecialchars($y['eksik_yonler'])) ?></div>
                                                    <div class="text-data"><strong>Öneri:</strong><br><?= nl2br(htmlspecialchars($y['oneri_metni'])) ?></div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

            <?php else: ?>
                <?php
                $kota_stmt = $db->prepare("SELECT hedef_gorev_sayisi FROM grup_ayarlari WHERE grup = ?"); $kota_stmt->execute([$grup]); $hedef_kota = (int)$kota_stmt->fetchColumn();
                $toplam_oturum = $db->prepare("SELECT COUNT(o.id) FROM oturumlar o JOIN kullanicilar k ON o.sunan_ogrenci_id = k.id WHERE o.durum = 0 AND k.grup = ? AND o.sunan_ogrenci_id != ?"); $toplam_oturum->execute([$grup, $kullanici_id]); $gereken_otomatik = $toplam_oturum->fetchColumn();
                $gereken = ($hedef_kota > 0) ? max(0, $hedef_kota - 1) : $gereken_otomatik;

                $katilim = $db->prepare("SELECT COUNT(d.id) FROM degerlendirmeler d JOIN oturumlar o ON d.oturum_id = o.id WHERE d.degerlendiren_id = ? AND d.oz_degerlendirme_mi = 0 AND o.durum = 0");
                $katilim->execute([$kullanici_id]);
                $yapilan = $katilim->fetchColumn();

                $oran = $gereken > 0 ? round(($yapilan / $gereken) * 100) : 100;
                ?>
                <div class="stat-grid">
                    <div class="stat-box"><div class="stat-value"><?= $yapilan ?> / <?= $gereken ?></div><div class="stat-label">Tamamlanan Görev</div></div>
                    <div class="stat-box green"><div class="stat-value">%<?= $oran ?></div><div class="stat-label">Katılım Oranı</div></div>
                </div>

                <div class="panel-box">
                    <div class="panel-title">Sunumuma Verilen Geri Bildirimler</div>
                    <?php
                    $sunumum = $db->prepare("SELECT id FROM oturumlar WHERE sunan_ogrenci_id = ? AND durum = 0 ORDER BY id DESC LIMIT 1"); $sunumum->execute([$kullanici_id]); $benim = $sunumum->fetch(PDO::FETCH_ASSOC);
                    if (!$benim) { echo '<div style="color: #aaa; text-align: center;">Sunumunuz henüz yapılmamış veya oturum kapatılmamış.</div>'; } 
                    else {
                        $genel_ort_stmt = $db->prepare("SELECT IFNULL(AVG(p.puan),0) as genel FROM puanlar p JOIN degerlendirmeler d ON p.degerlendirme_id = d.id WHERE d.oturum_id = ? AND d.oz_degerlendirme_mi = 0");
                        $genel_ort_stmt->execute([$benim['id']]);
                        $genel_ortalama = $genel_ort_stmt->fetchColumn();

                        $degsayi_stmt = $db->prepare("SELECT COUNT(id) FROM degerlendirmeler WHERE oturum_id = ? AND oz_degerlendirme_mi = 0");
                        $degsayi_stmt->execute([$benim['id']]);
                        $degerlendiren_sayisi = $degsayi_stmt->fetchColumn();

                        $oz_puanlar_stmt = $db->prepare("SELECT p.kriter_id, p.puan FROM degerlendirmeler d JOIN puanlar p ON p.degerlendirme_id = d.id WHERE d.oturum_id = ? AND d.degerlendiren_id = ? AND d.oz_degerlendirme_mi = 1");
                        $oz_puanlar_stmt->execute([$benim['id'], $kullanici_id]);
                        $oz_puanlar = $oz_puanlar_stmt->fetchAll(PDO::FETCH_KEY_PAIR);

                        $puanlar = $db->prepare("SELECT kr.id as kriter_id, kr.kriter_metni, IFNULL(AVG(p.puan), 0) as ortalama FROM kriterler kr LEFT JOIN (SELECT p2.kriter_id, p2.puan FROM puanlar p2 JOIN degerlendirmeler d2 ON p2.degerlendirme_id = d2.id WHERE d2.oturum_id = ? AND d2.oz_degerlendirme_mi = 0) p ON kr.id = p.kriter_id GROUP BY kr.id"); $puanlar->execute([$benim['id']]);
                        $puanlar_dizi = $puanlar->fetchAll(PDO::FETCH_ASSOC);
                        $yorumlar = $db->prepare("SELECT guclu_yonler, eksik_yonler, oneri_metni FROM degerlendirmeler WHERE oturum_id = ? AND oz_degerlendirme_mi = 0"); $yorumlar->execute([$benim['id']]);

                        if (!empty($puanlar_dizi)) {
                            $tumGrafikler[] = [
                                'id' => 'kriterGrafik',
                                'labels' => array_map(fn($p) => mb_strimwidth($p['kriter_metni'], 0, 40, '...'), $puanlar_dizi),
                                'data' => array_map(fn($p) => round((float)$p['ortalama'], 2), $puanlar_dizi)
                            ];
                        }
                    ?>
                        <div class="stat-grid">
                            <div class="stat-box green"><div class="stat-value"><?= number_format($genel_ortalama, 1) ?> / 5.0</div><div class="stat-label">Genel Akran Ortalaması</div></div>
                            <div class="stat-box"><div class="stat-value"><?= $degerlendiren_sayisi ?></div><div class="stat-label">Sizi Değerlendiren Kişi Sayısı</div></div>
                        </div>

                        <div class="yatay-scroll">
                            <table>
                                <tr><th>Değerlendirme Kriteri</th><th>Sınıf Ortalaması</th><?php if (!empty($oz_puanlar)): ?><th>Kendi Öz Değerlendirmeniz</th><?php endif; ?></tr>
                                <?php foreach ($puanlar_dizi as $p): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($p['kriter_metni']) ?></td>
                                        <td><b style="color:#28a745; font-size:16px;"><?= number_format($p['ortalama'], 1) ?></b> / 5.0</td>
                                        <?php if (!empty($oz_puanlar)): ?>
                                            <td><b style="color:#4da6ff; font-size:16px;"><?= isset($oz_puanlar[$p['kriter_id']]) ? (int)$oz_puanlar[$p['kriter_id']] : '-' ?></b> / 5.0</td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </table>
                        </div>

                        <?php if (!empty($puanlar_dizi)): ?>
                        <div class="aday-grafik-kutu">
                            <canvas id="kriterGrafik" height="90"></canvas>
                        </div>
                        <?php endif; ?>

                        <h4 style="color: #28a745; margin: 30px 0 15px 0;">Anonim Sınıf Yorumları</h4>
                        <div class="feedback-scroll">
                            <div class="feedback-grid">
                                <?php foreach ($yorumlar as $y): ?>
                                    <div class="feedback-card">
                                        <div class="feedback-header" style="color:#888; background:rgba(255,255,255,0.05); border-bottom-color:#333;">👤 ANONİM AKRAN</div>
                                        <div class="feedback-body">
                                            <div class="text-data"><strong>Güçlü Yönler:</strong><br><?= nl2br(htmlspecialchars($y['guclu_yonler'])) ?></div>
                                            <div class="text-data"><strong>Eksik Yönler:</strong><br><?= nl2br(htmlspecialchars($y['eksik_yonler'])) ?></div>
                                            <div class="text-data"><strong>Öneri:</strong><br><?= nl2br(htmlspecialchars($y['oneri_metni'])) ?></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php } ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if (!empty($tumGrafikler)): ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
<script>
    var grafikHaritasi = {};
    (<?= json_encode($tumGrafikler, JSON_UNESCAPED_UNICODE) ?>).forEach(function(g) { grafikHaritasi[g.id] = g; });
    var cizilenGrafikler = {};

    function grafigiCiz(canvasId) {
        if (cizilenGrafikler[canvasId]) return;
        var g = grafikHaritasi[canvasId];
        var canvasEl = document.getElementById(canvasId);
        if (!g || !canvasEl) return;
        var mobil = window.innerWidth <= 500;
        new Chart(canvasEl, {
            type: 'bar',
            data: {
                labels: g.labels,
                datasets: [{
                    label: 'Ortalama (5 üzerinden)',
                    data: g.data,
                    backgroundColor: 'rgba(40, 167, 69, 0.6)',
                    borderColor: '#28a745',
                    borderWidth: 1,
                    borderRadius: 6
                }]
            },
            options: {
                indexAxis: mobil ? 'y' : 'x',
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: mobil ? {
                    x: { beginAtZero: true, max: 5, ticks: { color: '#aaa' }, grid: { color: '#222' } },
                    y: { ticks: { color: '#aaa', font: { size: 10 } }, grid: { color: '#222' } }
                } : {
                    y: { beginAtZero: true, max: 5, ticks: { color: '#aaa' }, grid: { color: '#222' } },
                    x: { ticks: { color: '#aaa' }, grid: { color: '#222' } }
                }
            }
        });
        cizilenGrafikler[canvasId] = true;
    }

    document.addEventListener('DOMContentLoaded', function() {
        if (document.getElementById('kriterGrafik')) { grafigiCiz('kriterGrafik'); }
    });
</script>
<?php endif; ?>

<script>
    function openMainTab(evt, tabName) {
        var tabcontent = document.getElementsByClassName("tab-content");
        for (var i = 0; i < tabcontent.length; i++) { tabcontent[i].style.display = "none"; }
        var tablinks = document.getElementsByClassName("main-tab-btn");
        for (var i = 0; i < tablinks.length; i++) { tablinks[i].classList.remove("active"); }
        document.getElementById(tabName).style.display = "block";
        if(evt) {
            evt.currentTarget.classList.add("active");
            sessionStorage.setItem('activeReportTab', tabName);
        }
    }

    window.onload = function() {
        var activeTab = sessionStorage.getItem('activeReportTab');
        if(activeTab) {
            var hedefButon = document.querySelector(`button[onclick="openMainTab(event, '${activeTab}')"]`);
            if (hedefButon) { hedefButon.click(); }
        }
        
        var scrollPos = sessionStorage.getItem('scrollPosition');
        if(scrollPos) {
            document.querySelector('.content-area').scrollTo(0, scrollPos);
            sessionStorage.removeItem('scrollPosition');
        }
    };

    window.onbeforeunload = function() {
        var contentArea = document.querySelector('.content-area');
        if(contentArea) { sessionStorage.setItem('scrollPosition', contentArea.scrollTop); }
    };

    function toggleAccordion(element) {
        element.classList.toggle("active");
        var body = element.nextElementSibling;
        if (body.style.display === "block") { body.style.display = "none"; body.classList.remove("active"); } 
        else { 
            body.style.display = "block"; body.classList.add("active");
            var canvasEl = body.querySelector('canvas[id^="grafik_oturum_"]');
            if (canvasEl && typeof grafigiCiz === 'function') { grafigiCiz(canvasEl.id); }
        }
    }

    function toggleSidebar() {
        var sidebar = document.querySelector('.sidebar');
        sidebar.classList.toggle('collapsed');
        sidebar.classList.toggle('open');
        var overlay = document.querySelector('.mobile-overlay');
        if(overlay) overlay.classList.toggle('active');
    }
</script>
</body>
</html>