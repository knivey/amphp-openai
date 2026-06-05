<?php

namespace Knivey\OpenAi\Request\Content;

readonly class FilePart implements ContentPart
{
    public function __construct(
        private ?string $fileData = null,
        private ?string $fileId = null,
        private ?string $filename = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $file = [];

        if ($this->fileData !== null) {
            $file['file_data'] = $this->fileData;
        }

        if ($this->fileId !== null) {
            $file['file_id'] = $this->fileId;
        }

        if ($this->filename !== null) {
            $file['filename'] = $this->filename;
        }

        return ['type' => 'file', 'file' => $file];
    }
}
