<?php

declare(strict_types=1);

namespace Neo\MySQLBackup;

// Error Reporting
ini_set('display_errors', 'off');
error_reporting(E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED);
set_error_handler(function ($severity, $errstr, $errfile, $errline) {
    if (! (error_reporting() & $severity)) {
        return true;
    }
    throw new \ErrorException("{$errstr} in {$errfile} on line {$errline}", $severity, $severity, $errfile, $errline);
}, E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED);
set_exception_handler(function (\Throwable $ex) {exit($ex->getMessage() . PHP_EOL); });

// Default Timezone
date_default_timezone_set('Asia/Shanghai');

define('MB_WORKER_DIR', dirname(__FILE__));

// 参数配置
require MB_WORKER_DIR . '/config.php';

// Autoload
require MB_WORKER_DIR . '/vendor/autoload.php';

// 发邮件
require MB_WORKER_DIR . '/mail.php';

use Symfony\Component\Process\Process;

try {
    (new Backup(config(), MB_WORKER_DIR))->doit();
} catch (\Throwable $ex) {
    Backup::loginfo('失败，原因：' . $ex->getMessage());
}

echo PHP_EOL, PHP_EOL;
exit;

class Backup
{
    // 今天
    private string $today;

    private array $config;

    private string $dir;

    public function __construct(array $config, string $dir)
    {
        $this->config = $config;
        $this->dir = $dir;
    }

    public function doit()
    {
        $this->today = date('Ymd');

        chdir($this->dir);

        static::loginfo('导出数据库并压缩');

        $dbs = $this->config['mysqldump']['--databases'];
        $options = [
            '-c',
            '-B ' . $dbs,
        ];
        foreach ($this->config['mysqldump']['--ignore-table-data'] as $itd) {
            $options[] = '--ignore-table-data=' . $itd;
        }

        $file = $this->dir . '/' . $this->dump(str_replace(' ', '_', $dbs) . '-' . $this->today, $options);

        $subject = sprintf('[%s]-君欣欣兮乐康的备份文件', $this->today);
        $body = '备份数据库：' . $dbs;

        static::loginfo('发送邮件');
        $mail = new Mail($this->config['smtp']);
        $mail->send($subject, $body, $this->config['recipients'], [$file]);

        unlink($file);

        static::loginfo('成功备份');
    }

    public static function loginfo(string $txt)
    {
        echo date('[Y-m-d H:i:s]') . '-' . $txt . PHP_EOL;
    }

    /**
     * 使用 mysqldump 导出数据库，然后使用tar压缩
     *
     * @param string $basename 导出的文件名
     * @param array  $options  mysqldump 参数
     *
     * @return null|string 成功返回数据库的压缩文件名，失败返回null
     */
    public function dump(string $basename, array $options = [])
    {
        $sql = $basename . '.sql';
        $tar = $basename . '.tar.gz';

        // mysqldump -c -B lxy --ignore-table-data=lxy.wp_commentmeta --ignore-table-data=lxy.wp_PluginManager > lxy.sql
        $this->exec(sprintf('%s %s > %s', $this->config['cmd']['mysqldump'], implode(' ', $options), $sql));
        $this->exec(sprintf('tar zcf %s %s', $tar, $sql));
        $this->exec(sprintf('rm -f %s', $sql));

        return $tar;
    }

    public function exec(string $cmd)
    {
        static::loginfo($cmd);

        $process = Process::fromShellCommandline($cmd);
        $process->run();

        if (! $process->isSuccessful()) {
            $error = sprintf(
                'The command "%s" failed. Exit Code: %s(%s). Error Output: %s',
                $process->getCommandLine(),
                $process->getExitCode(),
                $process->getExitCodeText(),
                $process->getErrorOutput()
            );

            throw new \Exception($error, $process->getExitCode());
        }
    }
}
