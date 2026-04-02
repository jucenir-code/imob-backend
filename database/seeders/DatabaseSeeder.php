<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Usuário de teste/desenvolvimento
        \App\Models\User::factory()->create([
            'name' => 'Admin Teste',
            'email' => 'admin@teste.com',
            'password' => \Illuminate\Support\Facades\Hash::make('senha123'),
            'phone_e164' => '+5548999999999',
            'role' => 'agent',
            'status' => 'active',
        ]);

        // Opcional: criar mais usuários de teste
        // \App\Models\User::factory(5)->create();
    }
}
