<?php

namespace Knivey\OpenAi\Response;

readonly class Annotation
{
    use ResponseHelpers;

    private function __construct(
        public string $type,
        public ?UrlCitation $urlCitation = null,
    ) {
    }

    /**
     * @param array<mixed, mixed> $data
     */
    public static function fromApiResponse(array $data): self
    {
        $citationRaw = $data['url_citation'] ?? null;

        return new self(
            type: self::getString($data, 'type'),
            urlCitation: is_array($citationRaw)
                ? UrlCitation::fromApiResponse($citationRaw)
                : null,
        );
    }
}
