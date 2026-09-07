<?php

namespace Plugins\G7\Global\Font\Http\Controllers;

use App\Http\Controllers\Api\Base\PublicBaseController;
use Illuminate\Http\Response;
use Plugins\G7\Global\Font\Support\FontCss;
use Plugins\G7\Global\Font\Support\FontSettings;

/**
 * 전역 폰트 CSS 서빙 컨트롤러 (공개).
 *
 * 현재 플러그인 설정으로 `@font-face`(+ `@import`) 와 `:root { --font-sans }` 재정의를
 * 즉석 생성해 `text/css` 로 돌려준다. 커스텀 자산 파이프가 이 URL 을 전 페이지에
 * `<link rel="stylesheet">` 로 싣는다.
 *
 * 봉투(JSON) 응답 불가 — 브라우저가 스타일시트로 파싱하는 순수 CSS 여야 한다.
 * audit:allow response-helper-bypass reason: 스타일시트 응답
 */
class FontCssController extends PublicBaseController
{
    /**
     * 전역 폰트 CSS 를 돌려줍니다.
     */
    public function show(): Response
    {
        $settings = FontSettings::all();
        $file = FontSettings::activeFile($settings);

        $css = FontCss::build($settings, $file);
        $version = FontCss::version($settings, $file);

        return response($css, 200, [
            'Content-Type' => 'text/css; charset=UTF-8',
            // 설정이 바뀌면 ?v= 가 바뀌어 새 URL 이 되므로 길게 캐시해도 안전하다.
            'Cache-Control' => 'public, max-age=86400',
            'ETag' => '"'.$version.'"',
        ]);
    }
}
