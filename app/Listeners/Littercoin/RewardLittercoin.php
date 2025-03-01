<?php

namespace App\Listeners\Littercoin;

use App\Events\Littercoin\LittercoinMined;
use App\Events\TagsVerifiedByAdmin;
use App\Models\Littercoin;
use App\Models\Photo;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Redis;

class RewardLittercoin implements ShouldQueue
{
    /**
     * Increase the users Littercoin score
     *
     * Reward with Littercoin if criteria met
     *
     * @param  TagsVerifiedByAdmin  $event
     * @return void
     */
    public function handle (TagsVerifiedByAdmin $event)
    {
        $count = Redis::hincrby("user:$event->user_id", "littercoin_progress", 1);

        if ($count === 100)
        {
            $littercoin = Littercoin::create([
                'user_id' => $event->user_id,
                'photo_id' => $event->photo_id
            ]);

            Photo::where([
                'id' => $event->photo_id
            ])->update([
                'littercoin_id' => $littercoin->id
            ]);

            // Broadcast an event to anyone viewing the global map
            event (new LittercoinMined($event->user_id, '100-images-verified'));

            Redis::hset("user:$event->user_id", "littercoin_progress", 0);
        }
    }
}
