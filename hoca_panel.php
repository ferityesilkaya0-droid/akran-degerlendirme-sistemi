<?php
session_start();
require_once 'includes/db.php';

if (!isset($_SESSION['kullanici_id']) || $_SESSION['rol'] != 1) {
    header("Location: index.php");
    exit;
}
if (!empty($_SESSION['sifre_degisim_zorunlu'])) {
    header("Location: sifre_degistir.php");
    exit;
}

$mesaj = "";
$ogretmen_paneli_ac = false;
if (!empty($_SESSION['flash_mesaj'])) {
    $mesaj = $_SESSION['flash_mesaj'];
    unset($_SESSION['flash_mesaj']);
}
if (!empty($_SESSION['flash_ogretmen_ac'])) {
    $ogretmen_paneli_ac = true;
    unset($_SESSION['flash_ogretmen_ac']);
}

// GÖREV KOTASI GÜNCELLEME

if (isset($_POST['kota_guncelle'])) {
    csrf_dogrula();
    $sabah_kota = max(0, (int)$_POST['sabah_kota']);
    $ogle_kota = max(0, (int)$_POST['ogle_kota']);
    $db->prepare("UPDATE grup_ayarlari SET hedef_gorev_sayisi = ? WHERE grup = 'Sabah'")->execute([$sabah_kota]);
    $db->prepare("UPDATE grup_ayarlari SET hedef_gorev_sayisi = ? WHERE grup = 'Öğle'")->execute([$ogle_kota]);
    $mesaj = "<div class='alert-success'>Görev kotaları güncellendi.</div>";
}

if (isset($_POST['ogretmen_ekle'])) {
    csrf_dogrula();
    $ogretmen_paneli_ac = true;
    $ad_soyad = trim($_POST['yeni_ogretmen_ad']);

    if (empty($ad_soyad)) {
        $mesaj = "<div class='alert-error'>Ad soyad girin.</div>";
    } else {
        do {
            $ogr_no = '9' . str_pad((string)random_int(0, 99999), 5, '0', STR_PAD_LEFT);
            $kontrol = $db->prepare("SELECT id FROM kullanicilar WHERE ogr_no = ?");
            $kontrol->execute([$ogr_no]);
        } while ($kontrol->rowCount() > 0);

        $sifre_hash = password_hash($ogr_no, PASSWORD_BCRYPT);
        $db->prepare("INSERT INTO kullanicilar (ad_soyad, ogr_no, sifre, rol, grup) VALUES (?, ?, ?, 1, 'Yok')")->execute([$ad_soyad, $ogr_no, $sifre_hash]);
        $mesaj = "<div class='alert-success'>Yeni öğretim üyesi eklendi. Giriş numarası: <b>$ogr_no</b> — bu numarayı yeni öğretmene iletin. İlk şifresi de aynı numaradır ve ilk girişte değiştirmesi zorunludur. (Bu numarayı aşağıdaki listede istediğiniz zaman tekrar görebilirsiniz.)</div>";
    }
}

if (isset($_POST['sifre_sifirla'])) {
    csrf_dogrula();
    $hedef_id = (int)$_POST['sifirlanacak_id'];
    $hedef = $db->prepare("SELECT ogr_no FROM kullanicilar WHERE id = ?");
    $hedef->execute([$hedef_id]);
    $kayit = $hedef->fetch(PDO::FETCH_ASSOC);
    if ($kayit) {
        $yeni_hash = password_hash($kayit['ogr_no'], PASSWORD_BCRYPT);
        $db->prepare("UPDATE kullanicilar SET sifre = ? WHERE id = ?")->execute([$yeni_hash, $hedef_id]);
        $mesaj = "<div class='alert-success'>Şifre, öğrenci numarasına sıfırlandı. Öğrenci bir sonraki girişte şifresini değiştirmeye zorlanacak.</div>";
    }
}

if (isset($_POST['ogretmen_sil'])) {
    csrf_dogrula();
    $hedef_id = (int)$_POST['silinecek_ogretmen_id'];
    if ($hedef_id === (int)$_SESSION['kullanici_id']) {
        $mesaj = "<div class='alert-error'>Kendi hesabınızı silemezsiniz.</div>";
    } else {
        $kalan_sayisi = (int)$db->query("SELECT COUNT(*) FROM kullanicilar WHERE rol = 1")->fetchColumn();
        if ($kalan_sayisi <= 1) {
            $mesaj = "<div class='alert-error'>Sistemde en az bir öğretim üyesi hesabı kalmalı, bu son hesabı silemezsiniz.</div>";
        } else {
            $db->prepare("DELETE FROM kullanicilar WHERE id = ? AND rol = 1")->execute([$hedef_id]);
            $mesaj = "<div class='alert-success'>Öğretim üyesi silindi.</div>";
        }
    }
}

