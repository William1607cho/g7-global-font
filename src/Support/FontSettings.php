<?php

namespace Plugins\G7\Global\Font\Support;

use Plugins\G7\Global\Font\Models\GlobalFontFile;

/**
 * 전역 폰트 플러그인의 현재 설정 + 활성 폰트 파일 해석기.
 */
class FontSettings
{
    public const IDENTIFIER = 'g7-global-font';

    /**
     * 현재 플러그인 설정 전체를 돌려줍니다 (기본값 병합됨).
     *
     * @return array{source_type: string, font_family_name: string, font_file_id: string, webfont_css: string}
     */
    public static function all(): array
    {
        $raw = plugin_settings(self::IDENTIFIER);

        return [
            'source_type' => in_array($raw['source_type'] ?? null, ['upload', 'webfont'], true)
                ? $raw['source_type']
                : 'upload',
            'font_family_name' => trim((string) ($raw['font_family_name'] ?? '')),
            'font_file_id' => trim((string) ($raw['font_file_id'] ?? '')),
            'webfont_css' => trim((string) ($raw['webfont_css'] ?? '')),
        ];
    }

    /**
     * 업로드 방식일 때 설정이 가리키는 활성 폰트 파일을 돌려줍니다.
     *
     * webfont 방식이거나, 파일 ID 가 비었거나, 그 행이 삭제됐으면 null.
     *
     * @param  array<string, mixed>|null  $settings  미리 읽어 둔 설정 (없으면 새로 읽음)
     */
    public static function activeFile(?array $settings = null): ?GlobalFontFile
    {
        $settings ??= self::all();

        if (($settings['source_type'] ?? 'upload') !== 'upload') {
            return null;
        }

        $id = (int) ($settings['font_file_id'] ?? 0);

        if ($id <= 0) {
            return null;
        }

        return GlobalFontFile::find($id);
    }
}
