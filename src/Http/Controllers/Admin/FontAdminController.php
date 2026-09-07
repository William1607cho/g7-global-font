<?php

namespace Plugins\G7\Global\Font\Http\Controllers\Admin;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Api\Base\AdminBaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Plugins\G7\Global\Font\Http\Requests\FontUploadRequest;
use Plugins\G7\Global\Font\Models\GlobalFontFile;
use Plugins\G7\Global\Font\Services\FontUploadService;
use Plugins\G7\Global\Font\Support\FontSettings;

/**
 * 전역 폰트 파일 관리 컨트롤러 (관리자).
 *
 * GET    /api/plugins/g7-global-font/admin/fonts
 * POST   /api/plugins/g7-global-font/admin/fonts
 * DELETE /api/plugins/g7-global-font/admin/fonts/{id}
 *
 * 권한은 라우트 미들웨어(`g7-global-font.fonts.manage`)가 검증합니다.
 */
class FontAdminController extends AdminBaseController
{
    public function __construct(
        private readonly FontUploadService $uploadService,
    ) {
        parent::__construct();
    }

    /**
     * 업로드된 폰트 파일 목록을 반환합니다.
     */
    public function index(): JsonResponse
    {
        $activeId = (int) (FontSettings::all()['font_file_id'] ?: 0);

        $fonts = GlobalFontFile::orderByDesc('created_at')->get()->map(
            fn (GlobalFontFile $font) => $this->present($font, $activeId)
        );

        return ResponseHelper::success('common.success', ['data' => $fonts]);
    }

    /**
     * 폰트 파일을 업로드합니다.
     *
     * @param  FontUploadRequest  $request  검증된 업로드 요청
     */
    public function store(FontUploadRequest $request): JsonResponse
    {
        try {
            $font = $this->uploadService->store(
                $request->file('font_file'),
                $this->getCurrentUser()?->id,
            );
        } catch (\Throwable $e) {
            Log::error('[g7-global-font] 폰트 업로드 실패', ['error' => $e->getMessage()]);

            return ResponseHelper::error('messages.upload.failed', 500, domain: 'g7-global-font');
        }

        return ResponseHelper::success(
            'messages.upload.succeeded',
            $this->present($font, $font->id),
            domain: 'g7-global-font',
        );
    }

    /**
     * 폰트 파일을 삭제합니다.
     *
     * @param  int  $id  폰트 파일 ID
     */
    public function destroy(int $id): JsonResponse
    {
        $font = GlobalFontFile::find($id);

        if ($font === null) {
            return ResponseHelper::notFound('messages.font.not_found', domain: 'g7-global-font');
        }

        try {
            $this->uploadService->delete($font);
        } catch (\Throwable $e) {
            Log::error('[g7-global-font] 폰트 삭제 실패', ['error' => $e->getMessage(), 'id' => $id]);

            return ResponseHelper::error('messages.font.delete_failed', 500, domain: 'g7-global-font');
        }

        return ResponseHelper::success('messages.font.deleted', domain: 'g7-global-font');
    }

    /**
     * 목록/응답용 폰트 표현.
     *
     * @return array<string, mixed>
     */
    private function present(GlobalFontFile $font, int $activeId): array
    {
        return [
            'id' => $font->id,
            'original_name' => $font->original_name,
            'extension' => $font->extension,
            'format' => $font->format,
            'file_size' => $font->file_size,
            'serve_url' => $font->serve_url,
            'is_active' => $font->id === $activeId,
            'created_at' => $font->created_at?->toIso8601String(),
        ];
    }
}
