<?php
/**
 * Language / Localization system
 */

function getCurrentLanguage() {
    if (isset($_GET['lang']) && in_array($_GET['lang'], ['ar', 'en'])) {
        $_SESSION['lang'] = $_GET['lang'];
    }
    return $_SESSION['lang'] ?? getSetting('default_language', 'ar');
}

function getLang() {
    static $lang = null;
    if ($lang !== null) return $lang;
    $code = getCurrentLanguage();
    $file = __DIR__ . '/../lang/' . $code . '.php';
    if (file_exists($file)) {
        $lang = require $file;
    } else {
        $lang = require __DIR__ . '/../lang/ar.php';
    }
    return $lang;
}

function __($key) {
    $lang = getLang();
    return $lang[$key] ?? $key;
}

function getDirection() {
    return __('direction');
}

function getFontFamily() {
    return __('font_family');
}

function getLangCode() {
    return __('lang_code');
}

function getOtherLang() {
    return getCurrentLanguage() === 'ar' ? 'en' : 'ar';
}

function getOtherLangLabel() {
    return getCurrentLanguage() === 'ar' ? 'English' : 'العربية';
}

function langUrl($lang) {
    $params = $_GET;
    $params['lang'] = $lang;
    return basename($_SERVER['PHP_SELF']) . '?' . http_build_query($params);
}
