<?php

namespace Plugins\G7\Global\Font\Support;

use Plugins\G7\Global\Font\Models\GlobalFontFile;

/**
 * 전역 폰트 CSS 생성기 (순수 함수 모음).
 *
 * 현재 플러그인 설정 → 전 페이지에 주입할 CSS 문자열을 만든다. 관리자·프론트 템플릿
 * 모두 Tailwind v4 `--font-sans` 토큰을 소비하므로, `:root { --font-sans: ... }` 한 줄이면
 * 양쪽에 동일 폰트가 적용된다 (`--default-font-family: var(--font-sans)` → `html` 까지 전파).
 */
class FontCss
{
    /**
     * 커스텀 폰트 뒤에 붙는 폴백 스택.
     *
     * 커스텀 폰트가 로드되지 않아도(파일 404·웹폰트 URL 실패·미지정) 템플릿 동봉
     * Pretendard → 시스템 폰트 순으로 안전하게 폴백한다. 스택 뒷부분은 템플릿의
     * 원본 `--font-sans` 정의와 동일하다.
     */
    public const FALLBACK_STACK = "'Pretendard Variable', 'Pretendard', ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, 'Noto Sans', sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol', 'Noto Color Emoji'";

    /**
     * 확장자 → CSS `format()` 값.
     *
     * @var array<string, string>
     */
    public const FORMAT_MAP = [
        'woff2' => 'woff2',
        'woff' => 'woff',
        'ttf' => 'truetype',
        'otf' => 'opentype',
    ];

    /**
     * 현재 설정으로 주입할 CSS 를 만듭니다.
     *
     * 어느 경우에도 유효한 CSS 를 돌려준다. 폰트가 확정되지 않으면(패밀리명 없음,
     * 업로드 파일 없음, CSS 미입력) 주석만 담긴 빈 CSS 를 준다 — 주입은 되지만 아무것도
     * 바꾸지 않아 템플릿 기본 폰트가 그대로 유지된다.
     *
     * @param  array{source_type?: string, font_family_name?: string, webfont_css?: string}  $settings
     * @param  GlobalFontFile|null  $file  업로드 방식일 때 활성 폰트 파일
     * @return string CSS 본문
     */
    public static function build(array $settings, ?GlobalFontFile $file): string
    {
        $sourceType = ($settings['source_type'] ?? 'upload') === 'webfont' ? 'webfont' : 'upload';
        $family = trim((string) ($settings['font_family_name'] ?? ''));

        $header = "/* g7-global-font — generated, do not edit */\n";

        if ($family === '') {
            return $header."/* font_family_name 미지정 — 폰트 미적용 */\n";
        }

        $blocks = [];

        if ($sourceType === 'webfont') {
            // 운영자가 붙여넣은 CSS(@font-face 블록 또는 @import) 를 그대로 삽입한다.
            // 응답은 same-origin `text/css` 라 HTML 인젝션 벡터가 없고, 설정 화면 접근
            // 권한이 있으면 코어 커스텀 자산 기능으로 이미 사이트 전역 CSS 를 넣을 수
            // 있으므로 새 권한이 아니다. `@import` 는 스타일시트 첫 규칙이어야 하므로
            // 이 블록이 아래 `:root` 보다 먼저 온다.
            $css = self::sanitizeUserCss((string) ($settings['webfont_css'] ?? ''));

            if ($css === '') {
                return $header."/* webfont_css 미입력 — 폰트 미적용 */\n";
            }

            $blocks[] = $css;
        } else {
            if ($file === null) {
                return $header."/* 업로드된 폰트 파일 없음 — 폰트 미적용 */\n";
            }

            $format = self::FORMAT_MAP[strtolower($file->extension)] ?? 'woff2';

            $blocks[] = "@font-face {\n"
                ."  font-family: '".self::escapeFamily($family)."';\n"
                ."  src: url('".self::escapeUrl($file->serve_url)."') format('".$format."');\n"
                ."  font-weight: 1 1000;\n"
                ."  font-style: normal;\n"
                ."  font-display: swap;\n"
                .'}';
        }

        $blocks[] = ":root {\n  --font-sans: '".self::escapeFamily($family)."', ".self::FALLBACK_STACK.";\n}";

        return $header.implode("\n\n", $blocks)."\n";
    }

    /**
     * 설정 + 파일 상태로부터 캐시 무효화 버전을 만듭니다.
     *
     * `?v=` 로 CSS URL 에 붙어, 설정이나 폰트 파일이 바뀌면 브라우저가 새로 받는다.
     *
     * @param  array<string, mixed>  $settings
     * @param  GlobalFontFile|null  $file
     * @return string 8자리 hex 버전
     */
    public static function version(array $settings, ?GlobalFontFile $file): string
    {
        $material = implode('|', [
            (string) ($settings['source_type'] ?? ''),
            (string) ($settings['font_family_name'] ?? ''),
            (string) ($settings['font_file_id'] ?? ''),
            (string) ($settings['webfont_css'] ?? ''),
            $file?->updated_at?->getTimestamp() ?? '0',
        ]);

        return substr(hash('crc32b', $material), 0, 8);
    }

    /**
     * 운영자가 붙여넣은 웹폰트 CSS 를 삽입 전 최소 정리합니다.
     *
     * 원본 CSS(@font-face·@import 등)는 손대지 않는 것이 목적이다. NUL 바이트와
     * 스타일시트를 조기 종료시킬 수 있는 시퀀스만 제거하고 길이를 상한한다.
     *
     * @param  string  $css  원본 입력
     * @return string 정리된 CSS (빈 입력이면 빈 문자열)
     */
    private static function sanitizeUserCss(string $css): string
    {
        $css = str_replace("\0", '', trim($css));
        // `text/css` 응답이라 HTML 파서를 타지 않지만, 방어적으로 태그 종료 시퀀스를 없앤다.
        $css = preg_replace('#</\s*style#i', '', $css);

        if (strlen($css) > 20000) {
            $css = substr($css, 0, 20000);
        }

        return $css;
    }

    /**
     * CSS 문자열 리터럴에 넣을 폰트명을 이스케이프합니다.
     *
     * 작은따옴표·역슬래시·개행만 제거하면 CSS injection 을 막을 수 있다 (값은
     * 항상 작은따옴표로 감싼다).
     */
    private static function escapeFamily(string $value): string
    {
        return trim(preg_replace('/[\'"\\\\\r\n;{}]/', '', $value));
    }

    /**
     * `url()` 안에 넣을 URL 을 이스케이프합니다.
     */
    private static function escapeUrl(string $value): string
    {
        return str_replace(["'", '"', '\\', "\r", "\n", '(', ')', ' '], '', $value);
    }
}
