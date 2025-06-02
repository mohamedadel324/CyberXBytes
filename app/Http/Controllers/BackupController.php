<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use PDO;
use ZipArchive;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use Illuminate\Support\Facades\File;

class BackupController extends Controller
{
    public function index()
    {
        if (!Storage::exists('backups')) {
            Storage::makeDirectory('backups');
        }

        $backups = Storage::files('backups');
        $backupDetails = [];
        
        foreach ($backups as $backup) {
            try {
                $backupDetails[] = [
                    'name' => basename($backup),
                    'size' => $this->formatBytes(Storage::size($backup)),
                    'last_modified' => Carbon::createFromTimestamp(Storage::lastModified($backup)),
                    'path' => $backup
                ];
            } catch (\Exception $e) {
                continue;
            }
        }

        // Sort backups by last modified time, newest first
        usort($backupDetails, function($a, $b) {
            return $b['last_modified']->timestamp - $a['last_modified']->timestamp;
        });

        return view('admin.backup', ['backups' => $backupDetails]);
    }

    private function formatBytes($bytes, $decimals = 2)
    {
        if ($bytes === 0) return '0 Bytes';
        $k = 1024;
        $dm = $decimals < 0 ? 0 : $decimals;
        $sizes = ['Bytes', 'KB', 'MB', 'GB'];
        $i = floor(log($bytes) / log($k));
        return number_format($bytes / pow($k, $i), $dm) . ' ' . $sizes[$i];
    }

