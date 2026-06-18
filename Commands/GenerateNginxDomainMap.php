<?php

namespace MultiTenantSaas\Modules\Domain\Commands;

use Illuminate\Console\Command;
use MultiTenantSaas\Modules\Domain\Services\NginxConfigService;

class GenerateNginxDomainMap extends Command
{
    protected $signature = 'domains:generate-nginx-map
                          {--output= : 输出文件路径（默认：使用配置文件中的路径）}
                          {--reload : 生成后自动reload Nginx}';

    protected $description = '从数据库生成Nginx域名白名单map配置文件';

    public function handle(NginxConfigService $service): int
    {
        $this->info('开始生成Nginx域名白名单配置...');

        $outputPath = $this->option('output');

        $service->generateDomainWhitelistMap($outputPath);

        $finalPath = $outputPath ?? config('domain.nginx_map_file', '/etc/nginx/conf.d/allowed-domains.map');
        $this->info("✓ 配置文件已生成: {$finalPath}");

        if ($this->option('reload')) {
            $this->newLine();
            $this->info('正在reload Nginx配置...');

            $testResult = shell_exec('nginx -t 2>&1');
            $this->line($testResult);

            if (str_contains($testResult, 'syntax is ok') && str_contains($testResult, 'test is successful')) {
                if (PHP_OS_FAMILY === 'Darwin') {
                    $reloadResult = shell_exec('brew services reload nginx 2>&1');
                } else {
                    $reloadResult = shell_exec('sudo nginx -s reload 2>&1');
                }
                $this->info("✓ Nginx已reload: {$reloadResult}");
            } else {
                $this->error('✗ Nginx配置测试失败，未执行reload');
                return self::FAILURE;
            }
        } else {
            $this->newLine();
            $this->comment('提示：配置已生成，请手动reload Nginx：');
            if (PHP_OS_FAMILY === 'Darwin') {
                $this->line('  nginx -t && brew services reload nginx');
            } else {
                $this->line('  nginx -t && sudo nginx -s reload');
            }
        }

        return self::SUCCESS;
    }
}
