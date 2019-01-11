<?php
namespace Ydalbj\Logger;

use Monolog\Formatter\ElasticaFormatter;

class ElasticaLineFormatter extends ElasticaFormatter
{
    protected function normalizeException($e)
    {
        $data = parent::normalizeException($e);
        if (isset($data['previous'])) {
            $data['previous'] = $this->jsonEncode($data['previous'], JSON_UNESCAPED_UNICODE);
        }

        return $data;
    }

    protected function normalize($data)
    {
        if (is_array($data) && isset($data['context']) && is_array($data['context'])) {
            $data['context_json'] = json_encode($data['context'], JSON_UNESCAPED_UNICODE);
            unset($data['context']);
        }

        return parent::normalize($data);
    }
}
