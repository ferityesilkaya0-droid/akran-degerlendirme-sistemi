# Öğrenci Akran Değerlendirme Sistemi

Sınıf içi sunumlarda öğrencilerin birbirini **puanlayıp yazılı geri bildirim verdiği**, öğretim görevlisinin de oturumları yönetip sonuçları raporlayabildiği PHP + MySQL web uygulaması. "Marmara BYS" adıyla, BÖTE Mikroöğretim Modülü olarak geliştirildi.

## Ne yapar?

Bir öğrenci sunum yaparken öğretim görevlisi bir **değerlendirme oturumu** başlatır. Ekranda 4 haneli bir oturum kodu belirir. Sunumu izleyen aynı gruptaki öğrenciler bu kodla oturuma girer, kriterlere puan verir ve geri bildirim yazar. Sunumu yapan öğrenci kendini de değerlendirir. Oturum bitince sonuçlar kriter bazında özetlenir.

### Öğretim görevlisi paneli
- CSV ile toplu öğrenci yükleme (format: `İsim Soyisim ; Öğrenci No ; Grup`) ve manuel öğrenci ekleme, silme, grup değiştirme
- Oturum başlatma, bitirme ve silme
- Grup bazında hedef görev (değerlendirme) kotası belirleme
- Öğretim görevlisi hesaplarını yönetme

### Öğrenci paneli
- Öğrenci numarasıyla giriş, ilk girişte zorunlu şifre değiştirme
- Aktif oturuma oturum kodu (PIN) ile katılma
- Kriter bazlı puanlama ve üç alanlı yazılı geri bildirim (güçlü yönler, eksik yönler, öneri)
- Kendi görev kotasının ilerlemesini görme

### Sonuçlar ve raporlama
- Oturum bazında kriter ortalamaları ve grafikler (Chart.js)
- Katılım raporu ve geri bildirim raporu için CSV dışa aktarma (Excel'de doğrudan açılır)
- Oturum detay sayfası: tek tek değerlendirmeler, öz değerlendirme ile akran değerlendirmesinin ayrımı

## Teknolojiler

- PHP (PDO ile MySQL erişimi)
- MySQL / MariaDB (`utf8mb4`)
- HTML, CSS, JavaScript
- Chart.js (CDN)
- Geliştirme ortamı: XAMPP (localhost), PHP 8.2 ve MariaDB 10.4

## Güvenlik önlemleri

Projede, bir web uygulamasında temel güvenlik pratiklerini uygulamak hedeflendi:

- **SQL Injection:** Tüm sorgular PDO hazırlanmış ifadelerle (`prepare` / `execute`) çalışır, `ATTR_EMULATE_PREPARES` kapalıdır
- **Şifreler:** `password_hash` (bcrypt) ile saklanır, `password_verify` ile doğrulanır
- **CSRF:** Form gönderimlerinde oturuma bağlı token (`random_bytes`, `hash_equals` ile doğrulama)
- **Oturum güvenliği:** Girişte `session_regenerate_id`, çıkışta oturumun tamamen yok edilmesi ve önbelleğe almayı engelleyen başlıklar
- **Kaba kuvvet koruması:** Girişte ve oturum kodu girişinde 5 yanlış denemeden sonra 60 saniyelik kilit
- **İlk giriş koruması:** Şifresi hâlâ öğrenci numarasıyla aynı olan kullanıcı şifre değiştirmeye yönlendirilir
- **Çift oy engeli:** Aynı oturum için veritabanı kaydı ve cihaz çerezi kontrolü, ayrıca veritabanında `(oturum_id, degerlendiren_id)` üzerinde `UNIQUE` kısıtı
- **Sunucu tarafı doğrulama:** Gönderilen puanların kriter sayısıyla ve geçerli kriter kimlikleriyle tutarlılığı sunucuda kontrol edilir
- **Erişim kontrolü:** Öğrenci paneline yalnızca yerel ağdan (localhost ve `192.168.x.x`) erişilebilir, sayfalar rol kontrolüyle korunur
- **XSS:** Kullanıcıdan gelen metinler çıktıda `htmlspecialchars` ile kaçırılır
- **Günlükler:** Hatalar ekrana basılmaz, `logs/` klasörüne yazılır ve klasör `.htaccess` ile web erişimine kapatılmıştır

## Kurulum

1. Projeyi XAMPP'in `htdocs` klasörüne kopyalayın
2. phpMyAdmin'de `akran_db` adında, `utf8mb4_general_ci` karşılaştırmalı bir veritabanı oluşturun
3. `database/schema.sql` dosyasını bu veritabanına içe aktarın. Dosya 6 tabloyu (`kullanicilar`, `oturumlar`, `degerlendirmeler`, `puanlar`, `kriterler`, `grup_ayarlari`) ve varsayılan değerlendirme kriterleriyle grup ayarlarını oluşturur, kullanıcı verisi içermez
4. Gerekirse `includes/db.php` içindeki bağlantı bilgilerini düzenleyin (varsayılan: `localhost`, `root`, boş şifre, yalnızca yerel geliştirme içindir)
5. İlk öğretim görevlisi hesabını oluşturun. Önce şifrenin bcrypt hash'ini üretin:
   ```
   php -r "echo password_hash('BURAYA_SIFRE', PASSWORD_BCRYPT);"
   ```
   Sonra phpMyAdmin'de SQL sekmesinde çalıştırın (`rol = 1` öğretim görevlisidir):
   ```sql
   INSERT INTO kullanicilar (ad_soyad, ogr_no, sifre, rol, grup)
   VALUES ('Ad Soyad', 'hoca01', 'ÜRETİLEN_HASH', 1, 'Yok');
   ```
6. İsterseniz giriş ekranı için `assets/img/giris.jpg` adıyla kendi arka plan görselinizi ekleyin (yoksa koyu degrade arka plan kullanılır)
7. Tarayıcıdan `http://localhost/<klasör-adı>/` adresini açın ve `hoca01` ile giriş yapın

> Canlı bir sunucuda kullanmadan önce `root` kullanıcısı yerine yetkileri kısıtlı ayrı bir veritabanı kullanıcısı tanımlayın ve şifre belirleyin.

## Bilinen sınırlamalar

- Oturum kodu `rand()` ile üretiliyor, güvenlik açısından `random_int()` daha uygun olur
- Yerel ağ kısıtı `192.168.` ön ekine göre yapılıyor, kurumsal ağlarda farklı IP aralıkları gerekebilir
- Çift oy kontrolü çerez tabanlı olduğu için çerezler temizlenirse aşılabilir, asıl güvence veritabanı kaydıdır
- Otomatik test bulunmuyor

## Geliştirme süreci

Proje, yaklaşık beş gün boyunca yapay zekâ asistanı **Claude** (Anthropic) ile birlikte geliştirildi.

## Geliştirici

**Ferit Yeşilkaya**
Marmara Üniversitesi Bilgisayar ve Öğretim Teknolojileri Eğitimi (BÖTE) mezunu, Anadolu Üniversitesi AÖF İşletme öğrencisi. Ağ, Linux ve siber güvenlik temelleri üzerine çalışıyorum.

- GitHub: [ferityesilkaya0-droid](https://github.com/ferityesilkaya0-droid)
- LinkedIn: [linkedin.com/in/ferity](https://www.linkedin.com/in/ferity)

## Lisans

Lisans henüz belirlenmedi.
