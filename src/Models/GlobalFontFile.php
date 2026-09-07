<?php

namespace Plugins\G7\Global\Font\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * 전역 폰트 업로드 파일 모델
 *
 * @property int $id
 * @property string $original_name 원본 파일명
 * @property string $file_path fonts 카테고리 안의 상대 경로 (예: "2026/09/uuid.woff2")
 * @property string $storage_disk 스토리지 디스크
 * @property string $format CSS `format()` 값 (woff2/woff/truetype/opentype)
 * @property string $extension 파일 확장자 (woff2/woff/ttf/otf)
 * @property int $file_size 파일 크기 (bytes)
 * @property string|null $mime_type MIME 타입
 * @property int|null $uploaded_by 업로드 사용자 ID
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read string $serve_url 폰트 파일 서빙 URL
 */
class GlobalFontFile extends Model
{
    /**
     * 테이블명
     *
     * @var string
     */
    protected $table = 'g7_global_font_files';

    /**
     * 대량 할당 허용 필드
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'original_name',
        'file_path',
        'storage_disk',
        'format',
        'extension',
        'file_size',
        'mime_type',
        'uploaded_by',
    ];

    /**
     * 타입 캐스팅
     *
     * @var array<string, string>
     */
    protected $casts = [
        'file_size' => 'integer',
        'uploaded_by' => 'integer',
    ];

    /**
     * 폰트 파일 서빙 URL 반환.
     *
     * `plugins` 디스크는 `serve => false` 라 직접 정적 URL 이 없다 — 항상 플러그인
     * 자체 스트리밍 라우트를 쓴다.
     *
     * @return string 폰트 파일 URL
     */
    public function getServeUrlAttribute(): string
    {
        return '/api/plugins/g7-global-font/font-file/'.$this->id;
    }

    /**
     * 업로드한 사용자
     *
     * @return BelongsTo<User, GlobalFontFile>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
