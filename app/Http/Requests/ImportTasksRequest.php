<?php

namespace App\Http\Requests;

use App\Support\UploadLimits;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Validator;

/**
 * Validasi file import Excel. Maksimal 10 MB (OQ-4).
 *
 * Batas 10 MB adalah aturan aplikasi, sedangkan PHP punya batas sendiri
 * (`upload_max_filesize`, `post_max_size`) yang berlaku LEBIH DAHULU. Bila
 * batas PHP lebih kecil, file dipotong PHP sebelum sampai ke Laravel sehingga
 * terlihat seperti file yang tidak dipilih. Galat khusus disiapkan di sini
 * agar pengguna tahu penyebab sebenarnya, bukan sekadar "gagal diunggah".
 */
class ImportTasksRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:xlsx', 'max:10240'],
        ];
    }

    /**
     * Pesan overridden agar menyebut batas 10 MB dengan satuan yang jelas.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'Silakan pilih file Excel yang akan diimpor.',
            'file.mimes' => 'Berkas harus berupa file Excel .xlsx yang valid.',
            'file.max' => 'Ukuran berkas tidak boleh lebih dari 10 MB.',
        ];
    }

    /**
     * Galat untuk kegagalan yang berasal dari konfigurasi PHP, bukan dari
     * isi file. Berjalan setelah semua aturan di atas, sehingga pesan generik
     * dari aturan `file`/`required` diganti dengan pesan yang menjelaskan
     * batas server yang sebenarnya.
     *
     * CATATAN: kasus `post_max_size` terlampaui TIDAK ditangani di sini.
     * Middleware `ValidatePostSize` melempar `PostTooLargeException` lebih dulu,
     * jadi FormRequest tidak pernah dijalankan untuk request sebesar itu.
     * Penanganannya ada di bootstrap/app.php - satu titik, bukan dua.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $file = $this->file('file');

            if ($file instanceof UploadedFile && $file->getError() !== UPLOAD_ERR_OK) {
                $this->replaceError($validator, $this->uploadErrorMessage($file->getError()));
            }
        });
    }

    /**
     * Ganti seluruh galat pada field `file` dengan satu pesan yang jelas.
     *
     * `MessageBag` tidak punya `remove()`; cara yang benar adalah `forget()`
     * lalu `add()`. `errors()` mengembalikan MessageBag yang sama (bukan
     * salinan), jadi mutasinya langsung dipakai saat response dibangun.
     */
    protected function replaceError(Validator $validator, string $message): void
    {
        $errors = $validator->errors();

        $errors->forget('file');
        $errors->add('file', $message);
    }

    protected function uploadErrorMessage(int $code): string
    {
        $limitMb = UploadLimits::uploadLimitBytes() > 0
            ? UploadLimits::formatMb(UploadLimits::uploadLimitBytes())
            : null;

        return match ($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => $limitMb === null
                ? 'Ukuran berkas melebihi batas unggahan server. Perkecil berkas atau minta administrator menaikkan `upload_max_filesize` pada php.ini.'
                : 'Ukuran berkas melebihi batas unggahan server (maks. '.$limitMb.' MB). Perkecil berkas, atau minta administrator menaikkan `upload_max_filesize` pada php.ini.',
            UPLOAD_ERR_PARTIAL => 'Berkas hanya terunggah sebagian. Coba unggah ulang.',
            default => 'Berkas gagal diunggah. Coba lagi atau gunakan berkas lain.',
        };
    }
}
