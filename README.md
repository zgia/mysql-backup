# mysql-backup
Backup mysql DB, and email to gmail

# config.php

```php
<?php

namespace Neo\MySQLBackup;

function config()
{
    return [
        'mysqldump' => [
            '--databases' => 'lxy wan',
            '--ignore-table-data' => [
                'lxy.wp_commentmeta', 'lxy.wp_PluginManager',
                'wan.log', 'wan.logcontent',
            ],
        ],
        'smtp' => [
            'username' => 'xxx@163.com',
            'password' => 'xxx',
            'host' => 'smtp.163.com',
            'port' => '465',
            'frommail' => 'xxx@163.com',
            'fromname' => 'xxx',
        ],
        'recipients' => [
            'xxx@gmail.com',
        ],
        'cmd' => [
            'mysqldump' => '/usr/bin/mysqldump',
        ],
    ];
}
```