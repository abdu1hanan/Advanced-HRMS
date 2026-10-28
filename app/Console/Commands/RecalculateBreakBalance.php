<?php

namespace App\Console\Commands;

use App\Models\SlackMessage;
use App\Models\SlackUserMapping;
use Carbon\Carbon;
use Illuminate\Console\Command;

class RecalculateBreakBalance extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'breaks:recalculate {--days=30 : Number of past days to recalculate}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recalculate break balance flags for all users for past days using the configured daily break balance and grace period rules';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days = (int) $this->option('days');
        $this->info("Recalculating break balance for the past {$days} days...");

        $tz = 'Asia/Karachi';
        $today = Carbon::now($tz);

        $slackUserIds = SlackUserMapping::pluck('slack_user_id')
            ->merge(SlackMessage::distinct()->pluck('slack_user_id'))
            ->filter()
            ->unique();

        $count = 0;
        for ($i = 0; $i < $days; $i++) {
            $dateStr = $today->copy()->subDays($i)->format('Y-m-d');
            foreach ($slackUserIds as $slackUserId) {
                SlackMessage::recalculateBreakExceeded($slackUserId, $dateStr);
                $count++;
            }
        }

        $this->info("Successfully recalculated break balance for {$slackUserIds->count()} users across {$days} days ({$count} user-days total).");

        return Command::SUCCESS;
    }
}
