<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SolicitudFactura extends Model
{
    use SoftDeletes;

    protected $table = 'solicitudes_facturas';

    public const DESTINATARIO_CLIENTE = 'cliente';

    public const DESTINATARIO_TERCERO = 'tercero';

    protected $fillable = [
        'folio',
        'vendedor_id',
        'departamento_id',
        'cliente_id',
        'destinatario_tipo',
        'receptor_fiscal_id',
        'catalogo_estado_solicitud_id',
        'razon_social',
        'datos_fiscales',
        'campos_fiscales_solicitados',
        'formulario_enviado_at',
        'formulario_respondido_at',
        'archivo_fiscal_path',
        'observaciones_vendedor',
        'motivo_respuesta',
        'motivo_incorrecta',
        'campos_incorrectos',
        'factura_xml_path',
        'factura_xml_nombre',
        'evidencia_error_path',
        'respondida_por_id',
        'respondida_at',
        'legacy_solicitud_id',
    ];

    protected $casts = [
        'datos_fiscales' => 'array',
        'campos_fiscales_solicitados' => 'array',
        'campos_incorrectos' => 'array',
        'formulario_enviado_at' => 'datetime',
        'formulario_respondido_at' => 'datetime',
        'respondida_at' => 'datetime',
    ];

    protected $appends = [
        'tiene_voucher',
        'tiene_pdf_emitido',
        'tiene_xml',
        'tiene_archivo_fiscal',
    ];

    public function vendedor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendedor_id');
    }

    public function departamento(): BelongsTo
    {
        return $this->belongsTo(Departamento::class, 'departamento_id');
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    public function receptorFiscal(): BelongsTo
    {
        return $this->belongsTo(ReceptorFiscal::class, 'receptor_fiscal_id');
    }

    public function estado(): BelongsTo
    {
        return $this->belongsTo(CatalogoEstadoSolicitud::class, 'catalogo_estado_solicitud_id');
    }

    public function respondidaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'respondida_por_id');
    }

    public function vouchers(): HasMany
    {
        return $this->hasMany(SolicitudFacturaVoucher::class, 'solicitud_factura_id')->orderBy('orden');
    }

    public function pdfsEmitidos(): HasMany
    {
        return $this->hasMany(SolicitudFacturaPdf::class, 'solicitud_factura_id')->orderBy('orden');
    }

    public function enlacesFiscales(): HasMany
    {
        return $this->hasMany(EnlaceDatosFiscales::class, 'solicitud_factura_id')->orderByDesc('id');
    }

    public function auditorias(): HasMany
    {
        return $this->hasMany(AuditoriaSolicitudFactura::class, 'solicitud_factura_id')->orderByDesc('created_at');
    }

    public function getTieneVoucherAttribute(): bool
    {
        if ($this->relationLoaded('vouchers')) {
            return $this->vouchers->isNotEmpty();
        }

        return $this->vouchers()->exists();
    }

    public function getTienePdfEmitidoAttribute(): bool
    {
        if ($this->relationLoaded('pdfsEmitidos')) {
            return $this->pdfsEmitidos->isNotEmpty();
        }

        return $this->pdfsEmitidos()->exists();
    }

    public function getTieneXmlAttribute(): bool
    {
        return !empty($this->factura_xml_path);
    }

    public function getTieneArchivoFiscalAttribute(): bool
    {
        return !empty($this->archivo_fiscal_path);
    }

    public static function generarFolio(): string
    {
        $anio = now()->format('Y');
        $ultimo = static::withTrashed()
            ->where('folio', 'like', "FAC-{$anio}-%")
            ->orderByDesc('id')
            ->value('folio');

        $secuencia = 1;
        if ($ultimo && preg_match('/FAC-\d{4}-(\d+)/', $ultimo, $m)) {
            $secuencia = (int) $m[1] + 1;
        }

        return sprintf('FAC-%s-%05d', $anio, $secuencia);
    }
}
