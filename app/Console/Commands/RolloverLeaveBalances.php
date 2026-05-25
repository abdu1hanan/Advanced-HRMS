<?php

namespace App\Console\Commands;

use App\Services\LeaveBalanceService;
use Illuminate\Console\Command;

class RolloverLeaveBalances extends Command
{
    protected $signature = 'hrms:rollover-leave {from_year} {to_year}';

    protected $description = 'Roll over leave balances from one year to the next with carry-forward rules';

    public function handle(LeaveBalanceService $service): int
    {
        $from = (int) $this->argument('from_year');
        $to = (int) $this->argument('to_year');

        if ($from >= $to) {
            $this->error('from_year must be less than to_year.');

            return self::FAILURE;
        }

        $service->rolloverYear($from, $to);

        $this->info("Leave balances rolled over from {$from} to {$to}.");

        return self::SUCCESS;
    }
}
