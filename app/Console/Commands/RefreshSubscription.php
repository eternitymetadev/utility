<?php

// app/Console/Commands/RefreshSubscription.php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class RefreshSubscription extends Command
{
    protected $signature = 'subscription:refresh';
    protected $description = 'Refresh the Microsoft Graph subscription';

    public function handle()
    {
        $subscriptionId = 'your_subscription_id'; // Retrieve this from storage or configuration
        app()->make('App\Http\Controllers\OAuthController')->refreshSubscription($subscriptionId);
    }
}
