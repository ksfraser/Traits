<?php
/**
 * Timestamp Trait
 *
 * Provides created_at and updated_at timestamp management.
 *
 * @package Ksfraser\Traits
 */

declare(strict_types=1);

namespace Ksfraser\Traits;

trait TimestampTrait
{
    private int $createdAt;
    private int $updatedAt;

    public function getCreatedAt(): int
    {
        return $this->createdAt ?? time();
    }

    public function getUpdatedAt(): int
    {
        return $this->updatedAt ?? time();
    }

    public function setCreatedAt(int $timestamp): void
    {
        $this->createdAt = $timestamp;
    }

    public function setUpdatedAt(int $timestamp): void
    {
        $this->updatedAt = $timestamp;
        $this->markModified();
    }

    public function touch(): void
    {
        $this->updatedAt = time();
        $this->markModified();
    }
}