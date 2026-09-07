<?php

namespace Plugins\G7\Global\Font;

use App\Enums\ExtensionOwnerType;
use App\Extension\AbstractPlugin;
use App\Extension\Helpers\ExtensionMenuSyncHelper;
use Plugins\G7\Global\Font\Hooks\GlobalFontAssetListener;

/**
 * 전역 폰트 플러그인 (g7-global-font)
 *
 * 그누보드7 기본 관리자 템플릿과 사용자 템플릿은 빌드가 분리돼 있으나 두 템플릿 모두
 * Tailwind v4 `--font-sans` 토큰을 같은 이름으로 선언한다. 이 플러그인은
 * `core.assets.custom_assets` 필터로 동적 CSS 한 장(`/api/plugins/g7-global-font/font.css`)을
 * 전 페이지에 주입해 `:root { --font-sans: ... }` 를 재정의한다 — 템플릿 소스 수정 없이
 * 관리자·프론트에 동일 폰트가 적용된다.
 *
 * 폰트 공급 방식은 설정에서 선택한다:
 *  - upload  : 폰트 파일(otf/ttf/woff/woff2)을 업로드 → 플러그인 스토리지 저장 →
 *              스트리밍 라우트 서빙 → `@font-face` 동적 생성
 *  - webfont : 운영자가 붙여넣은 웹폰트 CSS(@font-face 블록 또는 @import)를 그대로 삽입
 *
 * 비활성화하면 필터가 더 이상 발화하지 않아 CSS 주입이 사라지고, 템플릿 기본 폰트로
 * 자동 복귀한다.
 */
class Plugin extends AbstractPlugin
{
    /**
     * 플러그인 메타데이터 반환
     *
     * @return array 메타데이터
     */
    public function getMetadata(): array
    {
        return [
            'author' => 'William Cho',
            'license' => 'MIT',
            'keywords' => ['font', 'webfont', 'typography', 'global'],
        ];
    }

    /**
     * 플러그인 설정 스키마 반환
     *
     * @return array 설정 스키마
     */
    public function getSettingsSchema(): array
    {
        return [
            'source_type' => [
                'type' => 'enum',
                'options' => ['upload', 'webfont'],
                'default' => 'upload',
                'label' => [
                    'ko' => '폰트 공급 방식',
                    'en' => 'Font Source',
                ],
                'hint' => [
                    'ko' => '업로드한 폰트 파일을 쓸지, 외부 웹폰트 URL 을 쓸지 선택합니다.',
                    'en' => 'Choose whether to use an uploaded font file or an external webfont URL.',
                ],
                'required' => false,
            ],
            'font_family_name' => [
                'type' => 'string',
                'max' => 100,
                'default' => '',
                'label' => [
                    'ko' => '폰트 패밀리명',
                    'en' => 'Font Family Name',
                ],
                'hint' => [
                    'ko' => 'CSS 에서 참조할 글꼴 이름입니다. 웹폰트 방식이면 해당 웹폰트가 정의하는 이름과 정확히 일치해야 합니다. (예: Noto Sans KR)',
                    'en' => 'The font-family name referenced in CSS. For webfonts it must exactly match the name the webfont defines. (e.g. Noto Sans KR)',
                ],
                'required' => false,
            ],
            'font_file_id' => [
                'type' => 'string',
                'max' => 64,
                'default' => '',
                'label' => [
                    'ko' => '업로드된 폰트 파일',
                    'en' => 'Uploaded Font File',
                ],
                'hint' => [
                    'ko' => '업로드 방식일 때 사용할 폰트 파일의 ID 입니다. 설정 화면의 업로드 필드가 자동으로 채웁니다.',
                    'en' => 'ID of the font file to use in upload mode. The settings screen fills this automatically.',
                ],
                'required' => false,
            ],
            'webfont_css' => [
                'type' => 'string',
                'max' => 20000,
                'default' => '',
                'label' => [
                    'ko' => '웹폰트 CSS 코드',
                    'en' => 'Webfont CSS Code',
                ],
                'hint' => [
                    'ko' => '웹폰트 방식일 때 그대로 삽입할 CSS 입니다. 눈누 등에서 제공하는 @font-face 블록 전체를 붙여넣으세요. Google Fonts 처럼 CSS URL 하나만 있으면 @import url(\'주소\'); 형태로 감싸서 넣습니다. 위 폰트 패밀리명은 여기 @font-face 의 font-family 값과 정확히 일치해야 합니다.',
                    'en' => 'CSS injected verbatim in webfont mode. Paste the whole @font-face block (e.g. from noonnu). If you only have a CSS URL like Google Fonts, wrap it as @import url(\'address\');. The Font Family Name above must exactly match the font-family value in this @font-face.',
                ],
                'required' => false,
            ],
        ];
    }

