<?php

declare(strict_types=1);

/**
 * SPDX-License-Identifier: Apache-2.0
 *
 * The OpenSearch Contributors require contributions made to
 * this file be licensed under the Apache-2.0 license or a
 * compatible open source license.
 *
 * Modifications Copyright OpenSearch Contributors. See
 * GitHub history for details.
 */

namespace OpenSearch\Endpoints;

use OpenSearch\Serializers\SerializerInterface;

/**
 * NOTE: This file is autogenerated using util/GenerateEndpoints.php
 */
class BulkStream extends AbstractEndpoint
{
    public function __construct(SerializerInterface $serializer)
    {
        $this->serializer = $serializer;
    }

    public function getURI(): string
    {
        $index = $this->index ?? null;
        if (isset($index)) {
            return "/$index/_bulk/stream";
        }
        return "/_bulk/stream";
    }

    public function getParamWhitelist(): array
    {
        return [
            '_source',
            '_source_excludes',
            '_source_includes',
            'batch_interval',
            'batch_size',
            'pipeline',
            'refresh',
            'require_alias',
            'routing',
            'timeout',
            'wait_for_active_shards',
            'pretty',
            'human',
            'error_trace',
            'source',
            'filter_path'
        ];
    }

    public function getMethod(): string
    {
        return 'PUT';
    }

    public function setBody(string|iterable|null $body): static
    {
        if (is_null($body)) {
            return $this;
        }

        if (is_string($body)) {
            if (!str_ends_with($body, "\n")) {
                $body .= "\n";
            }
            $this->body = $body;
            return $this;
        }

        // Must be an iterable.
        foreach ($body as $item) {
            $this->body .= $this->serializer->serialize($item) . "\n";
        }

        return $this;
    }

}
