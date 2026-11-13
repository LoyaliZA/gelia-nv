<?php
require dirname(__DIR__,4).'/vendor/autoload.php';$app=require dirname(__DIR__,4).'/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if(!app()->environment('qa-pr34')||config('database.connections.mysql.database')!=='gelia_pr34_qa')throw new RuntimeException('Solo QA');
foreach(App\Models\User::where('email','like','%@pr34.example.test')->get() as $u){$permisos=$u->roles->flatMap(fn($r)=>$r->permissions->pluck('name'))->unique()->values()->all();if($permisos){$u->syncPermissions($permisos);foreach($u->roles as $r)$r->syncPermissions([]);}echo json_encode(['actor'=>$u->username,'permisos'=>$u->fresh()->getAllPermissions()->pluck('name')]),PHP_EOL;}
app(Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
