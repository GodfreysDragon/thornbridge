<?php

namespace App\Game;

/**
 * The RuleBook's verdict on one part of an NPC proposal.
 */
final readonly class Ruling
{
    public const string ACCEPTED = 'accepted';

    public const string ADJUSTED = 'adjusted';

    public const string REJECTED = 'rejected';

    public function __construct(
        public string $proposal,
        public string $verdict,
        public string $rule,
        public string $explanation,
    ) {}

    public static function accepted(string $proposal, string $rule, string $explanation): self
    {
        return new self($proposal, self::ACCEPTED, $rule, $explanation);
    }

    public static function adjusted(string $proposal, string $rule, string $explanation): self
    {
        return new self($proposal, self::ADJUSTED, $rule, $explanation);
    }

    public static function rejected(string $proposal, string $rule, string $explanation): self
    {
        return new self($proposal, self::REJECTED, $rule, $explanation);
    }

    public function isRejected(): bool
    {
        return $this->verdict === self::REJECTED;
    }

    /**
     * @return array{proposal: string, verdict: string, rule: string, explanation: string}
     */
    public function toArray(): array
    {
        return [
            'proposal' => $this->proposal,
            'verdict' => $this->verdict,
            'rule' => $this->rule,
            'explanation' => $this->explanation,
        ];
    }
}