// CSV YÜKLEME (DOSYA TİPİ KONTROLÜ + TÜRKÇE KARAKTER VE BOM TEMİZLİĞİ)
if (isset($_POST['csv_yukle']) && isset($_FILES['csv_dosya'])) {
    csrf_dogrula();
    $dosya_adi = $_FILES['csv_dosya']['name'];
    $uzanti = strtolower(pathinfo($dosya_adi, PATHINFO_EXTENSION));

    if ($uzanti !== 'csv') {
        $mesaj = "<div class='alert-error'>Sadece .csv uzantılı dosya yükleyebilirsiniz. Yüklemeye çalıştığınız dosya (\"" . htmlspecialchars($dosya_adi) . "\") bir CSV dosyası değil.</div>";
    } else {
        $dosya = $_FILES['csv_dosya']['tmp_name'];
        if ($_FILES['csv_dosya']['size'] > 0) {
            $csv_icerik = file_get_contents($dosya);
            $bom = pack('H*', 'EFBBBF');
            $csv_icerik = preg_replace("/^$bom/", '', $csv_icerik);

            if (!mb_check_encoding($csv_icerik, 'UTF-8')) {
                $csv_icerik = mb_convert_encoding($csv_icerik, 'UTF-8', 'Windows-1254');
            }

            $satirlar = explode("\n", $csv_icerik);
            $eklenen = 0;
            $atlanan = 0;
            $atlanan_detay = [];

            foreach ($satirlar as $satir_no => $satir) {
                $satir = trim($satir);
                if (empty($satir)) continue;

                $data = str_getcsv($satir, ";");
                if (count($data) < 3) {
                    $atlanan++;
                    $atlanan_detay[] = "Satır " . ($satir_no + 1) . ": eksik sütun";
                    continue;
                }

                $ad_soyad = trim($data[0]);
                $ogr_no   = trim($data[1]);
                $grup_ham = trim($data[2]);

                if (empty($ad_soyad) || empty($ogr_no)) {
                    $atlanan++;
                    $atlanan_detay[] = "Satır " . ($satir_no + 1) . ": ad veya numara boş";
                    continue;
                }

                if (!preg_match('/^\d{6,20}$/', $ogr_no)) {
                    $atlanan++;
                    $atlanan_detay[] = "Satır " . ($satir_no + 1) . ": öğrenci numarası geçersiz (\"$ogr_no\") — yalnızca rakam, en az 6 hane olmalı";
                    continue;
                }

                $grup_kucuk = mb_strtolower($grup_ham, 'UTF-8');
                if (strpos($grup_kucuk, 'sabah') !== false) {
                    $grup = 'Sabah';
                } elseif (strpos($grup_kucuk, 'öğle') !== false || strpos($grup_kucuk, 'ogle') !== false || strpos($grup_kucuk, 'öğ') !== false) {
                    $grup = 'Öğle';
                } else {
                    $grup = 'Yok';
                }

                try {
                    $kontrol = $db->prepare("SELECT id FROM kullanicilar WHERE ogr_no = ?");
                    $kontrol->execute([$ogr_no]);
                    if ($kontrol->rowCount() == 0) {
                        $sifre_hash = password_hash($ogr_no, PASSWORD_BCRYPT);
                        $db->prepare("INSERT INTO kullanicilar (ad_soyad, ogr_no, sifre, rol, grup) VALUES (?, ?, ?, 0, ?)")->execute([$ad_soyad, $ogr_no, $sifre_hash, $grup]);
                        $eklenen++;
                    } else {
                        $atlanan++;
                        $atlanan_detay[] = "Satır " . ($satir_no + 1) . ": $ogr_no zaten kayıtlı";
                    }
                } catch (PDOException $e) {
                    $atlanan++;
                    $atlanan_detay[] = "Satır " . ($satir_no + 1) . ": kaydedilemedi ($ogr_no)";
                }
            }

            $mesaj = "<div class='alert-success'>$eklenen öğrenci başarıyla eklendi.</div>";
            if ($atlanan > 0) {
                $liste_html = '<ul style="margin:8px 0 0 20px; padding:0; text-align:left;">';
                foreach (array_slice($atlanan_detay, 0, 8) as $satir_metni) {
                    $liste_html .= '<li>' . htmlspecialchars($satir_metni) . '</li>';
                }
                if (count($atlanan_detay) > 8) { $liste_html .= '<li>... ve ' . (count($atlanan_detay) - 8) . ' satır daha</li>'; }
                $liste_html .= '</ul>';
                $mesaj .= "<div class='alert-error'>$atlanan satır atlandı:$liste_html</div>";
            }
        } else {
            $mesaj = "<div class='alert-error'>Dosya boş görünüyor, lütfen geçerli bir CSV dosyası seçin.</div>";
        }
    }
}

