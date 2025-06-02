<form method="POST" action="{{ route('admin.logout') }}" id="logout-form">
    @csrf
    <button type="submit" class="text-white bg-red-600 hover:bg-red-700 px-4 py-2 rounded-md">
        Logout
    </button>
</form> 