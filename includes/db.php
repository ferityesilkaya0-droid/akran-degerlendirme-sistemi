<?php
// Geliştirme aşaması için hata raporlama
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
$log_klasoru = __DIR__ . '/../logs';
if (!is_dir($log_klasoru)) {
    mkdir($log_klasoru, 0755, true);
}
ini_set('error_log', $log_klasoru . '/hata_kayitlari.log');

// Veritabanı bağlantı bilgileri
$host = 'localhost';
$db_name = 'akran_db'; // Güncel veritabanı adımız
$kullanici = 'root';
$sifre = '';

try {
    // utf8mb4 ile Türkçe karakter ve emojileri %100 destekleyen yapı
    $db = new PDO("mysql:host=$host;dbname=$db_name;charset=utf8mb4", $kullanici, $sifre);
    
    // Hata ayıklama modu
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // SİBER GÜVENLİK: SQL Injection'ı kökten çözen ince ayar
    $db->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    
} catch (PDOException $e) {
    die("Veritabanı Bağlantı Hatası: " . $e->getMessage());
}

// ==========================================
// CSRF KORUMASI
// ==========================================
function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}
function csrf_dogrula() {
    if (!isset($_POST['csrf_token']) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("<div style='background:#0b0b0b; color:#dc3545; height:100vh; display:flex; justify-content:center; align-items:center; text-align:center; font-family:sans-serif;'><h2>GÜVENLİK İHLALİ!<br><span style='color:#aaa; font-size:16px;'>Geçersiz istek (oturum süresi dolmuş olabilir).<br>Lütfen sayfayı yenileyip tekrar deneyin.</span></h2></div>");
    }
}
?>
