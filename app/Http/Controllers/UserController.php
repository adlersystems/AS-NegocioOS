<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $users = User::query()
            ->when($request->string('search')?->toString(), function (Builder $query, string $search) {
                $query->where(fn (Builder $q) => $q
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%"));
            })
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        return view('users.index', ['users' => $users]);
    }

    public function create(): View
    {
        return view('users.form', ['user' => new User]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $user = User::create($request->validated());

        return redirect()
            ->route('users.index')
            ->with('success', __('app.flash.created', ['entity' => __('app.users.singular')]));
    }

    public function edit(User $user): View
    {
        return view('users.form', ['user' => $user]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $admin = auth()->user();

        if ($admin?->is($user) && (string) $request->input('role') !== User::ROLE_ADMIN) {
            return back()->withErrors(['role' => __('app.users.last_admin')]);
        }

        $data = $request->validated();

        if (empty($data['password'])) {
            unset($data['password']);
        }

        $user->update($data);

        return redirect()
            ->route('users.index')
            ->with('success', __('app.flash.updated', ['entity' => __('app.users.singular')]));
    }

    public function destroy(User $user): RedirectResponse
    {
        $admin = auth()->user();

        if ($admin?->is($user)) {
            return back()->withErrors(['delete' => __('app.users.move_own_account')]);
        }

        if ($user->role === User::ROLE_ADMIN && $this->isLastAdmin($user)) {
            return back()->withErrors(['delete' => __('app.users.last_admin')]);
        }

        $user->delete();

        return redirect()
            ->route('users.index')
            ->with('success', __('app.flash.deleted', ['entity' => __('app.users.singular')]));
    }

    private function isLastAdmin(User $excluding): bool
    {
        return User::query()
            ->where('role', User::ROLE_ADMIN)
            ->whereKeyNot($excluding->getKey())
            ->doesntExist();
    }
}
