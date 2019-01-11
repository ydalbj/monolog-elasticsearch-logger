# monolog-es-logger
monolog-es-logger是基于laravel的monolog日志Elasticsearch存储通道

## 项目规范
-本项目所有文件使用Unix风格换行符LF

## 项目配置
-.gitattributes .text配置为eol=lf。强制使用Unix风格换行符
-git全局配置safecrlf=true，强制检查是否混合Windows和Unix风格换行符

## 使用

1. 固定monolog版本

    
    > 因monolog版本1.24存在无法写入Elasticsearch的问题，所以要在项目composer.json中固定monolog版本
    ```json
    "monolog/monolog": "1.23",
    ```

2. 引入类库

    ```shell
    $ composer require ydalbj/monolog-elasticsearch-logger
    ```

3. 修改日志配置

    * 修改laravel项目 config/logging.php, 增加自定义通道

    ```php
            'elasticsearch' => [
                'driver' => 'custom',
                'via' => \Wobatu\Logger\ElasticsearchLogger::class,
                'level' => 'debug',
                'connection' => [
                    'host' => 'localhost',
                    'port' => 9200,
                ],
                'index' => 'monolog',
                'days' => 7,
                // 'tags' => ['58cmd'],
            ],
    ```

    * 配置默认日志通道

        修改环境变量为`LOG_CHANNEL=elasticsearch` 或者配置stack通道，添加通道`elasticsearch`
