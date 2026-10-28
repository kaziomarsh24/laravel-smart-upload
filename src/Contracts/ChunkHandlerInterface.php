<?php

namespace KaziOmar\SmartUpload\Contracts;

use Illuminate\Http\Request;
use KaziOmar\SmartUpload\DTO\UploadResult;

/**
 * Interface ChunkHandlerInterface
 *
 * Defines the contract for chunked upload reception and assembly.
 */
interface ChunkHandlerInterface
{
    /**
     * Handle the incoming chunk from HTTP request.
     */
    public function receive(Request $request): static;

    /**
     * Check if all chunks have finished arriving.
     */
    public function isComplete(): bool;

    /**
     * Get the current percentage of uploaded chunks (0 to 100).
     */
    public function percentage(): float;

    /**
     * Merge all assembled chunks and save to final directory.
     */
    public function save(string $directory = ''): UploadResult;
}
