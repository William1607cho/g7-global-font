<?php

namespace Plugins\G7\Global\Font\Tests\Unit;

require_once dirname(__DIR__).'/PluginTestCase.php';

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Plugins\G7\Global\Font\Support\FontSignature;
use Plugins\G7\Global\Font\Tests\PluginTestCase;

/**
 * 폰트 파일 시그니처 판정 (1.0.2)
 *
 * - 확장자와 선두 4바이트 매직이 짝이 맞아야 통과한다
 * - `.otf` 는 `OTTO`(CFF)와 `0x00010000`(TrueType 아웃라인) 둘 다 통과 — 1.0.1 CFF 회귀 방지
 * - 읽기 실패·4바이트 미만·미지원 확장자는 거부
 */
class FontSignatureTest extends TestCase
{
    /** @var list<string> */
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
        $this->tempFiles = [];

        parent::tearDown();
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function acceptedCases(): array
    {
        return [
            '#1 .woff2 + wOF2' => ['woff2', PluginTestCase::synth('wOF2')],
            '#2 .woff + wOFF' => ['woff', PluginTestCase::synth('wOFF')],
            '#3 .ttf + 0x00010000' => ['ttf', PluginTestCase::synth("\x00\x01\x00\x00")],
            '#4 .otf + OTTO' => ['otf', PluginTestCase::synth('OTTO')],
            '#5 .otf + 0x00010000 (CFF 회귀 방지)' => ['otf', PluginTestCase::synth("\x00\x01\x00\x00")],
            '.ttf + true' => ['ttf', PluginTestCase::synth('true')],
            '.ttf + ttcf' => ['ttf', PluginTestCase::synth('ttcf')],
            '대문자 확장자 .WOFF2' => ['WOFF2', PluginTestCase::synth('wOF2')],
            '정확히 4바이트' => ['woff2', 'wOF2'],
        ];
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function rejectedCases(): array
    {
        return [
            '#6 .woff2 + OTTO (확장자·매직 불일치)' => ['woff2', PluginTestCase::synth('OTTO')],
            '#7 .ttf + MZ (PE 실행파일)' => ['ttf', PluginTestCase::synth("MZ\x90\x00")],
            '#8 .ttf + <?php' => ['ttf', "<?php echo 1;\n"],
            '#9 3바이트 파일' => ['ttf', PluginTestCase::synth("\x00\x01\x00", false)],
            '#10 빈 파일' => ['woff2', ''],
            '.ttf + 정체불명 바이너리' => ['ttf', PluginTestCase::synth("\x12\x34\x56\x78")],
            '.woff + wOF2 (woff2 매직)' => ['woff', PluginTestCase::synth('wOF2')],
            '.woff2 + wOFF (woff 매직)' => ['woff2', PluginTestCase::synth('wOFF')],
            '.ttf + OTTO (CFF 는 .otf 만)' => ['ttf', PluginTestCase::synth('OTTO')],
            '.otf + true' => ['otf', PluginTestCase::synth('true')],
            '.otf + ttcf' => ['otf', PluginTestCase::synth('ttcf')],
            '매직 대소문자 다름 (wof2)' => ['woff2', PluginTestCase::synth('wof2')],
            '미지원 확장자 .eot' => ['eot', PluginTestCase::synth("\x00\x01\x00\x00")],
            '빈 확장자' => ['', PluginTestCase::synth('wOF2')],
        ];
    }

    #[DataProvider('acceptedCases')]
    public function test_matching_signature_is_accepted(string $extension, string $contents): void
    {
        $this->assertTrue(FontSignature::matches($extension, $this->makeTempFile($contents)));
    }

    #[DataProvider('rejectedCases')]
    public function test_mismatched_or_invalid_signature_is_rejected(string $extension, string $contents): void
    {
        $this->assertFalse(FontSignature::matches($extension, $this->makeTempFile($contents)));
    }

    public function test_missing_file_is_rejected(): void
    {
        $path = sys_get_temp_dir().'/g7gf_missing_'.bin2hex(random_bytes(8));

        $this->assertFileDoesNotExist($path);
        $this->assertFalse(FontSignature::matches('woff2', $path));
        $this->assertNull(FontSignature::readHead($path));
    }

    public function test_empty_path_and_directory_are_rejected(): void
    {
        $this->assertFalse(FontSignature::matches('woff2', ''));
        $this->assertFalse(FontSignature::matches('woff2', sys_get_temp_dir()));
    }

    public function test_read_head_returns_only_first_four_bytes(): void
    {
        $path = $this->makeTempFile('wOF2'.str_repeat('X', 1024));

        $this->assertSame('wOF2', FontSignature::readHead($path));
    }

    public function test_read_head_rejects_short_files(): void
    {
        $this->assertNull(FontSignature::readHead($this->makeTempFile('')));
        $this->assertNull(FontSignature::readHead($this->makeTempFile('wOF')));
    }

    public function test_otf_accepts_both_cff_and_truetype_outlines(): void
    {
        $this->assertContains('OTTO', FontSignature::MAGIC_BY_EXTENSION['otf']);
        $this->assertContains("\x00\x01\x00\x00", FontSignature::MAGIC_BY_EXTENSION['otf']);
    }

    private function makeTempFile(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'g7gf_');
        file_put_contents($path, $contents);
        $this->tempFiles[] = $path;

        return $path;
    }
}
