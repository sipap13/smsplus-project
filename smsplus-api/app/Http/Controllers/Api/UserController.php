<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index()
    {
        $users = DB::table('ra_t_users')
            ->select('id', 'email', 'direction', 'role', 'tel', 'actif', 'created_at')
            ->get();
        return response()->json($users);
    }

    public function store(Request $request)
    {
        $request->validate([
            'email'    => 'required|email|unique:ra_t_users,email',
            'password' => 'required|min:6',
            'role'     => 'required|in:ADMIN,ANALYSTE_OP,ANALYSTE_BUSS',
        ]);

        $id = DB::table('ra_t_users')->insertGetId([
            'email'     => $request->email,
            'password'  => Hash::make($request->password),
            'direction' => $request->direction ?? 'Assurance et Fraude',
            'role'      => $request->role,
            'tel'       => $request->tel ?? null,
            'actif'     => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(
            DB::table('ra_t_users')->select('id','email','direction','role','tel','actif','created_at')->find($id),
            201
        );
    }

    public function update(Request $request, $id)
    {
        $data = array_filter([
            'actif'      => isset($request->actif) ? (bool) $request->actif : null,
            'direction'  => $request->direction,
            'tel'        => $request->tel,
            'updated_at' => now(),
        ], fn($v) => $v !== null);

        DB::table('ra_t_users')->where('id', $id)->update($data);

        return response()->json(
            DB::table('ra_t_users')->select('id','email','direction','role','tel','actif','created_at')->find($id)
        );
    }
}
