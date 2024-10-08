<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = auth()->user();
        $avatarUrl = $user->avatar;
        $request->user()->fill($request->validated());
        if ($request->hasFile('avatar')) {
            $avatarPath = $request->file('avatar')->store('avatars', 's3');
            // Xóa avatar cũ khỏi S3, nếu tồn tại
            if ($avatarUrl) {
                // Chuyển URL thành path trên S3
                $avatarUrl = str_replace(Storage::disk('s3')->url(''), '', $avatarUrl);
                // Xóa file avatar cũ
                Storage::disk('s3')->delete($avatarUrl);
            }
            if ($avatarPath) {
                // Cấp quyền công khai cho tệp
                Storage::disk('s3')->setVisibility($avatarPath, 'public');
                $avatarUrl = Storage::disk('s3')->url($avatarPath);
            }
        }

        $request->user()->avatar = $avatarUrl;

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
