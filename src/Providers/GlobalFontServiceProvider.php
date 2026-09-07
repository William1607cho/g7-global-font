<?php

namespace Plugins\G7\Global\Font\Providers;

use App\Extension\BasePluginServiceProvider;
use Plugins\G7\Global\Font\Services\FontServeService;
use Plugins\G7\Global\Font\Services\FontUploadService;

/**
 * 전역 폰트 플러그인 서비스 프로바이더.
 *
 * FontUploadService / FontServeService 에 플러그인 도메인 StorageInterface 를
 * 자동 주입하도록 BasePluginServiceProvider 표준에 위임한다.
 */
class GlobalFontServiceProvider extends BasePluginServiceProvider
{
    protected string $pluginIdentifier = 'g7-global-font';

    /**
     * 기본 StorageInterface 주입이 필요한 서비스.
     *
     * @var array<class-string>
     */
    protected array $storageServices = [
        FontUploadService::class,
        FontServeService::class,
    ];
}
