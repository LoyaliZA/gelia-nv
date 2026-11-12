<?php
require dirname(__DIR__,4).'/vendor/autoload.php';$app=require dirname(__DIR__,4).'/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if(!app()->environment('qa-pr34')||config('database.connections.mysql.database')!=='gelia_pr34_qa')throw new RuntimeException('Solo QA');
use App\Models\ControlPedidos\PedidoBma;
$ids=[];
foreach(PedidoBma::where('folio','like','QA-PR34-ORDEN-%')->orderBy('id')->get() as $p){
 $folio=str_replace('-ORDEN-','-CEDORDEN-',$p->folio);$nuevo=PedidoBma::where('folio',$folio)->first();
 if(!$nuevo){$nuevo=$p->replicate();$nuevo->folio=$folio;$nuevo->folio_remision=str_replace('QA-PR34-','QA34-',$folio);$json=$nuevo->solicitud_preparacion_json;$json['area_revision']='control_pedidos';$nuevo->solicitud_preparacion_json=$json;$nuevo->save();}
 $ids[]=['id'=>$nuevo->id,'folio'=>$nuevo->folio,'visible'=>$nuevo->folio_remision];
}
file_put_contents(storage_path('app/qa-pr34-orden-cedis.json'),json_encode($ids,JSON_PRETTY_PRINT));echo count($ids),PHP_EOL;
