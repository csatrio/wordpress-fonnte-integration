<?php

// Daftarkan jadwal jika belum ada
if (! wp_next_scheduled('run_wa_daily_drip_event')) {
    // Jadwalkan setiap pukul 7 pagi
    $first_run = strtotime('tomorrow 07:00:00');
    wp_schedule_event($first_run, 'daily', 'run_wa_daily_drip_event');
}

// Kaitkan timer run_wa_daily_drip_event ke fungsi process_wa_daily_drip di fonnte-wa.php
add_action('run_wa_daily_drip_event', 'process_wa_daily_drip');
