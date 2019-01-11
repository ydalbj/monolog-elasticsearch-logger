<?php
namespace Ydalbj\Logger;

use Monolog\Logger;
use Monolog\Handler\ElasticSearchHandler;
use Monolog\Processor\MemoryPeakUsageProcessor;
use Monolog\Processor\MemoryUsageProcessor;
use Monolog\Processor\TagProcessor;
use Carbon\Carbon;
use Cache;

class ElasticsearchLogger
{
    private $client;
    private $index;
    /**
     * 创建一个 Monolog 实例。
     *
     * @return \Monolog\Logger
     */
    public function __invoke(array $config)
    {
        $connection = $config['connection'];
        $client = new \Elastica\Client($connection);
        $this->client = $client;

        $index_name = $config['index'];
        $this->indexCheck($index_name);

        $days = $config['days'] ?? 7;
        $this->clear($days);

        $log = new Logger('elasticsearch');

        $tags = $config['tags'] ?? null;
        if ($tags) {
            $log = $log->pushProcessor(new TagProcessor($tags));
        }
        $log = $log->pushProcessor(new MemoryUsageProcessor())->pushProcessor(new MemoryPeakUsageProcessor());

        $es_config = [];
        $es_config['index'] = $index_name;
        $es_config['type'] = '_doc';
        $level_name = $config['level'] ?? 'debug';
        $level = Logger::toMonologLevel($level_name);
        $handler = new ElasticSearchHandler($client, $es_config, $level);
        $handler->setFormatter(new ElasticaLineFormatter($es_config['index'], $es_config['type']));
        $log->pushHandler($handler);

        return $log;
    }

    /**
     * 判断索引是否存在
     */
    private function indexCheck($index_name)
    {
        $client = $this->client;
        $index = $client->getIndex($index_name);
        if ($index->exists()) {
            $this->index = $index;
            return;
        }

        $mappings = $this->getMappings();
        $params = [
            'index' => $index_name,
            'mappings' => ['_doc' => $mappings]
        ];

        $index->create($params);
        $this->index = $index;
    }

    /**
     * 映射
     */
    private function getMappings()
    {
        $data = [];
        $data['message'] = ['type' => 'keyword'];
        $data['level'] = ['type' => 'integer'];
        $data['level_name'] = ['type' => 'keyword'];
        $data['channel'] = ['type' => 'keyword'];
        $data['datetime'] = ['type' => 'date', 'format' => 'strict_date_optional_time'];

        $data['context'] = [
            'type' => 'nested',
            'properties' => [
                'exceptions' => [
                    'type' => 'object',
                    'properties' => [
                        'class' => ['type' => 'text', 'index' => false],
                        'message' => ['type' => 'text', 'index' => false],
                        'code' => ['type' => 'integer'],
                        'file' => ['type' => 'text', 'index' => false],
                        'faultcode' => ['type' => 'integer'],
                        'faultactor' => ['type' => 'text', 'index' => false],
                        'detail' => ['type' => 'text', 'index' => false],
                        'trace' => ['type' => 'text', 'index' => false],
                        'previous' => ['type' => 'text', 'index' => false],
                    ],
                ],
            ],
        ];

        $data['context_json'] = ['type' => 'text', 'index' => false];

        $data['extra'] = [
            'type' => 'nested',
            'properties' => [
                'tags' => ['type' => 'keyword'],
                'memory_peak_usage' => ['type' => 'text', 'index' => false],
                'memory_usage' => ['type' => 'text', 'index' => false],
            ]
        ];

        $mappings = ['properties' => $data];
        return $mappings;
    }

    /**
     * 清楚最近日志
     */
    private function clear(int $days)
    {
        $key = self::class . $days;
        $date = Cache::get($key);
        $current_date = date('Y-m-d');
        if ($date && $date === $current_date) {
            return;
        }

        $this->delete($days);
        $min = 24*60;
        Cache::put($key, $current_date, $min);
    }

    /**
     * 删除操作
     */
    private function delete(int $days)
    {
        $date = Carbon::parse("{$days} days ago")->format(\DateTime::ISO8601);
        $map = [
            'query' => [
                'range' => [
                    'datetime' => [
                        'lte' => $date,
                        'time_zone' => '+08:00',
                    ],
                ],
            ],
        ];
        $this->index->deleteByQuery($map);
    }
}
