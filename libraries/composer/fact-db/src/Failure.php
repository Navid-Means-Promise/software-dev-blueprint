<?php

declare(strict_types=1);

namespace FactDb;

enum Failure: string
{
    case InvalidInput = 'invalid_input';
    case StaleRevision = 'stale_revision';
    case ImmutableRecord = 'immutable_record';
    case InvalidReference = 'invalid_reference';
    case InvalidPlan = 'invalid_plan';
    case Capacity = 'capacity';
    case Incomplete = 'incomplete';
    case Erased = 'erased';
    case Schema = 'schema';
}
