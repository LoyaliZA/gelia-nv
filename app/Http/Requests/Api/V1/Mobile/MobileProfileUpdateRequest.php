<?php

namespace App\Http\Requests\Api\V1\Mobile;

use Illuminate\Foundation\Http\FormRequest;

class MobileProfileUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'tema_visual' => 'sometimes|array',
            'tema_visual.modo' => 'sometimes|string|in:dark,light',
            'tema_visual.color_nombre' => 'sometimes|string|max:50',
            'tema_visual.fondo_base' => 'sometimes|string|max:500',
            'tema_visual.fuente_principal' => 'sometimes|string|max:50',
            'tema_visual.escala_fuente' => 'sometimes|numeric|min:0.875|max:1.5',
            'tema_visual.layout_sidebar' => 'sometimes|string|in:floating_left,floating_right,fixed,professional_left',
            'tema_visual.layout_sidebar_mobile' => 'sometimes|string|in:mobile_bottom,mobile_topbar',
            'tema_visual.sidebar_modo' => 'sometimes|string|in:collapsed,expanded',
            'tema_visual.sidebar_posicion_fija' => 'sometimes|string|in:left,right,top,bottom',
            'tema_visual.efecto_cristal' => 'sometimes|boolean',
            'tema_visual.densidad_contenido' => 'sometimes|string|in:compacto,completo,personalizado',
            'tema_visual.contenido_max_rem' => 'sometimes|numeric|min:60|max:120',
            'tema_visual.contenido_padding_rem' => 'sometimes|numeric|min:0.5|max:2',
            'tema_visual.alertas_prefs' => 'sometimes|array',
            'foto_perfil' => 'sometimes|image|mimes:jpeg,png,jpg,webp|max:2048',
            'remove_foto' => 'sometimes|boolean',
        ];
    }
}
