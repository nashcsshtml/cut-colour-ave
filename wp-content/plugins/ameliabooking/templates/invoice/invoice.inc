<?php

use AmeliaBooking\Application\Services\Helper\HelperService;

/** @var HelperService $helperService */
$helperService = $this->container->get('application.helper.service');

$data = $invoiceData['placeholders'];

$locale = get_locale();
$localePrefix = substr($locale, 0, 2);

$isRtl = is_rtl();

$googleFontCss = null;
switch ($localePrefix) {
    case 'ar':
    case 'fa':
    case 'ur':
        $googleFontCss = 'https://fonts.googleapis.com/css2?family=Noto+Sans+Arabic:wght@400;700&display=swap';
        $fontFamily = "'Noto Sans Arabic', 'DejaVu Sans', sans-serif";
        break;
    case 'he':
        $googleFontCss = 'https://fonts.googleapis.com/css2?family=Noto+Sans+Hebrew:wght@400;700&display=swap';
        $fontFamily = "'Noto Sans Hebrew', 'DejaVu Sans', sans-serif";
        break;
    case 'ja':
        $googleFontCss = 'https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@400;700&display=swap';
        $fontFamily = "'Noto Sans JP', 'DejaVu Sans', sans-serif";
        break;
    case 'ko':
        $googleFontCss = 'https://fonts.googleapis.com/css2?family=Noto+Sans+KR:wght@400;700&display=swap';
        $fontFamily = "'Noto Sans KR', 'DejaVu Sans', sans-serif";
        break;
    case 'th':
        $googleFontCss = 'https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@400;700&display=swap';
        $fontFamily = "'Noto Sans Thai', 'DejaVu Sans', sans-serif";
        break;
    case 'zh':
        $googleFontCss = 'https://fonts.googleapis.com/css2?family=Noto+Sans+SC:wght@400;700&display=swap';
        $fontFamily = "'Noto Sans SC', 'DejaVu Sans', sans-serif";
        break;
    default:
        $fontFamily = "'DejaVu Sans', sans-serif";
}

// Keep invoice-specific setup in one place and delegate markup to directional templates.
require __DIR__ . ($isRtl ? '/invoice.rtl.inc' : '/invoice.ltr.inc');
