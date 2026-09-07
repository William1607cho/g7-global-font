<?php

namespace Plugins\G7\Global\Font\Services;

use App\Contracts\Extension\StorageInterface;
use Illuminate\Support\Facades\Log;
use Plugins\G7\Global\Font\Models\GlobalFontFile;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * 전역 폰트 파일 서빙 서비스.
 *
 * 플러그인 스토리지 디스크는 HTTP 직접 서빙을 열어 두지 않으므로(`serve => false`),
 * `@font-face { src: url(...) }` 가 가리키는 폰트 바이너리를 이 서비스가
 * StreamedResponse 로 흘려보낸다.
 */
class FontServeService
{
    /**
     * 확장자 → 폰트 MIME 타입.
     *
     * @var array<string, string>
     */
    private const MIME_MAP = [
        'woff2' => 'font/woff2',
        'woff' => 'font/woff',
        'ttf' => 'font/ttf',
        'otf' => 'font/otf',
    ];

    /**
     * @param  StorageInterface  $storage  플러그인 스토리지 드라이버
     */
    public function __construct(
        protected StorageInterface $storage
    ) {}

    /**
     * 폰트 파일 스트림 응답을 만듭니다.
     *
     * @param  GlobalFontFile  $font  폰트 기록
     * @return StreamedResponse|null 스토리지에 파일이 없으면 null
     */
    public function serve(GlobalFontFile $font): ?StreamedResponse
    {
        $mime = self::MIME_MAP[strtolower($font->extension)] ?? 'application/octet-stream';

        $response = $this->storage->response(
            FontUploadService::CATEGORY,
            $font->file_path,
            $font->original_name,
            [
                'Content-Type' => $mime,
                'Cache-Control' => 'public, max-age=31536000, immutable',
            ],
        );

        if ($response === null) {
            Log::warning('g7-global-font: font file is missing from storage.', [
                'font_id' => $font->id,
                'path' => $font->file_path,
            ]);
        }

        return $response;
    }
}
