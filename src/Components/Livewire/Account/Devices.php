<?php

namespace Shopper\Framework\Components\Livewire\Account;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Jenssegers\Agent\Agent;
use Livewire\Component;
use Stevebauman\Location\Facades\Location;

class Devices extends Component
{
    /**
     * Indicates if logout is being confirmed.
     *
     * @var bool
     */
    public $confirmingLogout = false;

    /**
     * The user's current password.
     *
     * @var string
     */
    public $password = '';

    /**
     * Confirm that the user would like to logout from other browser sessions.
     *
     * @return void
     */
    public function confirmLogout()
    {
        $this->password = '';

        $this->dispatchBrowserEvent('confirming-logout-other-browser-sessions');

        $this->confirmingLogout = true;
    }

    public function logoutOtherBrowserSessions()
    {
        $this->resetErrorBag();

        if (! Hash::check($this->password, auth()->user()->password)) {
            throw ValidationException::withMessages([
               'password' => [__('This password does not match our records.')],
            ]);
        }

        auth()->logoutOtherDevices($this->password);

        $this->deleteOtherSessionRecords();

        $this->confirmingLogout = false;

        $this->emit('loggedOut');
    }

    /**
     * Delete the other browser session records from storage.
     *
     * @return void
     */
    protected function deleteOtherSessionRecords()
    {
        if (config('session.driver') !== 'database') {
            return;
        }

        DB::table('sessions')
            ->where('user_id', auth()->user()->getKey())
            ->where('id', '!=', request()->session()->getId())
            ->delete();
    }

    /**
     * Get the current sessions.
     *
     * @return \Illuminate\Support\Collection
     */
    public function getSessionsProperty()
    {
        if (config('session.driver') !== 'database') {
            return collect();
        }

        return collect(
            DB::table('sessions')
                ->where('user_id', auth()->user()->getKey())
                ->orderBy('last_activity', 'desc')
                ->limit(3)
                ->get()
        )->map(function ($session) {
            return (object) [
                'agent' => $this->createAgent($session),
                'ip_address' => $session->ip_address,
                'is_current_device' => $session->id === request()->session()->getId(),
                'last_active' => Carbon::createFromTimestamp($session->last_activity)->diffForHumans(),
                'location' => Location::get($session->ip_address),
            ];
        });
    }

    /**
     * Create a new agent instance from the given session.
     *
     * @param  mixed  $session
     * @return \Jenssegers\Agent\Agent
     */
    protected function createAgent($session)
    {
        return tap(new Agent, function ($agent) use ($session) {
            $agent->setUserAgent($session->user_agent);
        });
    }

    /**
     * Render the component.
     *
     * @return \Illuminate\View\View
     */
    public function render()
    {
        return view('shopper::livewire.account.devices');
    }
}