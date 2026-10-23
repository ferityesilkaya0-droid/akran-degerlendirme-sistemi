<?php
session_start();

// Tüm oturum değişkenlerini boşalt
$_SESSION = array();

// Oturumu tamamen imha et
session_destroy();

// SİBER GÜVENLİK: Çıkış yaptıktan sonra "Geri" tuşuyla sayfaya dönülmesini kesin olarak engeller
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Giriş ekranına yönlendir
header("Location: index.php");
exit;
?>