// ==========================================
// ADIM 1.5: MANUEL ÖĞRENCİ YÖNETİMİ
// ==========================================
if (isset($_POST['ogrenci_ekle'])) {
    csrf_dogrula();
    $ad_soyad = trim($_POST['yeni_ad_soyad']);
    $ogr_no = trim($_POST['yeni_ogr_no']);
    $grup = $_POST['yeni_grup'];
    $izinli_gruplar = ['Sabah', 'Öğle', 'Yok'];

    if (empty($ad_soyad) || empty($ogr_no) || !in_array($grup, $izinli_gruplar)) {
        $mesaj = "<div class='alert-error'>Ad, numara ve grup bilgisi eksik veya hatalı.</div>";
    } elseif (!preg_match('/^\d{6,20}$/', $ogr_no)) {
        $mesaj = "<div class='alert-error'>Öğrenci numarası yalnızca rakamlardan oluşmalı ve en az 6 haneli olmalıdır (ilk şifreniz bu numara olacağından, şifre kuralıyla tutarlı olması gerekir).</div>";
    } else {
        $kontrol = $db->prepare("SELECT id FROM kullanicilar WHERE ogr_no = ?");
        $kontrol->execute([$ogr_no]);
        if ($kontrol->rowCount() > 0) {
            $mesaj = "<div class='alert-error'>Bu öğrenci numarası zaten kayıtlı.</div>";
        } else {
            $sifre_hash = password_hash($ogr_no, PASSWORD_BCRYPT);
            $db->prepare("INSERT INTO kullanicilar (ad_soyad, ogr_no, sifre, rol, grup) VALUES (?, ?, ?, 0, ?)")->execute([$ad_soyad, $ogr_no, $sifre_hash, $grup]);
            $mesaj = "<div class='alert-success'>Öğrenci eklendi.</div>";
        }
    }
}

if (isset($_POST['grup_guncelle'])) {
    csrf_dogrula();
    $hedef_id = (int)$_POST['ogrenci_id_guncelle'];
    $yeni_grup = $_POST['yeni_grup_deger'];
    $izinli_gruplar = ['Sabah', 'Öğle', 'Yok'];

    if (in_array($yeni_grup, $izinli_gruplar)) {
        $db->prepare("UPDATE kullanicilar SET grup = ? WHERE id = ? AND rol = 0")->execute([$yeni_grup, $hedef_id]);
        $mesaj = "<div class='alert-success'>Grup güncellendi.</div>";
    } else {
        $mesaj = "<div class='alert-error'>Geçersiz grup değeri.</div>";
    }
}

if (isset($_POST['ogrenci_sil'])) {
    csrf_dogrula();
    $hedef_id = (int)$_POST['silinecek_ogrenci_id'];
    $db->prepare("DELETE FROM kullanicilar WHERE id = ? AND rol = 0")->execute([$hedef_id]);
    $mesaj = "<div class='alert-success'>Öğrenci silindi.</div>";
}

