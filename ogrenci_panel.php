<?php
session_start();
require_once 'includes/db.php';

if (!isset($_SESSION['kullanici_id']) || $_SESSION['rol'] != 0) {
    header("Location: index.php");
    exit;
}
if (!empty($_SESSION['sifre_degisim_zorunlu'])) {
    header("Location: sifre_degistir.php");
    exit;
}

// ==========================================
// GÜVENLİK 1: IP VE AĞ KONTROLÜ (Sadece Kampüs)
// ==========================================
$ogrenci_ip = $_SERVER['REMOTE_ADDR'];
$izin_verilen_ip = ($ogrenci_ip == '127.0.0.1' || $ogrenci_ip == '::1' || strpos($ogrenci_ip, '192.168.') === 0);

if (!$izin_verilen_ip) {
    die("<div style='background:#0b0b0b; color:#dc3545; height:100vh; display:flex; justify-content:center; align-items:center; text-align:center; font-family:sans-serif;'><h2>GÜVENLİK İHLALİ!<br><span style='color:#aaa; font-size:16px;'>Sisteme sadece kampüs ağından erişilebilir. (IP: " . htmlspecialchars($ogrenci_ip) . ")</span></h2></div>");
}

$ogrenci_id = $_SESSION['kullanici_id'];
$ogrenci_grup = $_SESSION['grup'];
$mesaj = "";
$kriterler = []; // Baştan tanımlı olsun ki hiçbir koşulda "tanımsız değişken" uyarısı çıkmasın

