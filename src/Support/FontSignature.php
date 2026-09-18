<?php

namespace Plugins\G7\Global\Font\Support;

/**
 * 폰트 파일 시그니처(선두 4바이트) 검사.
 *
 * 업로드 MIME 은 브라우저가 `application/octet-stream` 으로 보내는 일이 흔해 걸러낼 수 없다.
 * 그래서 파일 내용의 선두 4바이트가 확장자에 맞는 sfnt 계열 매직인지 확인한다.
 * 매직이 목록에 있기만 하면 통과시키지 않고, **확장자와 짝이 맞아야** 통과한다
 * (`.woff2` 파일에 `OTTO` 가 들어 있으면 거부).
 *
 * `.otf` 는 `OTTO`(CFF 아웃라인)와 `0x00010000`(TrueType 아웃라인) 둘 다 실제로 존재하므로
 * 둘 다 허용한다 — 1.0.1 에서 CFF OTF 업로드가 거부되던 회귀를 다시 내지 않기 위해서다.
 */
final class FontSignature
{
    /** 읽을 선두 바이트 수 */
    public const LENGTH = 4;

    /**
     * 확장자별 허용 매직.
     *
     * @var array<string, list<string>>
     */
    public const MAGIC_BY_EXTENSION = [
        'woff2' => ['wOF2'],
        'woff' => ['wOFF'],
        'ttf' => ["\x00\x01\x00\x00", 'true', 'ttcf'],
        'otf' => ['OTTO', "\x00\x01\x00\x00"],
    ];

    /**
     * 파일 선두 바이트가 확장자에 맞는 폰트 시그니처인지 확인합니다.
     *
     * @param  string  $extension  클라이언트 파일명 확장자 (대소문자 무관)
     * @param  string  $path  검사할 파일 경로
     * @return bool 확장자와 매직이 짝이 맞으면 true. 읽기 실패·4바이트 미만·미지원 확장자는 false
     */
    public static function matches(string $extension, string $path): bool
    {
        $allowed = self::MAGIC_BY_EXTENSION[strtolower($extension)] ?? null;

        if ($allowed === null) {
            return false;
        }

        $head = self::readHead($path);

        return $head !== null && in_array($head, $allowed, true);
    }

    /**
     * 파일 선두 4바이트를 읽습니다. 파일 전체는 읽지 않습니다.
     *
     * @param  string  $path  파일 경로
     * @return string|null 선두 4바이트. 읽기 실패 또는 4바이트 미만이면 null
     */
    public static function readHead(string $path): ?string
    {
        if ($path === '' || ! is_file($path) || ! is_readable($path)) {
            return null;
        }

        try {
            $head = file_get_contents($path, false, null, 0, self::LENGTH);
        } catch (\Throwable) {
            return null;
        }

        if ($head === false || strlen($head) < self::LENGTH) {
            return null;
        }

        return $head;
    }
}
