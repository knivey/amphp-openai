<?php

namespace Knivey\OpenAi\Tests\Request\Content;

use Knivey\OpenAi\Request\Content\ImagePart;
use PHPUnit\Framework\TestCase;

class ImagePartTest extends TestCase
{
    public function testUrlWithoutDetail(): void
    {
        $part = ImagePart::url('https://example.com/img.png');
        $this->assertSame(
            ['type' => 'image_url', 'image_url' => ['url' => 'https://example.com/img.png']],
            $part->toArray(),
        );
    }

    public function testUrlWithDetail(): void
    {
        $part = ImagePart::url('https://example.com/img.png', 'high');
        $this->assertSame(
            ['type' => 'image_url', 'image_url' => ['url' => 'https://example.com/img.png', 'detail' => 'high']],
            $part->toArray(),
        );
    }

    public function testBase64(): void
    {
        $part = ImagePart::base64('iVBORw0KGgo=', 'image/png', 'low');
        $this->assertSame(
            ['type' => 'image_url', 'image_url' => ['url' => 'data:image/png;base64,iVBORw0KGgo=', 'detail' => 'low']],
            $part->toArray(),
        );
    }

    public function testBase64WithoutDetail(): void
    {
        $part = ImagePart::base64('iVBORw0KGgo=', 'image/png');
        $this->assertSame(
            ['type' => 'image_url', 'image_url' => ['url' => 'data:image/png;base64,iVBORw0KGgo=']],
            $part->toArray(),
        );
    }
}