// OTURUM BAŞLAT / BİTİR / SİL
if (isset($_POST['oturum_baslat'])) {
    csrf_dogrula();
    $secilen = (int)$_POST['ogrenci_id'];
    $kod = rand(1000, 9999);
    $db->query("UPDATE oturumlar SET durum = 0 WHERE durum = 1");
    $db->prepare("INSERT INTO oturumlar (hoca_id, sunan_ogrenci_id, durum, oturum_kodu) VALUES (?, ?, 1, ?)")->execute([$_SESSION['kullanici_id'], $secilen, $kod]);
    header("Location: hoca_panel.php");
    exit;
}
if (isset($_POST['oturum_bitir'])) {
    csrf_dogrula();
    $db->prepare("UPDATE oturumlar SET durum = 0, bitis_tarihi = NOW() WHERE id = ?")->execute([(int)$_POST['oturum_id']]);
    header("Location: hoca_panel.php");
    exit;
}
if (isset($_POST['oturum_sil'])) {
    csrf_dogrula();
    $silinecek = (int)$_POST['silinecek_id'];
    $deg = $db->prepare("SELECT id FROM degerlendirmeler WHERE oturum_id = ?");
    $deg->execute([$silinecek]);
    foreach ($deg as $d) {
        $db->prepare("DELETE FROM puanlar WHERE degerlendirme_id = ?")->execute([$d['id']]);
    }
    $db->prepare("DELETE FROM degerlendirmeler WHERE oturum_id = ?")->execute([$silinecek]);
    $db->prepare("DELETE FROM oturumlar WHERE id = ?")->execute([$silinecek]);
    $mesaj = "<div class='alert-success'>Hatalı oturum silindi.</div>";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $mesaj !== '') {
    $_SESSION['flash_mesaj'] = $mesaj;
    if (isset($_POST['ogretmen_ekle']) || isset($_POST['ogretmen_sil'])) {
        $_SESSION['flash_ogretmen_ac'] = true;
    }
    header("Location: hoca_panel.php");
    exit;
}

