<?php

namespace Plugins\G7\Global\Font\Tests\Feature;

require_once dirname(__DIR__).'/PluginTestCase.php';

use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Redirector;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Plugins\G7\Global\Font\Http\Requests\FontUploadRequest;
use Plugins\G7\Global\Font\Tests\PluginTestCase;

/**
 * 폰트 업로드 폼 요청 검증 (1.0.2)
 *
 * 규칙(`extensions:`·`mimetypes:`·`max:`)과 `withValidator()` 후처리를 모두 거친 결과를 본다.
 *
 * - `mimetypes:` 는 클라이언트가 보낸 MIME 이 아니라 libmagic 이 **내용으로 추정한** MIME 을
 *   본다. 그래서 PE·PHP 는 1.0.1 에서도 MIME 규칙으로 걸렸고, 1.0.2 는 거기에 시그니처
 *   오류가 더해진다. libmagic 이 `application/octet-stream`·폰트 MIME 으로 추정하는 내용
 *   (정체불명 바이너리, 다른 폰트 형식)은 **시그니처 검사만** 막는다.
 * - 통과 케이스는 매직 + 0 패딩 60바이트로 합성한다. 이 합성 파일에 대한 libmagic 판정
 *   (2026-09-18, 스테이징 앱 이미지): wOF2→font/woff2, wOFF→font/woff,
 *   0x00010000→application/octet-stream, OTTO→application/vnd.ms-opentype — 모두 허용 목록 안.
 *   `true` 매직은 text/plain 으로 추정돼 MIME 규칙에서 걸리므로 여기서는 쓰지 않는다
 *   (시그니처 판정 자체는 FontSignatureTest 에서 본다).
 */
class FontUploadRequestTest extends PluginTestCase
{
    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function acceptedUploads(): array
    {
        return [
            '#1 .woff2 + wOF2' => ['font.woff2', self::synth('wOF2')],
            '#2 .woff + wOFF' => ['font.woff', self::synth('wOFF')],
            '#3 .ttf + 0x00010000' => ['font.ttf', self::synth("\x00\x01\x00\x00")],
            '#4 .otf + OTTO' => ['font.otf', self::synth('OTTO')],
            '#5 .otf + 0x00010000 (CFF 회귀 방지)' => ['font.otf', self::synth("\x00\x01\x00\x00")],
        ];
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function signatureRejectedUploads(): array
    {
        return [
            '#6 .woff2 + OTTO (확장자·매직 불일치)' => ['font.woff2', self::synth('OTTO')],
            '#7 .ttf + MZ (PE 실행파일)' => ['font.ttf', self::synth("MZ\x90\x00")],
            '#8 .ttf + <?php' => ['font.ttf', "<?php echo 1;\n"],
            '#9 3바이트 파일' => ['font.ttf', self::synth("\x00\x01\x00", false)],
            '#10 빈 파일' => ['font.woff2', ''],
            '.ttf + 정체불명 바이너리' => ['font.ttf', self::synth("\x12\x34\x56\x78")],
        ];
    }

    #[DataProvider('acceptedUploads')]
    public function test_valid_font_passes_validation(string $clientName, string $contents): void
    {
        $this->assertSame([], $this->validationErrors($clientName, $contents));
    }

    #[DataProvider('signatureRejectedUploads')]
    public function test_invalid_signature_is_rejected(string $clientName, string $contents): void
    {
        $extension = pathinfo($clientName, PATHINFO_EXTENSION);

        $this->assertContains(
            $this->signatureMessage($extension),
            $this->validationErrors($clientName, $contents)
        );
    }

    /**
     * MIME 규칙을 통과하는 내용은 시그니처 검사만 막는다 — 1.0.1 에서 통과하던 구멍.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function mimeAllowedButWrongSignature(): array
    {
        return [
            '.woff2 + OTTO' => ['font.woff2', self::synth('OTTO')],
            '.ttf + 정체불명 바이너리' => ['font.ttf', self::synth("\x12\x34\x56\x78")],
        ];
    }

    #[DataProvider('mimeAllowedButWrongSignature')]
    public function test_signature_check_alone_blocks_mime_allowed_content(string $clientName, string $contents): void
    {
        $path = $this->makeTempFile($contents);
        $guessed = (new UploadedFile($path, $clientName, null, null, true))->getMimeType();

        if (! in_array($guessed, ['application/octet-stream', 'application/vnd.ms-opentype'], true)) {
            $this->markTestSkipped("이 환경의 libmagic 이 {$guessed} 로 추정해 MIME 규칙 단독 검증이 되지 않는다.");
        }

        $extension = pathinfo($clientName, PATHINFO_EXTENSION);

        $this->assertSame(
            [$this->signatureMessage($extension)],
            $this->validationErrors($clientName, $contents)
        );
    }

    public function test_disallowed_extension_reports_extension_error_only(): void
    {
        $errors = $this->validationErrors('font.exe', self::synth('wOF2'));

        $this->assertContains($this->extensionMessage(), $errors);
        $this->assertNotContains($this->signatureMessage('exe'), $errors);
    }

    public function test_uppercase_extension_is_checked_case_insensitively(): void
    {
        $this->assertSame([], $this->validationErrors('FONT.WOFF2', self::synth('wOF2')));
        $this->assertContains(
            $this->signatureMessage('woff2'),
            $this->validationErrors('FONT.WOFF2', self::synth('OTTO'))
        );
    }

    /**
     * 폼 요청 검증을 돌려 `font_file` 오류 메시지 목록을 돌려준다. 통과하면 빈 배열.
     *
     * @return list<string>
     */
    private function validationErrors(string $clientName, string $contents): array
    {
        $file = new UploadedFile($this->makeTempFile($contents), $clientName, 'application/octet-stream', null, true);

        $request = FontUploadRequest::create('/api/plugins/g7-global-font/admin/fonts', 'POST', [], [], ['font_file' => $file]);
        $request->setContainer($this->app)->setRedirector($this->app->make(Redirector::class));

        try {
            $request->validateResolved();
        } catch (ValidationException $e) {
            return array_values($e->errors()['font_file'] ?? []);
        }

        return [];
    }

    private function signatureMessage(string $extension): string
    {
        return __('g7-global-font::messages.upload.invalid_signature', ['extension' => strtolower($extension)]);
    }

    private function extensionMessage(): string
    {
        return __('g7-global-font::messages.upload.invalid_extension', [
            'allowed' => implode(', ', FontUploadRequest::ALLOWED_EXTENSIONS),
        ]);
    }
}
