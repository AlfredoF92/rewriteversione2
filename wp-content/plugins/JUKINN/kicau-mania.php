<?php
/**
 * Plugin Name: Kicau Mania Plugin
 * Description: Plugin sederhana tema burung kicau untuk semua versi WordPress
 * Version: 1.0
 * Author: Zedd
 */

if (!defined('ABSPATH')) {
    exit;
}

// Fungsi utama
function kicau_mania_content($content) {
    if (is_single()) {
        $kicau_box = '
        <div style="margin-top:20px;padding:15px;border:2px solid #4CAF50;border-radius:10px;background:#f9fff9;">
            <h3 style="margin:0;color:#2e7d32;">🐦 Kicau Mania</h3>
            <p style="margin:5px 0 0;">Nikmati suara burung terbaik setiap hari! Rawat, latih, dan cintai burung kicauanmu.</p>
        </div>
        ';
        $content .= $kicau_box;
    }
    return $content;
}
add_filter('the_content', 'kicau_mania_content');


// Tambah CSS sederhana (aman untuk semua WP)
function kicau_mania_style() {
    echo "<style>
    .kicau-mania-box:hover {
        transform: scale(1.02);
        transition: 0.3s;
    }
    </style>";
}
add_action('wp_head', 'kicau_mania_style');