$aktif_oturum = $db->query("SELECT o.id, k.ad_soyad, k.grup, o.oturum_kodu FROM oturumlar o JOIN kullanicilar k ON o.sunan_ogrenci_id = k.id WHERE o.durum = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$sabah_grubu = $db->query("SELECT k.id, k.ad_soyad, (SELECT COUNT(*) FROM oturumlar o WHERE o.sunan_ogrenci_id = k.id AND o.durum = 0) as sunum_sayisi FROM kullanicilar k WHERE k.rol = 0 AND k.grup LIKE '%Sabah%' ORDER BY k.ad_soyad ASC")->fetchAll(PDO::FETCH_ASSOC);
$ogle_grubu = $db->query("SELECT k.id, k.ad_soyad, (SELECT COUNT(*) FROM oturumlar o WHERE o.sunan_ogrenci_id = k.id AND o.durum = 0) as sunum_sayisi FROM kullanicilar k WHERE k.rol = 0 AND (k.grup LIKE '%Öğle%' OR k.grup LIKE '%Ogle%' OR k.grup LIKE '%öğle%') ORDER BY k.ad_soyad ASC")->fetchAll(PDO::FETCH_ASSOC);
$gecmis_oturumlar = $db->query("SELECT o.id, k.ad_soyad, k.grup, o.tarih, (SELECT COUNT(*) FROM degerlendirmeler d WHERE d.oturum_id = o.id) as geri_bildirim_sayisi FROM oturumlar o JOIN kullanicilar k ON o.sunan_ogrenci_id = k.id WHERE o.durum = 0 ORDER BY o.id DESC")->fetchAll(PDO::FETCH_ASSOC);
$tum_ogrenciler = $db->query("SELECT id, ad_soyad, ogr_no, grup FROM kullanicilar WHERE rol = 0 ORDER BY grup ASC, ad_soyad ASC")->fetchAll(PDO::FETCH_ASSOC);
$kota_ham = $db->query("SELECT grup, hedef_gorev_sayisi FROM grup_ayarlari")->fetchAll(PDO::FETCH_KEY_PAIR);
$sabah_kota_mevcut = $kota_ham['Sabah'] ?? 0;
$ogle_kota_mevcut = $kota_ham['Öğle'] ?? 0;
$ogretmenler = $db->query("SELECT id, ad_soyad, ogr_no FROM kullanicilar WHERE rol = 1 ORDER BY ad_soyad ASC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hoca Paneli | Akran Değerlendirme</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body class="hoca-panel">

    <div class="panel-layout">
        <div class="mobile-overlay" onclick="toggleSidebar()"></div>

        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="logo-area" style="font-size: 15px;">
                    <svg viewBox="0 0 640 512" style="width: 25px; height: 25px;">
                        <path d="M320 32c-8.1 0-16.1 1.4-23.7 4.1L15.8 137.4C6.3 140.9 0 149.9 0 160s6.3 19.1 15.8 22.6l57.4 20.3c-1.2 5.3-1.9 10.9-1.9 16.6L71.3 416c0 35.3 28.7 64 64 64H504.7c35.3 0 64-28.7 64-64l0-196.5c0-5.7-.7-11.3-1.9-16.6l10.2-3.6c29.1-10.3 29.1-51.5 0-61.8L343.7 36.1C336.1 33.4 328.1 32 320 32zM128 408c0-13.3 10.7-24 24-24H488c13.3 0 24 10.7 24 24c0 4.4-3.6 8-8 8H136c-4.4 0-8-3.6-8-8z" />
                    </svg>
                    <span>Yönetim Paneli</span>
                </div>
            </div>
            <ul class="sidebar-menu">
                <li><a href="hoca_panel.php" class="active">Değerlendirme Yönetimi</a></li>
                <li><a href="sonuclar.php">Rapor ve Sonuçlar</a></li>
                <li><a href="sifre_degistir.php">Şifre İşlemleri</a></li>
            </ul>
        </aside>

        <div class="main-area">
            <header class="topbar">
                <div class="topbar-left">
                    <button class="menu-toggle" onclick="toggleSidebar()">☰</button>
                </div>
                <div class="topbar-right">
                    <div class="welcome-text" style="color: #888; margin-right: 20px;">Hoş Geldiniz, <b style="color:#fff;"><?= htmlspecialchars($_SESSION['ad_soyad']) ?></b></div>
                    <a href="cikis.php" class="logout-btn">Çıkış Yap</a>
                </div>
            </header>

            <div class="content-area">
                <?= $mesaj ?>
                <div class="grid-2">
                    <div>
                        <div class="panel-box">
                            <div class="panel-title">Toplu Öğrenci Yükle (CSV)</div>
                            <form action="" method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                <label>Format: İsim Soyisim ; Öğrenci No ; Grup — Sadece .csv dosyaları kabul edilir</label>
                                <input type="file" name="csv_dosya" accept=".csv" required>
                                <button type="submit" name="csv_yukle" class="btn-neon" style="font-size: 13px; padding: 10px;">EXCEL (CSV) YÜKLE</button>
                            </form>
                        </div>

                        <div class="panel-box">
                            <div class="panel-title">Değerlendirme Başlat</div>
                            <?php if ($aktif_oturum): ?>
                                <div class="alert-error">Devam eden bir oturum var. Önce onu kapatmalısınız.</div>
                            <?php else: ?>
                                <div class="tabs">
                                    <button class="tab-btn active" onclick="openTab(event, 'sabah_tab')">Sabah Grubu</button>
                                    <button class="tab-btn" onclick="openTab(event, 'ogle_tab')">Öğle Grubu</button>
                                </div>
                                <div id="sabah_tab" class="tab-content active">
                                    <form action="" method="POST">
                                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                        <select name="ogrenci_id" required>
                                            <option value="">Sabah Grubundan Aday Seçin</option>
                                            <?php foreach ($sabah_grubu as $ogr): ?><option value="<?= $ogr['id'] ?>"><?= htmlspecialchars($ogr['ad_soyad']) ?><?= $ogr['sunum_sayisi'] > 0 ? ' — ✅ ' . $ogr['sunum_sayisi'] . '. kez' : '' ?></option><?php endforeach; ?>
                                        </select>
                                        <button type="submit" name="oturum_baslat" class="btn-neon">OTURUMU BAŞLAT</button>
                                    </form>
                                </div>
                                <div id="ogle_tab" class="tab-content">
                                    <form action="" method="POST">
                                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                        <select name="ogrenci_id" required>
                                            <option value="">Öğle Grubundan Aday Seçin</option>
                                            <?php foreach ($ogle_grubu as $ogr): ?><option value="<?= $ogr['id'] ?>"><?= htmlspecialchars($ogr['ad_soyad']) ?><?= $ogr['sunum_sayisi'] > 0 ? ' — ✅ ' . $ogr['sunum_sayisi'] . '. kez' : '' ?></option><?php endforeach; ?>
                                        </select>
                                        <button type="submit" name="oturum_baslat" class="btn-neon">OTURUMU BAŞLAT</button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="panel-box">
                            <div class="panel-title">Görev Kotası Ayarları</div>
                            <p style="color:#888; font-size:13px; margin-bottom:15px;">0 bırakılırsa sistem otomatik hesaplar (kapanan oturum sayısına göre). 0'dan büyük değer girilirse sabit kota esas alınır.</p>
                            <form action="" method="POST" style="display:flex; gap:10px; flex-wrap:wrap; align-items:flex-end;" onsubmit="return confirm('Görev kotalarını güncellemek istediğinize emin misiniz? Bu değişiklik tüm öğrencilerin katılım oranı hesaplamasını etkiler.');">
                                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                <div style="flex:1; min-width:120px;">
                                    <label style="display:block; color:#bbb; font-size:12px; margin-bottom:6px;">Sabah Grubu Kota</label>
                                    <input type="number" name="sabah_kota" min="0" value="<?= (int)$sabah_kota_mevcut ?>" style="width:100%; padding:10px; background:rgba(0,0,0,0.5); border:1px solid #444; color:#fff; border-radius:8px;">
                                </div>
                                <div style="flex:1; min-width:120px;">
                                    <label style="display:block; color:#bbb; font-size:12px; margin-bottom:6px;">Öğle Grubu Kota</label>
                                    <input type="number" name="ogle_kota" min="0" value="<?= (int)$ogle_kota_mevcut ?>" style="width:100%; padding:10px; background:rgba(0,0,0,0.5); border:1px solid #444; color:#fff; border-radius:8px;">
                                </div>
                                <button type="submit" name="kota_guncelle" class="btn-neon" style="flex:0 0 auto; margin-top:0;">Kaydet</button>
                            </form>
                        </div>
                    </div>

                    <div>
                        <div class="panel-box" style="text-align: center; border-color: <?= $aktif_oturum ? '#28a745' : '#222' ?>; box-shadow: <?= $aktif_oturum ? '0 0 25px rgba(40,167,69,0.2)' : '0 5px 20px rgba(0,0,0,0.5)' ?>;">
                            <div class="panel-title" style="border:none;">Canlı Yayın Monitörü</div>
                            <?php if ($aktif_oturum): ?>
                                <div style="color: #28a745; font-weight: bold; margin-bottom: 10px;">CANLI YAYINDA</div>
                                <h2 style="color: #fff; margin-bottom: 5px;"><?= htmlspecialchars($aktif_oturum['ad_soyad']) ?></h2>
                                <p style="color: #888; margin-bottom: 25px;">(<?= htmlspecialchars($aktif_oturum['grup']) ?> Grubu)</p>
                                <div style="font-size: 60px; font-weight: 900; letter-spacing: 12px; color: #fff; background: #000; padding: 15px; border-radius: 12px; border: 2px dashed #28a745; display: inline-block; margin-bottom: 25px;">
                                    <?= htmlspecialchars($aktif_oturum['oturum_kodu']) ?>
                                </div>
                                <form action="" method="POST">
                                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                    <input type="hidden" name="oturum_id" value="<?= $aktif_oturum['id'] ?>">
                                    <button type="submit" name="oturum_bitir" class="btn-neon" style="background: #dc3545; color: #fff;">OTURUMU KAPAT</button>
                                </form>
                            <?php else: ?>
                                <div style="font-size: 50px; color: #333; margin: 40px 0;">⏸</div>
                                <p style="color: #666;">Aktif bir oturum bulunmuyor.</p>
                            <?php endif; ?>
                        </div>

                        <div class="panel-box">
                            <div class="panel-title">Oturum Hata Yönetimi (<?= count($gecmis_oturumlar) ?> kayıt)</div>
                            <div class="table-responsive yatay-scroll" style="max-height:320px; overflow-y:auto;">
                                <table class="hover-buton">
                                    <thead>
                                        <tr><th>Aday</th><th>Grup</th><th>Tarih</th><th>Bildirim</th><th></th></tr>
                                    </thead>
                                    <?php foreach ($gecmis_oturumlar as $go): ?>
                                        <tr style="cursor:pointer;" onclick="window.location.href='oturum_detay.php?id=<?= $go['id'] ?>'">
                                            <td><?= htmlspecialchars($go['ad_soyad']) ?></td>
                                            <td><?= htmlspecialchars($go['grup']) ?></td>
                                            <td style="color:#888; font-size:12px; white-space:nowrap;"><?= date('d.m.Y H:i', strtotime($go['tarih'])) ?></td>
                                            <td style="color:#888; font-size:12px;"><?= (int)$go['geri_bildirim_sayisi'] ?></td>
                                            <td style="text-align: right;" onclick="event.stopPropagation();">
                                                <form action="" method="POST" onsubmit="return confirm('Silmek istediğinize emin misiniz?');">
                                                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                                    <input type="hidden" name="silinecek_id" value="<?= $go['id'] ?>">
                                                    <button type="submit" name="oturum_sil" style="background: none; border: none; color: #ff6b6b; cursor: pointer; text-decoration: underline;">İptal Et</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="panel-box">
                    <div class="panel-title">Öğrenci Yönetimi</div>

                    <div class="arama-satiri">
                        <input type="text" id="ogrenciArama" placeholder="Öğrenci ara (isim veya numara)...">
                        <button type="button" onclick="ogrenciAra()">Ara</button>
                    </div>

                    <form action="" method="POST" class="ekle-form">
                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                        <input type="text" name="yeni_ad_soyad" placeholder="Ad Soyad" required>
                        <input type="text" name="yeni_ogr_no" placeholder="Öğrenci No" required>
                        <select name="yeni_grup" required>
                            <option value="Sabah">Sabah</option>
                            <option value="Öğle">Öğle</option>
                            <option value="Yok">Yok</option>
                        </select>
                        <button type="submit" name="ogrenci_ekle">EKLE</button>
                    </form>

                    <div class="table-responsive yatay-scroll" style="max-height:400px; overflow-y:auto;">
                        <table>
                            <thead>
                                <tr>
                                    <th>Ad Soyad</th>
                                    <th>Öğrenci No</th>
                                    <th>Grup</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody id="ogrenciTabloGovde">
                                <?php foreach ($tum_ogrenciler as $o): ?>
                                    <tr>
                                        <td title="<?= htmlspecialchars($o['ad_soyad']) ?>"><?= htmlspecialchars($o['ad_soyad']) ?></td>
                                        <td><?= htmlspecialchars($o['ogr_no']) ?></td>
                                                                                <td>
                                            <form action="" method="POST" style="display:flex; gap:6px;">
                                                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                                <input type="hidden" name="ogrenci_id_guncelle" value="<?= $o['id'] ?>">
                                                <select name="yeni_grup_deger" style="padding:6px; background:#000; color:#fff; border:1px solid #444; border-radius:6px;">
                                                    <option value="Sabah" <?= $o['grup'] == 'Sabah' ? 'selected' : '' ?>>Sabah</option>
                                                    <option value="Öğle" <?= $o['grup'] == 'Öğle' ? 'selected' : '' ?>>Öğle</option>
                                                    <option value="Yok" <?= $o['grup'] == 'Yok' ? 'selected' : '' ?>>Yok</option>
                                                </select>
                                                <button type="submit" name="grup_guncelle" style="background:#28a745; color:#fff; border:none; padding:6px 10px; border-radius:6px; cursor:pointer;">Kaydet</button>
                                            </form>
                                        </td>
                                        <td style="display:flex; gap:10px;">
                                            <form action="" method="POST" onsubmit="return confirm('Bu öğrencinin şifresini öğrenci numarasına sıfırlamak istediğinize emin misiniz?');">
                                                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                                <input type="hidden" name="sifirlanacak_id" value="<?= $o['id'] ?>">
                                                <button type="submit" name="sifre_sifirla" style="background:none; border:none; color:#4da6ff; cursor:pointer; text-decoration:underline;">Şifreyi Sıfırla</button>
                                            </form>
                                            <form action="" method="POST" onsubmit="return confirm('Bu öğrenciyi silmek istediğinize emin misiniz? Bu öğrenciye ait tüm oturum ve değerlendirme kayıtları da silinecektir.');">
                                                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                                <input type="hidden" name="silinecek_ogrenci_id" value="<?= $o['id'] ?>">
                                                <button type="submit" name="ogrenci_sil" style="background:none; border:none; color:#ff6b6b; cursor:pointer; text-decoration:underline;">Sil</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <tr id="ogrenciYokSatiri" style="display:none;">
                                    <td colspan="4" style="text-align:center; color:#888;">Kayıt bulunamadı.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="panel-box">
                    <div class="accordion-header <?= $ogretmen_paneli_ac ? 'active' : '' ?>" onclick="this.classList.toggle('active'); var b=this.nextElementSibling; b.style.display = (b.style.display==='block') ? 'none' : 'block'; b.classList.toggle('active');">
                        <span>⚙ Öğretim Üyeleri</span>
                        <span style="font-size:12px; color:#aaa;">Genişlet ▼</span>
                    </div>
                    <div class="accordion-body <?= $ogretmen_paneli_ac ? 'active' : '' ?>" style="display: <?= $ogretmen_paneli_ac ? 'block' : 'none' ?>;">
                        <p style="color:#888; font-size:13px; margin-bottom:15px;">Yeni eklenen hesap sizinle aynı tam yetkiye sahip olur. Yalnızca güvendiğiniz kişiler için kullanın.</p>
                        <form action="" method="POST" style="display:flex; gap:10px; flex-wrap:wrap; margin-bottom:20px;" onsubmit="return confirm('Bu kişiye tam yönetici yetkisi vereceksiniz, emin misiniz?');">
                            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                            <input type="text" name="yeni_ogretmen_ad" placeholder="Ad Soyad" required style="flex:2; min-width:160px; padding:10px; background:rgba(0,0,0,0.5); border:1px solid #444; color:#fff; border-radius:8px;">
                            <button type="submit" name="ogretmen_ekle" class="btn-neon" style="flex:0 0 auto; margin-top:0;">Öğretmen Ekle (Giriş No Otomatik Üretilir)</button>
                        </form>

                        <div style="border-top:1px solid #333; padding-top:15px;">
                            <div style="color:#888; font-size:13px; margin-bottom:10px;">Kayıtlı Öğretim Üyeleri (giriş numaraları)</div>
                            <table>
                                <tr><th>Ad Soyad</th><th>Giriş No</th><th></th></tr>
                                <?php foreach ($ogretmenler as $og): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($og['ad_soyad']) ?></td>
                                        <td><?= htmlspecialchars($og['ogr_no']) ?></td>
                                        <td>
                                            <?php if ((int)$og['id'] !== (int)$_SESSION['kullanici_id']): ?>
                                            <form action="" method="POST" onsubmit="return confirm('Bu öğretim üyesini silmek istediğinize emin misiniz?');" style="margin:0;">
                                                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                                <input type="hidden" name="silinecek_ogretmen_id" value="<?= $og['id'] ?>">
                                                <button type="submit" name="ogretmen_sil" style="background:none; border:none; color:#ff6b6b; cursor:pointer; text-decoration:underline; font-size:12px;">Sil</button>
                                            </form>
                                            <?php else: ?>
                                                <span style="color:#888; font-size:12px;">(Siz)</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function openTab(evt, tabName) {
            var i, tabcontent, tablinks;
            tabcontent = document.getElementsByClassName("tab-content");
            for (i = 0; i < tabcontent.length; i++) {
                tabcontent[i].style.display = "none";
                tabcontent[i].classList.remove("active");
            }
            tablinks = document.getElementsByClassName("tab-btn");
            for (i = 0; i < tablinks.length; i++) {
                tablinks[i].classList.remove("active");
            }
            document.getElementById(tabName).style.display = "block";
            document.getElementById(tabName).classList.add("active");
            evt.currentTarget.classList.add("active");
        }

        function toggleSidebar() {
            var sidebar = document.querySelector('.sidebar');
            sidebar.classList.toggle('collapsed');
            sidebar.classList.toggle('open');

            var overlay = document.querySelector('.mobile-overlay');
            if (overlay) overlay.classList.toggle('active');
        }

        function ogrenciAra() {
            var girdi = document.getElementById("ogrenciArama").value.toLocaleLowerCase('tr-TR');
            var satirlar = document.getElementById("ogrenciTabloGovde").getElementsByTagName("tr");
            var goruntulenen = 0;
            for (var i = 0; i < satirlar.length; i++) {
                if (satirlar[i].id === "ogrenciYokSatiri") continue;
                var metin = satirlar[i].textContent.toLocaleLowerCase('tr-TR');
                var uyuyor = metin.indexOf(girdi) > -1;
                satirlar[i].style.display = uyuyor ? "" : "none";
                if (uyuyor) goruntulenen++;
            }
            document.getElementById("ogrenciYokSatiri").style.display = goruntulenen === 0 ? "" : "none";
        }
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById("ogrenciArama").addEventListener('keyup', function(e) {
                if (e.key === 'Enter') ogrenciAra();
            });
        });

        window.addEventListener('load', function() {
            var mesajKutusu = document.querySelector('.content-area > .alert-success, .content-area > .alert-error');
            if (mesajKutusu) {
                mesajKutusu.scrollIntoView({ behavior: 'smooth', block: 'start' });
                sessionStorage.removeItem('hocaPanelScroll');
                return;
            }
            var scrollPos = sessionStorage.getItem('hocaPanelScroll');
            if (scrollPos) {
                document.querySelector('.content-area').scrollTo(0, scrollPos);
                sessionStorage.removeItem('hocaPanelScroll');
            }
        });
        window.addEventListener('beforeunload', function() {
            var contentArea = document.querySelector('.content-area');
            if (contentArea) sessionStorage.setItem('hocaPanelScroll', contentArea.scrollTop);
        });
    </script>
</body>

</html>