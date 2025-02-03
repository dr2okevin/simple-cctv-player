<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\If_\SimplifyIfElseToTernaryRector;
use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;
use Rector\Strict\Rector\Empty_\DisallowedEmptyRuleFixerRector;
use Rector\Symfony\Set\SymfonySetList;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
    ])
    ->withSymfonyContainerXml(__DIR__ . '/var/cache/dev/App_KernelDevDebugContainer.xml')
    ->withSets([
        SetList::CODE_QUALITY,
        SetList::CODING_STYLE,
        LevelSetList::UP_TO_PHP_82,
        SymfonySetList::SYMFONY_72,
        SymfonySetList::SYMFONY_CODE_QUALITY,
        SymfonySetList::SYMFONY_CONSTRUCTOR_INJECTION,
    ])
    ->withSkip([
        SimplifyIfElseToTernaryRector::class,
        DisallowedEmptyRuleFixerRector::class
    ]);
