<?php

declare(strict_types=1);

namespace app\dto\peergroup;

/**
 * Result DTO for create/update/toggle peer group operations.
 */
final readonly class SavePeerGroupResult
{
    /**
     * @param string[] $errors
     */
    public function __construct(
        public bool $success,
        public ?PeerGroupResponse $group = null,
        public array $errors = [],
    ) {
    }

    public static function success(PeerGroupResponse $group): self
    {
        return new self(success: true, group: $group);
    }

    /**
     * @param string[] $errors
     */
    public static function failure(array $errors): self
    {
        return new self(success: false, errors: $errors);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'group' => $this->group?->toArray(),
            'errors' => $this->errors,
        ];
    }
}
