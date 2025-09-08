<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreImageUploaderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'image.*' => 'required|image|mimes:jpeg,png,jpg,webp,gif,svg|max:2048',
            'height' => 'integer',
            'width' => 'integer',
        ];
    }

    public function messages(): array
    {
        return [
            'image.*.required' => 'The image field is required.',
            'image.*.image' => 'The image field must be an image.',
            'image.*.mimes' => 'The image field must be a file of type: jpeg, png, jpg, webp, gif, svg.',
            'image.*.max' => 'The image field must be a file with a maximum size of 2MB.',
            'height.integer' => 'The height field must be an integer.',
            'width.integer' => 'The width field must be an integer.',
        ];
    }
}
