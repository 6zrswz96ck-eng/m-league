<?php
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
class PlayerRequest extends FormRequest {
    public function authorize(): bool { return true; }
    public function rules(): array { return [
        'name' => ['required','string','max:80',Rule::unique('players')->ignore($this->route('player'))],
        'team_name' => ['required','string','max:80'],
        'season_point' => ['required','numeric','between:-10000,10000','decimal:0,1'],
    ]; }
}
