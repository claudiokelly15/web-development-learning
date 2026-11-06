<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Merchant;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    public function show(Request $request): View
    {
        return view('account.show', ['merchants' => Merchant::forMember($request->user())->get(['id', 'business_name', 'operational_status', 'verification_status'])]);
    }
}
