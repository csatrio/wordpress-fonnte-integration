<?php

// ------------ FUNGSI HELPER UMUM ------------

/**
 * Fungsi Untuk Memanggil Fonnte
 * Bisa dipanggil dari mana saja
 */
function send_to_fonnte_api($target, $message)
{
    $token = 'TOKEN_FONNTE'; //Kalau ganti account token nya ganti ya

    // Regex pembersihan nomor telepon (hapus selain angka)
    $target = preg_replace('/[^0-9]/', '', $target);

    $response = wp_remote_post('https://api.fonnte.com/send', [
        'method'    => 'POST',
        'timeout'   => 45,
        'headers'   => [ 'Authorization' => $token ],
        'body'      => [
            'target'      => $target,
            'message'     => $message,
            'countryCode' => '62',
            'duration'    => 2, // 2026 update: simulasi 'typing' untuk 2 detik (jangan diblokir)
        ],
    ]);

    return $response;
}

// ------------ FUNGSI GRAVITY FORM ------------
/**
 * Helper function untuk mencari Form ID berdasarkan nama form.
 */
function get_form_id_by_name($form_name)
{
    $forms = GFAPI::get_forms();

    foreach ($forms as $form) {
        if (trim(strtolower($form['title'])) === trim(strtolower($form_name))) {
            return $form['id'];
        }
    }

    error_log("Form dengan nama '{$form_name}' tidak ditemukan!");
    return null;
}

/**
 * Helper function untuk mengambil data Gravity Forms berdasarkan labelnya
 */
function get_val_by_label($entry, $form, $target_label)
{
    if (! isset($form['fields'])) {
        return '';
    }

    foreach ($form['fields'] as $field) {
        // Cocokkan label (normalkan keduanya ke huruf kecil + dipotong)
        if (trim(strtolower($field->label)) === trim(strtolower($target_label))) {
            return rgar($entry, (string) $field->id);
        }
    }
    error_log("Memproses Form: " . $form_title);
    error_log("Field: " . $target_label . " tidak ditemukan!");
    return '';
}


/**
 * Dispatcher CATCH-ALL HANDLER
 * Dipanggil setiap ada yang submit Gravity Forms di website.
 */
add_action('gform_after_submission', 'wa_integration_dispatcher', 10, 2);
function wa_integration_dispatcher($entry, $form)
{
    // ambil form title nya
    $form_title = rgar($form, 'title');

    // Logika dispatching berdasarkan pada Judul Form
    // Memanggil fungsi handler kamu berdasarkan nama form nya.
    switch ($form_title) {

        case 'User Registration':
            handle_user_registration($entry, $form);
            break;

        default:
            // opsional, jika seluruh form harus ada handlernya
            // error_log( "Tidak ada WA handler untuk: " . $form_title );
            break;
    }
}

// --- Handler Spesifik Per Nama Form --
function handle_user_registration($entry, $form)
{
    // Ambil data yang diperlukan dari form menggunakan helper get_val_by_label
    $name = get_val_by_label($entry, $form, 'Name');
    $phone = get_val_by_label($entry, $form, 'Phone');

    // Format pesan WA
    $message = "Halo " . $name . "! Terima kasih sudah mendaftar. Kami akan segera menghubungi Anda.";

    // Kirim ke Fonnte API
    $response = send_to_fonnte_api($phone, $message);

    // Log response untuk debug
    if (is_wp_error($response)) {
        error_log("Error mengirim WA: " . $response->get_error_message());
    } else {
        error_log("WA berhasil dikirim ke: " . $phone);
    }

}

// ------------ FUNGSI UNTUK WHATSAPP CLIENT HINGGA 3 HARI SETELAH REGISTRASI ------------

/**
 * Pemroses Pengiriman Bergilir 3 Hari
 */
function process_wa_daily_drip($form_name = 'User Registration')
{
    // 1. Konfigurasi - Tentukan nama form yang akan diproses
    $form_id = get_form_id_by_name($form_name);

    if (!$form_id) {
        error_log("Proses pengiriman dibatalkan: Form '{$form_name}' tidak ditemukan.");
        return;
    }

    $form = GFAPI::get_form($form_id);

    // 2. Hanya ambil entri dari 5 hari terakhir (Optimasi performa)
    $search_criteria = [
        'start_date' => date('Y-m-d', strtotime('-5 days')),
        'status'     => 'active',
    ];

    $entries = GFAPI::get_entries($form_id, $search_criteria);

    foreach ($entries as $entry) {
        $entry_id = $entry['id'];

        // Hitung hari sejak pendaftaran
        $reg_date = new DateTime($entry['date_created']);
        $now      = new DateTime();
        $interval = $reg_date->diff($now);
        $days_diff = $interval->days + 1; // Day 1, Day 2, Day 3

        if ($days_diff > 3) {
            continue;
        } // Di luar jendela 3 hari

        // CEK STATE: Apakah hari ini sudah dikirim?
        $last_sent = gform_get_meta($entry_id, 'wa_drip_last_sent_day');

        if ((int)$last_sent >= $days_diff) {
            continue; // Pesan hari ini sudah dikirim (atau lebih baru)
        }

        // 3. Ambil Data dan Kirim
        $phone = get_val_by_label($entry, $form, 'Phone');
        $name  = get_val_by_label($entry, $form, 'Name');

        if (empty($phone)) {
            continue;
        }

        // Pilih template
        $message = get_drip_message_template($days_diff, $name);

        // Kirim ke Fonnte
        dispatch_wa_to_fonnte($phone, $message);

        // 4. PERBARUI STATE: Catat bahwa kami mengirim Hari X
        gform_update_meta($entry_id, 'wa_drip_last_sent_day', $days_diff);
    }
}

/**
 * Mengambil template pesan WA yang dipersonalisasi berdasarkan hari ke-berapa
 * dalam siklus pengiriman 3 hari. HARAP DISESUAIKAN ISI PESANNYA SESUAI KEBUTUHAN ANDA!
 */
function get_drip_message_template($day, $name)
{
    $templates = [
        1 => "Hai {$name}! Terima kasih telah mendaftar. Bagaimana kami bisa membantu Anda hari ini?",
        2 => "Selamat pagi {$name}! Hanya ingin memastikan apakah Anda memiliki pertanyaan.",
        3 => "Hari terakhir! {$name}, jangan lewatkan penawaran spesial kami."
    ];
    return isset($templates[$day]) ? $templates[$day] : '';
}
