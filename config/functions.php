<?php
// config/functions.php

/**
 * Deteksi dan konversi link gambar ke tag img
 */
function parseImageLinks($text) {
    // Pattern untuk mendeteksi link gambar
    $imagePattern = '/\b(https?:\/\/\S+\.(?:jpg|jpeg|gif|png|webp|bmp|svg))\b/i';
    
    // Pattern untuk markdown style image ![alt](url)
    $markdownPattern = '/!\[(.*?)\]\((https?:\/\/[^\s)]+)\)/i';
    
    // Pattern untuk link yang dibungkus [url] (BBCode style)
    $bbcodePattern = '/\[img\](https?:\/\/[^\[]+)\[\/img\]/i';
    $bbcodeWithAltPattern = '/\[img=(.*?)\](https?:\/\/[^\[]+)\[\/img\]/i';
    
    // Proses BBCode dengan alt text
    $text = preg_replace_callback($bbcodeWithAltPattern, function($matches) {
        $alt = htmlspecialchars($matches[1]);
        $url = htmlspecialchars($matches[2]);
        return '<div class="embedded-image">' .
               '<img src="' . $url . '" alt="' . $alt . '" class="forum-image" loading="lazy">' .
               '<div class="image-caption">' . $alt . '</div>' .
               '</div>';
    }, $text);
    
    // Proses BBCode biasa
    $text = preg_replace_callback($bbcodePattern, function($matches) {
        $url = htmlspecialchars($matches[1]);
        return '<div class="embedded-image">' .
               '<img src="' . $url . '" alt="Gambar" class="forum-image" loading="lazy">' .
               '</div>';
    }, $text);
    
    // Proses markdown style
    $text = preg_replace_callback($markdownPattern, function($matches) {
        $alt = htmlspecialchars($matches[1]);
        $url = htmlspecialchars($matches[2]);
        return '<div class="embedded-image">' .
               '<img src="' . $url . '" alt="' . $alt . '" class="forum-image" loading="lazy">' .
               '<div class="image-caption">' . $alt . '</div>' .
               '</div>';
    }, $text);
    
    // Proses link gambar biasa
    $text = preg_replace_callback($imagePattern, function($matches) {
        $url = htmlspecialchars($matches[1]);
        return '<div class="embedded-image">' .
               '<img src="' . $url . '" alt="Gambar" class="forum-image" loading="lazy">' .
               '<div class="image-caption"><a href="' . $url . '" target="_blank">Lihat gambar asli</a></div>' .
               '</div>';
    }, $text);
    
    return $text;
}

/**
 * Deteksi link video YouTube dan embed
 */
function parseYouTubeLinks($text) {
    $youtubePattern = '/https?:\/\/(?:www\.)?(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]+)/i';
    
    return preg_replace_callback($youtubePattern, function($matches) {
        $videoId = $matches[1];
        return '<div class="embedded-video">' .
               '<iframe width="560" height="315" src="https://www.youtube.com/embed/' . $videoId . '" ' .
               'frameborder="0" allowfullscreen loading="lazy"></iframe>' .
               '</div>';
    }, $text);
}

/**
 * Parse konten dengan semua fitur
 */
function parseContent($text) {
    // Parse gambar dulu
    $text = parseImageLinks($text);
    
    // Parse YouTube
    $text = parseYouTubeLinks($text);
    
    // Parse link biasa jadi klikable
    $linkPattern = '/(?<!href="|src=")https?:\/\/[^\s<]+/i';
    $text = preg_replace_callback($linkPattern, function($matches) {
        $url = $matches[0];
        // Cek apakah ini gambar
        if (preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $url)) {
            return $url;
        }
        return '<a href="' . $url . '" target="_blank" rel="noopener noreferrer" class="forum-link">' . $url . '</a>';
    }, $text);
    
    // Konversi newline ke <br>
    $text = nl2br($text);
    
    return $text;
}

/**
 * Ekstrak gambar pertama dari konten untuk preview thumbnail
 */
function extractFirstImage($content) {
    if (empty($content)) return null;
    
    // Cari tag img
    $pattern = '/<img[^>]+src="([^">]+)"/';
    if (preg_match($pattern, $content, $matches)) {
        return $matches[1];
    }
    
    // Cari markdown image ![]()
    $markdownPattern = '/!\[.*?\]\((https?:\/\/[^\s)]+)\)/';
    if (preg_match($markdownPattern, $content, $matches)) {
        return $matches[1];
    }
    
    // Cari BBCode [img]
    $bbcodePattern = '/\[img\](https?:\/\/[^\[]+)\[\/img\]/';
    if (preg_match($bbcodePattern, $content, $matches)) {
        return $matches[1];
    }
    
    // Cari BBCode dengan alt [img=alt]url[/img]
    $bbcodeAltPattern = '/\[img=.*?\](https?:\/\/[^\[]+)\[\/img\]/';
    if (preg_match($bbcodeAltPattern, $content, $matches)) {
        return $matches[1];
    }
    
    // Cari link gambar langsung
    $imagePattern = '/\b(https?:\/\/\S+\.(?:jpg|jpeg|gif|png|webp|bmp|svg))\b/i';
    if (preg_match($imagePattern, $content, $matches)) {
        return $matches[1];
    }
    
    return null;
}

/**
 * Buat thumbnail HTML dari URL gambar
 */
function getImageThumbnail($url, $width = 80, $height = 60, $class = '') {
    if (!$url) return '';
    
    return '<div class="thumbnail-container" style="width: ' . $width . 'px; height: ' . $height . 'px; overflow: hidden; border-radius: 4px; ' . $class . '">' .
           '<img src="' . htmlspecialchars($url) . '" ' .
           'style="width: 100%; height: 100%; object-fit: cover;" ' .
           'loading="lazy" onerror="this.style.display=\'none\'; this.parentElement.style.display=\'none\';">' .
           '</div>';
}

/**
 * Mendapatkan excerpt dari konten
 */
function getExcerpt($content, $length = 150) {
    $content = strip_tags($content);
    if (strlen($content) > $length) {
        $content = substr($content, 0, $length) . '...';
    }
    return $content;
}
?>