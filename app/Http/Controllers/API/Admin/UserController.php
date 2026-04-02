<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    /**
     * Lista usuários pendentes de aprovação
     */
    public function pending()
    {
        $users = User::where('is_approved', false)
            ->where('role', 'agent')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($users);
    }

    /**
     * Lista todos os usuários
     */
    public function index(Request $request)
    {
        $query = User::query();

        if ($request->has('status')) {
            if ($request->status === 'pending') {
                $query->where('is_approved', false);
            } elseif ($request->status === 'approved') {
                $query->where('is_approved', true);
            }
        }

        if ($request->has('role')) {
            $query->where('role', $request->role);
        }

        $users = $query->orderBy('created_at', 'desc')->paginate(20);

        return response()->json($users);
    }

    /**
     * Aprovar um usuário
     */
    public function approve(User $user)
    {
        $admin = auth()->user();

        $user->update([
            'is_approved' => true,
            'approved_at' => now(),
            'approved_by' => $admin->id,
        ]);

        return response()->json([
            'message' => 'Usuário aprovado com sucesso.',
            'user' => $user,
        ]);
    }

    /**
     * Rejeitar/remover aprovação de um usuário
     */
    public function reject(User $user)
    {
        $user->update([
            'is_approved' => false,
            'approved_at' => null,
            'approved_by' => null,
        ]);

        return response()->json([
            'message' => 'Aprovação removida com sucesso.',
            'user' => $user,
        ]);
    }

    /**
     * Deletar um usuário
     */
    public function destroy(User $user)
    {
        // Não permitir deletar o próprio usuário
        if ($user->id === auth()->id()) {
            return response()->json([
                'message' => 'Você não pode deletar sua própria conta.'
            ], 403);
        }

        $user->delete();

        return response()->json([
            'message' => 'Usuário removido com sucesso.',
        ]);
    }
}
