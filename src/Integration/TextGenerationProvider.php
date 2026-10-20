<?php
namespace App\Integration;

interface TextGenerationProvider
{
    /** @return array{headline:string,text:string,highlights:array,checklist:array,mode:string} */
    public function generate(string $tool, array $facts, string $tone = 'sachlich', array $options = []): array;
}