    /**
     * 플러그인 설정 기본값 반환
     *
     * @return array 기본 설정값
     */
    public function getConfigValues(): array
    {
        return [
            'source_type' => 'upload',
            'font_family_name' => '',
            'font_file_id' => '',
            'webfont_css' => '',
        ];
    }

    /**
     * 훅 리스너 목록 반환
     *
     * @return array<int, class-string>
     */
    public function getHookListeners(): array
    {
        return [
            GlobalFontAssetListener::class,
        ];
    }

    /**
     * 관리자 메뉴 정의
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAdminMenus(): array
    {
        return [
            [
                'name' => ['ko' => '전역 폰트', 'en' => 'Global Font'],
                'slug' => 'g7-global-font-settings',
                'url' => '/admin/plugins/g7-global-font/settings',
                'icon' => 'fas fa-font',
                'order' => 60,
            ],
        ];
    }

    /**
     * 플러그인 권한 목록 반환
     *
     * 폰트 파일 업로드·삭제는 사이트 전 화면의 글꼴을 바꾸므로 별도 권한으로 둔다.
     * 설정 저장 자체는 코어의 `core.plugins.update` 를 따른다.
     *
     * @return array 권한 정의 배열
     */
    public function getPermissions(): array
    {
        return [
            'name' => [
                'ko' => '전역 폰트',
                'en' => 'Global Font',
            ],
            'description' => [
                'ko' => '전역 폰트 플러그인이 제공하는 권한',
                'en' => 'Permissions provided by the Global Font plugin',
            ],
            'categories' => [
                [
                    'identifier' => 'fonts',
                    'name' => ['ko' => '폰트 파일', 'en' => 'Font Files'],
                    'description' => [
                        'ko' => '업로드된 폰트 파일의 조회·업로드·삭제 권한',
                        'en' => 'View, upload and delete permissions for uploaded font files',
                    ],
                    'permissions' => [
                        [
                            'action' => 'manage',
                            'name' => ['ko' => '폰트 파일 관리', 'en' => 'Manage Font Files'],
                            'description' => [
                                'ko' => '폰트 파일 목록 조회·업로드·삭제',
                                'en' => 'List, upload and delete font files',
                            ],
                            'type' => 'admin',
                            'roles' => ['admin'],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * 플러그인이 관리하는 동적 테이블 목록 반환
     *
     * @return array 테이블명 배열
     */
    public function getDynamicTables(): array
    {
        return [
            'g7_global_font_files',
        ];
    }

    /**
     * 플러그인 활성화 — 관리자 메뉴 자동 등록.
     *
     * @return bool 활성화 성공 여부
     */
    public function activate(): bool
    {
        $helper = app(ExtensionMenuSyncHelper::class);

        foreach ($this->getAdminMenus() as $menuData) {
            $helper->syncMenuRecursive(
                $menuData,
                ExtensionOwnerType::Plugin,
                $this->getIdentifier(),
            );
        }

        return true;
    }

    /**
     * 플러그인 비활성화 — 관리자 메뉴 일괄 제거.
     *
     * @return bool 비활성화 성공 여부
     */
    public function deactivate(): bool
    {
        app(ExtensionMenuSyncHelper::class)->cleanupStaleMenus(
            ExtensionOwnerType::Plugin,
            $this->getIdentifier(),
            currentSlugs: [],
        );

        return true;
    }

    /**
     * 플러그인 제거 — 메뉴 잔존 안전망.
     *
     * @return bool 제거 성공 여부
     */
    public function uninstall(): bool
    {
        $this->deactivate();

        return true;
    }
}
