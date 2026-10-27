<?php

declare(strict_types=1);

namespace FactDb\Port;

use FactDb\Model\KnowledgeSnapshot;
use FactDb\Model\ReconciliationPlan;
use FactDb\Model\SourceVersion;

interface SourceAnalyzer
{
    public function analyze(SourceVersion $source, KnowledgeSnapshot $snapshot): ReconciliationPlan;
}
