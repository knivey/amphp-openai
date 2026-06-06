<?php

namespace Knivey\OpenAi\Tests\Response;

use Knivey\OpenAi\Response\Annotation;
use PHPUnit\Framework\TestCase;

class AnnotationTest extends TestCase
{
    public function testUrlCitationAnnotation(): void
    {
        $ann = Annotation::fromApiResponse([
            'type' => 'url_citation',
            'url_citation' => [
                'start_index' => 0,
                'end_index' => 10,
                'title' => 'Example',
                'url' => 'https://example.com',
            ],
        ]);
        $this->assertSame('url_citation', $ann->type);
        $this->assertNotNull($ann->urlCitation);
        $this->assertSame(0, $ann->urlCitation->startIndex);
        $this->assertSame(10, $ann->urlCitation->endIndex);
        $this->assertSame('Example', $ann->urlCitation->title);
        $this->assertSame('https://example.com', $ann->urlCitation->url);
    }
}
