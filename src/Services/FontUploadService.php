<?php

namespace Plugins\G7\Global\Font\Services;

use App\Contracts\Extension\StorageInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Plugins\G7\Global\Font\Models\GlobalFontFile;
use Plugins\G7\Global\Font\Support\FontCss;

/**
 * 전역 폰트 파일 업로드 서비스.
 *
 * 폰트 파일은 이 플러그인의 스토리지 드라이버가 관리하는 단일 카테고리(`fonts`)에만
 * 저장한다. `file_path` 에는 그 카테고리 안의 상대 경로만 담으므로, 서빙·삭제 쪽에서
 * 경로를 다시 분해할 필요가 없다.
 */
class FontUploadService
{
    /** 스토리지 카테고리 (이 플러그인이 관리하는 유일한 카테고리). */
    public const CATEGORY = 'fonts';

    /**
     * @param  StorageInterface  $storage  플러그인 스토리지 드라이버
     */
    public function __construct(
        protected StorageInterface $storage
    ) {}

    /**
     * 폰트 파일을 저장하고 기록을 만듭니다.
     *
     * @param  UploadedFile  $file  업로드된 폰트 파일 (검증 완료)
     * @param  int|null  $uploadedBy  업로드 사용자 ID
     * @return GlobalFontFile 생성된 기록
     */
    public function store(UploadedFile $file, ?int $uploadedBy): GlobalFontFile
    {
        $extension = strtolower(
            $file->getClientOriginalExtension() ?: ($file->guessExtension() ?? 'woff2')
        );

        if (! array_key_exists($extension, FontCss::FORMAT_MAP)) {
            $extension = 'woff2';
        }

        $relativePath = date('Y/m').'/'.Str::uuid()->toString().'.'.$extension;

        $this->storage->put(self::CATEGORY, $relativePath, file_get_contents($file->getRealPath()));

        return GlobalFontFile::create([
            'original_name' => $file->getClientOriginalName(),
            'file_path' => $relativePath,
            'storage_disk' => $this->storage->getDisk(),
            'format' => FontCss::FORMAT_MAP[$extension],
            'extension' => $extension,
            'file_size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
            'uploaded_by' => $uploadedBy,
        ]);
    }

    /**
     * 폰트 파일과 기록을 삭제합니다.
     *
     * @param  GlobalFontFile  $font  삭제할 기록
     */
    public function delete(GlobalFontFile $font): void
    {
        $this->storage->delete(self::CATEGORY, $font->file_path);
        $font->delete();
    }
}
