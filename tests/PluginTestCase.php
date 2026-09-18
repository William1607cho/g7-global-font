<?php

namespace Plugins\G7\Global\Font\Tests;

use Tests\TestCase;

/**
 * g7-global-font 테스트 베이스 클래스 (1.0.2)
 *
 * - DB 를 쓰지 않는다. 업로드 검증은 폼 요청 검증만 돌리며 저장·기록 생성까지 가지 않는다.
 * - 테스트용 폰트는 실제 폰트 파일이 아니라 4바이트 매직 + 패딩으로 합성한 임시 파일이다.
 *   만든 파일은 tearDown 에서 지운다.
 * - 이 플러그인은 번들(`plugins/_bundled`)이 아니고, 코어 테스트 bootstrap 이 디렉터리명으로
 *   계산하는 네임스페이스와 실제 네임스페이스(`Plugins\G7\Global\Font\`)가 달라
 *   자동 로드되지 않는다. 각 테스트 파일은 이 파일을 `require_once` 로 불러오고,
 *   여기서 플러그인 클래스 오토로드를 직접 등록한다.
 * - `tests/` 는 배포 zip 에서 빠진다(.gitattributes export-ignore). 실행은 저장소 사본을
 *   `plugins/g7-global-font/` 에 둔 테스트 환경에서 경로를 직접 지정한다.
 */
abstract class PluginTestCase extends TestCase
{
    public const IDENTIFIER = 'g7-global-font';

    /** 매직 뒤에 붙이는 패딩 길이(바이트) */
    public const PADDING_LENGTH = 60;

    /** @var list<string> 이 테스트가 만든 임시 파일 */
    private array $tempFiles = [];

    protected function setUp(): void
    {
        parent::setUp();

        self::registerPluginAutoload();
    }

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
     * composer.json 의 PSR-4 매핑(`src/`, `./`)과 같은 규칙으로 오토로드를 등록한다.
     */
    public static function registerPluginAutoload(): void
    {
        static $registered = false;
        if ($registered) {
            return;
        }
        $registered = true;

        $base = dirname(__DIR__);

        spl_autoload_register(function ($class) use ($base) {
            $prefix = 'Plugins\\G7\\Global\\Font\\';
            if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
                return;
            }

            $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
            foreach ([$base.'/src/'.$relative.'.php', $base.'/'.lcfirst($relative).'.php', $base.'/'.$relative.'.php'] as $file) {
                if (file_exists($file) && ! class_exists($class, false)) {
                    require_once $file;

                    return;
                }
            }
        });
    }

    /**
     * 주어진 내용으로 임시 파일을 만든다. tearDown 에서 지운다.
     */
    protected function makeTempFile(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'g7gf_');
        file_put_contents($path, $contents);
        $this->tempFiles[] = $path;

        return $path;
    }

    /**
     * 매직 + 0 패딩 합성 파일 내용. 매직이 4바이트 미만이면 패딩 없이 그대로 쓴다
     * (짧은 파일 케이스용).
     */
    public static function synth(string $magic, bool $pad = true): string
    {
        return $pad ? $magic.str_repeat("\x00", self::PADDING_LENGTH) : $magic;
    }
}

PluginTestCase::registerPluginAutoload();
