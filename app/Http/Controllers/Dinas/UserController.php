<?php

namespace App\Http\Controllers\Dinas;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Sekolah;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index()
    {
        $users = User::with('sekolah')
            ->orderBy('role')
            ->paginate(20);

        return view('dinas.users.index', compact('users'));
    }

    public function create()
    {
        $sekolahList = Sekolah::where('is_active', true)->orderBy('nama')->get();
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

        $data['password'] = Hash::make($data['password']);
        User::create($data);

        return redirect()->route('dinas.users.index')
                         ->with('success', 'Pengguna berhasil ditambahkan.');
    }

    public function edit(User $user)
    {
        $sekolahList = Sekolah::where('is_active', true)->orderBy('nama')->get();
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

        if ($request->filled('password')) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $user->update($data);
        return redirect()->route('dinas.users.index')
                         ->with('success', 'Data pengguna diperbarui.');
    }

    public function destroy(User $user)
    {
        $user->update(['is_active' => false]);
        return redirect()->route('dinas.users.index')
                         ->with('success', 'Pengguna dinonaktifkan.');
    }
}
