<?php

namespace Plugins\G7\Global\Font\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * 전역 폰트 파일 업로드 검증.
 *
 * 폰트 파일은 브라우저가 MIME 을 `application/octet-stream` 으로 올려 보내는 일이 흔하다.
 * 그래서 MIME 목록에 octet-stream 을 허용하되, 확장자(`extensions:` 규칙 + 클라이언트
 * 파일명 재검사)로 최종 게이트를 건다.
 */
class FontUploadRequest extends FormRequest
{
    /** 허용 확장자 */
    public const ALLOWED_EXTENSIONS = ['woff2', 'woff', 'ttf', 'otf'];

    /** 최대 크기 (MB) */
    private const MAX_MB = 10;

    /**
     * 권한은 라우트 미들웨어에서 처리.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * 검증 규칙.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $maxKb = self::MAX_MB * 1024;

        return [
            'font_file' => [
                'required',
                'file',
                'extensions:'.implode(',', self::ALLOWED_EXTENSIONS),
                'mimetypes:font/woff2,font/woff,font/ttf,font/otf,font/sfnt,'
                    .'application/font-woff,application/font-woff2,application/x-font-woff,'
                    .'application/x-font-ttf,application/x-font-truetype,application/x-font-otf,'
                    .'application/x-font-opentype,application/vnd.ms-fontobject,application/octet-stream',
                'max:'.$maxKb,
            ],
        ];
    }

    /**
     * 확장자 최종 재검사 — 클라이언트 파일명 기준.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $file = $this->file('font_file');

            if ($file === null) {
                return;
            }

            $ext = strtolower($file->getClientOriginalExtension());

            if (! in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
                $validator->errors()->add(
                    'font_file',
                    __('g7-global-font::messages.upload.invalid_extension', [
                        'allowed' => implode(', ', self::ALLOWED_EXTENSIONS),
                    ])
                );
            }
        });
    }

    /**
     * 오류 메시지.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'font_file.required' => __('g7-global-font::messages.upload.required'),
            'font_file.file' => __('g7-global-font::messages.upload.invalid_file'),
            'font_file.extensions' => __('g7-global-font::messages.upload.invalid_extension', [
                'allowed' => implode(', ', self::ALLOWED_EXTENSIONS),
            ]),
            'font_file.mimetypes' => __('g7-global-font::messages.upload.invalid_extension', [
                'allowed' => implode(', ', self::ALLOWED_EXTENSIONS),
            ]),
            'font_file.max' => __('g7-global-font::messages.upload.too_large', ['max' => self::MAX_MB]),
        ];
    }
}
