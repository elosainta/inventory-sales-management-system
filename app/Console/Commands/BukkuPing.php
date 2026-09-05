<?php

namespace App\Console\Commands;

use App\Support\Bukku;
use Illuminate\Console\Command;

/**
 * Confirms the invoice-scan credentials work, without printing them.
 *
 * Bukku publishes no API reference and the token lives only in the server
 * .env, so the one thing that cannot be settled while writing the code is
 * whether the base URL, the subdomain header and the token agree. This
 * settles it in one command on the box that holds them:
 *
 *     docker compose exec -T app php artisan bukku:ping
 *
 * A 401 means the token; a 404 on every path means BUKKU_BASE_URL; suppliers
 * reading zero while the rest works usually means the Company-Subdomain header
 * is wrong or unnecessary — try clearing BUKKU_SUBDOMAIN.
 */
class BukkuPing extends Command
{
    protected $signature = 'bukku:ping';

    protected $description = 'Check the invoice-scan credentials (Anthropic + Bukku) and refresh cached Bukku reference data';

    public function handle(): int
    {
        $ok = true;

        $this->line('');
        $this->line('  <options=bold>Invoice scan (BETA) — configuration check</>');
        $this->line('');

        // Never print a key. Whether it is set is the only question here.
        $anthropic = filled(config('services.anthropic.key'));
        $this->line(sprintf(
            '  %s  ANTHROPIC_API_KEY   %s',
            $anthropic ? '<fg=green>OK  </>' : '<fg=red>MISS</>',
            $anthropic ? 'set (model: ' . config('services.anthropic.model') . ')' : 'not set — scanning will fail',
        ));
        $ok = $ok && $anthropic;

        if (! Bukku::configured()) {
            $this->line('  <fg=red>MISS</>  BUKKU_API_TOKEN     not set — pushing to Bukku will fail');
            $this->line('');

            return self::FAILURE;
        }

        $this->line('  <fg=green>OK  </>  BUKKU_API_TOKEN     set');
        $this->line(sprintf('        base url            %s', config('services.bukku.base_url')));
        $this->line(sprintf('        subdomain header    %s', config('services.bukku.subdomain') ?: '(not sent)'));
        $this->line('');

        Bukku::forgetReferenceData();

        foreach (['suppliers' => 'contacts', 'accounts' => 'accounts', 'products' => 'products'] as $label => $method) {
            try {
                $count = count(Bukku::$method());
                $this->line(sprintf(
                    '  %s  %-18s %d found',
                    $count > 0 ? '<fg=green>OK  </>' : '<fg=yellow>WARN</>',
                    $label,
                    $count,
                ));
                $ok = $ok && $count > 0;
            } catch (\Throwable $e) {
                $this->line(sprintf('  <fg=red>FAIL</>  %-18s %s', $label, $e->getMessage()));
                $ok = false;
            }
        }

        $this->line('');
        $this->line($ok
            ? '  <fg=green>Ready.</> /invoice-scan will work for Owner and Head Chef.'
            : '  <fg=yellow>Not ready.</> Fix the lines above, then run this again.');
        $this->line('');

        return $ok ? self::SUCCESS : self::FAILURE;
    }
}
