# Integrasi WhatsApp Fonnte dengan WordPress

Panduan lengkap untuk mengintegrasikan WhatsApp Fonnte ke dalam WordPress menggunakan Gravity Forms.

**Kata Kunci:** Fonnte, WhatsApp API, WordPress, Gravity Forms, Integrasi WhatsApp, Otomasi Pesan, Drip Campaign, WP-Cron, Scheduled Task, PHP, WordPress Plugin, Automated Marketing, Customer Engagement

## 📋 Daftar Isi

1. [Konfigurasi fonnte-wa.php](#konfigurasi-fonnte-waphp)
2. [Upload ke Theme](#upload-ke-theme)
3. [Daftar di functions.php](#daftar-di-functionsphp)
4. [Cara Kerja](#cara-kerja)

---

## 🔧 Konfigurasi fonnte-wa.php

Sebelum upload, siapkan file `fonnte-wa.php` sesuai kebutuhan Anda:

### 1. **Ganti Token Fonnte**

Buka `fonnte-wa.php` dan cari baris ini:

```php
$token = 'TOKEN_FONNTE'; //Kalau ganti account token nya ganti ya
```

Ganti `TOKEN_FONNTE` dengan token Fonnte Anda yang sebenarnya. Token ini didapatkan dari dashboard Fonnte API Anda.

### 2. **Sesuaikan Nama Form (Opsional)**

Sistem sekarang menggunakan nama form untuk lookup otomatis. Secara default, fungsi `process_wa_daily_drip()` sudah menggunakan form bernama `'User Registration'`.

Jika form Anda memiliki nama berbeda, tidak perlu mengubah `fonnte-wa.php`. Yang perlu disesuaikan adalah:
- Di `functions.php`, ubah parameter saat memanggil fungsi:

```php
// Jika form Anda bernama 'User Registration' (default)
add_action('run_wa_daily_drip_event', 'process_wa_daily_drip');

// Jika form Anda bernama 'Contact Information'
add_action('run_wa_daily_drip_event', function() {
    process_wa_daily_drip('Contact Information');
});
```

Atau di `fonnte-wa.php` pada fungsi `process_wa_daily_drip($form_name = 'User Registration')`, ubah parameter default jika ingin mengganti form yang digunakan.

### 3. **Sesuaikan Handler Form**

Di fungsi `wa_integration_dispatcher()`, sesuaikan `case` dengan nama form Anda:

```php
switch ($form_title) {
    case 'User Registration':  // ← Ubah ke nama form Anda
        handle_user_registration($entry, $form);
        break;
}
```

### 4. **Sesuaikan Label Field**

Di fungsi `handle_user_registration()`, pastikan label field sesuai dengan Gravity Forms Anda:

```php
$name = get_val_by_label($entry, $form, 'Name');      // Ubah sesuai label di form
$phone = get_val_by_label($entry, $form, 'Phone');    // Ubah sesuai label di form
```

### 5. **Sesuaikan Pesan WhatsApp**

Di fungsi `get_drip_message_template()`, ubah template pesan sesuai kebutuhan:

```php
$templates = [
    1 => "Hai {$name}! Terima kasih telah mendaftar. Bagaimana kami bisa membantu Anda hari ini?",
    2 => "Selamat pagi {$name}! Hanya ingin memastikan apakah Anda memiliki pertanyaan.",
    3 => "Hari terakhir! {$name}, jangan lewatkan penawaran spesial kami."
];
```

Gunakan placeholder `{$name}` untuk menyisipkan nama otomatis dari entry.

---

## 📤 Upload ke Theme

### Langkah 1: Siapkan File

Pastikan `fonnte-wa.php` sudah dikonfigurasi sesuai kebutuhan.

### Langkah 2: Upload via FTP/Browser File Manager

**Gunakan FTP Client (seperti FileZilla):**

1. Buka FTP client dan hubungkan ke hosting Anda
2. Navigasi ke folder: `public_html/wp-content/themes/<website_anda>`
3. Upload `fonnte-wa.php` ke folder tersebut

**Atau gunakan File Manager WordPress:**

1. Masuk ke WordPress Dashboard
2. Pilih **Appearance** → **Theme Files** (jika tersedia)
3. Upload `fonnte-wa.php` ke folder theme

Setelah upload selesai, Anda akan memiliki path: `public_html/wp-content/themes/<website_anda>/fonnte-wa.php`

---

## ⚙️ Daftar di functions.php

Sekarang kita perlu mendaftarkan file `fonnte-wa.php` di `functions.php` theme.

### ⚠️ **PERINGATAN PENTING!**

**JANGAN MENIMPA/OVERWRITE file `functions.php` yang sudah ada!** Jika Anda overwrite, semua setting theme akan hilang dan website akan rusak/error.

### Langkah yang BENAR:

1. **Buka File `functions.php`**
   - Navigasi ke: `public_html/wp-content/themes/<website_anda>/functions.php`
   - Buka dengan text editor (jangan copy-paste ke tempat lain dulu)

2. **Tambahkan Include di Bagian Atas**

   Cari baris paling atas file (setelah `<?php`), dan tambahkan:

   ```php
   // Include Fonnte WhatsApp Integration
   require_once get_template_directory() . '/fonnte-wa.php';
   ```

   Contoh hasil (bagian atas saja):

   ```php
   <?php
   /**
    * Theme Functions
    */

   // Include Fonnte WhatsApp Integration
   require_once get_template_directory() . '/fonnte-wa.php';

   // ... kode-kode theme lainnya di bawah
   ```

3. **Simpan File**

   Jangan lupa save! Gunakan Ctrl+S atau Command+S.

4. **Verifikasi**

   - Buka WordPress Dashboard
   - Jika berhasil, tidak ada error
   - Cek di bagian Gravity Forms untuk memastikan form berfungsi

---

## 🚀 Cara Kerja

### Pengiriman WhatsApp Otomatis:

1. **Saat Submit Form:**
   - User submit "User Registration" form
   - Fungsi `handle_user_registration()` dijalankan
   - Pesan WhatsApp dikirim ke nomor yang terdaftar

2. **Drip Campaign 3 Hari:**
   - Setiap hari pukul 07:00 (WIB), WordPress menjalankan scheduled task
   - Sistem mencari form berdasarkan nama (menggunakan helper `get_form_id_by_name()`)
   - Mengecek semua user yang mendaftar dalam 5 hari terakhir
   - Jika belum mencapai hari ke-3, sistem mengirim pesan sesuai template
   - Status pencapaian disimpan di meta entry untuk mencegah duplikasi

### Alur Lookup Form:

```
process_wa_daily_drip('User Registration')
    ↓
get_form_id_by_name('User Registration')
    ↓
Cari form dengan nama 'User Registration' di GFAPI::get_forms()
    ↓
Return form ID jika ditemukan, atau null + error log jika tidak
```

Sistem ini lebih fleksibel karena tidak bergantung pada Form ID yang bisa berubah.

### Struktur File:

```
wp-content/
└── themes/
    └── <website_anda>/
        ├── functions.php          (Daftar fonnte-wa.php di sini)
        ├── fonnte-wa.php          (File baru yang di-upload)
        ├── style.css
        └── ... file theme lainnya
```

---

## 🔍 Troubleshooting

### WhatsApp Tidak Terkirim?

1. **Pastikan token Fonnte benar**
   - Cek di dashboard Fonnte: https://app.fonnte.com

2. **Cek nomor telepon format**
   - Format harus `62812345678` (tanpa +, tanpa 0 di awal jika pakai 62)
   - Sistem akan otomatis membersihkan karakter non-angka

3. **Cek error log WordPress**
   - Buka `wp-content/debug.log`
   - Cari pesan error terkait Fonnte

4. **Verifikasi Gravity Forms**
   - Pastikan nama form benar (cocok dengan yang di `process_wa_daily_drip()`)
   - Pastikan label field cocok dengan konfigurasi
   - Cek di WordPress Dashboard → Gravity Forms → Forms untuk melihat daftar form

### Pesan "Form tidak ditemukan"?

Jika melihat error: `Form dengan nama 'X' tidak ditemukan!`
- Periksa spelling nama form di WordPress Dashboard
- Pastikan nama form yang digunakan di `process_wa_daily_drip()` atau `functions.php` **cocok persis** dengan nama form di Gravity Forms
- Matching adalah case-insensitive, tapi spasi harus sama

### Scheduled Task Tidak Jalan?

1. **Pastikan WordPress Cron aktif**
   - Beberapa hosting menonaktifkan WP-Cron
   - Hubungi hosting support untuk mengaktifkannya

2. **Cek timezone WordPress**
   - Settings → General → Timezone

---

## ✅ Checklist Setup

- [ ] Token Fonnte sudah diisi di `fonnte-wa.php`
- [ ] Nama form sudah disesuaikan (atau gunakan default 'User Registration')
- [ ] Handler form dan label field sudah disesuaikan
- [ ] `fonnte-wa.php` sudah diupload ke `wp-content/themes/<website_anda>/`
- [ ] `require_once` sudah ditambahkan di `functions.php` (tidak overwrite!)
- [ ] Tidak ada error di WordPress Dashboard
- [ ] Tes kirim dengan submit form

---

## ⭐ Dukung Repository Ini

Jika Anda merasa repository ini bermanfaat dan membantu meningkatkan produktivitas website Anda, silakan berikan **⭐ Star** di GitHub!

Dengan memberikan star, Anda:
- Membantu project ini menjadi lebih terlihat oleh developer lain
- Menunjukkan apresiasi untuk kerja keras tim development
- Mendorong kami untuk terus mengembangkan fitur-fitur baru dan perbaikan
- Berkontribusi pada ekosistem WordPress dan Fonnte yang lebih baik

**[Klik di sini untuk memberikan Star](https://github.com)** 🌟

Terima kasih atas dukungan Anda!
Salam Hormat - Constantinus Satrio

---

## 📞 Support

Jika ada pertanyaan, hubungi:
- Team Development
- Atau cek dokumentasi Fonnte: https://docs.fonnte.com

---

**Last Updated:** 24 March 2026
