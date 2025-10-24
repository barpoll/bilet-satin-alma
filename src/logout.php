<?php
// logout.php

session_start(); // Oturumu başlat
session_unset(); // Tüm oturum değişkenlerini sil
session_destroy(); // Oturumu sonlandır

// Kullanıcıyı giriş sayfasına veya ana sayfaya yönlendir
header("Location: login.php"); 
exit();
?>