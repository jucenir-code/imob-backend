<?php

// This fixture is only allowed to operate on the isolated browser-test database.
$path = getenv('DB_DATABASE');
if (getenv('APP_ENV') !== 'testing' || ! $path || ! str_starts_with($path, sys_get_temp_dir().'/cci-browser-')) {
    throw new RuntimeException('An isolated browser test database is required.');
}
if (is_file($path)) {
    unlink($path);
}
touch($path);
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
$owner = App\Models\User::factory()->create([
    'name' => 'Marina Costa', 'email' => 'owner@cci.test', 'password' => Illuminate\Support\Facades\Hash::make('browser-test-123'),
    'role' => 'admin', 'status' => 'active', 'is_approved' => true,
]);
$agent = App\Models\User::factory()->create([
    'name' => 'Rafael Santos', 'email' => 'agent@cci.test', 'password' => Illuminate\Support\Facades\Hash::make('browser-test-123'),
    'role' => 'agent', 'status' => 'active', 'is_approved' => true,
]);
$group = App\Models\Group::create(['name' => 'Parceiros de São Paulo', 'owner_id' => $owner->id, 'visibility' => 'private']);
$group->members()->attach($owner->id, ['role_in_group' => 'owner']);
$group->members()->attach($agent->id, ['role_in_group' => 'member']);
foreach (['Apartamento com varanda em Pinheiros', 'Casa com jardim na Vila Mariana', 'Cobertura no Alto de Pinheiros'] as $index => $title) {
    App\Models\Property::create([
        'group_id' => $group->id, 'owner_id' => $owner->id, 'type' => $index === 1 ? 'house' : 'apartment',
        'title' => $title, 'slug' => 'imovel-'.$index, 'description' => 'Ambientes amplos, boa iluminação e localização privilegiada.',
        'bedrooms' => 3, 'bathrooms' => 2, 'parking' => 2, 'area_m2' => 120,
        'neighborhood' => $index === 1 ? 'Vila Mariana' : 'Pinheiros', 'city' => 'São Paulo', 'state' => 'SP',
        'price' => 890000 + $index * 150000, 'price_visibility' => 'show', 'status' => 'active',
    ]);
}
echo "Browser fixtures ready.\n";
