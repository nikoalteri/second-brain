<?php

namespace App\Console\Commands;

use App\Services\DataIntegrityAuditor;
use Illuminate\Console\Command;

class AuditDataIntegrity extends Command
{
    protected $signature = 'data:audit
        {--limit=25 : Maximum identifiers listed per check}
        {--json : Print the report as JSON}';

    protected $description = 'Read-only consistency checks on ownership, transfers and stored balances (prints identifiers only)';

    public function handle(DataIntegrityAuditor $auditor): int
    {
        $report = $auditor->run(max(1, (int) $this->option('limit')));
        $issues = array_sum(array_column($report, 'count'));

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return $issues > 0 ? self::FAILURE : self::SUCCESS;
        }

        foreach ($report as $key => $result) {
            if ($result['count'] === 0) {
                $this->line("<info>OK</info>    {$key}");

                continue;
            }

            $this->line("<error>FOUND</error> {$key}: {$result['count']} - {$result['description']}");
            $this->line('        ids: '.implode(', ', $result['ids']).($result['count'] > count($result['ids']) ? ', ...' : ''));
        }

        $this->newLine();
        $this->line($issues === 0 ? 'No inconsistencies found.' : "{$issues} inconsistent row(s) found. Nothing was modified.");

        return $issues > 0 ? self::FAILURE : self::SUCCESS;
    }
}
