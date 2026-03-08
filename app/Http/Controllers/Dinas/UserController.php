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

    public function index()
    {
        $users = $this->userService->getAllPaginated(20);

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
            'password'   => 'required|string|min:8|confirmed',
            'role'       => 'required|in:admin_dinas,admin_sekolah,pengawas',
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
            'role'       => 'required|in:admin_dinas,admin_sekolah,pengawas',
            'sekolah_id' => 'nullable|exists:sekolah,id',
            'is_active'  => 'boolean',
            'password'   => 'nullable|string|min:8|confirmed',
        ]);

        $this->userService->updateUser($user, $data);

        return redirect()->route('dinas.users.index')
                         ->with('success', 'Data pengguna diperbarui.');
    }

    public function destroy(User $user)
    {
        $this->userService->deleteUser($user);

        return redirect()->route('dinas.users.index')
                         ->with('success', 'Pengguna dinonaktifkan.');
    }
}
