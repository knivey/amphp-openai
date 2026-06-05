<?php

namespace Knivey\OpenAi\Tests\Request\Content;

use Knivey\OpenAi\Request\Content\FilePart;
use PHPUnit\Framework\TestCase;

class FilePartTest extends TestCase
{
    public function testWithFileData(): void
    {
        $part = new FilePart(fileData: 'base64data', filename: 'doc.pdf');
        $this->assertSame(
            ['type' => 'file', 'file' => ['file_data' => 'base64data', 'filename' => 'doc.pdf']],
            $part->toArray(),
        );
    }

    public function testWithFileId(): void
    {
        $part = new FilePart(fileId: 'file-abc123');
        $this->assertSame(
            ['type' => 'file', 'file' => ['file_id' => 'file-abc123']],
            $part->toArray(),
        );
    }

    public function testWithAllFields(): void
    {
        $part = new FilePart(fileData: 'data', fileId: 'file-123', filename: 'test.txt');
        $arr = $part->toArray();
        $file = $arr['file'];
        $this->assertIsArray($file);
        $this->assertSame('data', $file['file_data']);
        $this->assertSame('file-123', $file['file_id']);
        $this->assertSame('test.txt', $file['filename']);
    }

    public function testOmitsNullFields(): void
    {
        $part = new FilePart(fileId: 'file-123');
        $arr = $part->toArray();
        $file = $arr['file'];
        $this->assertIsArray($file);
        $this->assertArrayNotHasKey('file_data', $file);
        $this->assertArrayNotHasKey('filename', $file);
    }
}
