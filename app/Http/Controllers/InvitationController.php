<?php

namespace App\Http\Controllers;

use App\Mail\InvitationCreated;
use App\Models\Invitation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Str;

class InvitationController extends Controller
{

    protected StatefulGuard $guard;

    public function __construct(StatefulGuard $guard)
    {
        $this->guard = $guard;
        $this->authorizeResource(Invitation::class);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Inertia\Response|\Inertia\ResponseFactory
     */
    public function index()
    {
        request()->validate([
            'direction' => ['in:asc,desc', 'string'],
            'field' => ['in:name,created_at','string']
        ]);
        //This is necessary to enable sorting
        $query = Invitation::select(['invitations.*']);

        if(request()->has(['field', 'direction'])) {
            $query->orderBy(Str::of('invitations.')->append(request('field')), request('direction'));
        }

        return inertia('Invitations/Invitations', [
            'invitations' => $query->paginate(10)->through(fn($invitation) => [
                'id' => $invitation->invitable_id,
                'name' => $invitation->name,
                'email' => $invitation->email,
                'created_at' => Carbon::parse($invitation->created_at)->format('d.m.Y')
            ])
        ]);
    }

    public function invite() {
        return inertia('Invitations/Invite');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request)
    {
        $user = Auth::user();

        $token = createToken();

        $invitation = $user->invitations()->create([
            'name' => $request->name,
            'email' => $request->email,
            'token' => $token['hash'],
        ]);

        Mail::to($request->email)->send(new InvitationCreated($invitation, $user, $token['plain']));

        return Redirect::route('user.invitations')->with('success', 'Invitation created.');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Invitation  $invitation
     * @return \Inertia\Response|\Inertia\ResponseFactory
     */
    public function edit(Invitation $invitation)
    {
        return inertia('Invitations/InvitationEdit', [
            'invitation' => [
                'id' => $invitation->id,
                'name' => $invitation->invitation->name,
                'email' => $invitation->invitation->email,
            ]
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Invitation  $invitation
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request, Invitation $invitation)
    {
        $oldEmail = $invitation->email;
        $newMail = $request->input('email');

        $invitation->update($request->only('name', 'email'));

        if ($newMail !== $oldEmail) {
            $token = createToken();
            Mail::to($newMail)->send(new InvitationCreated($invitation, Auth::user(), $token['plain']));
            $invitation->update(['token' => $token['hash']]);
        }

        return Redirect::route('user.invitations')->with('success', 'Invitation updated.');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Invitation  $invitation
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(Invitation $invitation)
    {
        $invitation->delete();
        return Redirect::back()->with('success', 'Invitation deleted');
    }

    public function accept(Request $request) {
        return inertia('Invitations/Accept', [
            'token' => $request->query('token'),
            'email' => $request->query('email'),
        ]);
    }

    public function handle_accept(Request $request) {

        $invitation = Invitation::where('email', $request->email)->first();

        if (Hash::check($request->token, $invitation->token)) {

            $user = User::create([
                'name' => $invitation->name,
                'email' => $invitation->email,
                'phone_number' => $request->phone_number,
                'password' => Hash::make($request->password),
                'position' => $request->position,
                'business' => $request->business,
                'description' => $request->description
            ]);

            $user->givePermissionTo($request->permissions);

            $invitation->delete();

            $this->guard->login($user);

            return Redirect::to('/')->with('success', 'Herzlich Willkommen.');

        } else {
            abort(403);
        }
    }
}