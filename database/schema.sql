-- Öğrenci Akran Değerlendirme Sistemi - veritabanı şeması
-- Yalnızca tablo yapısı ve varsayılan kriter/grup ayarları içerir, kullanıcı veya değerlendirme verisi yoktur.
-- Kullanım: akran_db adlı utf8mb4 bir veritabanı oluşturup bu dosyayı içe aktarın.

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Veritabanı: `akran_db`
--

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `degerlendirmeler`
--

CREATE TABLE `degerlendirmeler` (
  `id` int(11) NOT NULL,
  `oturum_id` int(11) NOT NULL,
  `degerlendiren_id` int(11) NOT NULL,
  `guclu_yonler` text NOT NULL,
  `eksik_yonler` text NOT NULL,
  `oneri_metni` text NOT NULL,
  `oz_degerlendirme_mi` tinyint(1) DEFAULT 0,
  `tarih` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `grup_ayarlari`
--

CREATE TABLE `grup_ayarlari` (
  `id` int(11) NOT NULL,
  `grup` varchar(20) NOT NULL,
  `hedef_gorev_sayisi` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Tablo döküm verisi `grup_ayarlari`
--

INSERT INTO `grup_ayarlari` (`id`, `grup`, `hedef_gorev_sayisi`) VALUES
(1, 'Sabah', 18),
(2, 'Öğle', 50);

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `kriterler`
--

CREATE TABLE `kriterler` (
  `id` int(11) NOT NULL,
  `kriter_metni` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Tablo döküm verisi `kriterler`
--

INSERT INTO `kriterler` (`id`, `kriter_metni`) VALUES
(1, 'Ses tonunu ve beden dilini etkili kullandı mı?'),
(2, 'Ders materyalleri konuya uygun ve ilgi çekici miydi?'),
(3, 'Zaman yönetimini başarılı bir şekilde sağladı mı?'),
(4, 'Öğrencilerle etkili iletişim kurabildi mi?');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `kullanicilar`
--

CREATE TABLE `kullanicilar` (
  `id` int(11) NOT NULL,
  `ad_soyad` varchar(100) NOT NULL,
  `ogr_no` varchar(20) NOT NULL,
  `sifre` varchar(255) NOT NULL,
  `rol` tinyint(1) NOT NULL DEFAULT 0,
  `grup` enum('Sabah','Öğle','Yok') NOT NULL DEFAULT 'Yok',
  `olusturulma_tarihi` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `oturumlar`
--

CREATE TABLE `oturumlar` (
  `id` int(11) NOT NULL,
  `hoca_id` int(11) NOT NULL,
  `sunan_ogrenci_id` int(11) NOT NULL,
  `durum` tinyint(1) NOT NULL DEFAULT 1,
  `oturum_kodu` varchar(10) DEFAULT NULL,
  `tarih` timestamp NULL DEFAULT current_timestamp(),
  `bitis_tarihi` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `puanlar`
--

CREATE TABLE `puanlar` (
  `id` int(11) NOT NULL,
  `degerlendirme_id` int(11) NOT NULL,
  `kriter_id` int(11) NOT NULL,
  `puan` tinyint(1) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


--
-- Dökümü yapılmış tablolar için indeksler
--

--
-- Tablo için indeksler `degerlendirmeler`
--
ALTER TABLE `degerlendirmeler`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tek_oy_kurali` (`oturum_id`,`degerlendiren_id`),
  ADD KEY `degerlendiren_id` (`degerlendiren_id`);

--
-- Tablo için indeksler `grup_ayarlari`
--
ALTER TABLE `grup_ayarlari`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `grup` (`grup`);

--
-- Tablo için indeksler `kriterler`
--
ALTER TABLE `kriterler`
  ADD PRIMARY KEY (`id`);

--
-- Tablo için indeksler `kullanicilar`
--
ALTER TABLE `kullanicilar`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ogr_no` (`ogr_no`);

--
-- Tablo için indeksler `oturumlar`
--
ALTER TABLE `oturumlar`
  ADD PRIMARY KEY (`id`),
  ADD KEY `hoca_id` (`hoca_id`),
  ADD KEY `sunan_ogrenci_id` (`sunan_ogrenci_id`);

--
-- Tablo için indeksler `puanlar`
--
ALTER TABLE `puanlar`
  ADD PRIMARY KEY (`id`),
  ADD KEY `degerlendirme_id` (`degerlendirme_id`),
  ADD KEY `kriter_id` (`kriter_id`);

--
-- Dökümü yapılmış tablolar için AUTO_INCREMENT değeri
--

--
-- Tablo için AUTO_INCREMENT değeri `degerlendirmeler`
--
ALTER TABLE `degerlendirmeler`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- Tablo için AUTO_INCREMENT değeri `grup_ayarlari`
--
ALTER TABLE `grup_ayarlari`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Tablo için AUTO_INCREMENT değeri `kriterler`
--
ALTER TABLE `kriterler`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Tablo için AUTO_INCREMENT değeri `kullanicilar`
--
ALTER TABLE `kullanicilar`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- Tablo için AUTO_INCREMENT değeri `oturumlar`
--
ALTER TABLE `oturumlar`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- Tablo için AUTO_INCREMENT değeri `puanlar`
--
ALTER TABLE `puanlar`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- Dökümü yapılmış tablolar için kısıtlamalar
--

--
-- Tablo kısıtlamaları `degerlendirmeler`
--
ALTER TABLE `degerlendirmeler`
  ADD CONSTRAINT `degerlendirmeler_ibfk_1` FOREIGN KEY (`oturum_id`) REFERENCES `oturumlar` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `degerlendirmeler_ibfk_2` FOREIGN KEY (`degerlendiren_id`) REFERENCES `kullanicilar` (`id`) ON DELETE CASCADE;

--
-- Tablo kısıtlamaları `oturumlar`
--
ALTER TABLE `oturumlar`
  ADD CONSTRAINT `oturumlar_ibfk_1` FOREIGN KEY (`hoca_id`) REFERENCES `kullanicilar` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `oturumlar_ibfk_2` FOREIGN KEY (`sunan_ogrenci_id`) REFERENCES `kullanicilar` (`id`) ON DELETE CASCADE;

--
-- Tablo kısıtlamaları `puanlar`
--
ALTER TABLE `puanlar`
  ADD CONSTRAINT `puanlar_ibfk_1` FOREIGN KEY (`degerlendirme_id`) REFERENCES `degerlendirmeler` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `puanlar_ibfk_2` FOREIGN KEY (`kriter_id`) REFERENCES `kriterler` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
