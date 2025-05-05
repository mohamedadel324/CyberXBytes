<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <title>Backups</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
      tailwind.config = {
        theme: {
          extend: {
            colors: {
              dark: "#1a1a1a",
              darker: "#141414",
              "table-header": "#1e1e1e",
            },
          },
        },
      };
    </script>
  </head>
  <body class="bg-black text-white min-h-screen">
    <!-- Header -->
    <header class="bg-[#18181B] py-2">
      <div class="flex items-center h-16 px-4 pl-10">
        <div class="flex items-center">
          <a href="/admin">
            <img src="{{ asset('logo3.png') }}" alt="CyberXbytes" class="w-[56px] h-[56px]" />
          </a>
          <span class="ml-2 text-xl font-semibold">CyberXbytes</span>
        </div>
      </div>
    </header>

    <div class="p-8">
      <div class="max-w-7xl mx-auto">
        <div class="flex justify-between items-center mb-8">
          <h1 class="text-3xl font-bold">Backups</h1>
          <div class="space-x-4">
            <button
              class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg transition-colors"
              onclick="createFullBackup()"
            >
              Create Full Backup
            </button>
            <button
              class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg transition-colors"
              onclick="createBackup()"
            >
              Create Database Backup
            </button>
          </div>
        </div>

        <!-- Main Table -->
        <div class="bg-dark rounded-lg overflow-hidden mb-8">
          <table class="w-full">
            <thead class="bg-table-header">
              <tr>
                <th class="text-left py-4 px-6">Name</th>
                <th class="text-left py-4 px-6">Disk</th>
                <th class="text-left py-4 px-6">Healthy</th>
                <th class="text-left py-4 px-6">Amount</th>
                <th class="text-left py-4 px-6">Newest</th>
                <th class="text-left py-4 px-6">Used Storage</th>
                <th class="text-left py-4 px-6">Actions</th>
              </tr>
            </thead>
            <tbody id="backupTableBody">
              @forelse($backups as $backup)
              <tr class="border-t border-gray-700">
                <td class="py-4 px-6">{{ $backup['name'] }}</td>
                <td class="py-4 px-6">local</td>
                <td class="py-4 px-6">
                  <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                  </svg>
                </td>
                <td class="py-4 px-6">{{ count($backups) }}</td>
                <td class="py-4 px-6">{{ $backup['last_modified']->diffForHumans() }}</td>
                <td class="py-4 px-6 text-blue-400">{{ $backup['size'] }}</td>
                <td class="py-4 px-6">
                  <div class="flex space-x-2">
                    <a href="{{ route('backups.download', $backup['name']) }}" class="text-blue-500 hover:text-blue-400">
                      Download
                    </a>
      --}}
  </body>
</html>