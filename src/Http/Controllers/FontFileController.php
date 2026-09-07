<?php

namespace Plugins\G7\Global\Font\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Api\Base\PublicBaseController;
use Illuminate\Http\JsonResponse;
use Plugins\G7\Global\Font\Models\GlobalFontFile;
use Plugins\G7\Global\Font\Services\FontServeService;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * 전역 폰트 파일 서빙 컨트롤러 (공개).
 *
 * `@font-face { src: url(...) }` 가 가리키는 폰트 바이너리를 스트리밍한다. 인증 없이
 * 접근 가능해야 브라우저가 폰트를 받을 수 있다.
 */
class FontFileController extends PublicBaseController
{
    public function __construct(
        private FontServeService $fontServeService
    ) {}

    /**
     * 폰트 파일을 스트리밍합니다.
     *
     * @param  int  $id  폰트 파일 ID
     * @return StreamedResponse|JsonResponse
     */
    public function serve(int $id): StreamedResponse|JsonResponse
    {
        $font = GlobalFontFile::find($id);

        if ($font === null) {
            return ResponseHelper::notFound('messages.font.not_found', domain: 'g7-global-font');
        }

        $response = $this->fontServeService->serve($font);

        if ($response === null) {
            return ResponseHelper::notFound('messages.font.not_found', domain: 'g7-global-font');
        }

        return $response;
    }
}
