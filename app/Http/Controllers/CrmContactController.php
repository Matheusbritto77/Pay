<?php

namespace App\Http\Controllers;

use App\Models\CrmContact;
use Illuminate\Http\Request;

class CrmContactController extends Controller
{
    public function index(Request $request)
    {
        $teamId = auth()->user()->currentTeam->id;

        $contacts = CrmContact::forTeam($teamId)
            ->when($request->search, function ($q, $search) {
            $q->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                }
                );
            })
            ->withCount('leads')
            ->orderBy('name')
            ->paginate(50);

        return response()->json($contacts);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email',
        ]);

        $teamId = auth()->user()->currentTeam->id;

        $contact = CrmContact::create([
            'team_id' => $teamId,
            'name' => $request->name,
            'phone' => $request->phone,
            'email' => $request->email,
        ]);

        return response()->json($contact);
    }

    public function update(Request $request, CrmContact $contact)
    {
        if ($contact->team_id !== auth()->user()->currentTeam->id) {
            abort(403);
        }

        $contact->update($request->only(['name', 'phone', 'email', 'avatar_url']));

        return response()->json($contact);
    }

    public function destroy(CrmContact $contact)
    {
        if ($contact->team_id !== auth()->user()->currentTeam->id) {
            abort(403);
        }

        $contact->delete();

        return response()->json(['ok' => true]);
    }
}