    public function create()
    {
        try {
            $timestamp = Carbon::now()->format('Y-m-d-H-i-s');
            $filename = "database-" . $timestamp . ".sql";
            
            if (!Storage::exists('backups')) {
                Storage::makeDirectory('backups');
            }

            $output = "-- Backup created at " . Carbon::now() . "\n";
            $output .= "-- Database: " . config('database.connections.mysql.database') . "\n\n";
            $output .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

            $tables = DB::select('SHOW TABLES');
            if (!$tables) {
                throw new \Exception('No tables found in database');
            }

            $db = DB::connection()->getDatabaseName();
            $field = "Tables_in_" . $db;

            foreach ($tables as $table) {
                $tableName = $table->$field;
                $output .= "DROP TABLE IF EXISTS `$tableName`;\n";
                $createTable = DB::select("SHOW CREATE TABLE `$tableName`");
                $output .= $createTable[0]->{'Create Table'} . ";\n\n";
                
                $rows = DB::table($tableName)->get();
                if (count($rows) > 0) {
                    $chunks = array_chunk($rows->toArray(), 100);
                    foreach ($chunks as $chunk) {
                        $columns = array_keys((array)$chunk[0]);
                        $columnList = '`' . implode('`, `', $columns) . '`';
                        $output .= "INSERT INTO `$tableName` ($columnList) VALUES ";
                        $rowsValues = [];
                        foreach ($chunk as $row) {
                            $values = array_map(function ($value) {
                                if (is_null($value)) return 'NULL';
                                return "'" . str_replace("'", "''", $value) . "'";
                            }, (array)$row);
                            $rowsValues[] = '(' . implode(', ', $values) . ')';
                        }
                        $output .= implode(",\n", $rowsValues) . ";\n";
                    }
                    $output .= "\n";
                }
            }

            $output .= "SET FOREIGN_KEY_CHECKS=1;\n";

            // Save the backup file
            if (!Storage::put('backups/' . $filename, $output)) {
                throw new \Exception('Failed to write backup file');
            }

            // Get file size
            $size = Storage::size('backups/' . $filename);
            
            // Format the size
            $sizeFormatted = $this->formatBytes($size);

            return response()->json([
                'success' => true,
                'message' => 'Database backup created successfully',
                'filename' => $filename,
                'size' => $sizeFormatted,
                'date' => Carbon::now()->toDateTimeString()
            ]);

        } catch (\Exception $e) {
            \Log::error('Database backup failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Database backup failed: ' . $e->getMessage()
            ], 500);
        }
    }

    public function createFullBackup()
    {
        try {
            // Set maximum execution time to 5 minutes
            set_time_limit(300);
            
            $timestamp = Carbon::now()->format('Y-m-d-H-i-s');
            $backupFileName = "full-backup-" . $timestamp . ".zip";
            $backupDir = storage_path('app/backups');
            $tempDir = storage_path('app/temp-backup-' . $timestamp);
            
            // Ensure backup directory exists
            if (!file_exists($backupDir)) {
                if (!mkdir($backupDir, 0755, true)) {
                    throw new \Exception('Failed to create backup directory');
                }
            }

            // Create temporary directory
            if (!file_exists($tempDir)) {
                if (!mkdir($tempDir, 0755, true)) {
                    throw new \Exception('Failed to create temporary directory');
                }
            }

            // First, collect all files we want to backup
            $filesToBackup = [];
            $totalSize = 0;
            $projectRoot = base_path();

            try {
                $iterator = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($projectRoot, \RecursiveDirectoryIterator::SKIP_DOTS),
                    \RecursiveIteratorIterator::SELF_FIRST
                );

                $excludeDirs = [
                    'storage/app/backups',
                    'storage/app/temp-backup-' . $timestamp,
                    'storage/logs',
                    'storage/framework/cache',
                    'storage/framework/sessions',
                    'storage/framework/views',
                ];

                foreach ($iterator as $file) {
                    if ($file->isFile()) {
                        $filePath = $file->getRealPath();
                        $relativePath = substr($filePath, strlen($projectRoot) + 1);
                        
                        // Skip excluded directories
                        $skip = false;
                        foreach ($excludeDirs as $excludeDir) {
                            if (strpos($relativePath, $excludeDir) === 0) {
                                $skip = true;
                                break;
                            }
                        }
                        
                        if (!$skip) {
                            $filesToBackup[] = [
                                'path' => $filePath,
                                'relative' => $relativePath,
                                'size' => $file->getSize()
                            ];
                            $totalSize += $file->getSize();
                        }
                    }
                }
            } catch (\Exception $e) {
                throw new \Exception('Failed to scan directory: ' . $e->getMessage());
            }

            // Create SQL backup
            $sqlFileName = "database-" . $timestamp . ".sql";
            $sqlFilePath = $tempDir . '/' . $sqlFileName;
            
            try {
                $output = "-- Backup created at " . Carbon::now() . "\n";
                $output .= "-- Database: " . config('database.connections.mysql.database') . "\n\n";
                $output .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

                $tables = DB::select('SHOW TABLES');
                if (!$tables) {
                    throw new \Exception('No tables found in database');
                }

                $db = DB::connection()->getDatabaseName();
                $field = "Tables_in_" . $db;

                foreach ($tables as $table) {
                    $tableName = $table->$field;
                    $output .= "DROP TABLE IF EXISTS `$tableName`;\n";
                    $createTable = DB::select("SHOW CREATE TABLE `$tableName`");
                    $output .= $createTable[0]->{'Create Table'} . ";\n\n";
                    
                    $rows = DB::table($tableName)->get();
                    if (count($rows) > 0) {
                        $chunks = array_chunk($rows->toArray(), 100);
                        foreach ($chunks as $chunk) {
                            $columns = array_keys((array)$chunk[0]);
                            $columnList = '`' . implode('`, `', $columns) . '`';
                            $output .= "INSERT INTO `$tableName` ($columnList) VALUES ";
                            $rowsValues = [];
                            foreach ($chunk as $row) {
                                $values = array_map(function ($value) {
                                    if (is_null($value)) return 'NULL';
                                    return "'" . str_replace("'", "''", $value) . "'";
                                }, (array)$row);
                                $rowsValues[] = '(' . implode(', ', $values) . ')';
                            }
                            $output .= implode(",\n", $rowsValues) . ";\n";
                        }
                        $output .= "\n";
                    }
                }

                if (!file_put_contents($sqlFilePath, $output)) {
                    throw new \Exception('Failed to write SQL backup file');
                }
            } catch (\Exception $e) {
                throw new \Exception('Database backup failed: ' . $e->getMessage());
            }

            // Create ZIP archive
            $zipPath = $backupDir . '/' . $backupFileName;
            $zip = new \ZipArchive();
            
            $zipResult = $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
            if ($zipResult !== TRUE) {
                throw new \Exception('Failed to create ZIP file. Error code: ' . $zipResult);
            }

            // Add SQL backup first
            if (!$zip->addFile($sqlFilePath, $sqlFileName)) {
                throw new \Exception('Failed to add SQL file to ZIP');
            }

            // Add all collected files
            $processedSize = 0;
            foreach ($filesToBackup as $file) {
                if (!$zip->addFile($file['path'], $file['relative'])) {
                    \Log::error('Failed to add file to ZIP: ' . $file['path']);
                    continue;
                }
                $processedSize += $file['size'];
            }

            if (!$zip->close()) {
                throw new \Exception('Failed to close ZIP file');
            }

            // Clean up temporary files
            if (file_exists($sqlFilePath)) {
                unlink($sqlFilePath);
            }
            if (file_exists($tempDir)) {
                rmdir($tempDir);
            }

            // Check if the ZIP file was created and is readable
            if (!file_exists($zipPath)) {
                throw new \Exception('ZIP file was not created');
            }

            if (!is_readable($zipPath)) {
                throw new \Exception('ZIP file is not readable');
            }

            $finalSize = filesize($zipPath);
            if ($finalSize === 0) {
                throw new \Exception('ZIP file is empty');
            }

            // Return the file for immediate download
            return response()->download($zipPath, $backupFileName)->deleteFileAfterSend(true);

        } catch (\Exception $e) {
            \Log::error('Backup failed: ' . $e->getMessage());
            
            // Clean up any temporary files if they exist
            if (isset($sqlFilePath) && file_exists($sqlFilePath)) {
                unlink($sqlFilePath);
            }
            if (isset($tempDir) && file_exists($tempDir)) {
                rmdir($tempDir);
            }
            if (isset($zipPath) && file_exists($zipPath)) {
                unlink($zipPath);
            }

            // Get the full error chain
            $errorMessage = $e->getMessage();
            $previous = $e->getPrevious();
            while ($previous !== null) {
                $errorMessage .= "\nCaused by: " . $previous->getMessage();
                $previous = $previous->getPrevious();
            }

            return response()->json([
                'success' => false,
                'message' => 'Backup failed: ' . $errorMessage,
                'trace' => config('app.debug') ? $e->getTraceAsString() : null
            ], 500);
        }
    }

    public function restore($filename)
    {
        try {
            $path = 'backups/' . $filename;
            
            if (!Storage::exists($path)) {
                throw new \Exception('Backup file not found');
            }

            $sql = Storage::get($path);
            
            // Split SQL by semicolon to get individual queries
            $queries = array_filter(
                array_map('trim', 
                    preg_split("/;\s*[\r\n]+/", $sql)
                )
            );
            
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
            
            try {
                foreach ($queries as $query) {
                    if (!empty($query)) {
                        DB::unprepared($query);
                    }
                }
                
                DB::statement('SET FOREIGN_KEY_CHECKS=1');
                
            } catch (\Exception $e) {
                DB::statement('SET FOREIGN_KEY_CHECKS=1');
                throw $e;
            }

            return response()->json([
                'success' => true,
                'message' => 'Database restored successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Restore failed: ' . $e->getMessage()
            ], 500);
        }
    }

    public function download($filename)
    {
        $path = storage_path('app/backups/' . $filename);
        if (file_exists($path)) {
            return response()->download($path);
        }
        abort(404);
    }

    public function destroy($filename)
    {
        if (Storage::delete('backups/' . $filename)) {
            return response()->json([
                'success' => true,
                'message' => 'Backup deleted successfully'
            ]);
        }
        return response()->json([
            'success' => false,
            'message' => 'Failed to delete backup'
        ], 500);
    }
    
    public function uploadBackup(Request $request)
    {
        try {
            // Validate the uploaded file
            $request->validate([
                'backupFile' => 'required|file|mimes:sql,gz,txt|max:204800', // Max 200MB
            ]);
            
            $uploadedFile = $request->file('backupFile');
            $originalName = $uploadedFile->getClientOriginalName();
            $extension = $uploadedFile->getClientOriginalExtension();
            
            // Generate a unique filename with timestamp
            $timestamp = Carbon::now()->format('Y-m-d-H-i-s');
            $filename = "uploaded-" . $timestamp . "." . $extension;
            
            // Ensure backups directory exists
            if (!Storage::exists('backups')) {
                Storage::makeDirectory('backups');
            }
            
            // Save file to backups directory
            $path = $uploadedFile->storeAs('backups', $filename);
            
            if (!$path) {
                throw new \Exception('Failed to save uploaded file');
            }
            
            // Process file based on extension
            $sqlContent = '';
            
            if ($extension === 'gz') {
                // If it's a gzipped file, uncompress it first
                $tempPath = storage_path('app/' . $path);
                $tempSqlPath = storage_path('app/backups/temp_' . $timestamp . '.sql');
                
                $gzippedData = file_get_contents($tempPath);
                $uncompressedData = gzdecode($gzippedData);
                
                if ($uncompressedData === false) {
                    throw new \Exception('Failed to decompress .gz file');
                }
                
                File::put($tempSqlPath, $uncompressedData);
                $sqlContent = file_get_contents($tempSqlPath);
                File::delete($tempSqlPath); // Clean up temp file
            } else {
                // Regular SQL file
                $sqlContent = Storage::get($path);
            }
            
            // Execute SQL statements
            $queries = array_filter(
                array_map('trim', 
                    preg_split("/;\s*[\r\n]+/", $sqlContent)
                )
            );
            
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
            
            try {
                foreach ($queries as $query) {
                    if (!empty($query)) {
                        DB::unprepared($query);
                    }
                }
                
                DB::statement('SET FOREIGN_KEY_CHECKS=1');
                
            } catch (\Exception $e) {
                DB::statement('SET FOREIGN_KEY_CHECKS=1');
                throw $e;
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Database backup uploaded and imported successfully',
                'filename' => $filename,
                'size' => $this->formatBytes(Storage::size($path)),
                'date' => Carbon::now()->toDateTimeString()
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Database backup upload failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Database backup upload failed: ' . $e->getMessage()
            ], 500);
        }
    }
}
