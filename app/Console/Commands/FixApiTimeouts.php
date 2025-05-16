<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

class FixApiTimeouts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'api:fix-timeouts';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix API timeout issues by clearing caches and updating DDoS settings';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting API timeout fix...');

        // Step 1: Clear all rate limiting caches
        $this->info('Clearing rate limiting caches...');
        $keys = Cache::get('api_ddos_protection_*');
        if ($keys) {
            foreach ($keys as $key) {
                Cache::forget($key);
            }
        }
        
        $keys = Cache::get('api_endpoint_*');
        if ($keys) {
            foreach ($keys as $key) {
                Cache::forget($key);
            }
        }
        
        $keys = Cache::get('blocked_*');
        if ($keys) {
            foreach ($keys as $key) {
                Cache::forget($key);
            }
        }
        
        $keys = Cache::get('api_blocked_*');
        if ($keys) {
            foreach ($keys as $key) {
                Cache::forget($key);
            }
        }

        // Step 2: Clear application cache
        $this->info('Clearing application cache...');
        Artisan::call('cache:clear');
        $this->info(Artisan::output());

        // Step 3: Clear route cache
        $this->info('Clearing route cache...');
        Artisan::call('route:clear');
        $this->info(Artisan::output());

        // Step 4: Clear config cache
        $this->info('Clearing config cache...');
        Artisan::call('config:clear');
        $this->info(Artisan::output());

        // Step 5: Update DDoS settings
        $this->info('Updating DDoS protection settings...');
        Artisan::call('ddos:publish');
        $this->info(Artisan::output());

        $this->info('API timeout fix completed successfully!');
        $this->info('The system has been optimized to prevent timeouts on user profile requests.');
        
        return 0;
    }
} 