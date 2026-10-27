<?php

declare(strict_types=1);

namespace FactDb\Port;

use FactDb\Model\KnowledgeState;
use FactDb\Model\Scope;

interface Repository
{
    public function load(Scope $scope): KnowledgeState;
    public function commit(KnowledgeState $state, int $expectedRevision): void;
    public function erase(Scope $scope, int $expectedRevision): void;
}
