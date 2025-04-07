<?php

use Illuminate\Support\Facades\Storage;

if (!function_exists('get_filament_file_path')) {
    function get_filament_file_path($path)
    {
        if (empty($path)) {
            return null;
        }

        // If it's an array (multiple files), get the first one
        if (is_array($path)) {
            $path = $path[0];
        }

        // Get the full path from storage
        return Storage::disk('public')->path($path);
    }
}
