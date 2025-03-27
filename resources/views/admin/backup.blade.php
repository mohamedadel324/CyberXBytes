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

    <!-- Modal -->
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
            // Add new backup to the table
            const tbody = document.querySelector('tbody');
            if (tbody) {
              const downloadUrl = `/admin/backups/${data.filename}`;
              const newRow = `
                <tr class="border-b border-gray-800">
                  <td class="py-4 px-6">${data.filename}</td>
                  <td class="py-4 px-6">local</td>
                  <td class="py-4 px-6">
                    <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                  </td>
                  <td class="py-4 px-6">${data.size}</td>
                  <td class="py-4 px-6">${data.date}</td>
                  <td class="py-4 px-6">
                    <div class="flex space-x-2">
                      <a href="${downloadUrl}" class="text-blue-500 hover:text-blue-400">
                        Download
                      </a>
                      <button onclick="restoreBackup('${data.filename}')" class="text-green-500 hover:text-green-400">
                        Restore
                      </button>
                      <button onclick="deleteBackup('${data.filename}')" class="text-red-500 hover:text-red-400">
                        Delete
                      </button>
                    </div>
                  </td>
                </tr>
              `;
              tbody.insertAdjacentHTML('afterbegin', newRow);
            }

            // Show success message
            const successMsg = document.createElement('div');
            successMsg.className = 'fixed top-4 right-4 bg-green-500 text-white px-6 py-3 rounded shadow-lg z-50';
            successMsg.textContent = data.message;
            document.body.appendChild(successMsg);
            setTimeout(() => successMsg.remove(), 3000);
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
        if (!confirm('This will create a backup of your entire project including all files and database. Continue?')) {
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
                <p class="text-center text-white" id="backupStatus">Creating backup... This may take a while.</p>
                <div class="text-sm text-gray-400 text-center">Please don't close this window</div>
              </div>
            </div>
          </div>
        `;
        document.body.insertAdjacentHTML('beforeend', modalHtml);

        // Set a timeout to show additional message if it's taking too long
        const timeoutMessage = setTimeout(() => {
          const statusEl = document.getElementById('backupStatus');
          if (statusEl) {
            statusEl.innerHTML = 'Still working... Large projects may take several minutes.<br>Please be patient.';
          }
        }, 30000); // Show after 30 seconds

        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), 300000); // 5 minute timeout

        fetch('/admin/backup/full', {
          method: 'POST',
          headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
          },
          signal: controller.signal
        })
        .then(response => {
          clearTimeout(timeoutId);
          clearTimeout(timeoutMessage);
          
          // Remove the modal
          document.getElementById('backupModal')?.remove();

          if (!response.ok) {
            return response.json().then(data => {
              throw new Error(data.message || 'Network response was not ok');
            });
          }

          // Check if the response is JSON (error) or a file (success)
          const contentType = response.headers.get('content-type');
          if (contentType && contentType.includes('application/json')) {
            return response.json().then(data => {
              throw new Error(data.message || 'Backup failed');
            });
          }

          // If we get here, it's a file download
          return response.blob().then(blob => {
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.style.display = 'none';
            a.href = url;
            a.download = response.headers.get('content-disposition')?.split('filename=')[1]?.replace(/['"]/g, '') || 'backup.zip';
            document.body.appendChild(a);
            a.click();
            window.URL.revokeObjectURL(url);
            a.remove();
            
            // Show success message
            const successMsg = document.createElement('div');
            successMsg.className = 'fixed top-4 right-4 bg-green-500 text-white px-6 py-3 rounded shadow-lg z-50';
            successMsg.textContent = 'Backup created and downloaded successfully!';
            document.body.appendChild(successMsg);
            setTimeout(() => successMsg.remove(), 5000);
          });
        })
        .catch(error => {
          clearTimeout(timeoutId);
          clearTimeout(timeoutMessage);
          
          // Remove the modal
          document.getElementById('backupModal')?.remove();

          // Show error message in a modal
          const errorModalHtml = `
            <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" onclick="this.remove()">
              <div class="bg-darker p-6 rounded-lg max-w-2xl w-full mx-4" onclick="event.stopPropagation()">
                <h3 class="text-xl font-bold text-red-500 mb-4">Backup Failed</h3>
                <div class="bg-red-900 bg-opacity-20 p-4 rounded overflow-auto max-h-96">
                  <pre class="text-sm text-red-100 whitespace-pre-wrap">${
                    error.name === 'AbortError' 
                      ? 'The backup process timed out after 5 minutes. Your project might be too large for a single backup.\nTry excluding some directories or contact support for assistance.'
                      : error.message
                  }</pre>
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

      function restoreBackup(filename) {
        if (!confirm('Are you sure you want to restore this backup? This will overwrite your current database.')) {
          return;
        }

        const confirmButton = event.target;
        confirmButton.disabled = true;
        confirmButton.textContent = "Restoring...";

        fetch(`/admin/backups/${filename}/restore`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
          }
        })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            alert('Database restored successfully!');
          } else {
            alert('Failed to restore backup: ' + data.message);
          }
        })
        .catch(error => {
          alert('Error restoring backup: ' + error);
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
            window.location.reload(); // Refresh the page to update the list
          } else {
            alert('Failed to delete backup: ' + data.message);
          }
        })
        .catch(error => {
          alert('Error deleting backup: ' + error);
        });
      }

      // Close modal when clicking outside
      document.getElementById("backupModal").addEventListener("click", (e) => {
        if (e.target === e.currentTarget) {
          hideModal();
        }
      });
    </script>
  </body>
</html>