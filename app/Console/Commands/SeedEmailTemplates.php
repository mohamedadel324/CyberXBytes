<?php

namespace App\Console\Commands;

use Database\Seeders\EmailTemplateSeeder;
use Illuminate\Console\Command;

class SeedEmailTemplates extends Command
{
    protected $signature = 'seed:email-templates';

    protected $description = 'Seed the email templates table';

    public function handle()
    {
        $this->info('Seeding email templates...');
        (new EmailTemplateSeeder())->run();
        $this->info('Email templates seeded successfully!');

        return Command::SUCCESS;
    }
} 