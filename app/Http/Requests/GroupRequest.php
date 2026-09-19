<?php
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
class GroupRequest extends FormRequest {
    public function authorize(): bool { return true; }
    public function rules(): array { return [
        'name' => ['required','string','max:80', Rule::unique('groups')->ignore($this->route('group'))],
        'players' => ['required','array','size:4'],
        'players.*' => ['required','integer','distinct','exists:players,id'],
    ]; }
}
