<?php
namespace App\Integration;
interface DocumentExtractionProvider { public function extract(string $privatePath): array; }
