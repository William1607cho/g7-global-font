<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 마이그레이션 실행
     *
     * 전역 폰트는 한 번에 하나만 활성화되지만, 운영자가 여러 파일을 올려 두고
     * 갈아끼울 수 있어야 하므로 목록을 테이블로 둔다. 활성 파일은 플러그인 설정
     * (`font_file_id`)이 가리킨다.
     */
    public function up(): void
    {
        Schema::create('g7_global_font_files', function (Blueprint $table) {
            $table->id()->comment('고유 ID');
            $table->string('original_name')->comment('원본 파일명');
            $table->string('file_path', 1000)->comment('fonts 카테고리 안의 상대 경로');
            $table->string('storage_disk', 50)->default('plugins')->comment('스토리지 디스크');
            $table->string('format', 16)->comment('폰트 포맷 (woff2/woff/truetype/opentype)');
            $table->string('extension', 8)->comment('파일 확장자 (woff2/woff/ttf/otf)');
            $table->unsignedBigInteger('file_size')->comment('파일 크기(bytes)');
            $table->string('mime_type', 100)->nullable()->comment('MIME 타입');
            $table->unsignedBigInteger('uploaded_by')->nullable()->comment('업로드 사용자 ID');
            $table->timestamps();

            $table->index('uploaded_by');
            $table->index('created_at');
        });

        if (DB::getDriverName() === 'mysql') {
            Schema::table('g7_global_font_files', function (Blueprint $table) {
                $table->comment('전역 폰트 업로드 파일');
            });
        }
    }

    /**
     * 마이그레이션 롤백
     */
    public function down(): void
    {
        Schema::dropIfExists('g7_global_font_files');
    }
};
