<?php

use Illuminate\Database\Seeder;

class UsersTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('users')->insert([
            'username' => 'sholeh',
            'password' => app('hash')->make('sholeh'),
            'email' => 'sholeh@gmail.com'
        ]);
    }
}
