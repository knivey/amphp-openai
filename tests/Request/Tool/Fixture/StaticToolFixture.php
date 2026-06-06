<?php

namespace Knivey\OpenAi\Tests\Request\Tool\Fixture;

use Knivey\OpenAi\Request\Tool\Attribute\ToolDescription;

class StaticToolFixture
{
    #[ToolDescription('A static tool')]
    public static function static_tool(string $input): string
    {
        return $input;
    }
}
