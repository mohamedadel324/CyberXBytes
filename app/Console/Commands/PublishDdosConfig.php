<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class PublishDdosConfig extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ddos:publish';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Publish DDoS protection configuration to .env file';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Publishing DDoS protection configuration...');

        // Add DDoS protection settings to .env file
        $envFile = base_path('.env');
        
        if (File::exists($envFile)) {
            $envContent = File::get($envFile);
            
            // Check if DDoS settings are already in the .env file
            if (!str_contains($envContent, 'DDOS_PROTECTION_ENABLED')) {
                $ddosSettings = "\n# DDoS Protection Settings\n";
                $ddosSettings .= "DDOS_PROTECTION_ENABLED=true\n";
                $ddosSettings .= "DDOS_WEB_MAX_REQUESTS=60\n";
                $ddosSettings .= "DDOS_WEB_DECAY_MINUTES=1\n";
                $ddosSettings .= "DDOS_WEB_BLOCK_MINUTES=10\n";
                $ddosSettings .= "DDOS_WEB_BLOCK_THRESHOLD=120\n";
                $ddosSettings .= "DDOS_API_MAX_REQUESTS=60\n";
                $ddosSettings .= "DDOS_API_DECAY_MINUTES=1\n";
                $ddosSettings .= "DDOS_API_BLOCK_MINUTES=15\n";
                $ddosSettings .= "DDOS_API_BLOCK_THRESHOLD=120\n";
                $ddosSettings .= "DDOS_API_ENDPOINT_MAX_REQUESTS=30\n";
                $ddosSettings .= "DDOS_API_ENDPOINT_DECAY_MINUTES=1\n";
                
                File::append($envFile, $ddosSettings);
                $this->info('DDoS protection settings added to .env file.');
            } else {
                $this->info('DDoS protection settings already exist in .env file.');
                $this->info('Updating with new recommended values...');
                
                // Update existing values with new recommended settings
                $envContent = preg_replace('/DDOS_API_MAX_REQUESTS=(\d+)/', 'DDOS_API_MAX_REQUESTS=60', $envContent);
                $envContent = preg_replace('/DDOS_API_BLOCK_THRESHOLD=(\d+)/', 'DDOS_API_BLOCK_THRESHOLD=120', $envContent);
                $envContent = preg_replace('/DDOS_API_ENDPOINT_MAX_REQUESTS=(\d+)/', 'DDOS_API_ENDPOINT_MAX_REQUESTS=30', $envContent);
                
                File::put($envFile, $envContent);
                $this->info('DDoS protection settings updated in .env file.');
            }
        } else {
            $this->error('.env file not found.');
            return 1;
        }
        
        $this->info('DDoS protection configuration published successfully!');
        $this->info('You can now customize the settings in your .env file.');
        
        return 0;
    }
} 