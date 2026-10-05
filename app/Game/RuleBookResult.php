<?php

namespace App\Game;

final readonly class RuleBookResult
{
    /**
     * @param  array<string, mixed>  $state
     * @param  list<Ruling>  $rulings
     */
    public function __construct(
        public array $state,
        public array $rulings,
        public ?Ruling $actionRuling,
        public bool $passed,
    ) {}

    public function actionRejected(): bool
    {
        return $this->actionRuling?->isRejected() ?? false;
    }
}
