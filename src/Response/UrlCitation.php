<?php

namespace Knivey\OpenAi\Response;

readonly class UrlCitation
{
    use ResponseHelpers;

    private function __construct(
        public int $startIndex,
        public int $endIndex,
        public string $title,
        public string $url,
    ) {
    }

    /**
     * @param array<mixed, mixed> $data
     */
    public static function fromApiResponse(array $data): self
    {
        return new self(
            startIndex: self::getInt($data, 'start_index'),
            endIndex: self::getInt($data, 'end_index'),
            title: self::getString($data, 'title'),
            url: self::getString($data, 'url'),
        );
    }
}
