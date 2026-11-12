<?php
require dirname(__DIR__,4).'/vendor/autoload.php';
$app=require dirname(__DIR__,4).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (!app()->environment('qa-pr34') || config('database.connections.mysql.database')!=='gelia_pr34_qa') throw new RuntimeException('Solo QA PR34');
use App\Models\ControlPedidos\{PedidoBma, CatalogoEstatusPedido, CatalogoModalidadPreparacionPedido, PedidoBmaTareaPreparacion};
use App\Models\User;
$source=PedidoBma::where('folio','QA-PR34-TRANSFERENCIA')->firstOrFail();
$ventas=User::where('email','ventas@pr34.example.test')->firstOrFail();
$banco=App\Models\CatalogoBanco::where('nombre','QA PR34 Banco ficticio')->firstOrFail();
$salida=[];
foreach(['REALTIME','CAPTURA','CAPTURA2','ASINCRONO','ASINCRONO2'] as $caso){
 $p=PedidoBma::where('folio','QA-PR34-'.$caso)->first();
 if(!$p){
  $p=$source->replicate();$p->folio='QA-PR34-'.$caso;$p->folio_remision='QA34-'.$caso;$p->pago_validado_at=null;$p->pago_validado_por_id=null;$p->numero_remision=null;$p->total_mercancia=100;$p->total_a_cobrar=100;$p->revision_registro=0;
  $p->solicitud_preparacion_json=['version'=>1,'modalidad_entrega'=>'tienda','area_revision'=>'control_pedidos','caso_tienda'=>'consultar_cedis','codigo_modalidad'=>'RECOGE_TIENDA_TRANSFERENCIA','cobertura_tienda'=>['tipo'=>'transferencia'],'revision_disponibilidad'=>['estado'=>'sin_excepciones','aceptacion_vigente'=>true],'atencion_tienda'=>'no'];
  $p->catalogo_estatus_pedido_id=CatalogoEstatusPedido::porFase('PENDIENTE_AUXILIAR')->id;$p->empacado_at=null;$p->estatus_envio='completo';
  $p->save();
  $tmp=tempnam(sys_get_temp_dir(),'qa34');file_put_contents($tmp,"%PDF-1.4\n% COMPROBANTE FICTICIO $caso\n%%EOF\n");
  app(App\Services\SaldosAFavor\RegistrarPagoPedidoBmaService::class)->handle($p,['monto'=>100,'forma_pago'=>'transferencia','catalogo_banco_id'=>$banco->id,'idempotency_key'=>'qa34-'.$caso],new Illuminate\Http\UploadedFile($tmp,'QA34-'.$caso.'.pdf','application/pdf',null,true),$ventas->id);@unlink($tmp);
 }
 $salida[strtolower($caso)]=$p->id;
}
$modalidad=CatalogoModalidadPreparacionPedido::where('codigo','RECOGE_TIENDA')->firstOrFail();
$almacen=App\Models\Almacen::where('codigo','QA34-PDV')->firstOrFail();
foreach(range(0,22) as $n){
 $tipo=$n===0?'MD':($n===1?'ACTUALIZADA':($n===2?'NUEVA':($n%2===0?'MUNICIPAL':'NACIONAL')));
 $folio='QA-PR34-ORDEN-'.str_pad((string)$n,2,'0',STR_PAD_LEFT).'-'.$tipo;
 $json=['modalidad_entrega'=>$tipo==='MUNICIPAL'?'municipal':($tipo==='NACIONAL'?'nacional':'tienda')];
 if($tipo==='ACTUALIZADA')$json+=['revision_disponibilidad'=>['estado'=>'sin_excepciones','actualizacion_pendiente'=>true],'anexo_sin_remision'=>[['entrada'=>'documento']]];
 if($n>2)$json['revision_disponibilidad']=['estado'=>'sin_excepciones'];
 $p=PedidoBma::firstOrCreate(['folio'=>$folio],[
  'folio_remision'=>'QA34-ORDEN-'.$n,'fecha'=>now()->toDateString(),'vendedor_id'=>$ventas->id,'cliente_id'=>$source->cliente_id,'departamento_operacion_id'=>$source->departamento_operacion_id,'origen_id'=>$source->origen_id,'almacen_id'=>$source->almacen_id,'sucursal_destino_id'=>$source->sucursal_destino_id,'catalogo_estatus_pedido_id'=>CatalogoEstatusPedido::porFase('PESAJE_PENDIENTE')->id,'estatus_envio'=>'pendiente_pesaje','prioridad_md'=>$n===0,'consulta_actualizacion_pendiente'=>$n===1,'pesaje_respondido_at'=>$n>2?now()->subDay():null,'consulta_cerrada_at'=>$n>2?now()->subDay():null,'created_at'=>now()->subDays(3)->addMinutes($n),'solicitud_preparacion_json'=>$json]);
 PedidoBmaTareaPreparacion::firstOrCreate(['pedido_bma_id'=>$p->id],['catalogo_modalidad_preparacion_id'=>$modalidad->id,'almacen_id'=>$almacen->id,'estado'=>'PENDIENTE','version'=>1,'area_responsable_codigo'=>'TIENDA','solicitada_por_id'=>$ventas->id,'solicitada_at'=>now()->subDays(3)->addMinutes($n)]);
}
file_put_contents(storage_path('app/qa-pr34-extra.json'),json_encode($salida,JSON_PRETTY_PRINT));echo json_encode($salida),PHP_EOL;
