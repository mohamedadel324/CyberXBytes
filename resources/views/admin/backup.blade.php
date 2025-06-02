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
              onclick="showUploadModal()"
            >
              Upload Database Backup
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
                    <button onclick="restoreBackup('{{ $backup['name'] }}')" class="text-green-500 hover:text-green-400">
                      Restore
                    </button>
                    <button onclick="deleteBackup('{{ $backup['name'] }}')" class="text-red-500 hover:text-red-400">
                      Delete
                    </button>
                  </div>
                </td>
              </tr>
              @empty
              <tr class="border-t border-gray-700">
                <td colspan="7" class="py-4 px-6 text-center text-gray-400">
                  No backups found
                </td>
              </tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Backup Create Modal -->
    <div
      id="backupModal"
      class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center"
    >
      <div class="bg-dark rounded-lg p-6 max-w-md w-full mx-4">
        <h2 class="text-xl font-bold mb-4">Create Backup</h2>
        <p class="text-gray-400 mb-4">
          Create a backup of your MySQL database:
        </p>

        <div class="flex justify-end space-x-3">
          <button
            id="cancelBackup"
            class="px-4 py-2 rounded-lg border border-gray-700 hover:bg-table-header"
            onclick="hideModal()"
          >
            Cancel
          </button>
          <button
            id="confirmBackup"
            class="px-4 py-2 rounded-lg bg-blue-500 hover:bg-blue-600"
            onclick="handleBackupConfirmation()"
          >
            Create Backup
          </button>
        </div>
      </div>
    </div>

    <!-- Upload Modal -->    
    <div
      id="uploadModal"
      class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center"
    >
      <div class="bg-dark rounded-lg p-6 max-w-md w-full mx-4">
        <h2 class="text-xl font-bold mb-4">Upload Database Backup</h2>
        <p class="text-gray-400 mb-4">
          Upload a SQL dump file to restore your database.
        </p>

        <form id="uploadBackupForm" enctype="multipart/form-data">
          <div class="mb-4">
            <label class="block text-gray-300 mb-2" for="backupFile">Select backup file (.sql)</label>
            <input 
              type="file" 
              id="backupFile" 
              name="backupFile" 
              accept=".sql,.sql.gz,.gz"
              class="w-full p-2 border border-gray-700 rounded-lg bg-darker text-white"
              required
            >
          </div>
          
          <div class="flex justify-end space-x-3 mt-6">
            <button
              type="button"
              class="px-4 py-2 rounded-lg border border-gray-700 hover:bg-table-header"
              onclick="hideUploadModal()"
            >
              Cancel
            </button>
            <button
              type="submit"
              class="px-4 py-2 rounded-lg bg-green-500 hover:bg-green-600 text-white"
            >
              Upload & Restore
            </button>
          </div>
        </form>
      </div>
    </div>
    
    <script>
      function formatBytes(bytes, decimals = 2) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const dm = decimals < 0 ? 0 : decimals;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
      }

      function showModal() {
        const modal = document.getElementById("backupModal");
        modal.classList.remove("hidden");
        modal.classList.add("flex");
      }
      
      function showUploadModal() {
        const modal = document.getElementById("uploadModal");
        modal.classList.remove("hidden");
        modal.classList.add("flex");
      }
      
      function hideUploadModal() {
        const modal = document.getElementById("uploadModal");
        modal.classList.remove("flex");
        modal.classList.add("hidden");
      }

      function hideModal() {
        const modal = document.getElementById("backupModal");
        modal.classList.remove("flex");
        modal.classList.add("hidden");
      }

      function handleBackupConfirmation() {
        const confirmButton = document.getElementById("confirmBackup");
        confirmButton.disabled = true;
        confirmButton.textContent = "Creating Backup...";

        fetch('/admin/backups', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
          }
        })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            hideModal();
            window.location.reload(); // Refresh the page to show new backup
          } else {
            alert('Failed to create backup: ' + data.message);
          }
        })
        .catch(error => {
          alert('Error creating backup: ' + error);
        })
        .finally(() => {
          confirmButton.disabled = false;
          confirmButton.textContent = "Create Backup";
        });
      }

      function createBackup() {
        if (!confirm('Create database backup?')) {
          return;
        }

        // Show loading modal
        const modalHtml = `
          <div id="backupModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div class="bg-darker p-6 rounded-lg max-w-md w-full mx-4">
              <h3 class="text-xl font-bold text-white mb-4">Creating Database Backup</h3>
              <div class="space-y-4">
                <div class="flex items-center justify-center">
                  <svg class="animate-spin h-8 w-8 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                  </svg>
                </div>
                <p class="text-center text-white">Creating database backup...</p>
              </div>
            </div>
          </div>
        `;
        document.body.insertAdjacentHTML('beforeend', modalHtml);

        fetch('/admin/backups', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
          }
        })
        .then(response => response.json())
        .then(data => {
          // Remove loading modal
          document.getElementById('backupModal')?.remove();

          if (data.success) {
            // Show success notification
            const successMsg = document.createElement('div');
            successMsg.id = 'backupSuccessMsg';
            successMsg.className = 'fixed top-4 right-4 bg-green-500 text-white px-6 py-3 rounded shadow-lg z-50 transform transition-transform duration-300';
            successMsg.style.opacity = '0';
            successMsg.innerHTML = `
              <div class="flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                <span>Database backup created successfully!</span>
              </div>
            `;
            document.body.appendChild(successMsg);
            
            // Animate in
            setTimeout(() => {
              successMsg.style.opacity = '1';
            }, 10);
            
            // Remove success message after 3 seconds
            setTimeout(() => {
              const msg = document.getElementById('backupSuccessMsg');
              if (msg) {
                msg.style.opacity = '0';
                setTimeout(() => msg.remove(), 500);
              }
            }, 3000);
            
            // Refresh the backups table
            window.location.reload();
          } else {
            throw new Error(data.message);
          }
        })
        .catch(error => {
          // Remove loading modal
          document.getElementById('backupModal')?.remove();

          // Show error message
          const errorModalHtml = `
            <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" onclick="this.remove()">
              <div class="bg-darker p-6 rounded-lg max-w-2xl w-full mx-4" onclick="event.stopPropagation()">
                <h3 class="text-xl font-bold text-red-500 mb-4">Backup Failed</h3>
                <div class="bg-red-900 bg-opacity-20 p-4 rounded overflow-auto max-h-96">
                  <pre class="text-sm text-red-100 whitespace-pre-wrap">${error.message}</pre>
                </div>
                <div class="mt-4 flex justify-end">
                  <button class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded" onclick="this.closest('.fixed').remove()">Close</button>
                </div>
              </div>
            </div>
          `;
          document.body.insertAdjacentHTML('beforeend', errorModalHtml);
        });
      }

      function createFullBackup() {
        if (!confirm('This will create a backup of your entire project including all files and database (excluding vendor folder). Continue?')) {
          return;
        }

        // Create loading modal
        const modalHtml = `
          <div id="backupModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div class="bg-darker p-6 rounded-lg max-w-md w-full mx-4">
              <h3 class="text-xl font-bold text-white mb-4">Creating Full Backup</h3>
              <div class="space-y-4">
                <div class="flex items-center justify-center">
                  <svg class="animate-spin h-8 w-8 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                  </svg>
                </div>
                <p class="text-center text-white" id="backupStatus">Step 1/2: Creating database backup...</p>
                <div class="text-sm text-gray-400 text-center mb-2">Please don't close this window</div>
                <div id="backupTimeoutMessage" class="hidden">
                  <div class="mt-2 p-3 bg-yellow-900 bg-opacity-40 rounded border border-yellow-700">
                    <p class="text-yellow-400 text-sm">Taking longer than expected?</p>
                    <div class="mt-2 flex space-x-2 justify-between">
                      <button id="cancelBackupBtn" class="text-xs bg-red-600 hover:bg-red-700 text-white px-2 py-1 rounded">
                        Cancel
                      </button>
                      <button id="continueWaitBtn" class="text-xs bg-blue-600 hover:bg-blue-700 text-white px-2 py-1 rounded">
                        Continue Waiting
                      </button>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        `;
        document.body.insertAdjacentHTML('beforeend', modalHtml);

        // Set timeout to show alternate options if taking too long
        const timeoutId = setTimeout(() => {
          const timeoutEl = document.getElementById('backupTimeoutMessage');
          if (timeoutEl) {
            timeoutEl.classList.remove('hidden');
          }
        }, 30000); // 30 seconds timeout

        // Add event listeners
        document.getElementById('cancelBackupBtn')?.addEventListener('click', () => {
          clearTimeout(timeoutId);
          document.getElementById('backupModal')?.remove();
          
          const cancelMsg = document.createElement('div');
          cancelMsg.className = 'fixed top-4 right-4 bg-red-500 text-white px-6 py-3 rounded shadow-lg z-50';
          cancelMsg.innerHTML = 'Backup operation cancelled.';
          document.body.appendChild(cancelMsg);
          setTimeout(() => cancelMsg.remove(), 3000);
        });

        document.getElementById('continueWaitBtn')?.addEventListener('click', () => {
          document.getElementById('backupTimeoutMessage').classList.add('hidden');
          document.getElementById('backupStatus').innerHTML = 'Still working... Large projects may take several minutes.<br><span class="text-xs">Please be patient</span>';
        });

        // Step 1: First create database backup
        fetch('/admin/backups', {
          method: 'POST',
          headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
          }
        })
        .then(response => response.json())
        .then(data => {
          if (!data.success) {
            throw new Error(data.message || 'Database backup failed');
          }
          
          // Update status message for step 2
          const statusEl = document.getElementById('backupStatus');
          if (statusEl) {
            statusEl.textContent = 'Step 2/2: Creating full backup (excluding vendor)...';
          }
          
          // Create a form for direct download in a new tab
          const form = document.createElement('form');
          form.method = 'POST';
          form.action = '/admin/backup/full';
          form.target = '_blank'; // Open in new tab for large downloads
          
          // Add CSRF token
          const csrfInput = document.createElement('input');
          csrfInput.type = 'hidden';
          csrfInput.name = '_token';
          csrfInput.value = document.querySelector('meta[name="csrf-token"]').content;
          form.appendChild(csrfInput);
          
          // Add exclude vendor parameter
          const excludeInput = document.createElement('input');
          excludeInput.type = 'hidden';
          excludeInput.name = 'exclude_vendor';
          excludeInput.value = '1';
          form.appendChild(excludeInput);
          
          // Add direct download parameter
          const downloadInput = document.createElement('input');
          downloadInput.type = 'hidden';
          downloadInput.name = 'direct_download';
          downloadInput.value = '1';
          form.appendChild(downloadInput);
          
          // Submit the form
          document.body.appendChild(form);
          form.submit();
          
          // Remove the form after submission
          setTimeout(() => form.remove(), 100);
          
          // Show success message
          setTimeout(() => {
            clearTimeout(timeoutId);
            document.getElementById('backupModal')?.remove();
            
            const successMsg = document.createElement('div');
            successMsg.className = 'fixed top-4 right-4 bg-green-500 text-white px-6 py-3 rounded shadow-lg z-50';
            successMsg.innerHTML = `
              <div class="flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                <span>Backup created! Check your browser for download</span>
              </div>
              <div class="mt-2 text-sm">
                <button onclick="retryFullBackup()" class="underline hover:text-white">Download didn't start? Click here</button>
              </div>
            `;
            document.body.appendChild(successMsg);
            
            // Remove success message after 30 seconds
            setTimeout(() => successMsg.remove(), 30000);
          }, 3000);
        })
        .catch(error => {
          // Clear timeout and remove the modal
          clearTimeout(timeoutId);
          document.getElementById('backupModal')?.remove();

          // Show error message
          const errorModalHtml = `
            <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" onclick="this.remove()">
              <div class="bg-darker p-6 rounded-lg max-w-2xl w-full mx-4" onclick="event.stopPropagation()">
                <h3 class="text-xl font-bold text-red-500 mb-4">Backup Failed</h3>
                <div class="bg-red-900 bg-opacity-20 p-4 rounded overflow-auto max-h-96">
                  <pre class="text-sm text-red-100 whitespace-pre-wrap">${error.message}</pre>
                </div>
                <div class="mt-4 flex justify-end">
                  <button class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded" onclick="this.closest('.fixed').remove()">Close</button>
                </div>
              </div>
            </div>
          `;
          document.body.insertAdjacentHTML('beforeend', errorModalHtml);
        });
      }

      // Function to retry full backup download
      function retryFullBackup() {
        // Create a form for direct download in a new tab
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '/admin/backup/full';
        form.target = '_blank';
        
        // Add CSRF token
        const csrfInput = document.createElement('input');
        csrfInput.type = 'hidden';
        csrfInput.name = '_token';
        csrfInput.value = document.querySelector('meta[name="csrf-token"]').content;
        form.appendChild(csrfInput);
        
        // Add exclude vendor parameter
        const excludeInput = document.createElement('input');
        excludeInput.type = 'hidden';
        excludeInput.name = 'exclude_vendor';
        excludeInput.value = '1';
        form.appendChild(excludeInput);
        
        // Add direct download parameter
        const downloadInput = document.createElement('input');
        downloadInput.type = 'hidden';
        downloadInput.name = 'direct_download';
        downloadInput.value = '1';
        form.appendChild(downloadInput);
        
        // Submit the form
        document.body.appendChild(form);
        form.submit();
        
        // Show info message
        const infoMsg = document.createElement('div');
        infoMsg.className = 'fixed top-4 right-4 bg-blue-500 text-white px-6 py-3 rounded shadow-lg z-50';
        infoMsg.innerHTML = `
          <div class="flex items-center">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <span>Download retry initiated in new tab</span>
          </div>
        `;
        document.body.appendChild(infoMsg);
        setTimeout(() => {
          infoMsg.remove();
          form.remove();
        }, 5000);
      }

      function restoreBackup(filename) {
        if (!confirm('Are you sure you want to restore this backup? This will overwrite your current database.')) {
          return;
        }

        const confirmButton = event.target;
        confirmButton.disabled = true;
        confirmButton.textContent = "Restoring...";

        // Show loading modal
        const modalHtml = `
          <div id="restoreModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div class="bg-darker p-6 rounded-lg max-w-md w-full mx-4">
              <h3 class="text-xl font-bold text-white mb-4">Restoring Backup</h3>
              <div class="space-y-4">
                <div class="flex items-center justify-center">
                  <svg class="animate-spin h-8 w-8 text-green-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                  </svg>
                </div>
                <p class="text-center text-white">Restoring database from backup...</p>
              </div>
            </div>
          </div>
        `;
        document.body.insertAdjacentHTML('beforeend', modalHtml);

        fetch(`/admin/backups/${filename}/restore`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
          }
        })
        .then(response => response.json())
        .then(data => {
          // Remove loading modal
          document.getElementById('restoreModal')?.remove();
          
          if (data.success) {
            // Show success notification
            const successMsg = document.createElement('div');
            successMsg.id = 'restoreSuccessMsg';
            successMsg.className = 'fixed top-4 right-4 bg-green-500 text-white px-6 py-3 rounded shadow-lg z-50 transform transition-transform duration-300';
            successMsg.style.opacity = '0';
            successMsg.innerHTML = `
              <div class="flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                <span>Database restored successfully!</span>
              </div>
            `;
            document.body.appendChild(successMsg);
            
            // Animate in
            setTimeout(() => {
              successMsg.style.opacity = '1';
            }, 10);
            
            // Remove success message after 3 seconds and reload
            setTimeout(() => {
              const msg = document.getElementById('restoreSuccessMsg');
              if (msg) {
                msg.style.opacity = '0';
                setTimeout(() => {
                  msg.remove();
                  window.location.reload(); // Refresh page after notification
                }, 500);
              }
            }, 3000);
          } else {
            throw new Error(data.message || 'Restore failed');
          }
        })
        .catch(error => {
          // Remove loading modal
          document.getElementById('restoreModal')?.remove();
          
          // Show error message
          const errorModalHtml = `
            <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" onclick="this.remove()">
              <div class="bg-darker p-6 rounded-lg max-w-2xl w-full mx-4" onclick="event.stopPropagation()">
                <h3 class="text-xl font-bold text-red-500 mb-4">Restore Failed</h3>
                <div class="bg-red-900 bg-opacity-20 p-4 rounded overflow-auto max-h-96">
                  <pre class="text-sm text-red-100 whitespace-pre-wrap">${error.message}</pre>
                </div>
                <div class="mt-4 flex justify-end">
                  <button class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded" onclick="this.closest('.fixed').remove()">Close</button>
                </div>
              </div>
            </div>
          `;
          document.body.insertAdjacentHTML('beforeend', errorModalHtml);
        })
        .finally(() => {
          confirmButton.disabled = false;
          confirmButton.textContent = "Restore";
        });
      }

      function deleteBackup(filename) {
        if (!confirm('Are you sure you want to delete this backup?')) {
          return;
        }

        // Show loading indicator
        const deleteButton = event.target;
        deleteButton.disabled = true;
        deleteButton.textContent = "Deleting...";

        fetch(`/admin/backups/${filename}`, {
          method: 'DELETE',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
          }
        })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            // Show success notification
            const successMsg = document.createElement('div');
            successMsg.id = 'deleteSuccessMsg';
            successMsg.className = 'fixed top-4 right-4 bg-green-500 text-white px-6 py-3 rounded shadow-lg z-50 transform transition-transform duration-300';
            successMsg.style.opacity = '0';
            successMsg.innerHTML = `
              <div class="flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                <span>Backup deleted successfully!</span>
              </div>
            `;
            document.body.appendChild(successMsg);
            
            // Animate in
            setTimeout(() => {
              successMsg.style.opacity = '1';
            }, 10);
            
            // Remove success message after 2 seconds and reload
            setTimeout(() => {
              const msg = document.getElementById('deleteSuccessMsg');
              if (msg) {
                msg.style.opacity = '0';
                setTimeout(() => {
                  msg.remove();
                  window.location.reload(); // Refresh the page to update the list
                }, 500);
              }
            }, 2000);
          } else {
            throw new Error(data.message || 'Delete failed');
          }
        })
        .catch(error => {
          // Show error message
          const errorModalHtml = `
            <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" onclick="this.remove()">
              <div class="bg-darker p-6 rounded-lg max-w-2xl w-full mx-4" onclick="event.stopPropagation()">
                <h3 class="text-xl font-bold text-red-500 mb-4">Delete Failed</h3>
                <div class="bg-red-900 bg-opacity-20 p-4 rounded overflow-auto max-h-96">
                  <pre class="text-sm text-red-100 whitespace-pre-wrap">${error.message}</pre>
                </div>
                <div class="mt-4 flex justify-end">
                  <button class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded" onclick="this.closest('.fixed').remove()">Close</button>
                </div>
              </div>
            </div>
          `;
          document.body.insertAdjacentHTML('beforeend', errorModalHtml);
        })
        .finally(() => {
          deleteButton.disabled = false;
          deleteButton.textContent = "Delete";
        });
      }

      // Close modal when clicking outside
      document.getElementById("backupModal").addEventListener("click", (e) => {
        if (e.target === e.currentTarget) {
          hideModal();
        }
      });
      
      // Close upload modal when clicking outside
      document.getElementById("uploadModal").addEventListener("click", (e) => {
        if (e.target === e.currentTarget) {
          hideUploadModal();
        }
      });
      
      // Handle upload form submission
      document.getElementById("uploadBackupForm").addEventListener("submit", function(e) {
        e.preventDefault();
        
        const fileInput = document.getElementById("backupFile");
        const file = fileInput.files[0];
        
        if (!file) {
          alert("Please select a backup file first.");
          return;
        }
        
        // Create FormData object
        const formData = new FormData();
        formData.append("backupFile", file);
        formData.append("_token", document.querySelector('meta[name="csrf-token"]').content);
        
        // Show loading modal
        const modalHtml = `
          <div id="uploadingModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div class="bg-darker p-6 rounded-lg max-w-md w-full mx-4">
              <h3 class="text-xl font-bold text-white mb-4">Uploading Database Backup</h3>
              <div class="space-y-4">
                <div class="flex items-center justify-center">
                  <svg class="animate-spin h-8 w-8 text-green-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                  </svg>
                </div>
                <p class="text-center text-white">Uploading and processing backup file...</p>
                <div class="text-sm text-gray-400 text-center">This may take a few moments.</div>
              </div>
            </div>
          </div>
        `;
        document.body.insertAdjacentHTML('beforeend', modalHtml);
        hideUploadModal();
        
        // Send the request
        fetch('/admin/backups/upload', {
          method: 'POST',
          body: formData,
        })
        .then(response => response.json())
        .then(data => {
          // Remove loading modal
          document.getElementById('uploadingModal')?.remove();
          
          if (data.success) {
            // Show success notification
            const successMsg = document.createElement('div');
            successMsg.className = 'fixed top-4 right-4 bg-green-500 text-white px-6 py-3 rounded shadow-lg z-50';
            successMsg.innerHTML = `
              <div class="flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                <span>Database backup successfully uploaded and imported!</span>
              </div>
            `;
            document.body.appendChild(successMsg);
            
            // Remove success message after 3 seconds and reload
            setTimeout(() => {
              successMsg.remove();
              window.location.reload(); // Refresh page
            }, 3000);
          } else {
            throw new Error(data.message || 'Upload failed');
          }
        })
        .catch(error => {
          // Remove loading modal
          document.getElementById('uploadingModal')?.remove();
          
          // Show error message
          const errorModalHtml = `
            <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" onclick="this.remove()">
              <div class="bg-darker p-6 rounded-lg max-w-2xl w-full mx-4" onclick="event.stopPropagation()">
                <h3 class="text-xl font-bold text-red-500 mb-4">Upload Failed</h3>
                <div class="bg-red-900 bg-opacity-20 p-4 rounded overflow-auto max-h-96">
                  <pre class="text-sm text-red-100 whitespace-pre-wrap">${error.message}</pre>
                </div>
                <div class="mt-4 flex justify-end">
                  <button class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded" onclick="this.closest('.fixed').remove()">Close</button>
                </div>
              </div>
            </div>
          `;
          document.body.insertAdjacentHTML('beforeend', errorModalHtml);
        });
      });
    </script>
  </body>
</html>