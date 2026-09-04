<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Database\Eloquent\SoftDeletes;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles, SoftDeletes;

    // --- SECCIÓN: CAMPOS PERMITIDOS ---
    protected $fillable = [
        'name',
        'username',
        'apellido_paterno',
        'apellido_materno',
        'email',
        'password',
        'telefono',
        'fecha_nacimiento',
        'edad',
        'foto_perfil',
        'firma_ruta',
        'catalogo_sexo_id',
        'area_id',
        'departamento_id',
        'excluir_asignacion_tickets',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    // --- SECCIÓN: CONVERSIÓN DE DATOS ---
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'fecha_nacimiento' => 'date',
            'excluir_asignacion_tickets' => 'boolean',
        ];
    }

    // --- SECCIÓN: RELACIONES ---

    public function sexo(): BelongsTo
    {
        return $this->belongsTo(CatalogoSexo::class, 'catalogo_sexo_id');
    }

    // --- SECCIÓN: RELACIONES MATRICIALES ---

    public function departamentos()
    {
        return $this->belongsToMany(Departamento::class);
    }

    public function areas()
    {
        return $this->belongsToMany(Area::class);
    }

    /** Área principal del colaborador (reportes, RH, responsivas). */
    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class, 'area_id');
    }

    /** Departamento principal (marca / tickets térmicos). */
    public function departamento(): BelongsTo
    {
        return $this->belongsTo(Departamento::class, 'departamento_id');
    }

    /**
     * Departamento para branding: principal, o el único asignado.
     * Si hay varios sin principal, null (caller usa fallback de logos).
     */
    public function departamentoParaBranding(): ?Departamento
    {
        $this->loadMissing(['departamento', 'departamentos']);

        if ($this->departamento) {
            return $this->departamento;
        }

        if ($this->departamentos->count() === 1) {
            return $this->departamentos->first();
        }

        return null;
    }

    // Quiénes son los gerentes de este usuario
    public function gerentes()
    {
        return $this->belongsToMany(User::class, 'gerente_colaborador', 'colaborador_id', 'gerente_id');
    }

    // Quiénes son los colaboradores a cargo de este gerente
    public function colaboradores()
    {
        return $this->belongsToMany(User::class, 'gerente_colaborador', 'gerente_id', 'colaborador_id');
    }

    public function colaboradoresRhAsignados(): BelongsToMany
    {
        return $this->belongsToMany(RhColaborador::class, 'gerente_rh_colaborador', 'gerente_user_id', 'rh_colaborador_id')
            ->withTimestamps();
    }
    // Listas creadas por el usuario
    public function customLists()
    {
        return $this->hasMany(CustomList::class);
    }

    // Listas que le han compartido
    public function sharedCustomLists()
    {
        return $this->belongsToMany(CustomList::class, 'custom_list_user', 'user_id', 'custom_list_id');
    }

    public function permisoProcedencia(): HasMany
    {
        return $this->hasMany(UsuarioPermisoProcedencia::class);
    }

    public function perfilRh(): HasOne
    {
        return $this->hasOne(RhColaborador::class, 'user_id');
    }

    public function conversaciones(): BelongsToMany
    {
        return $this->belongsToMany(Conversacion::class, 'conversacion_participantes')
            ->withPivot(['rol', 'ultimo_leido_at', 'silenciado'])
            ->withTimestamps();
    }

    public function sucursales(): BelongsToMany
    {
        return $this->belongsToMany(Sucursal::class, 'sucursal_user')
            ->using(SucursalUser::class)
            ->withPivot(['es_principal', 'activo'])
            ->withTimestamps();
    }

    public function sucursalesOperables(): BelongsToMany
    {
        return $this->sucursales()
            ->wherePivot('activo', true)
            ->where('sucursales.activo', true);
    }

    /**
     * Contrato 0B: principal marcada entre asignaciones operables;
     * si hay exactamente una operable, esa es la principal por defecto.
     */
    public function sucursalPrincipal(): ?Sucursal
    {
        $this->loadMissing('sucursales');

        $operables = $this->sucursales->filter(
            static fn (Sucursal $sucursal): bool => $sucursal->activo && (bool) $sucursal->pivot->activo
        );

        $marcada = $operables->first(
            static fn (Sucursal $sucursal): bool => (bool) $sucursal->pivot->es_principal
        );

        if ($marcada instanceof Sucursal) {
            return $marcada;
        }

        if ($operables->count() === 1) {
            return $operables->first();
        }

        return null;
    }

    /**
     * @return Collection<int, int>
     */
    public function idsSucursalesOperables(): Collection
    {
        $this->loadMissing('sucursales');

        return $this->sucursales
            ->filter(static fn (Sucursal $sucursal): bool => $sucursal->activo && (bool) $sucursal->pivot->activo)
            ->pluck('id')
            ->values();
    }

    public function concederAccesoSucursal(Sucursal $sucursal, bool $esPrincipal = false, bool $activo = true): void
    {
        DB::transaction(function () use ($sucursal, $esPrincipal, $activo): void {
            if ($esPrincipal && $activo) {
                $this->sucursales()->newPivotQuery()
                    ->where('user_id', $this->id)
                    ->update(['es_principal' => false]);
            }

            $this->sucursales()->syncWithoutDetaching([
                $sucursal->id => [
                    'es_principal' => $esPrincipal,
                    'activo' => $activo,
                ],
            ]);
        });

        $this->unsetRelation('sucursales');
        $this->unsetRelation('sucursalesOperables');
    }

    /**
     * @param  list<int>  $sucursalIds
     */
    public function sincronizarSucursalesAsignadas(array $sucursalIds, ?int $sucursalPrincipalId = null): void
    {
        $ids = collect($sucursalIds)
            ->map(static fn (mixed $id): int => (int) $id)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            $this->sucursales()->detach();
            $this->unsetRelation('sucursales');
            $this->unsetRelation('sucursalesOperables');

            return;
        }

        $validIds = Sucursal::query()
            ->whereIn('id', $ids)
            ->where('activo', true)
            ->pluck('id');

        $principalId = $sucursalPrincipalId;
        if ($principalId !== null && ! $validIds->contains($principalId)) {
            $principalId = null;
        }

        if ($validIds->count() === 1) {
            $principalId = (int) $validIds->first();
        }

        $sync = [];
        foreach ($validIds as $id) {
            $sync[(int) $id] = [
                'es_principal' => $principalId !== null && (int) $id === $principalId,
                'activo' => true,
            ];
        }

        $this->sucursales()->sync($sync);
        $this->unsetRelation('sucursales');
        $this->unsetRelation('sucursalesOperables');
    }

    public function jornadasPdv(): HasMany
    {
        return $this->hasMany(PuntoVenta\JornadaPdv::class);
    }

    public function intervalosOperativosPdv(): HasMany
    {
        return $this->hasMany(PuntoVenta\IntervaloOperativoPdv::class);
    }

    public function mensajes(): HasMany
    {
        return $this->hasMany(Mensaje::class);
    }

    /**
     * Los roles ya no otorgan acceso; solo identidad organizacional.
     */
    public function getPermissionsViaRoles(): Collection
    {
        return collect();
    }
}
