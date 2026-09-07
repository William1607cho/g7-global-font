<?php

namespace Plugins\G7\Global\Font\Hooks;

use App\Contracts\Extension\HookListenerInterface;
use Plugins\G7\Global\Font\Support\FontCss;
use Plugins\G7\Global\Font\Support\FontSettings;

/**
 * 전역 폰트 — 커스텀 자산 주입 + 조건부 검증 규칙.
 *
 * `core.assets.custom_assets` 필터에 CSS 서술자 한 건을 더해, 코어 커스텀 자산 파이프가
 * 그것을 관리자·프론트 전 페이지에 `<link>` 로 싣게 한다. 서술자는 파일이 아니라 플러그인
 * 자체 동적 라우트(`/api/plugins/g7-global-font/font.css`)를 가리키므로, 설정이 바뀌면
 * 다음 요청에서 바로 반영된다(파일 쓰기·재게시 불필요).
 *
 * 이 필터는 활성 확장마다 호출되므로, 우리 플러그인(plugins/g7-global-font)일 때만
 * 항목을 더한다. 훅이 더한 항목은 코어가 파일 출처 뒤에 배치하므로, 템플릿 번들보다
 * 늦게 로드되어 `--font-sans` 재정의가 성립한다.
 */
class GlobalFontAssetListener implements HookListenerInterface
{
    /**
     * @inheritDoc
     */
    public static function getSubscribedHooks(): array
    {
        return [
            'core.assets.custom_assets' => [
                'method' => 'injectFontCss',
                'type' => 'filter',
                'priority' => 20,
            ],
            'core.plugin_settings.update_validation_rules' => [
                'method' => 'filterValidationRules',
                'type' => 'filter',
                'priority' => 20,
            ],
        ];
    }

    /**
     * 커스텀 자산 목록에 전역 폰트 CSS 서술자를 더합니다.
     *
     * @param  array<int, array<string, mixed>>  $assets  누적 서술자 목록
     * @param  string  $extensionType  확장 타입 (templates|modules|plugins)
     * @param  string  $identifier  확장 식별자
     * @return array<int, array<string, mixed>>
     */
    public function injectFontCss(array $assets, string $extensionType, string $identifier): array
    {
        if ($extensionType !== 'plugins' || $identifier !== FontSettings::IDENTIFIER) {
            return $assets;
        }

        $settings = FontSettings::all();
        $file = FontSettings::activeFile($settings);
        $version = FontCss::version($settings, $file);

        $assets[] = [
            'id' => 'g7-global-font:font-css',
            'type' => 'style',
            'url' => '/api/plugins/g7-global-font/font.css?v='.$version,
            'version' => null,
            'source' => 'g7-global-font',
        ];

        return $assets;
    }

    /**
     * 소스 타입에 따라 필수 필드를 조건부로 강제합니다.
     *
     * 스키마 기반 기본 규칙은 모두 nullable 이라, 화면에서 방식만 고르고 값을 비운 채
     * 저장하는 것을 막지 못한다. 현재 입력값(3번째 인자)을 보고 규칙을 조인다.
     *
     * @param  array<string, array<int, mixed>>  $rules  누적 규칙
     * @param  string  $identifier  플러그인 식별자
     * @param  array<string, mixed>  $input  이번 요청의 입력값
     * @return array<string, array<int, mixed>>
     */
    public function filterValidationRules(array $rules, string $identifier, array $input): array
    {
        if ($identifier !== FontSettings::IDENTIFIER) {
            return $rules;
        }

        $sourceType = $input['source_type'] ?? 'upload';

        // 방식과 무관하게 패밀리명은 항상 필요하다 (둘 다 CSS 에서 참조).
        $rules['font_family_name'] = ['required', 'string', 'max:100'];

        if ($sourceType === 'webfont') {
            // CSS 코드이므로 형식 검증은 하지 않는다 — 비어 있지만 않으면 된다.
            $rules['webfont_css'] = ['required', 'string', 'max:20000'];
        } else {
            $rules['font_file_id'] = ['required', 'string', 'max:64'];
        }

        return $rules;
    }

    /**
     * @inheritDoc
     */
    public function handle(...$args): void
    {
        // 이 리스너는 filter 훅만 구독한다 — action 진입점은 사용하지 않는다.
    }
}
