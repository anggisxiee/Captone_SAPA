<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\NomorWhatsapp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class TimAksesController extends Controller
{
    // Menampilkan daftar pengguna
    public function index()
    {
        $pengguna = User::with('aksesWhatsapp.nomorWhatsapp')
            ->orderBy('name')
            ->get();

        $nomorWhatsapp = NomorWhatsapp::orderBy('nama')->get();

        return view('tim-akses', compact(
            'pengguna',
            'nomorWhatsapp'
        ));
    }

    // Menyimpan pengguna baru
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:8',
            'role' => [
                'required',
                Rule::in(['Agen/CS', 'Supervisor', 'Administrator']),
            ],
            'akses' => 'nullable|array',
            'akses.*' => [
                'nullable',
                Rule::in([
                    'Tidak ada',
                    'Lihat saja',
                    'Balas chat',
                    'Kelola',
                ]),
            ],
        ]);

        DB::transaction(function () use ($validated) {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'role' => $validated['role'],
                'status' => 'Aktif',
            ]);

            foreach ($validated['akses'] ?? [] as $nomorId => $hakAkses) {
                if (!$hakAkses) {
                    continue;
                }

                $user->aksesWhatsapp()->create([
                    'nomor_whatsapp_id' => $nomorId,
                    'hak_akses' => $hakAkses,
                ]);
            }
        });

        return redirect()
            ->route('tim-akses')
            ->with('success', 'Pengguna berhasil ditambahkan.');
    }

    // Menampilkan data pengguna untuk diedit
    public function edit(User $user)
    {
        $user->load('aksesWhatsapp');

        $nomorWhatsapp = NomorWhatsapp::orderBy('nama')->get();

        return view('tim-akses', [
            'pengguna' => User::with('aksesWhatsapp.nomorWhatsapp')
                ->orderBy('name')
                ->get(),
            'nomorWhatsapp' => $nomorWhatsapp,
            'editUser' => $user,
        ]);
    }

    // Memperbarui data pengguna
    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'password' => 'nullable|string|min:8',
            'role' => [
                'required',
                Rule::in(['Agen/CS', 'Supervisor', 'Administrator']),
            ],
            'status' => [
                'required',
                Rule::in(['Aktif', 'Nonaktif']),
            ],
            'akses' => 'nullable|array',
            'akses.*' => [
                'nullable',
                Rule::in([
                    'Tidak ada',
                    'Lihat saja',
                    'Balas chat',
                    'Kelola',
                ]),
            ],
        ]);

        DB::transaction(function () use ($validated, $user) {
            $data = [
                'name' => $validated['name'],
                'email' => $validated['email'],
                'role' => $validated['role'],
                'status' => $validated['status'],
            ];

            if (!empty($validated['password'])) {
                $data['password'] = Hash::make($validated['password']);
            }

            $user->update($data);

            // Perbarui hak akses nomor WhatsApp
            $user->aksesWhatsapp()->delete();

            foreach ($validated['akses'] ?? [] as $nomorId => $hakAkses) {
                $user->aksesWhatsapp()->create([
                    'nomor_whatsapp_id' => $nomorId,
                    'hak_akses' => $hakAkses,
                ]);
            }
        });

        return redirect()
            ->route('tim-akses')
            ->with('success', 'Data pengguna berhasil diperbarui.');
    }

    // Mengubah status aktif/nonaktif
    public function toggleStatus(User $user)
    {
        $user->update([
            'status' => $user->status === 'Aktif'
                ? 'Nonaktif'
                : 'Aktif',
        ]);

        return redirect()
            ->route('tim-akses')
            ->with('success', 'Status pengguna berhasil diperbarui.');
    }

    // Menghapus pengguna
    public function destroy(User $user)
    {
        $user->delete();

        return redirect()
            ->route('tim-akses')
            ->with('success', 'Pengguna berhasil dihapus.');
    }
}
