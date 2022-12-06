<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use DB;

class UsersTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $size = 740;
        for ($x = 1; $x <= $size; $x++) {
  

        
        DB::table('users')->insert([
            'name' =>'mcd'.sprintf("%03d",$x),
            'store_id' => $x,
            'email' => 'mcd'.sprintf("%03d",$x).'@mcddelivery.com',
            'password' => bcrypt('password'),
        ]);
         } 
    }
}
