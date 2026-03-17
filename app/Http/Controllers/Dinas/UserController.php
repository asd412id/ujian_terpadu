<?php

namespace App\Http\Controllers\Dinas;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct(
        protected UserService $userService
    ) {}

    public function index(Request $request)
    {
        $role   = $request->input('role');
        $search = $request->input('search');
        $status = $request->filled('status') ? (bool) $request->input('status') : null;

        $users = $this->userService->getFilteredPaginated($role, $search, $status, 20);

        return view('dinas.users.index', compact('users'));
    }

    public function create()
    {
        $sekolahList = $this->userService->getActiveSekolahs();
        return view('dinas.users.form', compact('sekolahList'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'       => 'required|string|max:200',
            'email'      => 'required|email|unique:users',
            'password'   => 'required|string|min:8',
            'role'       => 'required|in:admin_dinas,admin_sekolah,pengawas,pembuat_soal',
            'sekolah_id' => 'nullable|exists:sekolah,id',
        ]);

        $this->userService->createUser($data);

        return redirect()->route('dinas.users.index')
                         ->with('success', 'Pengguna berhasil ditambahkan.');
    }

    public function edit(User $user)
    {
        $sekolahList = $this->userService->getActiveSekolahs();
        return view('dinas.users.form', compact('user', 'sekolahList'));
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'name'       => 'required|string|max:200',
            'email'      => 'required|email|unique:users,email,' . $user->id,
            'role'       => 'required|in:admin_dinas,admin_sekolah,pengawas,pembuat_soal',
            'sekolah_id' => 'nullable|exists:sekolah,id',
            'is_active'  => 'boolean',
            'password'   => 'nullable|string|min:8',
        ]);

        $this->userService->updateUser($user, $data);

        return redirect()->route('dinas.users.index')
                         ->with('success', 'Data pengguna diperbarui.');
    }

    public function destroy(User $user)
    {
        abort_if($user->id === auth()->id(), 403, 'Tidak dapat menghapus akun sendiri.');

        if (in_array($user->role, ['super_admin', 'admin_dinas'])) {
            $adminCount = User::whereIn('role', ['super_admin', 'admin_dinas'])->count();
            abort_if($adminCount <= 1, 403, 'Tidak dapat menghapus admin terakhir.');
        }

        $this->userService->deleteUser($user);

        return redirect()->route('dinas.users.index')
                         ->with('success', 'Pengguna berhasil dihapus.');
    }
}
