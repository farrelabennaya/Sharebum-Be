<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // sudah dilindungi auth:sanctum di route
    }

    public function rules(): array
    {
        $userId = $this->user()->id;

        return [
            'name'  => ['required','string','max:80'],
            'email' => ['email','max:190',"unique:users,email,{$userId}"],
            // avatar_url tidak diupdate langsung di sini (gunakan endpoint avatar)
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'  => 'Nama wajib diisi.',
            'name.max'       => 'Nama maksimal 80 karakter.',
            'email.required' => 'Email wajib diisi.',
            'email.email'    => 'Format email tidak valid.',
            'email.unique'   => 'Email sudah digunakan.',
        ];
    }
}
