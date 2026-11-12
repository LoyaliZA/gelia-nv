<?php
require dirname(__DIR__,4).'/vendor/autoload.php';$app=require dirname(__DIR__,4).'/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if(!app()->environment('qa-pr34')||config('database.connections.mysql.database')!=='gelia_pr34_qa')throw new RuntimeException('Solo QA');
use App\Models\ControlPedidos\{PedidoBma,CatalogoEstatusPedido};use Illuminate\Support\Facades\Storage;
$p=PedidoBma::where('folio','QA-PR34-VISUAL')->first();
if(!$p){$p=PedidoBma::findOrFail(1)->replicate();$p->folio='QA-PR34-VISUAL';$p->folio_remision='QA34-VISUAL';$p->consulta_cerrada_at=null;$p->consulta_actualizacion_pendiente=false;$p->pesaje_respondido_at=null;$p->pesaje_respondido_por_id=null;$p->revision_registro=0;$p->catalogo_estatus_pedido_id=CatalogoEstatusPedido::porFase('BORRADOR')->id;$p->solicitud_preparacion_json=['version'=>1,'modalidad_entrega'=>'tienda','area_revision'=>'preparacion_tienda','almacen_revision_id'=>1];$p->save();
$stream='BT /F1 18 Tf 40 740 Td (PEDIDO FICTICIO QA PR34) Tj ET';
$objects=['<</Type /Catalog /Pages 2 0 R>>','<</Type /Pages /Kids [3 0 R] /Count 1>>','<</Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources <</Font <</F1 5 0 R>>>>>>','<</Length '.strlen($stream).">>\nstream\n".$stream."\nendstream",'<</Type /Font /Subtype /Type1 /BaseFont /Helvetica>>'];
$pdf="%PDF-1.4\n";$offset=[0];foreach($objects as $i=>$o){$offset[]=strlen($pdf);$pdf.=($i+1)." 0 obj\n".$o."\nendobj\n";}$xref=strlen($pdf);$pdf.="xref\n0 6\n0000000000 65535 f \n";foreach(array_slice($offset,1) as $n)$pdf.=sprintf("%010d 00000 n \n",$n);$pdf.="trailer\n<</Size 6 /Root 1 0 R>>\nstartxref\n$xref\n%%EOF\n";
$ruta='pedidos_bma/qa-pr34/'.$p->id.'/pedido.pdf';Storage::disk('public')->put($ruta,$pdf);$p->documentos()->create(['tipo'=>'pdf_pedido','ruta_archivo'=>$ruta,'nombre_original'=>'QA34-pedido-visual.pdf','mime_type'=>'application/pdf','tamano_bytes'=>strlen($pdf),'activo'=>true,'orden'=>1]);}
file_put_contents(storage_path('app/qa-pr34-visual.json'),json_encode(['id'=>$p->id]));echo $p->id,PHP_EOL;
