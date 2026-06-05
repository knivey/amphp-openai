<?php

namespace Knivey\OpenAi\Request\Content;

readonly class ImagePart implements ContentPart
{
    private function __construct(
        private string $url,
        private ?string $detail = null,
    ) {
    }

    public static function url(string $url, ?string $detail = null): self
    {
        return new self($url, $detail);
    }

    public static function base64(string $data, string $mediaType, ?string $detail = null): self
    {
        return new self("data:{$mediaType};base64,{$data}", $detail);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $result = ['type' => 'image_url', 'image_url' => ['url' => $this->url]];

        if ($this->detail !== null) {
            $result['image_url']['detail'] = $this->detail;
        }

        return $result;
    }
}
