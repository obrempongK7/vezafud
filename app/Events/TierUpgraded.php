<?php
namespace App\Events;
use Illuminate\Foundation\Events\Dispatchable;

class TierUpgraded
{
    use Dispatchable;
    public function __construct(public \App\Models\User $user, public string $tier) {}
}
