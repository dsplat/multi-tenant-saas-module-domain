<?php

namespace MultiTenantSaas\Modules\Domain\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use MultiTenantSaas\Models\Tenant;

class UpdateTenantDomain extends Command
{
    protected $signature = 'tenant:update-domain
                            {old_domain : 旧自定义域名}
                            {new_domain : 新自定义域名}
                            {--regenerate-map : 同时重新生成 nginx 域名白名单}
                            {--map-output= : nginx map 文件输出路径}
                            {--reload-nginx : 重新加载 nginx}';

    protected $description = '更新租户自定义域名，并可选同步重新生成 nginx 白名单';

    public function handle(): int
    {
        $old = strtolower(trim($this->argument('old_domain')));
        $new = strtolower(trim($this->argument('new_domain')));

        $tenant = Tenant::where('custom_domain', $old)->first();

        if (!$tenant) {
            $this->error("未找到 custom_domain = '{$old}' 的租户。");
            $this->line('现有自定义域名：');
            Tenant::whereNotNull('custom_domain')->get(['name', 'custom_domain'])->each(
                fn ($t) => $this->line("  [{$t->name}] {$t->custom_domain}")
            );

            return self::FAILURE;
        }

        $this->info("租户：{$tenant->name}（ID: {$tenant->tenant_id}）");
        $this->line("  旧域名：{$old}");
        $this->line("  新域名：{$new}");

        if (!$this->confirm('确认更新？', true)) {
            $this->warn('已取消。');
            return self::SUCCESS;
        }

        $tenant->update(['custom_domain' => $new]);
        $this->info('✓ 数据库已更新。');

        if ($this->option('regenerate-map')) {
            $output = $this->option('map-output');
            $this->line('重新生成 nginx 白名单...');

            $params = [];
            if ($output) {
                $params['--output'] = $output;
            }
            if ($this->option('reload-nginx')) {
                $params['--reload'] = true;
            }

            $exitCode = Artisan::call('domains:generate-nginx-map', $params);

            if ($exitCode === 0) {
                $this->info('✓ nginx 白名单已重新生成。');
            } else {
                $this->warn('⚠ nginx 白名单生成时出现问题，请手动检查。');
            }
        } else {
            $this->warn('提示：请在服务器上执行以下命令更新 nginx 白名单：');
            $this->line('  php artisan domains:generate-nginx-map --reload');
        }

        return self::SUCCESS;
    }
}
