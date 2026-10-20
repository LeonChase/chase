<?php
namespace App\Integration;
interface ImageProcessingProvider { public function process(string $privatePath, array $options = []): array; }
