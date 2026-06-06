<?php

namespace Knivey\OpenAi;

enum Provider: string
{
    case OPENAI = 'openai';
    case OPENROUTER = 'openrouter';
}