// Aktif Oturumu Çek
$aktif_oturum = $db->query("SELECT o.id, o.sunan_ogrenci_id, k.ad_soyad, k.grup, o.oturum_kodu FROM oturumlar o JOIN kullanicilar k ON o.sunan_ogrenci_id = k.id WHERE o.durum = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);

$form_goster = false; 
$zaten_oy_verdi_mi = false; 
$pin_dogru_mu = false;

// ==========================================
// MANTIK 1: ÖNCE VERİTABANINI KONTROL ET (KENDİ OYU VAR MI?)
// ==========================================
if ($aktif_oturum && $aktif_oturum['grup'] == $ogrenci_grup) {
    $oy_kontrol = $db->prepare("SELECT id FROM degerlendirmeler WHERE oturum_id = ? AND degerlendiren_id = ?");
    $oy_kontrol->execute([$aktif_oturum['id'], $ogrenci_id]);
    
    if ($oy_kontrol->rowCount() > 0) {
        $zaten_oy_verdi_mi = true;
    }
}

// ==========================================
// GÜVENLİK 2: KENDİ OYU "YOKSA" AMA CİHAZDA ÇEREZ "VARSA" 
// ==========================================
if ($aktif_oturum && !$zaten_oy_verdi_mi && isset($_COOKIE['oy_kullandi_' . $aktif_oturum['id']])) {
    die("<div style='background:#0b0b0b; color:#dc3545; height:100vh; display:flex; justify-content:center; align-items:center; text-align:center; font-family:sans-serif;'><h2>GÜVENLİK İHLALİ!<br><span style='color:#aaa; font-size:16px;'>Bu cihazdan mevcut oturum için zaten oy kullanılmış.<br>Başkasının yerine yoklama veremezsiniz!</span></h2></div>");
}

// ==========================================
// FORMU GÖSTERME VE PIN ONAYI
// ==========================================
if ($aktif_oturum && $aktif_oturum['grup'] == $ogrenci_grup && !$zaten_oy_verdi_mi) {
    if (isset($_SESSION['pin_onay'][$aktif_oturum['id']])) {
        $pin_dogru_mu = true; 
        $form_goster = true;
        $kriterler = $db->query("SELECT * FROM kriterler")->fetchAll(PDO::FETCH_ASSOC);
    } else {
        if (isset($_POST['pin_gonder'])) {
            csrf_dogrula();
            $deneme_anahtari = 'pin_deneme_' . $aktif_oturum['id'];
            $kilit_anahtari = 'pin_kilit_' . $aktif_oturum['id'];

            if (isset($_SESSION[$kilit_anahtari]) && time() < $_SESSION[$kilit_anahtari]) {
                $kalan = $_SESSION[$kilit_anahtari] - time();
                $mesaj = "<div class='alert-error'>Çok fazla yanlış deneme yaptınız. Lütfen <span id='geriSayim' class='countdown-text' data-kalan='$kalan'>$kalan</span> saniye sonra tekrar deneyin.</div>";
            } elseif (trim($_POST['girilen_pin']) == $aktif_oturum['oturum_kodu']) {
                $_SESSION['pin_onay'][$aktif_oturum['id']] = true;
                unset($_SESSION[$deneme_anahtari], $_SESSION[$kilit_anahtari]);
                header("Location: ogrenci_panel.php"); exit;
            } else {
                $_SESSION[$deneme_anahtari] = ($_SESSION[$deneme_anahtari] ?? 0) + 1;
                if ($_SESSION[$deneme_anahtari] >= 5) {
                    $_SESSION[$kilit_anahtari] = time() + 60;
                    $mesaj = "<div class='alert-error'>5 kez yanlış kod girdiniz. 60 saniye bekleyin.</div>";
                } else {
                    $mesaj = "<div class='alert-error'>Oturum kodu hatalı! Tahtayı kontrol ediniz.</div>";
                }
            }
        }
    }
}

// ==========================================
// FORM GÖNDERİMİ VE ÇEREZ MÜHÜRLEME
// ==========================================
if (isset($_POST['degerlendirme_gonder']) && $form_goster) {
    csrf_dogrula();
    $guclu = trim($_POST['guclu_yonler']); 
    $eksik = trim($_POST['eksik_yonler']); 
    $oneri = trim($_POST['oneri_metni']);
    $oz_degerlendirme = ($ogrenci_id == $aktif_oturum['sunan_ogrenci_id']) ? 1 : 0;

    // ==========================================
    // GÜVENLİK 4: SUNUCU TARAFI PUAN DOĞRULAMA (ZG8 / İş Kuralı 21)
    // ==========================================
    $form_hatali = false;
    $gecerli_kriter_id = array_column($kriterler, 'id');
    $gonderilen_puanlar = (isset($_POST['puanlar']) && is_array($_POST['puanlar'])) ? $_POST['puanlar'] : [];

    if (empty($guclu) || empty($eksik) || empty($oneri)) {
        $form_hatali = true;
    } elseif (count($gonderilen_puanlar) != count($gecerli_kriter_id)) {
        $form_hatali = true;
    } else {
        foreach ($gecerli_kriter_id as $kid) {
            $p = $gonderilen_puanlar[$kid] ?? null;
            if ($p === null || !ctype_digit((string)$p) || (int)$p < 1 || (int)$p > 5) {
                $form_hatali = true;
                break;
            }
        }
    }

    if ($form_hatali) {
        $mesaj = "<div class='alert-error'>Form eksik veya geçersiz bilgi içeriyor. Lütfen tüm ölçütleri (1-5 arası) puanlayıp metin alanlarını doldurun.</div>";
    } else {
        try {
            $db->beginTransaction();
            $db->prepare("INSERT INTO degerlendirmeler (oturum_id, degerlendiren_id, guclu_yonler, eksik_yonler, oneri_metni, oz_degerlendirme_mi) VALUES (?, ?, ?, ?, ?, ?)")->execute([$aktif_oturum['id'], $ogrenci_id, $guclu, $eksik, $oneri, $oz_degerlendirme]);
            $deg_id = $db->lastInsertId();

            $stmt_puan = $db->prepare("INSERT INTO puanlar (degerlendirme_id, kriter_id, puan) VALUES (?, ?, ?)");
            foreach ($gonderilen_puanlar as $k_id => $puan) {
                $stmt_puan->execute([$deg_id, (int)$k_id, (int)$puan]);
            }
            $db->commit();

            setcookie("oy_kullandi_" . $aktif_oturum['id'], "evet", time() + 3600, "/");

            header("Location: ogrenci_panel.php?basarili=1"); exit;
        } catch (PDOException $e) {
            $db->rollBack();
            if ($e->getCode() == 23000) {
                header("Location: ogrenci_panel.php"); exit;
            }
            $mesaj = "<div class='alert-error'>Kaydedilirken beklenmeyen bir hata oluştu. Lütfen tekrar deneyin.</div>";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Öğrenci Paneli | Akran Değerlendirme</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="ogrenci-panel">

<div class="panel-layout">
    <div class="mobile-overlay" onclick="toggleSidebar()"></div>
    <aside class="sidebar">
        <div class="sidebar-header">
            <div class="logo-area" style="font-size: 15px;">
                <svg viewBox="0 0 640 512" style="width: 25px; height: 25px;"><path d="M320 32c-8.1 0-16.1 1.4-23.7 4.1L15.8 137.4C6.3 140.9 0 149.9 0 160s6.3 19.1 15.8 22.6l57.4 20.3c-1.2 5.3-1.9 10.9-1.9 16.6L71.3 416c0 35.3 28.7 64 64 64H504.7c35.3 0 64-28.7 64-64l0-196.5c0-5.7-.7-11.3-1.9-16.6l10.2-3.6c29.1-10.3 29.1-51.5 0-61.8L343.7 36.1C336.1 33.4 328.1 32 320 32zM128 408c0-13.3 10.7-24 24-24H488c13.3 0 24 10.7 24 24c0 4.4-3.6 8-8 8H136c-4.4 0-8-3.6-8-8z"/></svg>
                <span>Öğrenci Paneli</span>
            </div>
        </div>
        <ul class="sidebar-menu">
            <li><a href="ogrenci_panel.php" class="active">Canlı Değerlendirme</a></li>
            <li><a href="sonuclar.php">Kişisel Sonuçlarım</a></li>
            <li><a href="sifre_degistir.php">Şifre İşlemleri</a></li>
        </ul>
    </aside>

    <div class="main-area">
        <header class="topbar">
            <div class="topbar-left">
                <button class="menu-toggle" onclick="toggleSidebar()">☰</button>
            </div>
            <div class="topbar-right">
                <div class="welcome-text" style="color: #888; margin-right: 20px;">Hoş Geldin, <b style="color:#fff;"><?= htmlspecialchars($_SESSION['ad_soyad']) ?></b> <span style="font-size: 12px;">[<?= htmlspecialchars($ogrenci_grup) ?>]</span></div>
                <a href="cikis.php" class="logout-btn">Çıkış Yap</a>
            </div>
        </header>

        <div class="content-area">
            <?= $mesaj ?>
            <?php if (isset($_GET['basarili'])): ?>
                <div class="alert-success" style="font-size: 15px; padding: 18px;">✅ Değerlendirmeniz başarıyla kaydedilmiştir. Teşekkür ederiz!</div>
            <?php endif; ?>

            <div style="max-width: 800px; margin: 0 auto;">
                <?php if (!$aktif_oturum): ?>
                    <div class="panel-box" style="text-align: center;">
                        <span class="status-icon">⏸</span>
                        <h3 style="color: #fff; margin-bottom: 10px;">Oturum Bulunmuyor</h3>
                        <p style="color: #888;">Şu an aktif bir değerlendirme oturumu yok.</p>
                    </div>
                <?php elseif ($aktif_oturum['grup'] != $ogrenci_grup): ?>
                    <div class="panel-box" style="text-align: center; border-color: #dc3545;">
                        <span class="status-icon" style="color: #dc3545;">⚠️</span>
                        <h3 style="color: #ff6b6b; margin-bottom: 10px;">Farklı Grup Oturumu</h3>
                        <p style="color: #aaa;">Siz bu grubu değerlendiremezsiniz.</p>
                    </div>
                <?php elseif ($zaten_oy_verdi_mi): ?>
                    <div class="panel-box" style="text-align: center; border-color: #28a745;">
                        <span class="status-icon" style="color: #28a745;">✅</span>
                        <h3 style="color: #28a745; margin-bottom: 10px;">Görev Tamamlandı</h3>
                        <p style="color: #aaa;">Akran değerlendirmesini başarıyla tamamladınız.</p>
                    </div>
                <?php elseif (!$pin_dogru_mu): ?>
                    <div class="panel-box" style="text-align: center; max-width: 500px; margin: 0 auto;">
                        <div class="panel-title" style="color: #28a745; border:none;">Canlı Oturuma Katıl</div>
                        <p style="color: #bbb; margin-bottom: 25px;">Şu an <strong><?= htmlspecialchars($aktif_oturum['ad_soyad']) ?></strong> sunum yapıyor.<br>Tahtadaki kodu giriniz.</p>
                        <form action="" method="POST">
                            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                            <input type="text" name="girilen_pin" class="pin-input" maxlength="4" placeholder="••••" autocomplete="off" required>
                            <button type="submit" name="pin_gonder" class="btn-neon">DOĞRULA VE FORMU AÇ</button>
                        </form>
                    </div>
                <?php elseif ($form_goster): ?>
                    <div class="panel-box">
                        <div class="panel-title">Canlı Değerlendirme Formu</div>
                        <div style="background: rgba(40,167,69,0.1); border: 1px solid #28a745; padding: 15px; border-radius: 8px; margin-bottom: 25px; color: #ddd; text-align: center;">
                            Değerlendirilen: <strong><?= htmlspecialchars($aktif_oturum['ad_soyad']) ?></strong> 
                            <?= ($ogrenci_id == $aktif_oturum['sunan_ogrenci_id']) ? '<br><span style="display:inline-block; margin-top:10px; background:#1b689c; color:#fff; padding:4px 12px; border-radius:4px; font-size:12px;">ÖZ DEĞERLENDİRME YAPIYORSUNUZ</span>' : '' ?>
                        </div>
                        <form action="" method="POST">
                            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                            <?php foreach ($kriterler as $kriter): ?>
                                <div class="rating-box">
                                    <div style="font-weight: 500; color: #fff;"><?= htmlspecialchars($kriter['kriter_metni']) ?></div>
                                    <div class="radio-toolbar">
                                        <?php for($i=1; $i<=5; $i++): ?>
                                            <input type="radio" id="k_<?= $kriter['id'] ?>_<?= $i ?>" name="puanlar[<?= $kriter['id'] ?>]" value="<?= $i ?>" required>
                                            <label for="k_<?= $kriter['id'] ?>_<?= $i ?>"><?= $i ?></label>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <label>Güçlü Yönler</label> <textarea name="guclu_yonler" rows="2" required></textarea>
                            <label>Geliştirilmesi Gereken Yönler</label> <textarea name="eksik_yonler" rows="2" required></textarea>
                            <label>Öneriler</label> <textarea name="oneri_metni" rows="2" required></textarea>
                            <button type="submit" name="degerlendirme_gonder" class="btn-neon" style="margin-top: 20px;">Gönder</button>
                        </form>
                    </div>
                <?php endif; ?>
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
        if(overlay) overlay.classList.toggle('active');
    }

    // Site geneli tutarlılık: kaydırma pozisyonunu hatırla
    window.addEventListener('load', function() {
        var mesajKutusu = document.querySelector('.content-area > .alert-success, .content-area > .alert-error');
        if (mesajKutusu) {
            mesajKutusu.scrollIntoView({ behavior: 'smooth', block: 'start' });
            sessionStorage.removeItem('ogrenciPanelScroll');
            return;
        }
        var scrollPos = sessionStorage.getItem('ogrenciPanelScroll');
        if (scrollPos) {
            document.querySelector('.content-area').scrollTo(0, scrollPos);
            sessionStorage.removeItem('ogrenciPanelScroll');
        }
    });
    window.addEventListener('beforeunload', function() {
        var contentArea = document.querySelector('.content-area');
        if (contentArea) sessionStorage.setItem('ogrenciPanelScroll', contentArea.scrollTop);
    });

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