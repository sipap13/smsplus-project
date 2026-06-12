<?php
namespace App\Console\Commands;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
class FixAdmin extends Command
{
    protected $signature = 'fix:admin';
    public function handle()
    {
        DB::table('ra_t_users')->where('email', 'admin2@tt.tn')->update(['password' => Hash::make('admin2')]);
        $this->info("Password for admin2@tt.tn set to 'admin2'");
        DB::table('ra_t_users')->where('email', 'admin@tt.tn')->update(['password' => Hash::make('admin123')]);
        $this->info("Password for admin@tt.tn set to 'admin123'");
    }
}
